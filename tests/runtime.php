<?php
require dirname(__DIR__).'/src/runtime.php';
foreach ([80235=>false,80335=>false,80336=>true,80337=>true,80426=>false,80427=>true,80511=>false,80512=>true,80600=>false] as $version=>$expected) if (supportedRuntime($version)!==$expected) throw new RuntimeException('Runtime floor failed');
requireSecureRuntime();
echo "Runtime floor and required extensions passed\n";
