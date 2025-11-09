<?php
// api/recommendations/from_grid.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors','0');

// Localiza core en ../../core
$rootCore = realpath(__DIR__ . '/../../core');
if (!$rootCore || !is_dir($rootCore)) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>'No se ubica core en ../../core']);
  exit;
}
require_once $rootCore.'/db.php';
$helpers = $rootCore.'/helpers.php';
if (file_exists($helpers)) require_once $helpers;

// Fallback si no tienes helpers->json_out/read_json_body
if (!function_exists('json_out')) {
  function json_out(array $p, int $code=200): void {
    http_response_code($code);
    echo json_encode($p, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    exit;
  }
}
if (!function_exists('read_json_body')) {
  function read_json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
  }
}

// ---------- Helpers locales ----------
function is_grid_valid($grid): bool {
  if (!is_array($grid) || empty($grid) || !is_array($grid[0])) return false;
  $rows = count($grid); $cols = count($grid[0]);
  if ($rows < 1 || $cols < 1) return false;
  for ($y=0; $y<$rows; $y++) {
    if (!is_array($grid[$y]) || count($grid[$y]) !== $cols) return false;
    for ($x=0; $x<$cols; $x++) {
      if (!is_string($grid[$y][$x])) return false;
    }
  }
  return true;
}
function stats_from_grid($grid): array {
  $h = count($grid); $w = count($grid[0]);
  $S = [
    'total'=>$w*$h,'arable'=>0,'water'=>0,
    'soil'=>0,'crop_hort'=>0,'crop_apple'=>0,'crop_berries'=>0,
    'greenhouse'=>0,'irrigation'=>0,'slope'=>0,'biodiversity'=>0,'sensor'=>0
  ];
  foreach ($grid as $row) foreach ($row as $t) {
    if ($t==='water') $S['water']++;
    elseif (isset($S[$t])) $S[$t]++;
  }
  $S['arable'] = $S['total'] - $S['water'];
  $S['crops']  = $S['crop_hort'] + $S['crop_apple'] + $S['crop_berries'];
  return $S;
}
function default_climate(): array {
  return ['avg_tmin'=>5.0,'avg_tmax'=>19.0,'total_prcp_mm'=>1200,'frost_days_est'=>18,'source'=>'default','period'=>'promedio local'];
}
function clamp($v,$a,$b){ return max($a, min($b, $v)); }

// Reglas de recomendación (igual que antes)
function build_recommendations(array $S, array $C): array {
  $recs = [];
  $arable    = max(1, $S['arable']);
  $ghRatio   = $S['greenhouse']  / $arable;
  $irrRatio  = $S['irrigation']  / $arable;
  $bioRatio  = $S['biodiversity']/ $arable;
  $slopeRatio= $S['slope']       / $arable;
  $fruitRatio= ($S['crop_apple'] + $S['crop_berries']) / $arable;
  $cropsRatio= $S['crops'] / $arable;

  $lowRain = $C['total_prcp_mm'] < 700;
  $midRain = $C['total_prcp_mm'] >= 700 && $C['total_prcp_mm'] < 1000;
  $hiFrost = $C['frost_days_est'] >= 20;
  $midFrost= $C['frost_days_est'] >= 12;

  if ($irrRatio < 0.08 || $lowRain || $midRain) {
    $recs[] = [
      'title'=>'Instalación/optimización de riego tecnificado',
      'rationale'=>($lowRain?'Precipitaciones bajas ':($midRain?'Precipitaciones moderadas ':'')) .
                  "y riego actual ≈ ".round($irrRatio*100)."%.",
      'actions'=>['Evaluación hidráulica','Goteo/aspersión sectorizado','Automatización','Mantención filtros'],
      'cost_range_clp'=>'CLP 1,5–6,0 MM','priority'=>$lowRain?'alta':'media','tag'=>'agua'
    ];
  }
  if ($fruitRatio>0.05 && ($midFrost||$hiFrost) && $ghRatio<0.05) {
    $recs[] = [
      'title'=>'Módulos de invernadero',
      'rationale'=>"Frutales ≈ ".round($fruitRatio*100)."%, heladas ≈ {$C['frost_days_est']} d/año, GH ≈ ".round($ghRatio*100)."%.",
      'actions'=>['Módulos 8×20 m','Cortinas/ventanas','Data loggers'],
      'cost_range_clp'=>'CLP 2,0–9,0 MM','priority'=>$hiFrost?'alta':'media','tag'=>'heladas'
    ];
  }
  if ($bioRatio<0.06 || $slopeRatio>0.06) {
    $recs[] = [
      'title'=>'Coberturas y fajas de biodiversidad',
      'rationale'=>"Biodiv ≈ ".round($bioRatio*100)."%, pendiente ≈ ".round($slopeRatio*100)."%.",
      'actions'=>['Leguminosas/gramíneas','Setos nativos','Acolchados en surcos'],
      'cost_range_clp'=>'CLP 0,6–2,5 MM','priority'=>($slopeRatio>0.1?'alta':'media'),'tag'=>'suelo'
    ];
  }
  if ($cropsRatio<0.25) {
    $recs[] = [
      'title'=>'Plan de densificación/rotaciones',
      'rationale'=>"Área en cultivo ≈ ".round($cropsRatio*100)."%.",
      'actions'=>['Mapa de aptitud','Plan escalonado 3–4 cultivos','Ensayo piloto 10–20%'],
      'cost_range_clp'=>'CLP 0,4–1,2 MM','priority'=>'media','tag'=>'producción'
    ];
  }
  if ($S['sensor'] < max(2, ceil($arable/80))) {
    $recs[] = [
      'title'=>'Nodos IoT básicos',
      'rationale'=>"Sensores actuales: {$S['sensor']}.",
      'actions'=>['Humedad/temperatura suelo','Termómetros mínima','Gateway LoRa/WiFi','Alertas básicas'],
      'cost_range_clp'=>'CLP 0,8–3,5 MM','priority'=>'media','tag'=>'monitoreo'
    ];
  }
  if (($S['water']/max(1,$S['total'])) > 0.12) {
    $recs[] = [
      'title'=>'Drenaje y escorrentías',
      'rationale'=>"Agua superficial ≈ ".round($S['water']/$S['total']*100)."%.",
      'actions'=>['Zanjas de infiltración','Lagunas de retardo','Geotextil en puntos críticos'],
      'cost_range_clp'=>'CLP 1,0–4,0 MM','priority'=>'media','tag'=>'hidrología'
    ];
  }
  usort($recs, function($a,$b){
    $pri=['alta'=>2,'media'=>1,'baja'=>0];
    return ($pri[$b['priority']]??0) <=> ($pri[$a['priority']]??0);
  });
  return $recs;
}

