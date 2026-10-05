<?php
declare(strict_types=1);

function validateUserFields(array $a,bool $admin): array {
    $email=strtolower(textField($a,'email',191,true));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo invÃ¡lido');
    $phone=textField($a,'phone',40);
    if (!preg_match('/^[+0-9 ()-]{0,40}$/D',$phone)) throw new InvalidArgumentException('TelÃ©fono invÃ¡lido');
    $fields=['name'=>plainPromotionText($a,'name',150,true),'email'=>$email,'phone'=>$phone,'avatar_url'=>safeContentUrl($a,'avatarUrl')];
    if ($admin) {
        $role=textField($a,'role',60,true);
        $rid=query('SELECT id FROM roles WHERE code=?',[$role])->fetchColumn();
        if (!$rid) throw new InvalidArgumentException('Rol inexistente');
        $fields+=['role_id'=>(string)$rid,'status'=>choice($a,'status',['active','inactive'],'inactive')];
    }
    return $fields;
}
function protectLastAdmin(array $old,array $fields,bool $deleting=false): void {
    if ($old['role']!=='admin' || $old['status']!=='active') return;
    $admin=query("SELECT id FROM roles WHERE code='admin' FOR UPDATE")->fetchColumn();
    if (!$deleting && ($fields['status'] ?? $old['status'])==='active' && (string)($fields['role_id'] ?? $old['role_id'])===(string)$admin) return;
    $count=(int)query("SELECT COUNT(*) FROM users WHERE role_id=? AND status='active' AND deleted_at IS NULL",[$admin])->fetchColumn();
    if ($count<=1) fail('LAST_ADMIN','Debe permanecer un administrador activo.',409);
}
function ensureRoleAssignable(array $actor,string $role): void {
    if ($actor['role']==='admin') return;
    // A custom delegated users.manage role cannot grant privileges it does not have.
    $extra=query('SELECT COUNT(*) FROM role_permissions target WHERE target.role_id=? AND NOT EXISTS (SELECT 1 FROM role_permissions own JOIN users u ON u.role_id=own.role_id WHERE u.id=? AND own.permission_id=target.permission_id)',[$role,$actor['id']])->fetchColumn();
    $admin=query("SELECT id FROM roles WHERE code='admin'")->fetchColumn();
    if ((int)$extra>0 || (string)$admin===$role) fail('FORBIDDEN','No puedes asignar este rol.',403);
}
function handleUsers(string $kind,string $method,?string $id,array $user,string $requestId): never {
    permit($user,'users.manage');
    if ($kind==='roles' && $method==='GET' && $id===null) {
        $roles=query('SELECT id,code,name FROM roles ORDER BY id')->fetchAll();
        foreach ($roles as &$role) { $role['id']=(string)$role['id']; $role['permissions']=query('SELECT p.code FROM role_permissions rp JOIN permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.code',[$role['id']])->fetchAll(PDO::FETCH_COLUMN); }
        respond($roles);
    }
    if ($kind!=='users') fail('METHOD_NOT_ALLOWED','MÃ©todo no permitido.',405);
    if ($method==='GET') {
        limit('users-read',150);
        if ($id!==null) respond(userDocument(accountRow(promotionId($id,'id'))));
        $rows=query('SELECT u.*,r.code AS role FROM users u JOIN roles r ON r.id=u.role_id WHERE u.deleted_at IS NULL ORDER BY u.name,u.id')->fetchAll();
        respond(array_map('userDocument',$rows));
    }
    limit('users-write',40);
    if (($method==='POST' && $id===null) || ($method==='PUT' && $id!==null)) {
        $a=body(); contentKeys($a,['name','email','phone','avatarUrl','role','status'],$method==='POST' ? ['password'] : ['newPassword','mustChangePassword']);
        if ($method==='POST' && !array_key_exists('password',$a)) throw new InvalidArgumentException('ContraseÃ±a requerida');
        $fields=validateUserFields($a,true); ensureRoleAssignable($user,$fields['role_id']);
        $password=isset($a[$method==='POST' ? 'password' : 'newPassword']) ? validPassword($a[$method==='POST' ? 'password' : 'newPassword']) : null;
        $force=isset($a['mustChangePassword']) ? flag($a,'mustChangePassword') : null;
        if ($force===false) throw new InvalidArgumentException('El cambio obligatorio solo puede completarlo el usuario');
        if ($password!==null) $fields+=['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'password_changed_at'=>gmdate('Y-m-d H:i:s'),'must_change_password'=>1];
        if ($force===true) $fields['must_change_password']=1;
        db()->beginTransaction();
        // Serialize all administrative mutations, including simultaneous last-admin demotions.
        query("SELECT id FROM roles WHERE code='admin' FOR UPDATE")->fetchColumn();
        requireReauth($user,'users.manage'); $old=$id===null ? null : accountRow(promotionId($id,'id'),true);
        if ($old) protectLastAdmin($old,$fields);
        $uid=writeRow('users',$fields,$old ? (string)$old['id'] : null);
        if ($old && ($old['status']!==$fields['status'] || (string)$old['role_id']!==$fields['role_id'] || $old['email']!==$fields['email'] || $password!==null || $force===true)) revokeAccount($uid);
        audit($old ? 'user_updated' : 'user_created',$requestId,$user['id']); $result=userDocument(accountRow($uid)); db()->commit(); respond($result,$old ? 200 : 201);
    }
    if ($method==='DELETE' && $id!==null) {
        db()->beginTransaction(); query("SELECT id FROM roles WHERE code='admin' FOR UPDATE")->fetchColumn(); requireReauth($user,'users.manage');
        $old=accountRow(promotionId($id,'id'),true); protectLastAdmin($old,[],true);
        query("UPDATE users SET status='inactive',deleted_at=UTC_TIMESTAMP() WHERE id=?",[$old['id']]); revokeAccount((string)$old['id']); audit('user_deleted',$requestId,$user['id']); db()->commit(); respond(['id'=>(string)$old['id'],'deleted'=>true]);
    }
    fail('METHOD_NOT_ALLOWED','MÃ©todo no permitido.',405);
}
const SETTING_RULES=['site_url'=>['type'=>'string','public'=>true,'rule'=>'url'],'admin_url'=>['type'=>'string','public'=>false,'rule'=>'url'],'api_url'=>['type'=>'string','public'=>false,'rule'=>'url'],'timezone'=>['type'=>'string','public'=>true,'rule'=>'timezone'],'default_currency'=>['type'=>'string','public'=>true,'rule'=>'currency']];
function settingValue(string $key,mixed $value): string {
    $rule=SETTING_RULES[$key]['rule'];
    if (!is_string($value)) throw new InvalidArgumentException('Valor de configuraciÃ³n debe ser string');
    if ($rule==='url') return safeContentUrl(['value'=>$value],'value',true);
    if ($rule==='timezone' && !in_array($value,DateTimeZone::listIdentifiers(),true)) throw new InvalidArgumentException('Zona horaria invÃ¡lida');
    if ($rule==='currency' && !in_array($value,['CRC','USD'],true)) throw new InvalidArgumentException('Moneda invÃ¡lida');
    return $value;
}
function settingsDocument(bool $public): array {
    $out=[];
    foreach (SETTING_RULES as $key=>$rule) {
        if ($public && !$rule['public']) continue;
        $row=query('SELECT id,value FROM settings WHERE `key`=?',[$key])->fetch(); if (!$row) continue;
        try { $value=settingValue($key,$row['value']); } catch (InvalidArgumentException) { continue; }
        $out[]=['id'=>(string)$row['id'],'key'=>$key,'value'=>$value,'valueType'=>$rule['type'],'public'=>$rule['public']];
    }
    return $out;
}
function handleSettings(string $scope,string $method,?string $key,?array $user,string $requestId): never {
    $public=$scope==='public'; if (!$public) permit($user,'settings.manage');
    if ($method==='GET' && $key===null) { limit('settings-read',150); respond(settingsDocument($public)); }
    if (!$public && $method==='PUT' && $key!==null) {
        limit('settings-write',30); if (!isset(SETTING_RULES[$key])) throw new InvalidArgumentException('Clave no editable');
        $a=body(); contentKeys($a,['value']); $value=settingValue($key,$a['value']); $rule=SETTING_RULES[$key];
        db()->beginTransaction(); requireReauth($user,'settings.manage');
        query('INSERT INTO settings (`key`,value,value_type,is_public,updated_by) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE value=VALUES(value),value_type=VALUES(value_type),is_public=VALUES(is_public),updated_by=VALUES(updated_by)',[$key,$value,$rule['type'],(int)$rule['public'],$user['id']]);
        audit('setting_updated_'.$key,$requestId,$user['id']); $result=array_values(array_filter(settingsDocument(false),fn($x)=>$x['key']===$key))[0]; db()->commit(); respond($result);
    }
    fail('METHOD_NOT_ALLOWED','MÃ©todo no permitido.',405);
}
