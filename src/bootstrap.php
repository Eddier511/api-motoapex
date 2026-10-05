<?php
declare(strict_types=1);

function config(): array {
    static $config;
    if ($config === null) {
        $path = dirname(__DIR__) . '/config.local.php';
        if (!is_file($path)) throw new RuntimeException('Server configuration missing');
        $config = require $path;
    }
    return $config;
}

function audit(string $event, string $requestId, ?string $userId = null, string $entityType='api', ?string $entityId=null): void {
    // Do not log credentials, bearer tokens, request bodies or lead contact details.
    error_log(json_encode(['service'=>'motoapex','event'=>$event,'requestId'=>$requestId,'userId'=>$userId,'time'=>gmdate('c')], JSON_THROW_ON_ERROR));
    query('INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,request_id) VALUES (?,?,?,?,?)',[$userId,$event,$entityType,$entityId,$requestId]);
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $c = config();
        $pdo = new PDO("mysql:host={$c['db_host']};port={$c['db_port']};dbname={$c['db_name']};charset=utf8mb4", $c['db_user'], $c['db_password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement {
    $s = db()->prepare($sql);
    $s->execute($params);
    return $s;
}

function respond(mixed $data, int $status = 200): never {
    metric($status);
    http_response_code($status);
    echo json_encode(['data' => $data], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function fail(string $code, string $message, int $status): never {
    metric($status);
    http_response_code($status);
    echo json_encode(['error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
    exit;
}

function metric(int $status): void {
    error_log(json_encode(['service'=>'motoapex','event'=>'http_request','requestId'=>$GLOBALS['requestId'] ?? null,'status'=>$status,'durationMs'=>round((microtime(true)-($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true)))*1000,2)],JSON_THROW_ON_ERROR));
}

function body(): array {
    $raw = file_get_contents('php://input', false, null, 0, 262145);
    if (strlen($raw) > 262144) fail('PAYLOAD_TOO_LARGE', 'Máximo 256 KiB.', 413);
    try { $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
    catch (JsonException) { fail('INVALID_JSON', 'JSON inválido.', 400); }
    if (!is_array($data) || !str_starts_with(ltrim($raw), '{')) fail('INVALID_JSON', 'Se requiere un objeto JSON.', 400);
    return $data;
}

function limit(string $action, int $maximum): void {
    // REMOTE_ADDR is supplied by the server; do not trust client forwarded headers.
    $bucket = hash('sha256', $action . ':' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . ':' . intdiv(time(), 900));
    query('INSERT INTO rate_limits (bucket,hits,expires_at) VALUES (?,1,DATE_ADD(UTC_TIMESTAMP(), INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE hits=hits+1', [$bucket]);
    if ((int) query('SELECT hits FROM rate_limits WHERE bucket=?', [$bucket])->fetchColumn() > $maximum) {
        header('Retry-After: 900');
        fail('RATE_LIMITED', 'Demasiados intentos. Intenta más tarde.', 429);
    }
    query('DELETE FROM rate_limits WHERE expires_at < UTC_TIMESTAMP() LIMIT 100');
}

function identity(): array { return accountIdentity(); }

function authorize(array $user, array $roles): void {
    if (!in_array($user['role'], $roles, true)) fail('FORBIDDEN', 'No tienes permisos.', 403);
}

function permit(array $user, string $permission): void {
    $granted=query('SELECT p.id FROM users u JOIN role_permissions rp ON rp.role_id=u.role_id JOIN permissions p ON p.id=rp.permission_id WHERE u.id=? AND p.code=?',[$user['id'],$permission])->fetchColumn();
    if (!$granted) fail('FORBIDDEN','Permiso no concedido.',403);
}

function visible(array $m): bool {
    return $m['published'] && activeResource('brands', $m['brandId']) && activeResource('categories', $m['categoryId']);
}

function publicMotorcycle(array $m): array {
    $b = resource('brands', $m['brandId']);
    $c = resource('categories', $m['categoryId']);
    $out = [
        'id'=>$m['id'], 'slug'=>$m['slug'], 'brandId'=>$m['brandId'], 'brandName'=>$b['name'], 'brandColor'=>$b['primaryColor'],
        'model'=>$m['model'], 'version'=>$m['version'], 'year'=>$m['year'], 'categoryId'=>$m['categoryId'], 'categoryName'=>$c['name'],
        'currency'=>$m['currency'], 'description'=>$m['description'], 'shortDescription'=>$m['shortDescription'],
        'availability'=>['available'=>'available','reserved'=>'reserved','coming_soon'=>'coming-soon','sold_out'=>'sold-out'][$m['status']],
        'isNew'=>$m['isNew'], 'isFeatured'=>$m['featured'], 'cc'=>$m['displacement'], 'hp'=>$m['hp'], 'tagline'=>$m['tagline'],
        'allowQuote'=>$m['allowQuote'], 'showPrice'=>$m['showPrice'], 'specs'=>$m['specs'],
        'colorOptions'=>array_values(array_filter($m['colors'], fn($color)=>$color['status']==='active')),
    ];
    if ($m['showPrice']) { $out['price']=$m['price']; $out['promoPrice']=$m['promoPrice']; }
    return $out;
}
