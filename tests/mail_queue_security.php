<?php
declare(strict_types=1);
// Real database/crypto checks, confined to the disposable CI environment.
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/mail_queue.php';
if ((config()['environment'] ?? '')!=='test') throw new RuntimeException('Disposable database required');
function verifyQueue(bool $condition,string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$email='queue-security@example.test'; $key=recoveryQueueKey();
$master=base64_decode(config()['recovery_queue_key'] ?? config()['mfa_encryption_key'],true);
verifyQueue($key!==$master,'Queue key must be domain separated');
$subject=hash_hmac('sha256',$email,$key);
db()->beginTransaction();
try {
    $tokens=(int)query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn();
    queueEmail($email);
    $row=query('SELECT * FROM password_recovery_jobs WHERE subject_key=?',[$subject])->fetch();
    verifyQueue((bool)$row && $row['status']==='pending','Encrypted pending job missing');
    $cipher=$row['encrypted_email']; $raw=base64_decode($cipher,true);
    verifyQueue(!str_contains(json_encode($row,JSON_THROW_ON_ERROR),$email) && !str_contains($raw,$email),'Plaintext email persisted');
    verifyQueue($row['subject_key']!==hash('sha256',$email),'Unkeyed email identifier');
    verifyQueue(decryptQueueEmail($cipher)===$email,'Queue round trip failed');
    verifyQueue((int)query('SELECT COUNT(*) FROM password_reset_tokens')->fetchColumn()===$tokens,'HTTP enqueue created a reset token');
    queueEmail($email);
    $again=query('SELECT * FROM password_recovery_jobs WHERE subject_key=?',[$subject])->fetch();
    verifyQueue($again['id']===$row['id'] && $again['encrypted_email']!==$cipher,'Coalescing must retain identity and use a fresh nonce');
    verifyQueue((int)query('SELECT COUNT(*) FROM password_recovery_jobs WHERE subject_key=?',[$subject])->fetchColumn()===1,'Duplicate recovery job');
    $tampered=$raw; $tampered[strlen($tampered)-1]=chr(ord($tampered[strlen($tampered)-1])^1);
    $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
    $wrongKey=base64_encode($nonce.sodium_crypto_secretbox($email,$nonce,random_bytes(32)));
    foreach (['not base64!','',base64_encode(substr($raw,0,10)),base64_encode($tampered),$wrongKey] as $invalid) {
        $rejected=false;
        try { decryptQueueEmail($invalid); } catch (RuntimeException) { $rejected=true; }
        verifyQueue($rejected,'Unauthenticated queue payload accepted');
    }
} finally { if (db()->inTransaction()) db()->rollBack(); }
echo "Queue ciphertext at rest, fresh nonces, coalescing and tamper/wrong-key rejection passed\n";
