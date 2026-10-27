<?php
require dirname(__DIR__).'/src/bootstrap.php';
require dirname(__DIR__).'/src/validation.php';
require dirname(__DIR__).'/src/shared_validation.php';
require dirname(__DIR__).'/src/repository.php';
require dirname(__DIR__).'/src/read_limits.php';
require dirname(__DIR__).'/src/catalog.php';
if ((config()['environment'] ?? '')!=='test') throw new RuntimeException('Disposable database required');
foreach (['brands','categories'] as $kind) {
    $dto=publicTaxonomy($kind,['id'=>'1','slug'=>'safe','name'=>'Safe','createdAt'=>'private','updatedAt'=>'private','futureSecret'=>'private']);
    if ($dto!==['id'=>'1','slug'=>'safe','name'=>'Safe']) throw new RuntimeException('Public taxonomy allowlist regression');
}
$rows=query("SELECT * FROM motorcycles WHERE slug LIKE 'hardening-bike-%' AND deleted_at IS NULL ORDER BY id")->fetchAll();
if (!$rows) { echo "Batch budget fixture cleaned\n"; exit; }
foreach ([array_slice($rows,0,1),$rows] as $selection) {
    $GLOBALS['dbQueries']=0; $batch=catalogBatch('motorcycles',$selection,true);
    $data=boundedDocuments($selection,fn($row)=>publicMotorcycle(hydrate('motorcycles',$row,$batch),$batch['brands'][$row['brand_id']],$batch['categories'][$row['category_id']]));
    if (count($data)!==count($selection) || $GLOBALS['dbQueries']>5) throw new RuntimeException('N+1 query regression');
}
echo "Public motorcycle relation queries remain constant for 1 and 25 rows\n";