// ---------- Main ----------
try {
  $in = read_json_body();
  // Soporte GET ?predio_id=... (para abrir en navegador)
  if (empty($in) && isset($_GET['predio_id'])) {
    $in['predio_id'] = (int)$_GET['predio_id'];
  }

  $grid = $in['grid'] ?? null;
  $clim = $in['climate'] ?? null;

  // Fallback: si no viene grid/climate y hay predio_id, cargar desde BD
  if ((!$grid || !is_grid_valid($grid)) && !empty($in['predio_id'])) {
    $pdo = db();
    $st = $pdo->prepare("SELECT world_grid, avg_tmin, avg_tmax, total_prcp_mm, frost_days_est FROM predios WHERE id=?");
    $st->execute([ (int)$in['predio_id'] ]);
    if ($row = $st->fetch(PDO::FETCH_ASSOC)) {
      if (!empty($row['world_grid'])) $grid = json_decode($row['world_grid'], true);
      if (!$clim) {
        $clim = [
          'avg_tmin'=>(float)($row['avg_tmin'] ?? 5),
          'avg_tmax'=>(float)($row['avg_tmax'] ?? 19),
          'total_prcp_mm'=>(float)($row['total_prcp_mm'] ?? 1200),
          'frost_days_est'=>(int)($row['frost_days_est'] ?? 18),
          'source'=>'db/predio','period'=>'último cálculo'
        ];
      }
    }
  }

  //if (!$grid || !is_grid_valid($grid)) {
  //  json_out(['ok'=>false,'error'=>'grid inválido o ausente'], 422);
  //}

  // Tras intentar leer $grid de la BD:
    if (!$grid || !is_grid_valid($grid)) {
  
      // Semilla un grid 20x20 con 'soil' por defecto
      $grid = array_fill(0, 20, array_fill(0, 20, 'soil'));
    }



  if (!$clim || !is_array($clim)) {
    $clim = default_climate();
  } else {
    $clim = array_merge(default_climate(), $clim);
    $clim['avg_tmin']       = (float)$clim['avg_tmin'];
    $clim['avg_tmax']       = (float)$clim['avg_tmax'];
    $clim['total_prcp_mm']  = (float)$clim['total_prcp_mm'];
    $clim['frost_days_est'] = (int)$clim['frost_days_est'];
  }

  $S = stats_from_grid($grid);
  $recs = build_recommendations($S, $clim);

  json_out(['ok'=>true,'data'=>$recs,'stats'=>$S,'climate'=>$clim]);
} catch (Throwable $e) {
  json_out(['ok'=>false,'error'=>$e->getMessage()], 500);
}
