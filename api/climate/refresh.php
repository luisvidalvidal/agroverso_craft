<?php
// api/climate/refresh.php
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';

try {
  $pdo = db();



  $in = read_json_body();

  $predioId = isset($in['predio_id']) ? (int)$in['predio_id'] : null;
  if ($predioId) {
    $stmt = $pdo->prepare('SELECT id, lat, lng FROM predios WHERE id = ?');
    $stmt->execute([$predioId]);
    $row = $stmt->fetch();
    if (!$row || $row['lat'] === null || $row['lng'] === null) {
      json_out(['ok'=>false, 'error'=>'Predio no encontrado o sin lat/lng'], 400);
    }
    $lat = (float)$row['lat']; $lng = (float)$row['lng'];
  } else {
    if (!isset($in['lat'], $in['lng'])) {
      json_out(['ok'=>false, 'error'=>'Faltan lat/lng o predio_id'], 400);
    }
    $lat = (float)$in['lat']; $lng = (float)$in['lng'];
  }




  // ---------- Intento 1: FORECAST (past_days ~ 92) ----------
  $qs1 = http_build_query([
    'latitude'  => $lat,
    'longitude' => $lng,
    'daily'     => 'temperature_2m_min,temperature_2m_max,precipitation_sum',
    'timezone'  => 'auto',
    'past_days' => 92
  ]);
  $url1 = "https://api.open-meteo.com/v1/forecast?$qs1";
  $j1 = http_get_json($url1);

  // Función local para validar bloque "daily"
  $valid_daily = function($j) {
    return isset($j['daily']['temperature_2m_min'], $j['daily']['temperature_2m_max'], $j['daily']['precipitation_sum'])
           && is_array($j['daily']['temperature_2m_min'])
           && count($j['daily']['temperature_2m_min']) > 0;
  };

  $source = 'open-meteo';
  $period = 'last3m';
  $raw    = $j1;

  if (!$valid_daily($j1)) {
    // ---------- Intento 2: ARCHIVE ERA5 (últimos 365 días) ----------
    $end = new DateTime('today');
    $start = (clone $end)->modify('-365 days');

    $qs2 = http_build_query([
      'latitude'  => $lat,
      'longitude' => $lng,
      'start_date'=> $start->format('Y-m-d'),
      'end_date'  => $end->format('Y-m-d'),
      'daily'     => 'temperature_2m_min,temperature_2m_max,precipitation_sum',
      'timezone'  => 'auto'
    ]);
    $url2 = "https://archive-api.open-meteo.com/v1/era5?$qs2";
    $j2 = http_get_json($url2);

    if (!$valid_daily($j2)) {
      json_out(['ok'=>false, 'error'=>'No se pudo obtener datos diarios ni con forecast ni con archive'], 502);
    }
    $raw    = $j2;
    $period = 'last12m';
    $source = 'open-meteo-archive';
  }

  $mins = $raw['daily']['temperature_2m_min'];
  $maxs = $raw['daily']['temperature_2m_max'];
  $prcp = $raw['daily']['precipitation_sum'];
  $n = count($mins);

  if ($n === 0 || $n !== count($maxs) || $n !== count($prcp)) {
    json_out(['ok'=>false, 'error'=>'Datos diarios inconsistentes'], 502);
  }

  // Resumen de métricas
  $avg_tmin = array_sum($mins) / $n;
  $avg_tmax = array_sum($maxs) / $n;
  $total_prcp_mm = array_sum($prcp);
  $frost_days = 0;
  for ($i=0; $i<$n; $i++) { if ($mins[$i] <= 0) $frost_days++; }

  // (Opcional) GDD base 10
  $gdd = 0;
  for ($i=0; $i<$n; $i++) {
    $d = (($mins[$i] + $maxs[$i]) / 2) - 10.0;
    if ($d > 0) $gdd += $d;
  }

  // Guardar snapshot
  $stmt = $pdo->prepare("
    INSERT INTO climate_snapshots (predio_id, lat, lng, source, period, avg_tmin, avg_tmax, total_prcp_mm, frost_days_est, gdd_base10, raw_json)
    VALUES (:predio_id, :lat, :lng, :source, :period, :tmin, :tmax, :prcp, :frost, :gdd, :raw)
  ");
  $stmt->execute([
    ':predio_id' => $predioId,
    ':lat' => $lat,
    ':lng' => $lng,
    ':source' => $source,
    ':period' => $period,
    ':tmin' => round($avg_tmin, 2),
    ':tmax' => round($avg_tmax, 2),
    ':prcp' => round($total_prcp_mm, 1),
    ':frost' => $frost_days,
    ':gdd' => (int)round($gdd),
    ':raw' => json_encode($raw, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)
  ]);

  $id = (int)$pdo->lastInsertId();
  json_out([
    'ok' => true,
    'data' => [
      'snapshot_id' => $id,
      'lat' => $lat, 'lng' => $lng,
      'avg_tmin' => round($avg_tmin,1),
      'avg_tmax' => round($avg_tmax,1),
      'total_prcp_mm' => round($total_prcp_mm,0),
      'frost_days_est' => $frost_days,
      'gdd_base10' => (int)round($gdd),
      'period' => $period,
      'source' => $source
    ]
  ]);
} catch (Throwable $e) {
  json_out(['ok'=>false, 'error'=>$e->getMessage()], 500);
}