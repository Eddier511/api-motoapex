<?php
declare(strict_types=1);

const CONTENT_TABLES=['pages'=>'web_pages','banners'=>'web_banners','social-links'=>'social_links','contact'=>'site_contact'];
function contentKeys(array $a, array $required, array $optional=[]): void {
    foreach ($required as $key) if (!array_key_exists($key,$a)) throw new InvalidArgumentException("Campo requerido: $key");
    foreach (array_keys($a) as $key) if (!in_array($key,[...$required,...$optional],true)) throw new InvalidArgumentException("Campo desconocido: $key");
}
function safeContentUrl(array $a,string $key,bool $required=false): string {
    $value=urlField($a,$key);
    if (($required && $value==='') || ($value!=='' && (parse_url($value,PHP_URL_USER)!==null || parse_url($value,PHP_URL_PASS)!==null || preg_match('/[\x00-\x20\x7f\\\\]/',rawurldecode($value))))) throw new InvalidArgumentException("Enlace HTTPS invÃ¡lido: $key");
    return $value;
}
function contentButton(mixed $a): ?array {
    if ($a===null) return null;
    if (!is_array($a) || array_is_list($a)) throw new InvalidArgumentException('BotÃ³n invÃ¡lido');
    contentKeys($a,['label','href']);
    return ['label'=>plainPromotionText($a,'label',100,true),'href'=>promotionHref(['buttonHref'=>$a['href']])];
}
function validateContent(string $kind,array $a): array {
    if ($kind==='pages') {
        contentKeys($a,['title','slug','content','contentFormat','order','status','seo']);
        $slug=textField($a,'slug',191,true);
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug) || ctype_digit($slug)) throw new InvalidArgumentException('Slug invÃ¡lido');
        $format=choice($a,'contentFormat',['text','blocks'],'text');
        if ($format==='text') $body=plainPromotionText($a,'content',100000);
        else {
            $blocks=listField($a,'content',100); $clean=[];
            foreach ($blocks as $block) {
                $type=choice($block,'type',['heading','paragraph','image','link'],'paragraph');
                $keys=match($type){'heading'=>['type','text','level'],'paragraph'=>['type','text'],'image'=>['type','url','alt'],'link'=>['type','text','href']};
                contentKeys($block,$keys);
                $item=['type'=>$type];
                if (isset($block['text'])) $item['text']=plainPromotionText($block,'text',20000,true);
                if ($type==='heading') { $item['level']=numberField($block,'level',6,true); if ($item['level']<1) throw new InvalidArgumentException('Nivel invÃ¡lido'); }
                if ($type==='image') { $item['url']=safeContentUrl($block,'url',true); $item['alt']=plainPromotionText($block,'alt',500,true); }
                if ($type==='link') $item['href']=promotionHref(['buttonHref'=>$block['href']]);
                $clean[]=$item;
            }
            $body=json_encode($clean,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
            if (strlen($body)>100000) throw new InvalidArgumentException('Contenido demasiado grande');
        }
        if (!is_array($a['seo'])) throw new InvalidArgumentException('SEO invÃ¡lido');
        contentKeys($a['seo'],['title','description']);
        return ['title'=>plainPromotionText($a,'title',255,true),'slug'=>$slug,'body'=>$body,'content_format'=>$format,'sort_order'=>numberField($a,'order',1000000,true),'status'=>choice($a,'status',['draft','published','hidden','archived'],'draft'),'meta_title'=>plainPromotionText($a['seo'],'title',255),'meta_description'=>plainPromotionText($a['seo'],'description',500)];
    }
    if ($kind==='banners') {
        contentKeys($a,['title','subtitle','imageUrl','mobileImageUrl','alt','brandId','accentColor','ctaPrimary','ctaSecondary','placement','order','status','startsAt','endsAt'],['pageId']);
        $start=$a['startsAt']===null ? null : promotionDate($a,'startsAt'); $end=$a['endsAt']===null ? null : promotionDate($a,'endsAt');
        if ($start!==null && $end!==null && $end<$start) throw new InvalidArgumentException('Fin anterior al inicio');
        $placement=textField($a,'placement',100,true);
        if (!preg_match('/^[a-z][a-z0-9_-]*$/D',$placement)) throw new InvalidArgumentException('UbicaciÃ³n invÃ¡lida');
        $primary=contentButton($a['ctaPrimary']); $secondary=contentButton($a['ctaSecondary']);
        return ['title'=>plainPromotionText($a,'title',255,true),'subtitle'=>plainPromotionText($a,'subtitle',3000),'imageUrl'=>safeContentUrl($a,'imageUrl',true),'mobileImageUrl'=>safeContentUrl($a,'mobileImageUrl'),'alt_text'=>plainPromotionText($a,'alt',500,true),'brand_id'=>$a['brandId']===null ? null : promotionId($a['brandId'],'brandId'),'page_id'=>($a['pageId'] ?? null)===null ? null : promotionId($a['pageId'],'pageId'),'accent_color'=>colorField($a,'accentColor'),'button_label'=>$primary['label'] ?? null,'link_url'=>$primary['href'] ?? null,'secondary_button_label'=>$secondary['label'] ?? null,'secondary_link_url'=>$secondary['href'] ?? null,'placement'=>$placement,'sort_order'=>numberField($a,'order',1000000,true),'status'=>choice($a,'status',['active','inactive'],'inactive'),'starts_at'=>$start,'ends_at'=>$end];
    }
    if ($kind==='social-links') {
        contentKeys($a,['platform','label','url','order','status']);
        return ['platform'=>choice($a,'platform',['facebook','instagram','tiktok','youtube','x','linkedin','whatsapp','other'],'other'),'label'=>plainPromotionText($a,'label',150,true),'url'=>safeContentUrl($a,'url',true),'sort_order'=>numberField($a,'order',1000000,true),'status'=>choice($a,'status',['active','inactive'],'inactive')];
    }
    contentKeys($a,['businessName','phone','whatsapp','email','address','latitude','longitude','hours','logoUrl','faviconUrl']);
    $email=textField($a,'email',191); if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Correo invÃ¡lido');
    foreach (['latitude'=>90,'longitude'=>180] as $key=>$max) if ($a[$key]!==null && ((!is_int($a[$key]) && !is_float($a[$key])) || abs($a[$key])>$max)) throw new InvalidArgumentException("Coordenada invÃ¡lida: $key");
    if (($a['latitude']===null)!==($a['longitude']===null)) throw new InvalidArgumentException('Coordenadas deben venir juntas');
    foreach (['phone','whatsapp'] as $key) if (!preg_match('/^[+0-9 ()-]{0,40}$/D',textField($a,$key,40))) throw new InvalidArgumentException('TelÃ©fono invÃ¡lido');
    $hours=[];
    foreach (listField($a,'hours',7) as $item) {
        contentKeys($item,['day','closed','opens','closes']); $day=numberField($item,'day',7,true);
        if ($day<1 || isset($hours[$day])) throw new InvalidArgumentException('DÃ­a invÃ¡lido o duplicado');
        $closed=flag($item,'closed');
        foreach (['opens','closes'] as $key) if ($closed ? $item[$key]!==null : (!is_string($item[$key]) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$item[$key]))) throw new InvalidArgumentException('Horario invÃ¡lido');
        if (!$closed && $item['closes']<=$item['opens']) throw new InvalidArgumentException('Horario debe cerrar despuÃ©s de abrir');
        $hours[$day]=['day'=>$day,'closed'=>$closed,'opens'=>$item['opens'],'closes'=>$item['closes']];
    }
    ksort($hours);
    return ['site_name'=>plainPromotionText($a,'businessName',150,true),'phone'=>textField($a,'phone',40),'whatsapp'=>textField($a,'whatsapp',40),'email'=>$email,'address'=>plainPromotionText($a,'address',3000),'latitude'=>$a['latitude'],'longitude'=>$a['longitude'],'opening_hours'=>json_encode(array_values($hours),JSON_THROW_ON_ERROR),'logoUrl'=>safeContentUrl($a,'logoUrl'),'faviconUrl'=>safeContentUrl($a,'faviconUrl')];
}
function contentRow(string $kind,?string $id,bool $lock=false): array {
    $table=CONTENT_TABLES[$kind];
    $selector=$kind==='contact' ? "site_key='main'" : ($kind==='pages' && !ctype_digit($id ?? '') ? 'slug=?' : 'id=?');
    $row=query("SELECT * FROM $table WHERE $selector".($kind==='contact' ? '' : ' AND deleted_at IS NULL').($lock ? ' FOR UPDATE' : ''),$kind==='contact' ? [] : [$id])->fetch();
    if (!$row) fail('NOT_FOUND','Contenido no encontrado.',404);
    return $row;
}
function contentDocument(string $kind,array $row,bool $public): ?array {
    $out=['id'=>(string)$row['id']];
    if ($kind==='pages') {
        if ($public && $row['status']!=='published') return null;
        $content=$row['content_format']==='blocks' ? json_decode($row['body'] ?? '',true) : strip_tags($row['body'] ?? '');
        if ($row['content_format']==='blocks') {
            try { validateContent('pages',['title'=>$row['title'],'slug'=>$row['slug'],'content'=>$content,'contentFormat'=>'blocks','order'=>(int)$row['sort_order'],'status'=>$row['status'],'seo'=>['title'=>$row['meta_title'] ?? '','description'=>$row['meta_description'] ?? '']]); }
            catch (InvalidArgumentException) { if ($public) return null; }
        }
        $out+=['title'=>strip_tags($row['title']),'slug'=>$row['slug'],'content'=>$content,'contentFormat'=>$row['content_format'],'order'=>(int)$row['sort_order'],'status'=>$row['status'],'seo'=>['title'=>strip_tags($row['meta_title'] ?? ''),'description'=>strip_tags($row['meta_description'] ?? '')]];
    } elseif ($kind==='banners') {
        if ($public && !query("SELECT id FROM web_banners WHERE id=? AND status='active' AND deleted_at IS NULL AND (starts_at IS NULL OR starts_at<=UTC_TIMESTAMP()) AND (ends_at IS NULL OR ends_at>=UTC_TIMESTAMP())",[$row['id']])->fetchColumn()) return null;
        $brand=promotionBrand($row['brand_id'],$public);
        if ($public && $row['brand_id']!==null && !$brand) return null;
        if ($public && $row['page_id']!==null && !query("SELECT id FROM web_pages WHERE id=? AND status='published' AND deleted_at IS NULL",[$row['page_id']])->fetchColumn()) return null;
        $out+=['title'=>strip_tags($row['title']),'subtitle'=>strip_tags($row['subtitle'] ?? ''),'imageUrl'=>mediaUrl($row['desktop_media_id']),'mobileImageUrl'=>mediaUrl($row['mobile_media_id']),'alt'=>strip_tags($row['alt_text']),'brandId'=>$row['brand_id']===null ? null : (string)$row['brand_id'],'brandSlug'=>$brand['slug'] ?? null,'brand'=>$brand,'pageId'=>$row['page_id']===null ? null : (string)$row['page_id'],'accentColor'=>$row['accent_color'],'ctaPrimary'=>$row['button_label']===null ? null : ['label'=>strip_tags($row['button_label']),'href'=>$row['link_url']],'ctaSecondary'=>$row['secondary_button_label']===null ? null : ['label'=>strip_tags($row['secondary_button_label']),'href'=>$row['secondary_link_url']],'placement'=>$row['placement'],'order'=>(int)$row['sort_order'],'status'=>$row['status'],'startsAt'=>iso($row['starts_at']),'endsAt'=>iso($row['ends_at'])];
        if ($public) { try { validateContent('banners',array_diff_key($out,array_flip(['id','brandSlug','brand']))); } catch (InvalidArgumentException) { return null; } }
    } elseif ($kind==='social-links') {
        if ($public && $row['status']!=='active') return null;
        if ($public) { try { safeContentUrl(['url'=>$row['url']],'url',true); } catch (InvalidArgumentException) { return null; } }
        $out+=['platform'=>$row['platform'],'label'=>strip_tags($row['label']),'url'=>$row['url'],'order'=>(int)$row['sort_order'],'status'=>$row['status']];
    } else {
        $hours=json_decode($row['opening_hours'] ?? '[]',true);
        $out+=['businessName'=>strip_tags($row['site_name']),'phone'=>$row['phone'] ?? '','whatsapp'=>$row['whatsapp'] ?? '','email'=>$row['email'] ?? '','address'=>strip_tags($row['address'] ?? ''),'latitude'=>$row['latitude']===null ? null : (float)$row['latitude'],'longitude'=>$row['longitude']===null ? null : (float)$row['longitude'],'hours'=>is_array($hours) ? $hours : [],'logoUrl'=>mediaUrl($row['logo_media_id']),'faviconUrl'=>mediaUrl($row['favicon_media_id'])];
        foreach (['logoUrl','faviconUrl'] as $key) { try { safeContentUrl($out,$key); } catch (InvalidArgumentException) { $out[$key]=''; } }
    }
    if (!$public) $out+=['createdAt'=>iso($row['created_at']),'updatedAt'=>iso($row['updated_at'])];
    return $out;
}
function contentRevision(string $kind,string $id,array $snapshot,string $actor,string $requestId,string $action): void {
    // Caller holds the resource row lock, serializing revision numbers.
    $revision=(int)query('SELECT COALESCE(MAX(revision_number),0)+1 FROM content_revisions WHERE entity_type=? AND entity_id=?',[$kind,$id])->fetchColumn();
    query('INSERT INTO content_revisions (entity_type,entity_id,revision_number,payload,author_id) VALUES (?,?,?,?,?)',[$kind,$id,$revision,json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$actor]);
    query('INSERT INTO audit_logs (actor_id,action,entity_type,entity_id,request_id) VALUES (?,?,?,?,?)',[$actor,$action,'content_'.$kind,$id,$requestId]);
}
function handleContent(string $scope,string $kind,string $method,?string $id,?array $user,string $requestId): never {
    $public=$scope==='public'; $table=CONTENT_TABLES[$kind];
    if (!$public) permit($user,'content.manage');
    if ($public && $method!=='GET') fail('METHOD_NOT_ALLOWED','MÃ©todo no permitido.',405);
    if ($method==='GET') {
        limit('content-read',300);
        if ($kind==='contact' || $id!==null) { $doc=contentDocument($kind,contentRow($kind,$id),$public); if (!$doc) fail('NOT_FOUND','Contenido no encontrado.',404); respond($doc); }
        $rows=query("SELECT * FROM $table WHERE deleted_at IS NULL ORDER BY sort_order,id")->fetchAll();
        if ($kind==='banners' && isset($_GET['placement'])) { $placement=textField($_GET,'placement',100,true); $rows=array_values(array_filter($rows,fn($r)=>$r['placement']===$placement)); }
        respond(array_values(array_filter(array_map(fn($r)=>contentDocument($kind,$r,$public),$rows))));
    }
    limit('content-write',80);
    if (($method==='POST' && $id===null && $kind!=='contact') || ($method==='PUT' && ($id!==null || $kind==='contact'))) {
        $a=validateContent($kind,body()); db()->beginTransaction();
        try {
            $old=($method==='PUT') ? contentRow($kind,$id,true) : null;
            if ($old) contentRevision($kind,(string)$old['id'],contentDocument($kind,$old,false),$user['id'],$requestId,'content_before_update');
            if ($kind==='banners') {
                foreach (['brand_id'=>'brands','page_id'=>'web_pages'] as $key=>$related) if ($a[$key]!==null && !query("SELECT id FROM $related WHERE id=? AND deleted_at IS NULL FOR UPDATE",[$a[$key]])->fetchColumn()) throw new InvalidArgumentException('RelaciÃ³n inexistente');
                foreach (['imageUrl'=>'desktop_media_id','mobileImageUrl'=>'mobile_media_id'] as $key=>$column) { $a[$column]=$a[$key]==='' ? null : createMedia($a[$key],$a['alt_text'],$user['id']); unset($a[$key]); }
            }
            if ($kind==='contact') foreach (['logoUrl'=>'logo_media_id','faviconUrl'=>'favicon_media_id'] as $key=>$column) { $a[$column]=$a[$key]==='' ? null : createMedia($a[$key],$a['site_name'],$user['id']); unset($a[$key]); }
            if ($kind==='pages') { $a['updated_by']=$user['id']; $a['published_at']=$a['status']==='published' ? ($old['published_at'] ?? gmdate('Y-m-d H:i:s')) : null; }
            $saved=writeRow($table,$a,$old ? (string)$old['id'] : null); $doc=contentDocument($kind,contentRow($kind,$saved),false);
            contentRevision($kind,$saved,$doc,$user['id'],$requestId,'content_saved'); db()->commit();
        } catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        respond($doc,$old ? 200 : 201);
    }
    if ($method==='DELETE' && $id!==null && $kind!=='contact') {
        db()->beginTransaction();
        try { $old=contentRow($kind,$id,true); contentRevision($kind,(string)$old['id'],contentDocument($kind,$old,false),$user['id'],$requestId,'content_deleted'); query("UPDATE $table SET deleted_at=UTC_TIMESTAMP() WHERE id=?",[$old['id']]); db()->commit(); }
        catch (Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
        respond(['id'=>(string)$old['id'],'deleted'=>true]);
    }
    fail('METHOD_NOT_ALLOWED','MÃ©todo no permitido.',405);
}
