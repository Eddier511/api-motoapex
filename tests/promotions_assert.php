<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/bootstrap.php';
$row=query("SELECT * FROM promotions WHERE slug='campaign'")->fetch();
if (!$row || !$row['deleted_at']) throw new RuntimeException('Physical record must survive deletion');
if (!query("SELECT COUNT(*) FROM audit_logs WHERE action='promotion_deleted'")->fetchColumn()) throw new RuntimeException('Missing audit');
if (!query("SELECT COUNT(*) FROM schema_migrations WHERE version='003_promotions'")->fetchColumn()) throw new RuntimeException('Missing migration');
echo "Promotion persistence and audit checks passed\n";
