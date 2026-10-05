<?php
declare(strict_types=1);

function iso(?string $date): ?string { return $date ? str_replace(' ','T',$date).'Z' : null; }
function tableFor(string $kind): string {
    if (!in_array($kind,['brands','categories','motorcycles'],true)) throw new InvalidArgumentException('Recurso inválido');
    return $kind;
}
function resource(string $kind, string $id): array {
    $table=tableFor($kind); $lock=db()->inTransaction() ? ' FOR UPDATE' : '';
    $r=query("SELECT * FROM $table WHERE (id=? OR slug=?) AND deleted_at IS NULL".$lock,[$id,$id])->fetch();
    if (!$r) fail('NOT_FOUND','Recurso no encontrado.',404);
    return hydrate($kind,$r);
}
function resources(string $kind): array {
    $table=tableFor($kind);
    return array_map(fn($r)=>hydrate($kind,$r),query("SELECT * FROM $table WHERE deleted_at IS NULL ORDER BY id DESC")->fetchAll());
}
function hydrate(string $kind, array $r): array {
    $out=['id'=>(string)$r['id'],'slug'=>$r['slug'],'createdAt'=>iso($r['created_at']),'updatedAt'=>iso($r['updated_at'])];
    if ($kind!=='motorcycles') $out+=['name'=>$r['name'],'description'=>$r['description'] ?? '', 'status'=>$r['status'],'order'=>(int)$r['sort_order']];
    if ($kind==='brands') {
        $out+=['primaryColor'=>$r['primary_color'],'secondaryColor'=>$r['secondary_color'],'accentLight'=>$r['accent_light'],'tagline'=>$r['tagline'],'slogan'=>$r['slogan'],'heroImageUrl'=>$r['hero_image_url'] ?? '', 'tileImageUrl'=>$r['tile_image_url'] ?? '', 'logo'=>mediaUrl($r['logo_media_id'])];
    } elseif ($kind==='categories') {
        $out['brandId']=$r['brand_id']===null ? '' : (string)$r['brand_id'];
    } else {
        $b=query('SELECT name FROM brands WHERE id=?',[$r['brand_id']])->fetchColumn();
        $c=query('SELECT name FROM categories WHERE id=?',[$r['category_id']])->fetchColumn();
        $out+=['brandId'=>(string)$r['brand_id'],'categoryId'=>(string)$r['category_id'],'brand'=>$b,'category'=>$c,'model'=>$r['model'],'version'=>$r['version'],'year'=>(int)$r['model_year'],'sku'=>$r['sku'] ?? '', 'price'=>$r['price']===null ? 0 : (float)$r['price'],'promoPrice'=>$r['promo_price']===null ? null : (float)$r['promo_price'],'currency'=>$r['currency'],'status'=>$r['availability_status'],'published'=>$r['publication_status']==='published','featured'=>(bool)$r['featured'],'isNew'=>(bool)$r['is_new'],'showPrice'=>(bool)$r['show_price'],'allowQuote'=>(bool)$r['allow_quote'],'shortDescription'=>$r['short_description'] ?? '', 'description'=>$r['description'] ?? '', 'displacement'=>(float)($r['displacement_cc'] ?? 0),'hp'=>(float)($r['horsepower'] ?? 0),'tagline'=>$r['tagline']];
        $out['inventory']=(int)query('SELECT COALESCE(SUM(quantity),0) FROM inventory_stock WHERE motorcycle_id=?',[$r['id']])->fetchColumn();
        $out['specs']=array_map(fn($s)=>['group'=>$s['group_name'] ?? 'General','label'=>$s['name'],'value'=>$s['value'].($s['unit'] ? ' '.$s['unit'] : '')],query('SELECT * FROM motorcycle_custom_specs WHERE motorcycle_id=? ORDER BY sort_order,id',[$r['id']])->fetchAll());
        $out['colors']=array_map(function($color) {
            $images=array_map(fn($i)=>['id'=>(string)$i['id'],'url'=>$i['url'],'alt'=>$i['alt_text'] ?? '', 'label'=>$i['label'] ?? '', 'order'=>(int)$i['sort_order'],'isPrimary'=>(bool)$i['is_primary']],query('SELECT i.*,a.url FROM motorcycle_color_images i JOIN media_assets a ON a.id=i.media_id AND a.deleted_at IS NULL WHERE i.color_id=? ORDER BY i.sort_order,i.id',[$color['id']])->fetchAll());
            return ['id'=>(string)$color['id'],'name'=>$color['name'],'hex'=>$color['hex'],'status'=>$color['status'],'available'=>(bool)$color['available'],'order'=>(int)$color['sort_order'],'images'=>$images];
        },query('SELECT * FROM motorcycle_colors WHERE motorcycle_id=? ORDER BY sort_order,id',[$r['id']])->fetchAll());
    }
    return $out;
}
function mediaUrl(mixed $id): string {
    return $id ? (query('SELECT url FROM media_assets WHERE id=? AND deleted_at IS NULL',[$id])->fetchColumn() ?: '') : '';
}
function createMedia(string $url, string $alt, string $actor): ?string {
    if ($url==='') return null;
    query("INSERT INTO media_assets (url,mime_type,alt_text,uploaded_by) VALUES (?,'application/octet-stream',?,?)",[$url,$alt,$actor]);
    return db()->lastInsertId();
}
function writeRow(string $table, array $fields, ?string $id): string {
    // Tables and field names come exclusively from server constants in saveResource.
    $columns=array_keys($fields); $values=array_values($fields);
    if ($id) {
        query('UPDATE '.$table.' SET '.implode(',',array_map(fn($k)=>$k.'=?',$columns)).' WHERE id=?',[...$values,$id]);
        return $id;
    }
    query('INSERT INTO '.$table.' ('.implode(',',$columns).') VALUES ('.implode(',',array_fill(0,count($values),'?')).')',$values);
    return db()->lastInsertId();
}
function saveResource(string $kind, array $a, ?array $old, string $actor): array {
    $id=$old['id'] ?? null;
    $fields=['slug'=>$a['slug']];
    if ($kind!=='motorcycles') $fields+=['name'=>$a['name'],'description'=>$a['description'],'status'=>$a['status'],'sort_order'=>$a['order']];
    if ($kind==='brands') {
        $fields+=['primary_color'=>$a['primaryColor'],'secondary_color'=>$a['secondaryColor'],'accent_light'=>$a['accentLight'],'tagline'=>$a['tagline'],'slogan'=>$a['slogan'],'hero_image_url'=>$a['heroImageUrl'],'tile_image_url'=>$a['tileImageUrl'],'logo_media_id'=>createMedia($a['logo'],$a['name'],$actor)];
    } elseif ($kind==='categories') {
        $fields['brand_id']=$a['brandId']==='' ? null : $a['brandId'];
    } else {
        $fields+=['brand_id'=>$a['brandId'],'category_id'=>$a['categoryId'],'model'=>$a['model'],'version'=>$a['version'],'model_year'=>$a['year'],'sku'=>$a['sku']==='' ? null : $a['sku'],'price'=>$a['price'],'promo_price'=>$a['promoPrice'],'currency'=>$a['currency'],'availability_status'=>$a['status'],'publication_status'=>$a['published'] ? 'published' : 'draft','featured'=>(int)$a['featured'],'is_new'=>(int)$a['isNew'],'show_price'=>(int)$a['showPrice'],'allow_quote'=>(int)$a['allowQuote'],'short_description'=>$a['shortDescription'],'description'=>$a['description'],'displacement_cc'=>$a['displacement'],'horsepower'=>$a['hp'],'tagline'=>$a['tagline'],'updated_by'=>$actor];
        if (!$id) $fields['created_by']=$actor;
        if ($a['published']) $fields['published_at']=gmdate('Y-m-d H:i:s');
    }
    $id=writeRow(tableFor($kind),$fields,$id);
    if ($kind==='motorcycles') {
        query('DELETE FROM motorcycle_custom_specs WHERE motorcycle_id=?',[$id]);
        foreach ($a['specs'] as $order=>$s) query('INSERT INTO motorcycle_custom_specs (motorcycle_id,name,value,group_name,sort_order) VALUES (?,?,?,?,?)',[$id,$s['label'],$s['value'],$s['group'],$order]);
        $keep=[];
        foreach ($a['colors'] as $color) {
            $existing=ctype_digit($color['id']) ? query('SELECT id FROM motorcycle_colors WHERE id=? AND motorcycle_id=?',[$color['id'],$id])->fetchColumn() : false;
            $cid=writeRow('motorcycle_colors',['motorcycle_id'=>$id,'name'=>$color['name'],'hex'=>$color['hex'],'status'=>$color['status'],'available'=>(int)$color['available'],'sort_order'=>$color['order']],$existing ? (string)$existing : null);
            $keep[]=$cid;
            query('DELETE FROM motorcycle_color_images WHERE color_id=?',[$cid]);
            foreach ($color['images'] as $image) {
                if ($image['url']==='') continue;
                $mid=createMedia($image['url'],$image['alt'],$actor);
                query('INSERT INTO motorcycle_color_images (color_id,media_id,label,alt_text,sort_order,is_primary) VALUES (?,?,?,?,?,?)',[$cid,$mid,$image['label'],$image['alt'],$image['order'],(int)$image['isPrimary']]);
            }
        }
        foreach (query('SELECT id FROM motorcycle_colors WHERE motorcycle_id=?',[$id])->fetchAll() as $color) {
            if (!in_array((string)$color['id'],$keep,true)) query('UPDATE motorcycle_colors SET status=\'inactive\',available=0 WHERE id=?',[$color['id']]);
        }
        $location=query("SELECT id FROM inventory_locations WHERE code='MAIN' AND status='active'")->fetchColumn();
        if (!$location) throw new RuntimeException('MAIN location missing');
        $stock=query('SELECT * FROM inventory_stock WHERE motorcycle_id=? AND color_id IS NULL AND location_id=? FOR UPDATE',[$id,$location])->fetch();
        // Aggregated editor is only valid when stock is not distributed by color/location.
        $other=(int)query('SELECT COUNT(*) FROM inventory_stock WHERE motorcycle_id=? AND NOT (color_id IS NULL AND location_id=?)',[$id,$location])->fetchColumn();
        if ($other) { if ($old && $a['inventory']!==$old['inventory']) throw new InvalidArgumentException('Inventario distribuido: requiere módulo de movimientos'); }
        else {
            $reserved=(int)($stock['reserved_quantity'] ?? 0);
            if ($a['inventory']<$reserved) throw new InvalidArgumentException('Inventario menor a las reservas');
            $sid=writeRow('inventory_stock',['motorcycle_id'=>$id,'location_id'=>$location,'quantity'=>$a['inventory']],$stock ? (string)$stock['id'] : null);
            $delta=$a['inventory']-(int)($stock['quantity'] ?? 0);
            if (!$stock || $delta!==0) query('INSERT INTO inventory_movements (stock_id,movement_type,quantity_delta,quantity_after,reserved_after,reason,actor_id) VALUES (?,?,?,?,?,?,?)',[$sid,$stock ? 'adjustment' : 'opening',$delta,$a['inventory'],$reserved,'Guardado de ficha administrativa',$actor]);
        }
    }
    return resource($kind,$id);
}
function activeResource(string $kind, string $id): bool {
    $table=tableFor($kind);
    return (bool)query("SELECT id FROM $table WHERE id=? AND status='active' AND deleted_at IS NULL",[$id])->fetchColumn();
}
function deleteResource(string $kind, string $id): void {
    $table=tableFor($kind);
    // Soft delete preserves inventories, quote references and historical leads.
    query("UPDATE $table SET deleted_at=UTC_TIMESTAMP() WHERE id=?",[$id]);
}
function leadDocument(array $r): array {
    return ['id'=>(string)$r['id'],'date'=>iso($r['created_at']),'name'=>$r['name'],'phone'=>$r['phone'],'email'=>$r['email'],'brand'=>$r['brand_snapshot'],'motorcycle'=>$r['motorcycle_snapshot'],'motorcycleId'=>$r['motorcycle_id']===null ? '' : (string)$r['motorcycle_id'],'type'=>$r['type'],'status'=>$r['status'],'message'=>$r['message'],'assignedTo'=>$r['assigned_to']===null ? null : (string)$r['assigned_to'],'notes'=>array_map(fn($n)=>['note'=>$n['note'],'date'=>iso($n['created_at'])],query('SELECT note,created_at FROM lead_notes WHERE lead_id=? ORDER BY id',[$r['id']])->fetchAll())];
}

function brandSummary(mixed $id, bool $public): ?array {
    if ($id===null) return null;
    $row=query('SELECT id,name,slug,primary_color,status,deleted_at FROM brands WHERE id=?',[$id])->fetch();
    if (!$row || ($public && ($row['deleted_at']!==null || $row['status']!=='active'))) return null;
    return ['id'=>(string)$row['id'],'name'=>$row['name'],'slug'=>$row['slug'],'primaryColor'=>$row['primary_color']];
}
