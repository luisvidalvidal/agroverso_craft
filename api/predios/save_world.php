<?php
require_once __DIR__.'/../../core/db.php';
require_once __DIR__.'/../../core/helpers.php';
try {
  $pdo = db(); $in = read_json_body();
  $id = (int)($in['predio_id'] ?? 0); $grid = $in['grid'] ?? null;
  if(!$id || !$grid) json_out(['ok'=>false,'error'=>'predio_id y grid requeridos'],400);
  $stmt = $pdo->prepare("UPDATE predios SET grid_json=:g WHERE id=:id");
  $stmt->execute([':g'=>json_encode($grid,JSON_UNESCAPED_UNICODE),':id'=>$id]);
  json_out(['ok'=>true]);
} catch(Throwable $e){ json_out(['ok'=>false,'error'=>$e->getMessage()],500); }
