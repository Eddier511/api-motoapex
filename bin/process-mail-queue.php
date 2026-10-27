<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
ini_set('display_errors','0'); ini_set('log_errors','1');
require dirname(__DIR__).'/src/runtime.php';
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/repository.php';
require dirname(__DIR__).'/src/accounts.php';
require dirname(__DIR__).'/src/mail_queue.php';
try {
    requireSecureRuntime();
    $limit=$argv[1] ?? '20';
    if (!ctype_digit($limit) || (int)$limit<1 || (int)$limit>100) throw new InvalidArgumentException('Invalid limit');
    echo json_encode(runMailQueue((int)$limit),JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) { fwrite(STDERR,"Mail queue failed; check private configuration and sanitized server logs.\n"); exit(1); }
