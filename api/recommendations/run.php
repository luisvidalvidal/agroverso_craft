<?php
// api/recommendations/run.php  (VERSIÓN DIAGNÓSTICO AUTÓNOMO)
declare(strict_types=1);

// ----- Diagnóstico y logging -----
ini_set('display_errors', '0');       // no mostrar HTML de errores
ini_set('log_errors', '1');
@mkdir(__DIR__ . '/../../storage/logs', 0777, true);
ini_set('error_log', __DIR__ . '/../../storage/logs/php.log');
error_reporting(E_ALL);

// ----- Helpers internos (evitamos dependencias) -----
function json_out($payload, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  exit;
}
function read_json_body(): array {
  $raw = file_get_contents('php://input');
  $j = json_decode($raw ?: '[]', true);
  return is_array($j) ? $j : [];
}

// ----- Conexión PDO reusando core/db.php (esto ya te funcionó en climate) -----
require_once __DIR__ . '/../../core/db.php';

try {
  $pdo = db(); // si falla, 500 por excepción

  $in = read_json_body();
  $predioId = (int)($in['predio_id'] ?? 0);
  if (!$predioId) {
    json_out(['ok'=>false,'stage'=>'input','error'=>'predio_id requerido'], 400);
  }

  // 1) Traer último snapshot climático del predio
  $stmt = $pdo->prepare("SELECT id, avg_tmin, avg_tmax, total_prcp_mm, frost_days_est FROM climate_snapshots WHERE predio_id=? ORDER BY id DESC LIMIT 1");
  $stmt->execute([$predioId]);
  $snap = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$snap) {
    json_out(['ok'=>false,'stage'=>'snapshot','error'=>'No hay snapshot climático para este predio. Corre /api/climate/refresh.php primero.'], 404);
  }

  // 2) Cargar y validar rules.json
  $rulesPath = realpath(__DIR__ . '/../../data/rules.json');
  if ($rulesPath === false || !file_exists($rulesPath)) {
    json_out(['ok'=>false,'stage'=>'rules','error'=>'No existe data/rules.json','path'=>__DIR__ . '/../../data/rules.json'], 500);
  }
  $raw = file_get_contents($rulesPath);
  if ($raw === false) {
    json_out(['ok'=>false,'stage'=>'rules','error'=>'No se pudo leer rules.json','path'=>$rulesPath], 500);
  }
  $rules = json_decode($raw, true);
  if (!is_array($rules)) {
    json_out([
      'ok'=>false,'stage'=>'rules','error'=>'rules.json inválido',
      'json_last_error'=> json_last_error(),
      'json_last_error_msg'=> json_last_error_msg()
    ], 500);
  }

  // 3) Evaluación de reglas (solo con clima; pendiente se simula si no está)
  $precip = (float)$snap['total_prcp_mm'];
  $tmin   = (float)$snap['avg_tmin'];
  $tmax   = (float)$snap['avg_tmax'];
  $frost  = (int)$snap['frost_days_est'];
  $pend   = 12.0; // TODO: si tienes predios.pendiente numérico, léelo allí.

  $out = [];
  foreach ($rules as $r) {
    $c = $r['condition'] ?? [];
    $ok = true;

    if (isset($c['precip_mm_max']) && $precip > (float)$c['precip_mm_max']) $ok=false;
    if (isset($c['precip_mm_min']) && $precip < (float)$c['precip_mm_min']) $ok=false;
    if (isset($c['frost_days_min']) && $frost < (int)$c['frost_days_min']) $ok=false;
    if (isset($c['tmin_max']) && $tmin > (float)$c['tmin_max']) $ok=false;
    if (isset($c['tmax_max']) && $tmax > (float)$c['tmax_max']) $ok=false;
    if (isset($c['pendiente_min']) && $pend < (float)$c['pendiente_min']) $ok=false;

    if ($ok) {
      $out[] = [
        'title'    => $r['name'] ?? 'Recomendación',
        'rationale'=> $r['rationale'] ?? '',
        'actions'  => $r['actions'] ?? [],
        'impact'   => $r['impact'] ?? [],
        'cost_range_clp' => $r['cost_range_clp'] ?? 'N/D'
      ];
    }
  }

  json_out(['ok'=>true,'stage'=>'done','snapshot'=>$snap,'count'=>count($out),'data'=>$out]);

} catch (Throwable $e) {
  // Log a archivo y responde JSON
  error_log('[run.php] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
  json_out(['ok'=>false,'stage'=>'exception','error'=>$e->getMessage()], 500);
}
