<?php
require dirname(__DIR__).'/src/bootstrap.php';
if ((int)query('SELECT COUNT(*) FROM content_revisions')->fetchColumn()<15) throw new RuntimeException('Missing revisions');
foreach (['web_pages','web_banners','social_links'] as $table) if (!query("SELECT id FROM $table WHERE deleted_at IS NOT NULL")->fetchColumn()) throw new RuntimeException('Missing logical deletion');
if ((int)query('SELECT COUNT(*) FROM site_contact')->fetchColumn()!==1) throw new RuntimeException('Contact duplicated');
if (!query("SELECT id FROM audit_logs WHERE action='content_deleted'")->fetchColumn()) throw new RuntimeException('Missing audit');
echo "Content revisions, audit and preserved history passed\n";
