<?php
// api/predios/duplicate.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

// Localiza core/
$rootCore = realpath(__DIR__ . '/../../core');
if (!$rootCore || !is_dir($rootCore)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se ubica core en ../../core']);
  exit;
}
require_once $rootCore . '/db.php';
if (file_exists($rootCore . '/helpers.php')) require_once $rootCore . '/helpers.php';

// Fallback de json_out si no existe en helpers
if (!function_exists('json_out')) {
  function json_out(array $p, int $code=200): void {
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
}

// Lee input
$raw = file_get_contents('php://input') ?: '';
$in  = json_decode($raw, true) ?: [];
$srcId   = isset($in['id']) ? (int)$in['id'] : 0;
$newName = isset($in['nombre']) ? trim((string)$in['nombre']) : '';
$overrideUser = isset($in['user_id']) ? (int)$in['user_id'] : null;

if ($srcId <= 0) {
  json_out(['ok'=>false,'error'=>'id origen requerido'], 422);
}

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // 1) Carga predio origen
  $sel = $pdo->prepare("SELECT * FROM predios WHERE id = ?");
  $sel->execute([$srcId]);
  $src = $sel->fetch(PDO::FETCH_ASSOC);
  if (!$src) {
    json_out(['ok'=>false,'error'=>'Predio origen no encontrado'], 404);
  }

  // 2) Prepara campos clonados
  $userId    = ($overrideUser !== null) ? $overrideUser : (int)$src['user_id'];
  $nombre    = $newName !== '' ? $newName : (($src['nombre'] ?: 'Predio') . ' (copia)');
  $direccion = $src['direccion'] ?? null;
  $lat       = $src['lat'] ?? null;
  $lng       = $src['lng'] ?? null;

  // Campos “mundo” y clima (si los manejas en esta tabla)
  $worldGrid = $src['world_grid'] ?? null;

  // Si tu tabla guarda promedios climáticos, clónalos también (estos campos son opcionales)
  $avg_tmin       = $src['avg_tmin'] ?? null;
  $avg_tmax       = $src['avg_tmax'] ?? null;
  $total_prcp_mm  = $src['total_prcp_mm'] ?? null;
  $frost_days_est = $src['frost_days_est'] ?? null;

  // 3) Inserta copia
  $sql = "INSERT INTO predios
            (user_id, nombre, direccion, lat, lng, world_grid, avg_tmin, avg_tmax, total_prcp_mm, frost_days_est, created_at, updated_at)
          VALUES
            (:user_id, :nombre, :direccion, :lat, :lng, :world_grid, :avg_tmin, :avg_tmax, :total_prcp_mm, :frost_days_est, NOW(), NOW())";
  $st = $pdo->prepare($sql);
  $st->bindValue(':user_id', $userId, PDO::PARAM_INT);
  $st->bindValue(':nombre', $nombre, PDO::PARAM_STR);
  $st->bindValue(':direccion', $direccion, $direccion===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  $st->bindValue(':lat', $lat, $lat===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  $st->bindValue(':lng', $lng, $lng===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  $st->bindValue(':world_grid', $worldGrid, $worldGrid===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  // Climáticos
  $st->bindValue(':avg_tmin', $avg_tmin, $avg_tmin===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  $st->bindValue(':avg_tmax', $avg_tmax, $avg_tmax===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  $st->bindValue(':total_prcp_mm', $total_prcp_mm, $total_prcp_mm===null?PDO::PARAM_NULL:PDO::PARAM_STR);
  $st->bindValue(':frost_days_est', $frost_days_est, $frost_days_est===null?PDO::PARAM_NULL:PDO::PARAM_INT);

  $st->execute();
  $newId = (int)$pdo->lastInsertId();

  json_out([
    'ok'   => true,
    'data' => [
      'id'        => $newId,
      'user_id'   => $userId,
      'nombre'    => $nombre,
      'direccion' => $direccion,
      'lat'       => $lat,
      'lng'       => $lng,
      'copied_from' => $srcId
    ]
  ]);
} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()], 500);
}
