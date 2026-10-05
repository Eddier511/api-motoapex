<?php
declare(strict_types=1);

function userDocument(array $u): array {
    return ['id'=>(string)$u['id'],'name'=>$u['name'],'email'=>$u['email'],'phone'=>$u['phone'] ?? '','avatarUrl'=>$u['avatar_url'] ?? '','role'=>$u['role'],'status'=>$u['status'],'lastAccess'=>iso($u['last_access_at']),'mustChangePassword'=>(bool)$u['must_change_password']];
}
function accountLimit(string $action,string $subject,int $maximum): void {
    // Independent of client IP: distributed guesses share the same account bucket.
    $bucket=hash('sha256','account:'.$action.':'.$subject.':'.intdiv(time(),900));
    query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,1,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE hits=hits+1',[$bucket]);
    if ((int)query('SELECT hits FROM rate_limits WHERE bucket=?',[$bucket])->fetchColumn()>$maximum) { header('Retry-After: 900'); fail('RATE_LIMITED','Demasiados intentos. Intenta mÃ¡s tarde.',429); }
}
function accountRow(string $id,bool $lock=false): array {
    $u=query('SELECT u.*,r.code AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.deleted_at IS NULL'.($lock ? ' FOR UPDATE' : ''),[$id])->fetch();
    if (!$u) fail('NOT_FOUND','Usuario no encontrado.',404);
    return $u;
}
function permissionFingerprint(string $id): string {
    $row=query('SELECT role_id FROM users WHERE id=?',[$id])->fetch();
    $permissions=query('SELECT p.code FROM users u JOIN role_permissions rp ON rp.role_id=u.role_id JOIN permissions p ON p.id=rp.permission_id WHERE u.id=? ORDER BY p.code',[$id])->fetchAll(PDO::FETCH_COLUMN);
    return hash('sha256',json_encode([$row['role_id'] ?? null,$permissions],JSON_THROW_ON_ERROR));
}
function revokeAccount(string $id): void {
    query('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE user_id=? AND revoked_at IS NULL',[$id]);
    query('UPDATE auth_challenges SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL',[$id]);
    query('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL',[$id]);
}
function accountIdentity(): array {
    $auth=$_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer ([a-f0-9]{64})$/D',$auth,$m)) fail('UNAUTHENTICATED','Inicia sesiÃ³n.',401);
    $s=query('SELECT s.*,u.status,u.deleted_at,u.must_change_password FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP() AND s.revoked_at IS NULL',[hash('sha256',$m[1])])->fetch();
    if (!$s || $s['status']!=='active' || $s['deleted_at']!==null) fail('UNAUTHENTICATED','SesiÃ³n invÃ¡lida o vencida.',401);
    $fingerprint=permissionFingerprint((string)$s['user_id']);
    if ($s['permission_fingerprint']===null || !hash_equals($s['permission_fingerprint'],$fingerprint)) { revokeAccount((string)$s['user_id']); fail('UNAUTHENTICATED','Permisos cambiaron. Inicia sesiÃ³n.',401); }
    if ($s['must_change_password']) { revokeAccount((string)$s['user_id']); fail('PASSWORD_CHANGE_REQUIRED','Completa el cambio obligatorio desde el login.',403); }
    if (query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$s['user_id']])->fetchColumn() && $s['mfa_verified_at']===null) { revokeAccount((string)$s['user_id']); fail('MFA_REQUIRED','Completa MFA desde el login.',403); }
    query('UPDATE user_sessions SET last_seen_at=UTC_TIMESTAMP() WHERE id=?',[$s['id']]);
    $GLOBALS['accountSession']=$s;
    return userDocument(accountRow((string)$s['user_id']));
}
function validPassword(mixed $v): string {
    if (!is_string($v) || strlen($v)<16 || strlen($v)>72 || str_contains($v,"\0")) throw new InvalidArgumentException('ContraseÃ±a debe tener 16-72 bytes');
    return $v;
}
function changedPassword(array $u,string $password): void {
    if (password_verify($password,$u['password_hash'])) throw new InvalidArgumentException('La contraseÃ±a nueva debe ser diferente');
    query('UPDATE users SET password_hash=?,password_changed_at=UTC_TIMESTAMP(),must_change_password=0,failed_login_attempts=0,locked_until=NULL WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$u['id']]);
    revokeAccount((string)$u['id']);
}
function issueSession(array $u,?string $mfaTime=null): array {
    $token=bin2hex(random_bytes(32)); $expires=gmdate('Y-m-d H:i:s',time()+min(28800,max(300,(int)config()['token_lifetime'])));
    query('INSERT INTO user_sessions (user_id,token_hash,expires_at,permission_fingerprint,mfa_verified_at,ip_address,user_agent) VALUES (?,?,?,?,?,?,?)',[$u['id'],hash('sha256',$token),$expires,permissionFingerprint((string)$u['id']),$mfaTime,$_SERVER['REMOTE_ADDR'] ?? null,substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,500)]);
    query('UPDATE users SET last_access_at=UTC_TIMESTAMP(),failed_login_attempts=0,locked_until=NULL WHERE id=?',[$u['id']]);
    return ['token'=>$token,'expiresAt'=>iso($expires),'user'=>userDocument(accountRow((string)$u['id']))];
}
function issueChallenge(array $u,string $purpose,?string $mfaTime=null,?string $action=null,?string $session=null): array {
    $token=bin2hex(random_bytes(32)); $expires=gmdate('Y-m-d H:i:s',time()+300);
    query('INSERT INTO auth_challenges (user_id,session_id,token_hash,purpose,action,permission_fingerprint,password_fingerprint,mfa_verified_at,expires_at) VALUES (?,?,?,?,?,?,?,?,?)',[$u['id'],$session,hash('sha256',$token),$purpose,$action,permissionFingerprint((string)$u['id']),hash('sha256',$u['password_hash']),$mfaTime,$expires]);
    return [$purpose==='reauth' ? 'reauthToken' : 'challengeToken'=>$token,'challenge'=>$purpose,'expiresAt'=>iso($expires)];
}
function takeChallenge(string $token,string $purpose,?string $action=null,?string $session=null): array {
    if (!preg_match('/^[a-f0-9]{64}$/D',$token)) fail('UNAUTHENTICATED','DesafÃ­o invÃ¡lido.',401);
    $c=query('SELECT * FROM auth_challenges WHERE token_hash=? AND purpose=? AND expires_at>UTC_TIMESTAMP() AND used_at IS NULL FOR UPDATE',[hash('sha256',$token),$purpose])->fetch();
    if (!$c || ($purpose==='reauth' && ($c['action']!==$action || (string)$c['session_id']!==$session))) fail('UNAUTHENTICATED','DesafÃ­o invÃ¡lido o vencido.',401);
    $u=accountRow((string)$c['user_id'],true);
    if ($u['status']!=='active' || !hash_equals($c['permission_fingerprint'],permissionFingerprint((string)$u['id'])) || !hash_equals($c['password_fingerprint'],hash('sha256',$u['password_hash']))) fail('UNAUTHENTICATED','DesafÃ­o invalidado.',401);
    query('UPDATE auth_challenges SET used_at=UTC_TIMESTAMP() WHERE id=?',[$c['id']]);
    return [$u,$c];
}
function requireReauth(array $u,string $action): void {
    if (!db()->inTransaction()) throw new LogicException('Reauthentication requires a transaction');
    [$verified]=takeChallenge($_SERVER['HTTP_X_REAUTH_TOKEN'] ?? '', 'reauth',$action,(string)$GLOBALS['accountSession']['id']);
    if ((string)$verified['id']!==$u['id']) fail('FORBIDDEN','ReautenticaciÃ³n de otra cuenta.',403);
}
function mfaKey(): string {
    $key=base64_decode(config()['mfa_encryption_key'] ?? '',true);
    if (!function_exists('sodium_crypto_secretbox') || $key===false || strlen($key)!==32) fail('MFA_NOT_CONFIGURED','MFA requiere configurar cifrado en el servidor.',503);
    return $key;
}
function encryptMfa(string $secret): string { $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); return base64_encode($nonce.sodium_crypto_secretbox($secret,$nonce,mfaKey())); }
function decryptMfa(string $cipher): string {
    $raw=base64_decode($cipher,true);
    if ($raw===false || strlen($raw)<SODIUM_CRYPTO_SECRETBOX_NONCEBYTES+SODIUM_CRYPTO_SECRETBOX_MACBYTES) throw new RuntimeException('Invalid encrypted MFA secret');
    $plain=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),mfaKey());
    if ($plain===false) throw new RuntimeException('MFA decryption failed');
    return $plain;
}
function base32(string $bytes): string {
    $alphabet='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; $bits=''; foreach (str_split($bytes) as $byte) $bits.=str_pad(decbin(ord($byte)),8,'0',STR_PAD_LEFT);
    $out=''; foreach (str_split($bits,5) as $chunk) $out.=$alphabet[bindec(str_pad($chunk,5,'0',STR_PAD_RIGHT))]; return $out;
}
function totp(string $secret,int $counter,int $digits=6): string {
    $mac=hash_hmac('sha1',pack('N2',intdiv($counter,4294967296),$counter%4294967296),$secret,true);
    $offset=ord($mac[19])&15; $number=unpack('N',substr($mac,$offset,4))[1]&0x7fffffff;
    return str_pad((string)($number%(10**$digits)),$digits,'0',STR_PAD_LEFT);
}
function verifyMfa(string $id,array $a,bool $pending=false): bool {
    $m=query('SELECT * FROM user_mfa WHERE user_id=? FOR UPDATE',[$id])->fetch();
    if (!$m || (!$pending && $m['confirmed_at']===null)) return false;
    if (isset($a['recoveryCode']) && !$pending) {
        $code=textField($a,'recoveryCode',64,true);
        $result=query('UPDATE mfa_recovery_codes SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND code_hash=? AND used_at IS NULL',[$id,hash('sha256',strtolower(str_replace('-','',$code)))]);
        return $result->rowCount()===1;
    }
    $code=$a['code'] ?? ''; if (!is_string($code) || !preg_match('/^[0-9]{6}$/D',$code)) return false;
    $secret=decryptMfa($m['encrypted_secret']); $now=intdiv(time(),30);
    foreach ([$now,$now-1,$now+1] as $counter) if ($counter>(int)$m['last_counter'] && hash_equals(totp($secret,$counter),$code)) { query('UPDATE user_mfa SET last_counter=? WHERE id=?',[$counter,$m['id']]); return true; }
    return false;
}
function recoveryCodes(string $id): array {
    query('DELETE FROM mfa_recovery_codes WHERE user_id=?',[$id]); $codes=[];
    for ($i=0;$i<10;$i++) { $raw=bin2hex(random_bytes(10)); query('INSERT INTO mfa_recovery_codes (user_id,code_hash) VALUES (?,?)',[$id,hash('sha256',$raw)]); $codes[]=implode('-',str_split($raw,5)); }
    return $codes;
}
function smtpConfig(): array {
    $s=config()['smtp'] ?? []; $url=config()['password_reset_url'] ?? '';
    if (empty($s['host']) || empty($s['from']) || !filter_var($s['from'],FILTER_VALIDATE_EMAIL) || !filter_var($url,FILTER_VALIDATE_URL) || parse_url($url,PHP_URL_SCHEME)!=='https' || parse_url($url,PHP_URL_USER)!==null || parse_url($url,PHP_URL_FRAGMENT)!==null) fail('SMTP_NOT_CONFIGURED','RecuperaciÃ³n pendiente: configurar SMTP y URL de recuperaciÃ³n en el servidor.',503);
    $localTest=(config()['environment'] ?? '')==='test' && $s['host']==='127.0.0.1';
    if (!$localTest && (!in_array($s['encryption'] ?? '',['tls','ssl'],true) || empty($s['username']) || empty($s['password']))) fail('SMTP_NOT_CONFIGURED','SMTP requiere TLS y credenciales del servidor.',503);
    return $s;
}
function sendResetMail(string $email,string $token): void {
    $s=smtpConfig();
    foreach (['Exception','SMTP','PHPMailer'] as $file) require_once dirname(__DIR__).'/vendor/phpmailer/src/'.$file.'.php';
    $mail=new PHPMailer\PHPMailer\PHPMailer(true); $mail->isSMTP(); $mail->Host=$s['host']; $mail->Port=(int)($s['port'] ?? 587); $mail->SMTPSecure=$s['encryption'] ?? 'tls'; $mail->SMTPAutoTLS=$mail->SMTPSecure!==''; $mail->SMTPAuth=!empty($s['username']); $mail->Username=$s['username'] ?? ''; $mail->Password=$s['password'] ?? ''; $mail->Timeout=10; $mail->SMTPDebug=0; $mail->CharSet='UTF-8';
    $mail->setFrom($s['from'],'MotoApex'); $mail->addAddress($email); $mail->Subject='Restablecer contraseÃ±a MotoApex'; $mail->isHTML(false);
    // Fragment avoids sending the token to frontend HTTP access logs or Referer.
    $mail->Body="Abre este enlace para restablecer tu contraseÃ±a. Vence en 30 minutos y solo puede usarse una vez.\n".config()['password_reset_url'].'#token='.$token."\nSi no solicitaste el cambio, ignora este correo.";
    $mail->send();
}
function handleAuth(array $path,string $method,string $requestId): void {
    if (($path[0] ?? '')!=='auth') return;
    $route=implode('/',array_slice($path,1));
    if ($route==='me' && $method==='GET') respond(identity());
    if ($route==='login' && $method==='POST') {
        limit('login',10); $a=body(); contentKeys($a,['email','password']); $email=strtolower(textField($a,'email',191,true));
        $password=$a['password']; if (!is_string($password) || strlen($password)<1 || strlen($password)>72 || str_contains($password,"\0")) throw new InvalidArgumentException('ContraseÃ±a invÃ¡lida');
        accountLimit('login',hash('sha256',$email),8);
        db()->beginTransaction();
        $u=query('SELECT u.*,r.code AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE email=? AND deleted_at IS NULL FOR UPDATE',[$email])->fetch();
        $valid=password_verify($password,$u['password_hash'] ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi');
        $blocked=$u && $u['locked_until'] && strtotime($u['locked_until'].' UTC')>time();
        query('INSERT INTO login_attempts (email,user_id,ip_address,succeeded) VALUES (?,?,?,?)',[$email,$u['id'] ?? null,$_SERVER['REMOTE_ADDR'] ?? null,(int)($u && $valid && !$blocked && $u['status']==='active')]);
        if (!$u || !$valid || $blocked || $u['status']!=='active') {
            if ($u && !$blocked) query('UPDATE users SET failed_login_attempts=failed_login_attempts+1,locked_until=CASE WHEN failed_login_attempts>=8 THEN DATE_ADD(UTC_TIMESTAMP(),INTERVAL 15 MINUTE) ELSE NULL END WHERE id=?',[$u['id']]);
            audit('login_failed',$requestId); db()->commit(); fail('INVALID_CREDENTIALS','Credenciales incorrectas.',401);
        }
        query('UPDATE users SET failed_login_attempts=0,locked_until=NULL WHERE id=?',[$u['id']]);
        if (query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$u['id']])->fetchColumn()) $result=issueChallenge($u,'mfa_login');
        elseif ($u['must_change_password']) $result=issueChallenge($u,'password_change');
        else $result=issueSession($u);
        audit(isset($result['token']) ? 'login_success' : 'login_challenge',$requestId,(string)$u['id']); db()->commit(); respond($result);
    }
    if ($route==='mfa/challenge' && $method==='POST') {
        limit('mfa-login',10); $a=body(); contentKeys($a,['challengeToken'],['code','recoveryCode']);
        $subject=query('SELECT user_id FROM auth_challenges WHERE token_hash=?',[hash('sha256',textField($a,'challengeToken',64,true))])->fetchColumn();
        if ($subject) accountLimit('mfa-login',(string)$subject,8);
        db()->beginTransaction(); [$u,$c]=takeChallenge(textField($a,'challengeToken',64,true),'mfa_login');
        if (!verifyMfa((string)$u['id'],$a)) { audit('mfa_failed',$requestId,(string)$u['id']); db()->commit(); fail('INVALID_MFA','CÃ³digo invÃ¡lido. Inicia sesiÃ³n para un nuevo desafÃ­o.',401); }
        $time=gmdate('Y-m-d H:i:s'); $result=$u['must_change_password'] ? issueChallenge($u,'password_change',$time) : issueSession($u,$time);
        audit('mfa_login_success',$requestId,(string)$u['id']); db()->commit(); respond($result);
    }
    if ($route==='password/required' && $method==='POST') {
        limit('password-required',10); $a=body(); contentKeys($a,['challengeToken','newPassword']); $password=validPassword($a['newPassword']);
        db()->beginTransaction(); [$u,$c]=takeChallenge(textField($a,'challengeToken',64,true),'password_change');
        if (!$u['must_change_password']) fail('CONFLICT','Cambio obligatorio ya completado.',409);
        if (query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$u['id']])->fetchColumn() && $c['mfa_verified_at']===null) fail('MFA_REQUIRED','Completa MFA primero.',403);
        changedPassword($u,$password); $result=issueSession(accountRow((string)$u['id']),$c['mfa_verified_at']); audit('password_required_completed',$requestId,(string)$u['id']); db()->commit(); respond($result);
    }
    if ($route==='password/forgot' && $method==='POST') {
        limit('password-forgot',5); $a=body(); contentKeys($a,['email']); $email=strtolower(textField($a,'email',191,true)); if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo invÃ¡lido');
        accountLimit('recovery',hash('sha256',$email),3); smtpConfig();
        $u=query("SELECT id,email FROM users WHERE email=? AND status='active' AND deleted_at IS NULL",[$email])->fetch();
        if ($u) {
            $token=bin2hex(random_bytes(32)); db()->beginTransaction();
            accountRow((string)$u['id'],true); query('UPDATE password_reset_tokens SET used_at=UTC_TIMESTAMP() WHERE user_id=? AND used_at IS NULL',[$u['id']]);
            query('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE))',[$u['id'],hash('sha256',$token)]);
            try { sendResetMail($u['email'],$token); db()->commit(); }
            catch (Throwable) { db()->rollBack(); /* Same public response for missing addresses and delivery failures. */ error_log('MotoApex request='.$requestId.' smtp_delivery_failed'); }
        }
        audit('password_recovery_requested',$requestId); respond(['message'=>'Si existe una cuenta activa, recibirÃ¡s un enlace de recuperaciÃ³n.']);
    }
    if ($route==='password/reset' && $method==='POST') {
        limit('password-reset',10); $a=body(); contentKeys($a,['token','newPassword']); $password=validPassword($a['newPassword']); $token=textField($a,'token',64,true);
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) fail('INVALID_RESET','Enlace invÃ¡lido o vencido.',401);
        db()->beginTransaction();
        // Lock user first, then token, consistently with forgot and password changes.
        $candidate=query('SELECT user_id FROM password_reset_tokens WHERE token_hash=?',[hash('sha256',$token)])->fetchColumn();
        if (!$candidate) fail('INVALID_RESET','Enlace invÃ¡lido o vencido.',401);
        $u=accountRow((string)$candidate,true);
        $reset=query('SELECT id FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>UTC_TIMESTAMP() FOR UPDATE',[hash('sha256',$token)])->fetchColumn();
        if (!$reset || $u['status']!=='active') fail('INVALID_RESET','Enlace invÃ¡lido o vencido.',401);
        changedPassword($u,$password); audit('password_reset_completed',$requestId,(string)$u['id']); db()->commit(); respond(['changed'=>true,'loginRequired'=>true]);
    }
    $user=identity(); $uid=$user['id'];
    if ($route==='logout' && $method==='POST') { query('UPDATE user_sessions SET revoked_at=UTC_TIMESTAMP() WHERE id=?',[$GLOBALS['accountSession']['id']]); audit('logout',$requestId,$uid); respond(['loggedOut'=>true]); }
    if ($route==='reauth' && $method==='POST') {
        limit('reauth',10); $a=body(); contentKeys($a,['password','action'],['code','recoveryCode']); $action=choice($a,'action',['users.manage','settings.manage','profile.edit','password.change','mfa.manage'],'profile.edit');
        accountLimit('reauth',$uid,8);
        db()->beginTransaction(); $u=accountRow($uid,true);
        if (!is_string($a['password']) || strlen($a['password'])>72 || !password_verify($a['password'],$u['password_hash'])) { db()->rollBack(); fail('INVALID_CREDENTIALS','Credenciales incorrectas.',401); }
        $mfaTime=null;
        if (query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$uid])->fetchColumn()) { if (!verifyMfa($uid,$a)) { db()->rollBack(); fail('INVALID_MFA','CÃ³digo invÃ¡lido.',401); } $mfaTime=gmdate('Y-m-d H:i:s'); }
        $result=issueChallenge($u,'reauth',$mfaTime,$action,(string)$GLOBALS['accountSession']['id']); audit('reauth_success',$requestId,$uid); db()->commit(); respond($result);
    }
    if ($route==='profile' && $method==='GET') respond($user);
    if ($route==='profile' && $method==='PUT') {
        limit('profile-write',20); $a=body(); contentKeys($a,['name','email','phone','avatarUrl']); $fields=validateUserFields($a,false);
        db()->beginTransaction(); $old=accountRow($uid,true); requireReauth($user,'profile.edit'); writeRow('users',$fields,$uid); if ($old['email']!==$fields['email']) revokeAccount($uid); audit('profile_updated',$requestId,$uid); $result=userDocument(accountRow($uid)); db()->commit(); respond($result);
    }
    if ($route==='password/change' && $method==='POST') {
        limit('password-change',10); $a=body(); contentKeys($a,['currentPassword','newPassword']); $password=validPassword($a['newPassword']);
        db()->beginTransaction(); $u=accountRow($uid,true); requireReauth($user,'password.change');
        if (!is_string($a['currentPassword']) || strlen($a['currentPassword'])>72 || !password_verify($a['currentPassword'],$u['password_hash'])) { db()->rollBack(); fail('INVALID_CREDENTIALS','ContraseÃ±a actual incorrecta.',401); }
        changedPassword($u,$password); audit('password_changed',$requestId,$uid); db()->commit(); respond(['changed'=>true,'loginRequired'=>true]);
    }
    if ($route==='mfa/status' && $method==='GET') respond(['enabled'=>(bool)query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$uid])->fetchColumn()]);
    if ($route==='mfa/enroll' && $method==='POST') {
        limit('mfa-enroll',5); mfaKey(); db()->beginTransaction(); $u=accountRow($uid,true); requireReauth($user,'mfa.manage');
        if (query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$uid])->fetchColumn()) fail('CONFLICT','MFA ya estÃ¡ activo.',409);
        $secret=random_bytes(20); query('INSERT INTO user_mfa (user_id,encrypted_secret) VALUES (?,?) ON DUPLICATE KEY UPDATE encrypted_secret=VALUES(encrypted_secret),last_counter=-1',[$uid,encryptMfa($secret)]);
        audit('mfa_enrollment_started',$requestId,$uid); db()->commit(); respond(['otpauthUri'=>'otpauth://totp/'.rawurlencode('MotoApex:'.$u['email']).'?secret='.base32($secret).'&issuer=MotoApex&algorithm=SHA1&digits=6&period=30']);
    }
    if ($route==='mfa/confirm' && $method==='POST') {
        limit('mfa-confirm',10); $a=body(); contentKeys($a,['code']); db()->beginTransaction(); accountRow($uid,true); requireReauth($user,'mfa.manage');
        if (query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$uid])->fetchColumn()) fail('CONFLICT','MFA ya estÃ¡ activo.',409);
        if (!verifyMfa($uid,$a,true)) { db()->rollBack(); fail('INVALID_MFA','CÃ³digo invÃ¡lido.',401); }
        query('UPDATE user_mfa SET confirmed_at=UTC_TIMESTAMP() WHERE user_id=?',[$uid]); $codes=recoveryCodes($uid); revokeAccount($uid); audit('mfa_enabled',$requestId,$uid); db()->commit(); respond(['enabled'=>true,'recoveryCodes'=>$codes,'loginRequired'=>true]);
    }
    if ($route==='mfa/disable' && $method==='POST') {
        limit('mfa-disable',5); db()->beginTransaction(); accountRow($uid,true); requireReauth($user,'mfa.manage');
        query('DELETE FROM mfa_recovery_codes WHERE user_id=?',[$uid]); query('DELETE FROM user_mfa WHERE user_id=?',[$uid]); revokeAccount($uid); audit('mfa_disabled',$requestId,$uid); db()->commit(); respond(['enabled'=>false,'loginRequired'=>true]);
    }
    if ($route==='mfa/recovery-codes' && $method==='POST') {
        limit('mfa-recovery-codes',5); db()->beginTransaction(); accountRow($uid,true); requireReauth($user,'mfa.manage');
        if (!query('SELECT id FROM user_mfa WHERE user_id=? AND confirmed_at IS NOT NULL',[$uid])->fetchColumn()) fail('CONFLICT','MFA no estÃ¡ activo.',409);
        $codes=recoveryCodes($uid); audit('mfa_recovery_codes_rotated',$requestId,$uid); db()->commit(); respond(['recoveryCodes'=>$codes]);
    }
    fail('NOT_FOUND','Ruta no encontrada.',404);
}
