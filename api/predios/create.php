<?php
// api/predios/create.php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors','0'); // pon '1' temporalmente si quieres ver el fatal en pantalla
header('Content-Type: application/json; charset=utf-8');

/* --- localizar core en la RAÍZ (../../core desde api/predios) --- */
$rootCore = realpath(__DIR__ . '/../../core');
if (!$rootCore || !is_dir($rootCore)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se ubica carpeta core en ../../core'], JSON_UNESCAPED_UNICODE);
  exit;
}
if (!file_exists($rootCore.'/db.php')) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'Falta core/db.php ('.$rootCore.'/db.php)'], JSON_UNESCAPED_UNICODE);
  exit;
}

/* --- incluye primero helpers (si existe), luego DB --- */
if (file_exists($rootCore.'/helpers.php')) {
  require_once $rootCore.'/helpers.php';
}
require_once $rootCore.'/db.php';

/* --- fallbacks SÓLO si no existen en helpers --- */
if (!function_exists('json_out')) {
  function json_out(array $p, int $code=200): void {
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('read_json_body')) {
  function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $d = json_decode($raw, true);
    return is_array($d) ? $d : [];
  }
}

/* --- modo ping para debug rápido: /api/predios/create.php?ping=1 --- */
if (isset($_GET['ping'])) {
  json_out([
    'ok'=>true,
    'ping'=>'pong',
    'core'=>$rootCore,
    'has_db'=>file_exists($rootCore.'/db.php'),
    'has_helpers'=>file_exists($rootCore.'/helpers.php')
  ]);
}

/* --- MAIN --- */
try {
  $in = read_json_body();

  $nombre    = trim((string)($in['nombre'] ?? ''));
  $direccion = trim((string)($in['direccion'] ?? ''));
  $latRaw    = $in['lat'] ?? null;
  $lngRaw    = $in['lng'] ?? null;

  // Permitir "lat,lng" pegado en lat si lng viene vacío
  if (is_string($latRaw) && strpos($latRaw, ',') !== false && ($lngRaw === null || $lngRaw === '')) {
    [$latRaw, $lngRaw] = array_map('trim', explode(',', $latRaw, 2));
  }

  // Validación básica
  $errors=[];
  if ($nombre==='')               $errors[]='El nombre es obligatorio.';
  if (mb_strlen($nombre)>120)     $errors[]='El nombre no debe superar 120 caracteres.';
  if (mb_strlen($direccion)>240)  $errors[]='La dirección no debe superar 240 caracteres.';

  $lat=null; $lng=null;
  if ($latRaw!==null && $latRaw!=='') {
    if (!is_numeric($latRaw))      $errors[]='Latitud inválida.';
    else {
      $lat=(float)$latRaw;
      if ($lat<-90 || $lat>90)     $errors[]='Latitud fuera de rango (-90 a 90).';
    }
  }
  if ($lngRaw!==null && $lngRaw!=='') {
    if (!is_numeric($lngRaw))      $errors[]='Longitud inválida.';
    else {
      $lng=(float)$lngRaw;
      if ($lng<-180 || $lng>180)   $errors[]='Longitud fuera de rango (-180 a 180).';
    }
  }

  if ($errors) json_out(['ok'=>false,'error'=>implode(' ',$errors)],422);

  // DB
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);




  // Lee user_id (opcional). En un futuro lo traerás de la sesión.
// Fallback: 1 (usuario demo)
$userId = isset($in['user_id']) && is_numeric($in['user_id']) ? (int)$in['user_id'] : 1;

// Si quieres además crear un world_grid vacío por defecto:
$worldGrid = null;
if (!empty($in['with_world_grid_default'])) {
  // 20x20 "soil"
  $grid = array_fill(0, 20, array_fill(0, 20, 'soil'));
  $worldGrid = json_encode($grid, JSON_UNESCAPED_UNICODE);
}

// Ajusta columnas a tu schema real:
if ($worldGrid !== null) {
  $sql = "INSERT INTO predios (user_id, nombre, direccion, lat, lng, world_grid, created_at, updated_at)
          VALUES (:user_id, :nombre, :direccion, :lat, :lng, :world_grid, NOW(), NOW())";
} else {
  $sql = "INSERT INTO predios (user_id, nombre, direccion, lat, lng, created_at, updated_at)
          VALUES (:user_id, :nombre, :direccion, :lat, :lng, NOW(), NOW())";
}

$st = $pdo->prepare($sql);
$st->bindValue(':user_id',  $userId,   PDO::PARAM_INT);
$st->bindValue(':nombre',   $nombre,   PDO::PARAM_STR);
$st->bindValue(':direccion',($direccion!==''?$direccion:null), $direccion!==''?PDO::PARAM_STR:PDO::PARAM_NULL);
$st->bindValue(':lat',      $lat!==null?$lat:null, $lat!==null?PDO::PARAM_STR:PDO::PARAM_NULL);
$st->bindValue(':lng',      $lng!==null?$lng:null, $lng!==null?PDO::PARAM_STR:PDO::PARAM_NULL);
if ($worldGrid !== null) {
  $st->bindValue(':world_grid', $worldGrid, PDO::PARAM_STR);
}
$st->execute();






  $id = (int)$pdo->lastInsertId();

  json_out(['ok'=>true,'data'=>[
    'id'=>$id,'nombre'=>$nombre,
    'direccion'=>($direccion!==''?$direccion:null),
    'lat'=>$lat,'lng'=>$lng
  ]]);

} catch (Throwable $e) {
  // deja display_errors=1 si quieres ver el fatal, pero aquí devolvemos JSON
  json_out(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()],500);
}
