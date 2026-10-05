<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/src/bootstrap.php';
$email=strtolower(trim($argv[1] ?? ''));
$name=trim($argv[2] ?? 'Administrador');
$password=getenv('MOTOAPEX_ADMIN_PASSWORD') ?: '';
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<16 || strlen($password)>72) {
    fwrite(STDERR,"Uso: define MOTOAPEX_ADMIN_PASSWORD (16–72 bytes) y ejecuta php bin/create-admin.php correo nombre\n"); exit(1);
}
query('INSERT INTO users (name,email,password_hash,role_id,status,password_changed_at) VALUES (?,?,?,(SELECT id FROM roles WHERE code=\'admin\'),\'active\',UTC_TIMESTAMP())',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
echo "Administrador creado.\n";
