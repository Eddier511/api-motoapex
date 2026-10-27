<?php
declare(strict_types=1);

const LEGACY_LIST_MAX=250;
const RELATED_ROWS_MAX=5000;
const RESPONSE_BYTES_MAX=8388608;

function boundedRows(string $sql,array $params=[],int $maximum=RELATED_ROWS_MAX): array {
    $rows=query($sql.' LIMIT '.($maximum+1),$params)->fetchAll();
    if (count($rows)>$maximum) fail('PAGE_TOO_LARGE','Reduce limit para consultar este listado.',422);
    return $rows;
}
function listRows(string $from,string $where,array $params=[],string $alias='',bool $descendingId=false): array {
    // All SQL identifiers/expressions are server constants, never query parameters.
    $prefix=$alias==='' ? '' : $alias.'.';
    $paged=isset($_GET['limit']) || isset($_GET['cursor']);
    $maximum=$paged ? 50 : LEGACY_LIST_MAX;
    if (isset($_GET['limit'])) {
        if (!is_string($_GET['limit']) || !preg_match('/^[1-9][0-9]{0,2}$/D',$_GET['limit']) || (int)$_GET['limit']>100) throw new InvalidArgumentException('limit debe estar entre 1 y 100');
        $maximum=(int)$_GET['limit'];
    }
    if (isset($_GET['cursor'])) {
        $cursor=textField($_GET,'cursor',40,true);
        if ($descendingId) { $id=entityId($cursor,'cursor'); $where.=" AND {$prefix}id<?"; $params[]=$id; }
        else {
            if (!preg_match('/^(0|[1-9][0-9]{0,9}):([1-9][0-9]{0,19})$/D',$cursor,$match) || (float)$match[1]>4294967295) throw new InvalidArgumentException('Cursor inválido');
            $id=entityId($match[2],'cursor'); $where.=" AND ({$prefix}sort_order>? OR ({$prefix}sort_order=? AND {$prefix}id>?))";
            array_push($params,$match[1],$match[1],$id);
        }
    }
    $order=$descendingId ? "{$prefix}id DESC" : "{$prefix}sort_order,{$prefix}id";
    $rows=query("SELECT {$prefix}* FROM $from WHERE $where ORDER BY $order LIMIT ".($maximum+1),$params)->fetchAll();
    if (count($rows)>$maximum) {
        if (!$paged) fail('PAGINATION_REQUIRED','Listado grande: usa limit y sigue X-Next-Cursor.',422);
        array_pop($rows); $last=$rows[array_key_last($rows)];
        header('X-Next-Cursor: '.($descendingId ? (string)$last['id'] : $last['sort_order'].':'.$last['id']));
    }
    if ($paged) header('X-Page-Limit: '.$maximum);
    return $rows;
}
function boundedDocuments(array $rows,callable $document): array {
    $out=[]; $bytes=11;
    foreach ($rows as $row) {
        $value=$document($row); if ($value===null) continue;
        $bytes+=strlen(json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR))+1;
        if ($bytes>RESPONSE_BYTES_MAX) fail('PAGE_TOO_LARGE','Reduce limit para consultar este listado.',422);
        $out[]=$value;
    }
    return $out;
}
function inParameters(array $ids): string { return implode(',',array_fill(0,count($ids),'?')); }
function keyedRows(array $rows): array { $out=[]; foreach ($rows as $row) $out[(string)$row['id']]=$row; return $out; }
function groupedRows(array $rows,string $key): array { $out=[]; foreach ($rows as $row) $out[(string)$row[$key]][]=$row; return $out; }
function mediaMap(array $ids): array {
    $ids=array_values(array_unique(array_filter($ids,fn($id)=>$id!==null)));
    if (!$ids) return [];
    $rows=query('SELECT id,url FROM media_assets WHERE deleted_at IS NULL AND id IN ('.inParameters($ids).')',$ids)->fetchAll();
    return array_column($rows,'url','id');
}
