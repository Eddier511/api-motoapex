<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/repository.php';
require dirname(__DIR__).'/src/accounts.php';
query("UPDATE users SET status='inactive'");
$out=[];
foreach (['admin','sales','marketing','editor'] as $role) {
    $password=bin2hex(random_bytes(16)); $email='accounts-'.$role.'@example.test';
    query('INSERT INTO users (name,email,password_hash,role_id,status) VALUES (?,?,?,(SELECT id FROM roles WHERE code=?),?)',[$role,$email,password_hash($password,PASSWORD_DEFAULT),$role,'active']);
    $id=(string)db()->lastInsertId(); $out[$role]=['id'=>$id,'password'=>$password,'email'=>$email,...issueSession(accountRow($id))];
}
file_put_contents(__DIR__.'/account-fixtures.json',json_encode($out,JSON_THROW_ON_ERROR));
// RFC 6238 Appendix B, SHA-1 vectors (8 digits).
foreach ([59=>'94287082',1111111109=>'07081804',1111111111=>'14050471',1234567890=>'89005924',2000000000=>'69279037',20000000000=>'65353130'] as $time=>$expected) if (totp('12345678901234567890',intdiv($time,30),8)!==$expected) throw new RuntimeException('TOTP vector failed');
if (base32('12345678901234567890')!=='GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ') throw new RuntimeException('Base32 failed');
$secret=random_bytes(20); if (decryptMfa(encryptMfa($secret))!==$secret) throw new RuntimeException('MFA encryption failed');
echo "RFC TOTP and authenticated encryption passed\n";
