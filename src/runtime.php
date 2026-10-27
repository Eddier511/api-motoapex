<?php
declare(strict_types=1);

function supportedRuntime(int $version): bool {
    // Reject untested future branches; extend only after compatibility tests.
    $minimum=[803=>80336,804=>80427,805=>80512];
    return isset($minimum[intdiv($version,100)]) && $version >= $minimum[intdiv($version,100)];
}
function runtimeFailures(int $version,bool $pdoMysql,bool $sodium): array {
    $failures=[];
    if (!supportedRuntime($version)) $failures[]='php_version';
    if (!$pdoMysql) $failures[]='pdo_mysql';
    if (!$sodium) $failures[]='sodium';
    return $failures;
}
function runtimeDiagnostics(): array {
    $pdoMysql=extension_loaded('pdo_mysql'); $sodium=extension_loaded('sodium');
    $entry=dirname(__DIR__).'/public_html/index.php';
    if (!is_file($entry)) $entry=dirname(__DIR__).'/public/index.php';
    return ['phpVersion'=>PHP_VERSION,'phpVersionId'=>PHP_VERSION_ID,'sapi'=>PHP_SAPI,
        'pdoMysql'=>$pdoMysql,'sodium'=>$sodium,
        'failures'=>runtimeFailures(PHP_VERSION_ID,$pdoMysql,$sodium),
        'opcacheEnabled'=>filter_var(ini_get('opcache.enable'),FILTER_VALIDATE_BOOL),
        'opcacheValidateTimestamps'=>filter_var(ini_get('opcache.validate_timestamps'),FILTER_VALIDATE_BOOL),
        'runtimeDiskSha256'=>hash_file('sha256',__FILE__),
        'entrypointDiskSha256'=>is_readable($entry) ? hash_file('sha256',$entry) : null];
}
function requireSecureRuntime(): void {
    if (runtimeFailures(PHP_VERSION_ID,extension_loaded('pdo_mysql'),extension_loaded('sodium'))) {
        throw new RuntimeException('Unsupported PHP runtime or missing required extensions');
    }
}
