<?php
if (PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/src/bootstrap.php';
if ((config()['environment'] ?? '')!=='test') throw new RuntimeException('Disposable test database required');
if (($argv[1] ?? '')==='clean') {
    foreach (['motorcycles','categories','brands','web_pages'] as $table) query("UPDATE $table SET deleted_at=UTC_TIMESTAMP() WHERE slug LIKE 'hardening-%'");
    exit;
}
$brand=null;
for ($i=0;$i<260;$i++) {
    query("INSERT INTO brands (name,slug,status) VALUES (?,?,'active')",['Hardening '.$i,'hardening-brand-'.$i]);
    if ($brand===null) $brand=(string)db()->lastInsertId();
}
query("INSERT INTO categories (name,slug,status) VALUES ('Hardening','hardening-category','active')"); $category=(string)db()->lastInsertId();
for ($i=0;$i<25;$i++) {
    query("INSERT INTO motorcycles (brand_id,category_id,model,slug,model_year,publication_status,show_price,allow_quote,price) VALUES (?,?,'Hardening',?,2026,'published',?,?,1000)",[$brand,$category,'hardening-bike-'.$i,(int)($i%2===0),(int)($i%2===0)]);
    $m=(string)db()->lastInsertId();
    query("INSERT INTO inventory_stock (motorcycle_id,location_id,quantity) VALUES (?,(SELECT id FROM inventory_locations WHERE code='MAIN'),5)",[$m]);
    query("INSERT INTO motorcycle_custom_specs (motorcycle_id,name,value) VALUES (?,'Power','20')",[$m]);
    query("INSERT INTO motorcycle_colors (motorcycle_id,name,hex,status) VALUES (?,'Test','#123456','active')",[$m]); $color=db()->lastInsertId();
    query("INSERT INTO media_assets (url,mime_type) VALUES ('https://example.test/image.jpg','image/jpeg')"); $media=db()->lastInsertId();
    query('INSERT INTO motorcycle_color_images (color_id,media_id) VALUES (?,?)',[$color,$media]);
}
for ($i=0;$i<6;$i++) query("INSERT INTO web_pages (title,slug,body,status,sort_order) VALUES (?,?,?,'published',?)",['Hardening','hardening-page-'.$i,'Text',intdiv($i,2)]);
echo "Disposable hardening fixtures prepared\n";
