<?php
// api/predios/update.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$rootCore = realpath(__DIR__ . '/../../core');
require_once $rootCore.'/db.php';

function out($p,$c=200){ http_response_code($c); echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw,true) ?: [];
$id  = (int)($in['id'] ?? 0);
if ($id<=0) out(['ok'=>false,'error'=>'id requerido'],422);

$nombre    = trim((string)($in['nombre'] ?? ''));
$direccion = trim((string)($in['direccion'] ?? ''));
$lat       = isset($in['lat']) ? trim((string)$in['lat']) : null;
$lng       = isset($in['lng']) ? trim((string)$in['lng']) : null;

// Permitir pegar "lat,lng" en lat si lng viene vacío
if ($lat && !$lng && strpos($lat,',')!==false) {
  [$lat,$lng] = array_map('trim', explode(',',$lat,2));
}
$latF = ($lat!==null && $lat!=='') ? (float)$lat : null;
$lngF = ($lng!==null && $lng!=='') ? (float)$lng : null;

try {
  $pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $sql = "UPDATE predios SET nombre=:n, direccion=:d, lat=:lat, lng=:lng, updated_at=NOW() WHERE id=:id";
  $st  = $pdo->prepare($sql);
  $st->bindValue(':n',  $nombre!==''?$nombre:null, $nombre!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
  $st->bindValue(':d',  $direccion!==''?$direccion:null, $direccion!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
  $st->bindValue(':lat', $latF!==null?$latF:null, $latF!==null?PDO::PARAM_STR:PDO::PARAM_NULL);
  $st->bindValue(':lng', $lngF!==null?$lngF:null, $lngF!==null?PDO::PARAM_STR:PDO::PARAM_NULL);
  $st->bindValue(':id', $id, PDO::PARAM_INT);
  $st->execute();
  out(['ok'=>true,'data'=>['id'=>$id]]);
} catch(Throwable $e){ out(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()],500); }
