<?php
// api/predios/set_location.php
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/helpers.php';

try {
  $pdo = db();
  $in = read_json_body();

  $id  = (int)($in['predio_id'] ?? 0);
  $lat = $in['lat'] ?? null;   // número
  $lng = $in['lng'] ?? null;   // número
  $dir = $in['direccion'] ?? null; // string opcional

  if (!$id || $lat === null || $lng === null) {
    json_out(['ok'=>false, 'error'=>'predio_id, lat y lng son requeridos'], 400);
  }

  // Normaliza tipos
  $lat = floatval($lat);
  $lng = floatval($lng);

  // Validaciones básicas de rango
  if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    json_out(['ok'=>false, 'error'=>'lat/lng fuera de rango'], 422);
  }

  $stmt = $pdo->prepare("UPDATE predios SET lat=:lat, lng=:lng, direccion=:dir WHERE id=:id");
  $stmt->execute([
    ':lat' => $lat,
    ':lng' => $lng,
    ':dir' => $dir,
    ':id'  => $id
  ]);

  // Devuelve estado actual por comodidad
  json_out([
    'ok'   => true,
    'data' => ['predio_id'=>$id, 'lat'=>$lat, 'lng'=>$lng, 'direccion'=>$dir]
  ]);
} catch (Throwable $e) {
  json_out(['ok'=>false, 'error'=>$e->getMessage()], 500);
}


