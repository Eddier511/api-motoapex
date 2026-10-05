<?php
declare(strict_types=1);

function plainText(array $a, string $key, int $max, bool $required = false): string {
    $value=textField($a,$key,$max,$required);
    if ($value!==strip_tags($value)) throw new InvalidArgumentException("Usa texto plano: $key");
    return $value;
}
function entityId(mixed $value, string $key): string {
    if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,19}$/D',$value) || (strlen($value)===20 && strcmp($value,'18446744073709551615')>0)) throw new InvalidArgumentException("ID inválido: $key");
    return $value;
}
function utcDate(array $a, string $key): string {
    $value=textField($a,$key,32,true);
    if (!preg_match('/^([0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2})(Z|[+-][0-9]{2}:[0-9]{2})$/D',$value,$matches)) throw new InvalidArgumentException("Fecha ISO 8601 con zona requerida: $key");
    // MySQL DATETIME supports years 1000..9999. Reject normalized impossible dates.
    $date=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP',$matches[1].($matches[2]==='Z' ? '+00:00' : $matches[2]));
    $errors=DateTimeImmutable::getLastErrors();
    if (!$date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format('Y-m-d\TH:i:s')!==$matches[1] || (int)$date->format('Y')<1000) throw new InvalidArgumentException("Fecha inválida: $key");
    if ($matches[2]!=='Z' && ((int)substr($matches[2],1,2)>14 || (int)substr($matches[2],4,2)>59 || ((int)substr($matches[2],1,2)===14 && (int)substr($matches[2],4,2)!==0))) throw new InvalidArgumentException("Zona inválida: $key");
    $date=$date->setTimezone(new DateTimeZone('UTC'));
    if ((int)$date->format('Y')<1000 || (int)$date->format('Y')>9999) throw new InvalidArgumentException("Fecha fuera de rango: $key");
    return $date->format('Y-m-d H:i:s');
}
function safeDestination(array $a): string {
    $value=textField($a,'buttonHref',2048,true);
    $decoded=rawurldecode($value);
    if (preg_match('/[\x00-\x20\x7f\\\\]/',$decoded)) throw new InvalidArgumentException('Destino inválido');
    if (str_starts_with($decoded,'/') && !str_starts_with($decoded,'//')) return $value;
    if (!filter_var($value,FILTER_VALIDATE_URL) || parse_url($value,PHP_URL_SCHEME)!=='https' || parse_url($value,PHP_URL_USER)!==null || parse_url($value,PHP_URL_PASS)!==null) throw new InvalidArgumentException('Destino debe ser ruta /... o URL HTTPS sin credenciales');
    return $value;
}
