<?php
declare(strict_types=1);

function recoveryQueueKey(): string {
    $encoded=config()['recovery_queue_key'] ?? '';
    if ($encoded==='') $encoded=config()['mfa_encryption_key'] ?? '';
    $key=base64_decode($encoded,true);
    if ($key===false || strlen($key)!==32 || !extension_loaded('sodium')) {
        if (PHP_SAPI==='cli') throw new RuntimeException('RECOVERY_NOT_CONFIGURED');
        fail('RECOVERY_NOT_CONFIGURED','Configura la clave privada de la cola de recuperación.',503);
    }
    // Domain separation even when sharing the legacy MFA master key.
    return hash_hmac('sha256','MotoApex recovery queue v1',$key,true);
}
function queueEmail(string $email): void {
    $key=recoveryQueueKey(); $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $cipher=base64_encode($nonce.sodium_crypto_secretbox($email,$nonce,$key));
    $subject=hash_hmac('sha256',$email,$key);
    if ((int)query("SELECT COUNT(*) FROM password_recovery_jobs WHERE status IN ('pending','processing')")->fetchColumn()>=10000) fail('RECOVERY_BUSY','Servicio ocupado. Intenta más tarde.',503);
    // Identical work for existent/nonexistent accounts; no user lookup in HTTP.
    // A processing job is coalesced rather than replaced while its SMTP send runs.
    query("INSERT INTO password_recovery_jobs (subject_key,encrypted_email,available_at,expires_at) VALUES (?,?,UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE)) ON DUPLICATE KEY UPDATE encrypted_email=IF(status='processing',encrypted_email,VALUES(encrypted_email)),attempts=IF(status='processing',attempts,0),available_at=IF(status='processing',available_at,UTC_TIMESTAMP()),expires_at=IF(status='processing',expires_at,VALUES(expires_at)),status=IF(status='processing','processing','pending')",[$subject,$cipher]);
}
function decryptQueueEmail(string $cipher): string {
    $raw=base64_decode($cipher,true);
    if ($raw===false || strlen($raw)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES) throw new RuntimeException('Invalid queue payload');
    $email=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),recoveryQueueKey());
    if ($email===false || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid queue payload');
    return $email;
}
function finishMailJob(array $job,string $status,bool $retry=false): void {
    query("UPDATE password_recovery_jobs SET status=?,encrypted_email=IF(?=1,encrypted_email,NULL),available_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 60 SECOND),lease_hash=NULL,lease_until=NULL WHERE id=? AND status='processing' AND lease_hash=?",[$status,(int)$retry,$job['id'],$job['lease_hash']]);
}
function claimMailJob(): ?array {
    query("UPDATE password_recovery_jobs SET status='failed',encrypted_email=NULL,lease_hash=NULL,lease_until=NULL WHERE expires_at<=UTC_TIMESTAMP() AND status IN ('pending','processing')");
    query("UPDATE password_recovery_jobs SET status=IF(attempts<3,'pending','failed'),encrypted_email=IF(attempts<3,encrypted_email,NULL),lease_hash=NULL,lease_until=NULL WHERE status='processing' AND lease_until<UTC_TIMESTAMP()");
    db()->beginTransaction();
    try {
        $job=query("SELECT * FROM password_recovery_jobs WHERE status='pending' AND available_at<=UTC_TIMESTAMP() AND expires_at>UTC_TIMESTAMP() ORDER BY id LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch();
        if (!$job) { db()->commit(); return null; }
        $job['lease_hash']=hash('sha256',random_bytes(32)); $job['attempts']=(int)$job['attempts']+1;
        query("UPDATE password_recovery_jobs SET status='processing',attempts=?,lease_hash=?,lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE id=?",[$job['attempts'],$job['lease_hash'],$job['id']]);
        db()->commit(); return $job;
    } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
}
function processRecoveryJob(array $job): string {
    $tokenHash=null; $requestId=bin2hex(random_bytes(12));
    try {
        $email=decryptQueueEmail($job['encrypted_email']);
        $candidate=query("SELECT id FROM users WHERE email=? AND status='active' AND deleted_at IS NULL",[$email])->fetchColumn();
        if (!$candidate) { finishMailJob($job,'discarded'); return 'discarded'; }
        db()->beginTransaction();
        $u=query('SELECT id,email,status,deleted_at FROM users WHERE id=? FOR UPDATE',[$candidate])->fetch();
        if (!$u || $u['deleted_at']!==null || $u['status']!=='active' || strtolower($u['email'])!==$email) { db()->rollBack(); finishMailJob($job,'discarded'); return 'discarded'; }
        $token=bin2hex(random_bytes(32)); $tokenHash=hash('sha256',$token);
        query('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))',[$u['id'],$tokenHash]);
        db()->commit();
        // No database transaction or user row lock is held during network I/O.
        sendResetMail($u['email'],$token); unset($token);
        db()->beginTransaction();
        $current=query('SELECT id,email,status,deleted_at FROM users WHERE id=? FOR UPDATE',[$candidate])->fetch();
        if (!$current || $current['deleted_at']!==null || $current['status']!=='active' || strtolower($current['email'])!==$email) {
            query('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE token_hash=? AND used_at IS NULL',[$tokenHash]);
            finishMailJob($job,'discarded'); db()->commit(); return 'discarded';
        }
        $owned=query("SELECT id FROM password_recovery_jobs WHERE id=? AND status='processing' AND lease_hash=? AND lease_until>UTC_TIMESTAMP() FOR UPDATE",[$job['id'],$job['lease_hash']])->fetchColumn();
        if (!$owned) {
            query('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE token_hash=? AND used_at IS NULL',[$tokenHash]); db()->commit(); return 'discarded';
        }
        query('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND token_hash<>? AND used_at IS NULL',[$candidate,$tokenHash]);
        finishMailJob($job,'sent'); audit('password_recovery_delivered',$requestId,(string)$candidate); db()->commit(); return 'sent';
    } catch (Throwable) {
        if (db()->inTransaction()) db()->rollBack();
        if ($tokenHash!==null) query('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE token_hash=? AND used_at IS NULL',[$tokenHash]);
        $retry=$job['attempts']<3; finishMailJob($job,$retry ? 'pending' : 'failed',$retry);
        // Do not print SMTP exceptions, recipient, message, tokens or secrets.
        error_log('MotoApex request='.$requestId.' smtp_delivery_failed'); return 'failed';
    }
}
function runMailQueue(int $maximum): array {
    smtpConfig(); recoveryQueueKey();
    $counts=['sent'=>0,'discarded'=>0,'failed'=>0];
    $deadline=microtime(true)+45;
    for ($i=0;$i<$maximum && microtime(true)<$deadline;$i++) { $job=claimMailJob(); if (!$job) break; $counts[processRecoveryJob($job)]++; }
    query("DELETE FROM password_recovery_jobs WHERE status IN ('sent','discarded','failed') AND updated_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 7 DAY) LIMIT 500");
    return $counts;
}
