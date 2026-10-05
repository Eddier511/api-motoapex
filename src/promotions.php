<?php
declare(strict_types=1);

function promotionMoney(array $a, string $key): string {
    if (!array_key_exists($key,$a)) throw new InvalidArgumentException("Precio requerido: $key");
    $value=numberField($a,$key,1000000000);
    $cents=round($value*100);
    if (abs($value*100-$cents)>0.00001) throw new InvalidArgumentException("Máximo dos decimales: $key");
    return number_format($cents/100,2,'.','');
}
function validatePromotion(array $a): array {
    $allowed=['title','slug','description','imageUrl','brandId','motorcycles','startsAt','endsAt','status','featured','showOnHome','order','buttonLabel','buttonHref'];
    foreach (array_keys($a) as $key) if (!in_array($key,$allowed,true)) throw new InvalidArgumentException("Campo desconocido: $key");
    foreach (['title','slug','description','imageUrl','motorcycles','startsAt','endsAt','status','featured','showOnHome','order','buttonLabel','buttonHref'] as $key) if (!array_key_exists($key,$a)) throw new InvalidArgumentException("Campo requerido: $key");
    $slug=textField($a,'slug',191,true);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug) || ctype_digit($slug)) throw new InvalidArgumentException('Slug inválido');
    $image=urlField($a,'imageUrl');
    if ($image==='' || parse_url($image,PHP_URL_USER)!==null || parse_url($image,PHP_URL_PASS)!==null) throw new InvalidArgumentException('Imagen HTTPS requerida, sin credenciales');
    $brand=$a['brandId'] ?? null;
    if ($brand!==null) $brand=entityId($brand,'brandId');
    $start=utcDate($a,'startsAt'); $end=utcDate($a,'endsAt');
    if ($end<$start) throw new InvalidArgumentException('Fin de vigencia anterior al inicio');
    $seen=[]; $links=[];
    foreach (listField($a,'motorcycles',100) as $item) {
        foreach (array_keys($item) as $key) if (!in_array($key,['motorcycleId','originalPrice','promoPrice','currency'],true)) throw new InvalidArgumentException("Campo desconocido en relación: $key");
        $id=entityId($item['motorcycleId'] ?? null,'motorcycleId');
        if (isset($seen[$id])) throw new InvalidArgumentException('Moto relacionada duplicada');
        $seen[$id]=true;
        if (!isset($item['currency'])) throw new InvalidArgumentException('Moneda requerida en relación');
        $original=promotionMoney($item,'originalPrice'); $promo=promotionMoney($item,'promoPrice');
        if ((float)$promo>(float)$original) throw new InvalidArgumentException('Precio promocional mayor al original');
        $links[]=['motorcycleId'=>$id,'originalPrice'=>$original,'promoPrice'=>$promo,'currency'=>choice($item,'currency',['CRC','USD'],'CRC')];
    }
    return ['title'=>plainText($a,'title',255,true),'slug'=>$slug,'description'=>plainText($a,'description',20000),'imageUrl'=>$image,'brandId'=>$brand,'motorcycles'=>$links,'startsAt'=>$start,'endsAt'=>$end,'status'=>choice($a,'status',['active','inactive','expired'],'inactive'),'featured'=>flag($a,'featured'),'showOnHome'=>flag($a,'showOnHome'),'order'=>numberField($a,'order',1000000,true),'buttonLabel'=>plainText($a,'buttonLabel',100,true),'buttonHref'=>safeDestination($a)];
}
function promotionRow(string $id, bool $lock=false): array {
    $selector=ctype_digit($id) ? 'id=?' : 'slug=?';
    $row=query("SELECT * FROM promotions WHERE $selector AND deleted_at IS NULL".($lock ? ' FOR UPDATE' : ''),[$id])->fetch();
    if (!$row) fail('NOT_FOUND','Promoción no encontrada.',404);
    return $row;
}
function promotionDocument(array $row, bool $public): ?array {
    // Public validity uses the same UTC database clock as catalog queries.
    if ($public && !query("SELECT id FROM promotions WHERE id=? AND status='active' AND deleted_at IS NULL AND starts_at<=UTC_TIMESTAMP() AND ends_at>=UTC_TIMESTAMP()",[$row['id']])->fetchColumn()) return null;
    $brand=brandSummary($row['brand_id'],$public);
    if ($public && $row['brand_id']!==null && $brand===null) return null;
    $image=mediaUrl($row['image_media_id']);
    if ($public && (!filter_var($image,FILTER_VALIDATE_URL) || parse_url($image,PHP_URL_SCHEME)!=='https' || parse_url($image,PHP_URL_USER)!==null || parse_url($image,PHP_URL_PASS)!==null)) return null;
    // Reject unsafe legacy destinations rather than exposing them publicly.
    if ($public) {
        try { safeDestination(['buttonHref'=>$row['button_href']]); }
        catch (InvalidArgumentException) { return null; }
    }
    $relations=query('SELECT * FROM promotion_motorcycles WHERE promotion_id=? ORDER BY id',[$row['id']])->fetchAll();
    $items=[];
    foreach ($relations as $relation) {
        $m=query('SELECT m.id,m.slug,m.model,m.version,m.model_year,m.currency,m.show_price,m.allow_quote,m.publication_status,m.deleted_at,b.id AS brand_id,b.name AS brand_name,b.slug AS brand_slug,b.primary_color,b.status AS brand_status,b.deleted_at AS brand_deleted,c.status AS category_status,c.deleted_at AS category_deleted FROM motorcycles m JOIN brands b ON b.id=m.brand_id JOIN categories c ON c.id=m.category_id WHERE m.id=?',[$relation['motorcycle_id']])->fetch();
        if ($public && (!$m || $m['deleted_at']!==null || $m['publication_status']!=='published' || $m['brand_status']!=='active' || $m['brand_deleted']!==null || $m['category_status']!=='active' || $m['category_deleted']!==null || $relation['currency']!==$m['currency'] || ($row['brand_id']!==null && (string)$row['brand_id']!==(string)$m['brand_id']))) continue;
        $item=['id'=>(string)$relation['id'],'motorcycleId'=>(string)$relation['motorcycle_id'],'currency'=>$relation['currency'],'motorcycle'=>$m ? ['id'=>(string)$m['id'],'slug'=>$m['slug'],'model'=>$m['model'],'version'=>$m['version'],'year'=>(int)$m['model_year'],'showPrice'=>(bool)$m['show_price'],'allowQuote'=>(bool)$m['allow_quote'],'brand'=>['id'=>(string)$m['brand_id'],'name'=>$m['brand_name'],'slug'=>$m['brand_slug'],'primaryColor'=>$m['primary_color']]] : null];
        if (!$public || ($m && $m['show_price'])) $item+=['originalPrice'=>(float)$relation['original_price'],'promoPrice'=>(float)$relation['promo_price']];
        $items[]=$item;
    }
    // A general campaign can have no bikes; an offer whose bikes all became private is hidden.
    if ($public && $relations && !$items) return null;
    $out=['id'=>(string)$row['id'],'title'=>strip_tags($row['title']),'slug'=>$row['slug'],'description'=>strip_tags($row['description'] ?? ''),'imageUrl'=>$image,'brandId'=>$row['brand_id']===null ? null : (string)$row['brand_id'],'brand'=>$brand,'motorcycles'=>$items,'startsAt'=>iso($row['starts_at']),'endsAt'=>iso($row['ends_at']),'status'=>$row['status'],'featured'=>(bool)$row['featured'],'showOnHome'=>(bool)$row['show_on_home'],'order'=>(int)$row['sort_order'],'buttonLabel'=>strip_tags($row['button_label']),'buttonHref'=>$row['button_href']];
    if (!$public) $out+=['createdAt'=>iso($row['created_at']),'updatedAt'=>iso($row['updated_at'])];
    return $out;
}
function savePromotion(array $a, ?array $old, string $actor): array {
    if ($a['brandId']!==null && !query('SELECT id FROM brands WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$a['brandId']])->fetchColumn()) throw new InvalidArgumentException('Marca inexistente o eliminada');
    // Lock related motorcycles in stable ID order to reduce deadlock risk.
    $links=$a['motorcycles']; usort($links,fn($x,$y)=>strlen($x['motorcycleId'])<=>strlen($y['motorcycleId']) ?: strcmp($x['motorcycleId'],$y['motorcycleId']));
    foreach ($links as $link) {
        $m=query('SELECT id,brand_id,currency FROM motorcycles WHERE id=? AND deleted_at IS NULL FOR UPDATE',[$link['motorcycleId']])->fetch();
        if (!$m) throw new InvalidArgumentException('Moto inexistente o eliminada');
        if ($link['currency']!==$m['currency']) throw new InvalidArgumentException('La moneda de la pareja de precios debe coincidir con la moto');
        if ($a['brandId']!==null && (string)$m['brand_id']!==$a['brandId']) throw new InvalidArgumentException('Moto de otra marca');
    }
    $media=$old ? query('SELECT url,deleted_at FROM media_assets WHERE id=?',[$old['image_media_id']])->fetch() : false;
    $mid=$media && !$media['deleted_at'] && $media['url']===$a['imageUrl'] ? (string)$old['image_media_id'] : createMedia($a['imageUrl'],$a['title'],$actor);
    $id=writeRow('promotions',['title'=>$a['title'],'slug'=>$a['slug'],'description'=>$a['description'],'image_media_id'=>$mid,'brand_id'=>$a['brandId'],'starts_at'=>$a['startsAt'],'ends_at'=>$a['endsAt'],'status'=>$a['status'],'featured'=>(int)$a['featured'],'show_on_home'=>(int)$a['showOnHome'],'sort_order'=>$a['order'],'button_label'=>$a['buttonLabel'],'button_href'=>$a['buttonHref'],...($old ? [] : ['created_by'=>$actor])],$old ? (string)$old['id'] : null);
    // Entire set is replaced in the same transaction; removed links cannot survive PUT.
    query('DELETE FROM promotion_motorcycles WHERE promotion_id=?',[$id]);
    foreach ($a['motorcycles'] as $link) query('INSERT INTO promotion_motorcycles (promotion_id,motorcycle_id,original_price,promo_price,currency) VALUES (?,?,?,?,?)',[$id,$link['motorcycleId'],$link['originalPrice'],$link['promoPrice'],$link['currency']]);
    return promotionDocument(promotionRow($id),false);
}
function handlePromotions(string $scope, string $method, ?string $id, ?array $user, string $requestId): never {
    if ($scope==='admin') permit($user,'promotions.manage');
    if ($scope==='public' && $method!=='GET') fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
    if ($method==='GET') {
        limit('promotions-read',300);
        $public=$scope==='public';
        $rows=$id!==null ? [promotionRow($id)] : query("SELECT * FROM promotions WHERE deleted_at IS NULL".($public ? " AND status='active' AND starts_at<=UTC_TIMESTAMP() AND ends_at>=UTC_TIMESTAMP()" : '').' ORDER BY sort_order,id')->fetchAll();
        $data=array_values(array_filter(array_map(fn($row)=>promotionDocument($row,$public),$rows),fn($row)=>$row!==null));
        if ($id!==null && !$data) fail('NOT_FOUND','Promoción no encontrada.',404);
        respond($id!==null ? $data[0] : $data);
    }
    limit('promotions-write',60);
    if (($method==='POST' && $id===null) || ($method==='PUT' && $id!==null)) {
        $a=validatePromotion(body());
        db()->beginTransaction();
        try {
            $old=$id!==null ? promotionRow($id,true) : null;
            $result=savePromotion($a,$old,$user['id']);
            audit('promotion_saved',$requestId,$user['id']); db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        respond($result,$old ? 200 : 201);
    }
    if ($method==='DELETE' && $id!==null) {
        db()->beginTransaction();
        try {
            $row=promotionRow($id,true);
            query('UPDATE promotions SET deleted_at=UTC_TIMESTAMP() WHERE id=?',[$row['id']]);
            audit('promotion_deleted',$requestId,$user['id']); db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        respond(['id'=>(string)$row['id'],'deleted'=>true]);
    }
    fail('METHOD_NOT_ALLOWED','Método no permitido.',405);
}
