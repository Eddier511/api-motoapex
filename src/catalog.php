<?php
declare(strict_types=1);

function publicTaxonomy(string $kind,array $document): array {
    // Explicit public DTO: new private hydration fields must never become public by default.
    $fields=['id','slug','name','description','status','order'];
    $fields=array_merge($fields,$kind==='brands'
        ? ['primaryColor','secondaryColor','accentLight','tagline','slogan','heroImageUrl','tileImageUrl','logo']
        : ['brandId']);
    return array_intersect_key($document,array_flip($fields));
}

function catalogBatch(string $kind,array $rows,bool $public): array {
    $batch=['media'=>[],'brands'=>[],'categories'=>[],'inventory'=>[],'specs'=>[],'colors'=>[],'images'=>[]];
    if (!$rows) return $batch;
    if ($kind==='brands') { $batch['media']=mediaMap(array_column($rows,'logo_media_id')); return $batch; }
    if ($kind==='categories') return $batch;
    $ids=array_column($rows,'id'); $marks=inParameters($ids);
    $brandIds=array_values(array_unique(array_column($rows,'brand_id'))); $categoryIds=array_values(array_unique(array_column($rows,'category_id')));
    $batch['brands']=keyedRows(query('SELECT id,name,primary_color FROM brands WHERE id IN ('.inParameters($brandIds).')',$brandIds)->fetchAll());
    $batch['categories']=keyedRows(query('SELECT id,name FROM categories WHERE id IN ('.inParameters($categoryIds).')',$categoryIds)->fetchAll());
    if (!$public) $batch['inventory']=array_column(query("SELECT motorcycle_id,SUM(quantity) AS quantity FROM inventory_stock WHERE motorcycle_id IN ($marks) GROUP BY motorcycle_id",$ids)->fetchAll(),'quantity','motorcycle_id');
    $batch['specs']=groupedRows(boundedRows("SELECT * FROM motorcycle_custom_specs WHERE motorcycle_id IN ($marks) ORDER BY sort_order,id",$ids),'motorcycle_id');
    $colors=boundedRows("SELECT * FROM motorcycle_colors WHERE motorcycle_id IN ($marks)".($public ? " AND status='active'" : '').' ORDER BY sort_order,id',$ids);
    $batch['colors']=groupedRows($colors,'motorcycle_id');
    if ($colors) {
        $colorIds=array_column($colors,'id');
        $batch['images']=groupedRows(boundedRows('SELECT i.*,a.url FROM motorcycle_color_images i JOIN media_assets a ON a.id=i.media_id AND a.deleted_at IS NULL WHERE i.color_id IN ('.inParameters($colorIds).') ORDER BY i.sort_order,i.id',$colorIds),'color_id');
    }
    return $batch;
}
function catalogRows(string $kind,bool $public,?string $id): array {
    $table=tableFor($kind); $where='m.deleted_at IS NULL'; $params=[];
    if ($public) {
        $where.=$kind==='motorcycles'
            ? " AND m.publication_status='published' AND EXISTS (SELECT 1 FROM brands b WHERE b.id=m.brand_id AND b.status='active' AND b.deleted_at IS NULL) AND EXISTS (SELECT 1 FROM categories c WHERE c.id=m.category_id AND c.status='active' AND c.deleted_at IS NULL)"
            : " AND m.status='active'";
    }
    if ($id!==null) {
        $where.=ctype_digit($id) ? ' AND m.id=?' : ' AND m.slug=?'; $params[]=$id;
        $rows=query("SELECT m.* FROM $table m WHERE $where",$params)->fetchAll();
        if (!$rows) fail('NOT_FOUND','Recurso no encontrado.',404);
        return $rows;
    }
    return listRows($table.' m',$where,$params,'m',true);
}
function handleCatalogRead(string $kind,bool $public,?string $id): never {
    limit($public ? 'catalog-public-read' : 'catalog-admin-read',300);
    $rows=catalogRows($kind,$public,$id); $batch=catalogBatch($kind,$rows,$public);
    $categories=[];
    if ($public && $kind==='brands' && $rows) {
        $brandIds=array_column($rows,'id');
        $categoryRows=boundedRows("SELECT * FROM categories WHERE deleted_at IS NULL AND status='active' AND (brand_id IS NULL OR brand_id IN (".inParameters($brandIds).')) ORDER BY id DESC',$brandIds);
        $categories=array_map(fn($r)=>publicTaxonomy('categories',hydrate('categories',$r,[])),$categoryRows);
    }
    $data=boundedDocuments($rows,function($r) use($kind,$public,$batch,$categories) {
        $out=hydrate($kind,$r,$batch);
        if ($public && $kind!=='motorcycles') $out=publicTaxonomy($kind,$out);
        if ($public && $kind==='motorcycles') $out=publicMotorcycle($out,$batch['brands'][$r['brand_id']],$batch['categories'][$r['category_id']]);
        if ($public && $kind==='brands') $out['categories']=array_values(array_filter($categories,fn($c)=>$c['brandId']==='' || $c['brandId']===$out['id']));
        return $out;
    });
    respond($id!==null ? $data[0] : $data);
}
