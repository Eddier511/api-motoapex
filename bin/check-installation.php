<?php
declare(strict_types=1);
// Read-only private CLI helper. This does NOT describe the PHP web worker.
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0');
$root=dirname(__DIR__); $problems=[]; $revision=null;
$manifestPath=$root.'/deployment-manifest.json';
try {
    if (!is_file($manifestPath)) throw new RuntimeException('manifest_missing');
    $manifest=json_decode(file_get_contents($manifestPath),true,512,JSON_THROW_ON_ERROR);
    $revision=$manifest['sourceRevision'] ?? null;
    if (!is_string($revision) || !preg_match('/^[a-f0-9]{40}$/D',$revision)) throw new RuntimeException('revision_missing');
    if (empty($manifest['fileSha256']) || !is_array($manifest['fileSha256'])) throw new RuntimeException('checksums_missing_legacy_package');
    foreach ($manifest['fileSha256'] as $relative=>$expected) {
        if (!is_string($relative) || !preg_match('#^[a-zA-Z0-9_.\-/]+$#D',$relative) || str_contains($relative,'..') || str_starts_with($relative,'/') || !is_string($expected) || !preg_match('/^[a-f0-9]{64}$/D',$expected)) throw new RuntimeException('invalid_manifest');
        $path=$root.'/'.$relative;
        if (!is_file($path) || !is_readable($path) || !hash_equals($expected,hash_file('sha256',$path))) $problems[]=$relative;
    }
} catch (Throwable) { $problems[]='manifest_unverifiable'; }
echo json_encode(['scope'=>'CLI only; web PHP must be checked separately','phpVersion'=>PHP_VERSION,'sapi'=>PHP_SAPI,
    'pdoMysql'=>extension_loaded('pdo_mysql'),'sodium'=>extension_loaded('sodium'),
    'sourceRevision'=>$revision,'diskIntegrityVerified'=>!$problems,'mismatchedFiles'=>$problems,
    'opcacheNote'=>'Disk integrity cannot prove which bytecode the web workers have cached.'],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL;
exit($problems ? 1 : 0);
