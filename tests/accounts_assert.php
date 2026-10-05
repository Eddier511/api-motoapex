<?php
require dirname(__DIR__).'/src/bootstrap.php';
$u=query("SELECT id,deleted_at FROM users WHERE email='new-user@example.test'")->fetch();
if (!$u || !$u['deleted_at']) throw new RuntimeException('Missing logical deletion');
if (query('SELECT id FROM user_sessions WHERE user_id=? AND revoked_at IS NULL',[$u['id']])->fetchColumn()) throw new RuntimeException('Unrevoked session');
if (!query('SELECT id FROM password_reset_tokens WHERE user_id=? AND used_at IS NOT NULL',[$u['id']])->fetchColumn()) throw new RuntimeException('Reset was not consumed');
if (query('SELECT id FROM user_mfa WHERE user_id=?',[$u['id']])->fetchColumn()) throw new RuntimeException('MFA not disabled');
$count=query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.code='admin' AND u.status='active' AND u.deleted_at IS NULL")->fetchColumn();
if ((int)$count!==1) throw new RuntimeException('Last admin invariant broken');
foreach (['user_created','user_updated','user_deleted','mfa_enabled','mfa_disabled','password_reset_completed','password_changed'] as $action) if (!query('SELECT id FROM audit_logs WHERE action=?',[$action])->fetchColumn()) throw new RuntimeException('Missing audit action');
echo "Account history, audit, reset consumption and last admin invariant passed\n";
