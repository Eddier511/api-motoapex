<?php
declare(strict_types=1);

function textField(array $a, string $key, int $max = 500, bool $required = false): string {
    $v = $a[$key] ?? '';
    if (!is_string($v) || strlen($v) > $max || ($required && trim($v) === '')) throw new InvalidArgumentException("Campo inválido: $key");
    return trim($v);
}
function choice(array $a, string $key, array $choices, string $default): string {
    $v = $a[$key] ?? $default;
    if (!in_array($v, $choices, true)) throw new InvalidArgumentException("Campo inválido: $key");
    return $v;
}
function flag(array $a, string $key, bool $default = false): bool {
    $v = $a[$key] ?? $default;
    if (!is_bool($v)) throw new InvalidArgumentException("Campo inválido: $key");
    return $v;
}
function numberField(array $a, string $key, float $max = 1000000000, bool $integer = false): int|float {
    $v = $a[$key] ?? 0;
    if ((!is_int($v) && !is_float($v)) || $v < 0 || $v > $max || ($integer && !is_int($v))) throw new InvalidArgumentException("Campo inválido: $key");
    return $v;
}
function urlField(array $a, string $key): string {
    $v = textField($a, $key, 2048);
    if ($v !== '' && (!filter_var($v, FILTER_VALIDATE_URL) || parse_url($v, PHP_URL_SCHEME) !== 'https')) throw new InvalidArgumentException("URL HTTPS inválida: $key");
    return $v;
}
function colorField(array $a, string $key, string $default = '#000000'): string {
    $v = $a[$key] ?? $default;
    if (!is_string($v) || !preg_match('/^#[a-fA-F0-9]{6}$/D', $v)) throw new InvalidArgumentException("Color inválido: $key");
    return $v;
}
function listField(array $a, string $key, int $max): array {
    $v = $a[$key] ?? [];
    if (!is_array($v) || !array_is_list($v) || count($v) > $max) throw new InvalidArgumentException("Lista inválida: $key");
    foreach ($v as $item) if (!is_array($item)) throw new InvalidArgumentException("Elemento inválido: $key");
    return $v;
}
function validateResource(string $kind, array $a): array {
    $slug = textField($a, 'slug', 190, true);
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) throw new InvalidArgumentException('Slug inválido');
    $out = ['slug'=>$slug];
    if ($kind !== 'motorcycles') {
        $out += ['name'=>textField($a,'name',120,true),'description'=>textField($a,'description',10000),'status'=>choice($a,'status',['active','inactive'],'inactive'),'order'=>numberField($a,'order',10000,true)];
    }
    if ($kind === 'brands') {
        foreach (['primaryColor','secondaryColor','accentLight'] as $key) $out[$key]=colorField($a,$key);
        foreach (['logo','heroImageUrl','tileImageUrl'] as $key) $out[$key]=urlField($a,$key);
        foreach (['tagline','slogan'] as $key) $out[$key]=textField($a,$key);
    } elseif ($kind === 'categories') {
        $out['brandId']=textField($a,'brandId',32);
    } elseif ($kind === 'motorcycles') {
        foreach (['brandId','categoryId'] as $key) $out[$key]=textField($a,$key,32,true);
        $out['model']=textField($a,'model',120,true);
        foreach (['version','sku','tagline'] as $key) $out[$key]=textField($a,$key,190);
        $out['shortDescription']=textField($a,'shortDescription',1000);
        $out['description']=textField($a,'description',20000);
        $out['year']=numberField($a,'year',2100,true);
        if ($out['year'] < 1900) throw new InvalidArgumentException('Año inválido');
        foreach (['price','displacement','inventory','hp'] as $key) $out[$key]=numberField($a,$key,in_array($key,['displacement','hp'],true) ? 999999 : 1000000000,$key==='inventory');
        $out['promoPrice']=isset($a['promoPrice']) ? numberField($a,'promoPrice') : null;
        if ($out['promoPrice'] !== null && $out['promoPrice'] > $out['price']) throw new InvalidArgumentException('Precio promocional mayor al precio');
        $out['currency']=choice($a,'currency',['CRC','USD'],'CRC');
        $out['status']=choice($a,'status',['available','reserved','sold_out','coming_soon'],'coming_soon');
        foreach (['published','featured','isNew','showPrice','allowQuote'] as $key) $out[$key]=flag($a,$key);
        $out['specs']=array_map(fn($s)=>['group'=>textField($s,'group',120,true),'label'=>textField($s,'label',120,true),'value'=>textField($s,'value',500,true)],listField($a,'specs',100));
        $out['colors']=array_map(function($c) {
            $images=array_map(fn($i)=>['id'=>textField($i,'id',64,true),'url'=>urlField($i,'url'),'alt'=>textField($i,'alt',500),'label'=>textField($i,'label',120),'order'=>numberField($i,'order',1000,true),'isPrimary'=>flag($i,'isPrimary')],listField($c,'images',30));
            if (count(array_filter($images,fn($i)=>$i['isPrimary'])) > 1) throw new InvalidArgumentException('Una sola imagen principal por color');
            usort($images,fn($a,$b)=>$a['order'] <=> $b['order']);
            return ['id'=>textField($c,'id',64,true),'name'=>textField($c,'name',120,true),'hex'=>colorField($c,'hex'),'status'=>choice($c,'status',['active','inactive'],'active'),'available'=>flag($c,'available'),'order'=>numberField($c,'order',1000,true),'images'=>$images];
        },listField($a,'colors',30));
        usort($out['colors'],fn($a,$b)=>$a['order'] <=> $b['order']);
    }
    return $out;
}
