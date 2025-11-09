<?php
// api/predios/list.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

$rootCore = realpath(__DIR__ . '/../../core');
if (!$rootCore || !is_dir($rootCore)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se ubica core en ../../core']);
  exit;
}
require_once $rootCore.'/db.php';
if (file_exists($rootCore.'/helpers.php')) require_once $rootCore.'/helpers.php';

if (!function_exists('json_out')) {
  function json_out(array $p, int $code=200): void {
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
}

$q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = (int)($_GET['limit'] ?? 20);
if ($limit < 1)   $limit = 20;
if ($limit > 200) $limit = 200;
$offset = ($page - 1) * $limit;

// Ordenamiento (whitelist)
$allowedOrder = ['updated_at','nombre','id'];
$order = $_GET['order'] ?? 'updated_at';
$order = in_array($order, $allowedOrder, true) ? $order : 'updated_at';
$dir   = strtolower((string)($_GET['dir'] ?? 'desc'));
$dir   = ($dir === 'asc') ? 'ASC' : 'DESC';

try {
  $pdo = db();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $where = '';
  $paramsCount = [];
  $paramsList  = [];

  if ($q !== '') {
    $where = "WHERE nombre LIKE :q1 OR direccion LIKE :q2";
    $like  = "%{$q}%";
    $paramsCount = [':q1' => $like, ':q2' => $like];
    $paramsList  = [':q1' => $like, ':q2' => $like];
  }

  // TOTAL
  $sqlCount = "SELECT COUNT(*) FROM predios $where";
  $st = $pdo->prepare($sqlCount);
  foreach ($paramsCount as $k=>$v) $st->bindValue($k, $v, PDO::PARAM_STR);
  $st->execute();
  $total = (int)$st->fetchColumn();

  // ITEMS + flags
  $sqlList = "SELECT 
                id, user_id, nombre, direccion, lat, lng, updated_at,
                (world_grid IS NOT NULL AND world_grid <> '') AS has_world,
                (lat IS NOT NULL AND lng IS NOT NULL) AS has_loc
              FROM predios
              $where
              ORDER BY $order $dir, id DESC
              LIMIT $offset, $limit";
  $st = $pdo->prepare($sqlList);
  foreach ($paramsList as $k=>$v) $st->bindValue($k, $v, PDO::PARAM_STR);
  $st->execute();
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  json_out([
    'ok'=>true,
    'data'=>$rows,
    'total'=>$total,
    'page'=>$page,
    'limit'=>$limit,
    'order'=>$order,
    'dir'=>strtolower($dir)
  ]);
} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()],500);
}
