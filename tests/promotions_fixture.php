<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$tokens=[];
foreach (['admin','marketing','editor','sales'] as $role) {
    $email=$role.'-promotions@example.test';
    query('INSERT INTO users (name,email,password_hash,role_id) VALUES (?,?,?,(SELECT id FROM roles WHERE code=?))',[$role,$email,password_hash(bin2hex(random_bytes(20)),PASSWORD_DEFAULT),$role]);
    $id=db()->lastInsertId(); $token=bin2hex(random_bytes(32));
    query('INSERT INTO user_sessions (token_hash,user_id,expires_at) VALUES (?,?,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR))',[hash('sha256',$token),$id]);
    $tokens[$role]=$token;
}
file_put_contents(__DIR__.'/promotion-tokens.json',json_encode($tokens,JSON_THROW_ON_ERROR));
