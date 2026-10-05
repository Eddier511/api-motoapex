<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('log_errors','1');
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/validation.php';
require dirname(__DIR__).'/src/shared_validation.php';
require dirname(__DIR__).'/src/repository.php';
require dirname(__DIR__).'/src/promotions.php';
require dirname(__DIR__).'/src/content.php';
require dirname(__DIR__).'/src/accounts.php';
require dirname(__DIR__).'/src/users_settings.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Security-Policy: default-src \'none\'; frame-ancestors \'none\'');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
$requestId=bin2hex(random_bytes(12));
header('X-Request-ID: '.$requestId);
try {
    $origin=$_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '') {
        if (!in_array($origin,config()['allowed_origins'],true)) fail('ORIGIN_DENIED','Origen no permitido.',403);
        header('Access-Control-Allow-Origin: '.$origin);
        header('Vary: Origin');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Reauth-Token');
        header('Access-Control-Expose-Headers: X-Request-ID, Retry-After');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
    if (PHP_SAPI !== 'cli-server' && empty($_SERVER['HTTPS']) && ($_SERVER['SERVER_PORT'] ?? '') != 443) fail('HTTPS_REQUIRED','Usa HTTPS.',400);
    if (PHP_SAPI !== 'cli-server') header('Strict-Transport-Security: max-age=31536000');
    $method=$_SERVER['REQUEST_METHOD'];
    if ($method==='OPTIONS') { http_response_code(204); exit; }
    if (in_array($method,['POST','PUT','PATCH'],true) && !preg_match('/^application\/json(?:\s*;|$)/i',$_SERVER['CONTENT_TYPE'] ?? '')) fail('UNSUPPORTED_MEDIA_TYPE','Usa application/json.',415);
    $path=explode('/',trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'));
    if (($path[0] ?? '')!=='v1') fail('NOT_FOUND','Ruta no encontrada.',404);
    array_shift($path);
    if ($path===['health'] && $method==='GET') { query('SELECT 1'); respond(['status'=>'ok']); }
    handleAuth($path,$method,$requestId);
    if ($path===['public','leads'] && $method==='POST') {
        limit('lead',5); $a=body();
        $out=['status'=>'new'];
        $out['name']=textField($a,'name',120,true); $out['phone']=textField($a,'phone',40,true);
        $out['email']=textField($a,'email',190);
        if ($out['email']!=='' && !filter_var($out['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Email inválido');
        $out['type']=choice($a,'type',['quote','availability','test_ride','contact','whatsapp'],'contact');
        $out['message']=textField($a,'message',3000);
        $out['motorcycleId']=textField($a,'motorcycleId',32);
        if ($out['motorcycleId']!=='') {
            $m=resource('motorcycles',$out['motorcycleId']);
            if (!visible($m)) fail('NOT_FOUND','Moto no disponible.',404);
            if ($out['type']==='quote' && !$m['allowQuote']) fail('QUOTE_DISABLED','Cotización no disponible.',422);
            $out['brand']=resource('brands',$m['brandId'])['name']; $out['motorcycle']=$m['model'];
        }
        $mid=$out['motorcycleId']==='' ? null : $out['motorcycleId'];
        query('INSERT INTO leads (name,phone,email,type,message,motorcycle_id,brand_id,brand_snapshot,motorcycle_snapshot) VALUES (?,?,?,?,?,?,?,?,?)',[$out['name'],$out['phone'],$out['email'],$out['type'],$out['message'],$mid,isset($m) ? $m['brandId'] : null,$out['brand'] ?? null,$out['motorcycle'] ?? null]);
        respond(['id'=>db()->lastInsertId()],201);
    }
    if ($path===['public','leads']) fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
    $scope=$path[0] ?? ''; $kind=$path[1] ?? ''; $id=$path[2] ?? null;
    if (count($path)>3 || !in_array($scope,['admin','public'],true)) fail('NOT_FOUND','Ruta no encontrada.',404);
    $user=$scope==='admin' ? identity() : null;
    if ($scope==='admin' && $kind==='lead-assignees' && $method==='GET' && $id===null) {
        permit($user,'leads.manage'); limit('lead-assignees',150);
        $rows=query("SELECT u.id,u.name FROM users u JOIN roles r ON r.id=u.role_id WHERE u.status='active' AND u.deleted_at IS NULL AND r.code IN ('admin','sales') ORDER BY u.name,u.id")->fetchAll();
        respond(array_map(fn($r)=>['id'=>(string)$r['id'],'name'=>$r['name']],$rows));
    }
    if ($scope==='admin' && in_array($kind,['users','roles'],true)) handleUsers($kind,$method,$id,$user,$requestId);
    if ($kind==='settings') handleSettings($scope,$method,$id,$user,$requestId);
    if (isset(CONTENT_TABLES[$kind])) handleContent($scope,$kind,$method,$id,$user,$requestId);
    if ($kind==='promotions') handlePromotions($scope,$method,$id,$user,$requestId);
    if ($kind==='leads' && $scope==='admin') {
        authorize($user,['admin','sales']);
        permit($user,'leads.manage');
        if ($method==='GET' && !$id) {
            $rows=query('SELECT * FROM leads WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 200')->fetchAll();
            respond(array_map('leadDocument',$rows));
        }
        if ($method==='PATCH' && $id) {
            db()->beginTransaction();
            $doc=query('SELECT * FROM leads WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$id])->fetch();
            if (!$doc) fail('NOT_FOUND','Solicitud no encontrada.',404);
            $a=body();
            $doc['status']=choice($a,'status',['new','contacted','follow_up','closed','discarded'],$doc['status']);
            $assigned=array_key_exists('assignedTo',$a) ? textField($a,'assignedTo',20) : ($doc['assigned_to']===null ? '' : (string)$doc['assigned_to']);
            if ($assigned!=='' && !query("SELECT u.id FROM users u JOIN roles r ON r.id=u.role_id WHERE u.id=? AND u.status='active' AND u.deleted_at IS NULL AND r.code IN ('admin','sales')",[$assigned])->fetchColumn()) throw new InvalidArgumentException('Asignación inválida');
            query('UPDATE leads SET status=?,assigned_to=? WHERE id=?',[$doc['status'],$assigned==='' ? null : $assigned,$id]);
            $note=textField($a,'notes',5000);
            if ($note!=='') query('INSERT INTO lead_notes (lead_id,author_id,note) VALUES (?,?,?)',[$id,$user['id'],$note]);
            $result=leadDocument(query('SELECT * FROM leads WHERE id=?',[$id])->fetch());
            audit('lead_updated',$requestId,$user['id']); db()->commit(); respond($result);
        }
        fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
    }
    if (!in_array($kind,['brands','categories','motorcycles'],true)) fail('NOT_FOUND','Ruta no encontrada.',404);
    if ($scope==='public' && $method!=='GET') fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
    if ($scope==='admin') { authorize($user,['admin','editor','marketing']); permit($user,'motorcycles.read'); }
    if ($method==='GET') {
        $rows=$id ? [resource($kind,$id)] : resources($kind);
        if ($scope==='public') {
            $rows=array_values(array_filter($rows,fn($r)=>$kind==='motorcycles' ? visible($r) : $r['status']==='active'));
            if ($kind==='motorcycles') $rows=array_map('publicMotorcycle',$rows);
            if ($kind==='brands') $rows=array_map(function($b) {
                $b['categories']=array_values(array_filter(resources('categories'),fn($c)=>$c['status']==='active' && ($c['brandId']==='' || $c['brandId']===$b['id'])));
                return $b;
            },$rows);
        }
        if ($id && !$rows) fail('NOT_FOUND','Recurso no encontrado.',404);
        respond($id ? $rows[0] : $rows);
    }
    authorize($user,['admin','editor']);
    if (($method==='POST' && !$id) || ($method==='PUT' && $id)) {
        db()->beginTransaction();
        $old=$id ? resource($kind,$id) : null; $out=validateResource($kind,body());
        permit($user,['motorcycles'=>'motorcycles.write','brands'=>'brands.manage','categories'=>'categories.manage'][$kind]);
        if ($kind==='motorcycles' && $out['published']!==($old['published'] ?? false)) permit($user,'motorcycles.publish');
        if ($kind==='motorcycles') {
            $brand=resource('brands',$out['brandId']); $category=resource('categories',$out['categoryId']);
            if ($category['brandId']!=='' && $category['brandId']!==$out['brandId']) throw new InvalidArgumentException('Categoría de otra marca');
            $out['brand']=$brand['name']; $out['category']=$category['name'];
        }
        if ($kind==='categories' && $out['brandId']!=='') resource('brands',$out['brandId']);
        $out=saveResource($kind,$out,$old,$user['id']);
        audit('resource_saved',$requestId,$user['id']); db()->commit(); respond($out,$old ? 200 : 201);
    }
    if ($method==='DELETE' && $id) {
        db()->beginTransaction();
        authorize($user,['admin']); $old=resource($kind,$id);
        permit($user,['motorcycles'=>'motorcycles.write','brands'=>'brands.manage','categories'=>'categories.manage'][$kind]);
        if ($kind!=='motorcycles') {
            $key=$kind==='brands' ? 'brand_id' : 'category_id';
            $used=query("SELECT COUNT(*) FROM motorcycles WHERE $key=? AND deleted_at IS NULL",[$old['id']])->fetchColumn();
            if ($kind==='brands') $used+=(int)query('SELECT COUNT(*) FROM categories WHERE brand_id=? AND deleted_at IS NULL',[$old['id']])->fetchColumn();
            if ($used) fail('IN_USE','Recurso utilizado. Desactívalo.',409);
        }
        deleteResource($kind,$old['id']);
        audit('resource_deleted',$requestId,$user['id']); db()->commit(); respond(['deleted'=>true]);
    }
    fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
} catch (InvalidArgumentException $e) { fail('VALIDATION_ERROR',$e->getMessage(),422); }
catch (PDOException $e) {
    if ($e->getCode()==='23000') fail('CONFLICT','Ya existe un recurso con ese identificador.',409);
    error_log('MotoApex request='.$requestId.' database_error'); fail('INTERNAL_ERROR','Error interno. Referencia: '.$requestId,500);
} catch (Throwable $e) {
    error_log('MotoApex request='.$requestId.' exception='.get_class($e)); fail('INTERNAL_ERROR','Error interno. Referencia: '.$requestId,500);
}
