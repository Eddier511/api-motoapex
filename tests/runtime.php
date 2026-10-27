<?php
require dirname(__DIR__).'/src/runtime.php';
foreach ([80235=>false,80335=>false,80336=>true,80337=>true,80426=>false,80427=>true,80511=>false,80512=>true,80600=>false] as $version=>$expected) if (supportedRuntime($version)!==$expected) throw new RuntimeException('Runtime floor failed');
requireSecureRuntime();
foreach ([[80336,true,true,[]],[80335,true,true,['php_version']],[80336,false,true,['pdo_mysql']],[80336,true,false,['sodium']],[80235,false,false,['php_version','pdo_mysql','sodium']]] as [$version,$pdo,$sodium,$expected]) {
    if (runtimeFailures($version,$pdo,$sodium)!==$expected) throw new RuntimeException('Runtime reason classification failed');
}
$diagnostics=runtimeDiagnostics();
if ($diagnostics['failures']!==[] || $diagnostics['sapi']!==PHP_SAPI || $diagnostics['phpVersionId']!==PHP_VERSION_ID) throw new RuntimeException('Runtime diagnostics mismatch');
echo "Runtime floor and required extensions passed\n";
