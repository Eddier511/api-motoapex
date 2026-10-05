<?php
// CLI-only helper for disposable CI fixtures; never included in deployment ZIP.
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/src/bootstrap.php';
$action=$argv[1] ?? '';
if ($action==='rates') query('DELETE FROM rate_limits');
elseif ($action==='expire-reset') query('UPDATE password_reset_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE used_at IS NULL');
elseif ($action==='expire-challenges') query('UPDATE auth_challenges SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE) WHERE used_at IS NULL');
elseif ($action==='permissions') query("DELETE rp FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.code='marketing' AND p.code='content.manage'");
elseif ($action==='force-password') query('UPDATE users SET must_change_password=1 WHERE id=?',[$argv[2]]);
elseif ($action==='missing-smtp') { $path=dirname(__DIR__).'/config.local.php'; $c=require $path; $c['smtp']=[]; file_put_contents($path,'<?php return '.var_export($c,true).';'); }
elseif ($action==='delegated') query("INSERT INTO role_permissions (role_id,permission_id) SELECT r.id,p.id FROM roles r JOIN permissions p WHERE r.code='editor' AND p.code='users.manage'");
else exit(1);
