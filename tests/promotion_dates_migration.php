<?php
declare(strict_types=1);
// Included by setup.php, after 006 and before any HTTP fixture. Never packaged.
if (PHP_SAPI!=='cli' || (config()['environment'] ?? '')!=='test') throw new RuntimeException('Disposable database required');
foreach (['validation','shared_validation','repository','read_limits','promotions'] as $source) require_once dirname(__DIR__).'/src/'.$source.'.php';
function dateCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
query("INSERT INTO brands (name,slug,status) VALUES ('Migration preserved','migration-preserved','active')"); $brand=db()->lastInsertId();
query("INSERT INTO categories (name,slug,status,brand_id) VALUES ('Migration preserved','migration-preserved','active',?)",[$brand]); $category=db()->lastInsertId();
query("INSERT INTO motorcycles (brand_id,category_id,model,slug,model_year,publication_status,currency,price) VALUES (?,?,'Migration preserved','migration-preserved',2026,'published','CRC',1000)",[$brand,$category]); $bike=db()->lastInsertId();
query("INSERT INTO media_assets (url,mime_type) VALUES ('https://example.test/preserved.jpg','image/jpeg')"); $media=db()->lastInsertId();
query("INSERT INTO promotions (title,slug,image_media_id,brand_id,starts_at,ends_at,status) VALUES ('Migration preserved','migration-preserved',?,?,'2026-10-01 00:00:00','2026-10-31 00:00:00','active')",[$media,$brand]); $promotion=db()->lastInsertId();
query("INSERT INTO promotion_motorcycles (promotion_id,motorcycle_id,original_price,promo_price,currency) VALUES (?,?,1000,900,'CRC')",[$promotion,$bike]);
$tables=['promotions','promotion_motorcycles','media_assets','motorcycles','brands','categories']; $before=[];
foreach ($tables as $table) $before[$table]=query("SELECT * FROM $table ORDER BY id")->fetchAll();
$checks=query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promotions' AND CONSTRAINT_TYPE='CHECK' ORDER BY CONSTRAINT_NAME")->fetchAll();
db()->exec(file_get_contents(dirname(__DIR__).'/database/007_optional_promotion_dates.sql'));
foreach ($tables as $table) dateCheck(query("SELECT * FROM $table ORDER BY id")->fetchAll()===$before[$table],'Migration changed existing records');
dateCheck($checks && $checks===query("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promotions' AND CONSTRAINT_TYPE='CHECK' ORDER BY CONSTRAINT_NAME")->fetchAll(),'Existing CHECK lost');
dateCheck((int)query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='promotions' AND COLUMN_NAME IN ('starts_at','ends_at') AND IS_NULLABLE='YES'")->fetchColumn()===2,'Nullable columns missing');
dateCheck((int)query("SELECT COUNT(*) FROM schema_migrations WHERE version='007_optional_promotion_dates'")->fetchColumn()===1,'Migration marker missing');

// Freeze only THIS isolated SQL session, avoiding races at inclusive boundaries.
db()->exec('SET timestamp=1793188800'); // 2026-10-28 12:00:00 UTC
$now=query('SELECT UTC_TIMESTAMP()')->fetchColumn();
$past=query('SELECT DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND)')->fetchColumn();
$future=query('SELECT DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 SECOND)')->fetchColumn();
db()->beginTransaction();
try {
    foreach ([[null,null,true],[$past,null,true],[$future,null,false],[null,$future,true],[null,$past,false],[$past,$future,true],[$now,$now,true],[$now,null,true],[null,$now,true],[$future,$future,false],[$past,$past,false]] as [$start,$end,$visible]) {
        query('UPDATE promotions SET starts_at=?,ends_at=? WHERE id=?',[$start,$end,$promotion]);
        $document=promotionDocument(promotionRow($promotion),true);
        dateCheck(($document!==null)===$visible,'Detail date boundary failed');
        $rows=query('SELECT p.id FROM promotions p WHERE p.id=? AND '.promotionDateWindow('p.'),[$promotion])->fetchAll();
        dateCheck((bool)$rows===$visible,'List date boundary failed');
        if ($visible) dateCheck($document['startsAt']===iso($start) && $document['endsAt']===iso($end),'Nullable serialization failed');
    }
    $rejected=false;
    try { query('UPDATE promotions SET starts_at=?,ends_at=? WHERE id=?',[$future,$past,$promotion]); }
    catch (PDOException $e) { $rejected=(int)($e->errorInfo[1] ?? 0)===3819; }
    dateCheck($rejected,'CHECK no longer rejects reversed non-null dates');
} finally { db()->rollBack(); db()->exec('SET timestamp=0'); }
// Remove only this disposable pre-migration fixture before the remaining suites.
query('DELETE FROM promotions WHERE id=?',[$promotion]);
query('DELETE FROM motorcycles WHERE id=?',[$bike]);
query('DELETE FROM categories WHERE id=?',[$category]);
query('DELETE FROM brands WHERE id=?',[$brand]);
query('DELETE FROM media_assets WHERE id=?',[$media]);
echo "Migration 007 preserves records/relations/prices/CHECK and nullable inclusive date boundaries passed\n";
