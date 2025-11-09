<?php
// api/predios/get.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$rootCore = realpath(__DIR__ . '/../../core');
require_once $rootCore.'/db.php';

function out($p,$c=200){ http_response_code($c); echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id<=0) out(['ok'=>false,'error'=>'id requerido'],422);

try {
  $pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $st = $pdo->prepare("SELECT id,user_id,nombre,direccion,lat,lng,updated_at,created_at FROM predios WHERE id=?");
  $st->execute([$id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if (!$row) out(['ok'=>false,'error'=>'No encontrado'],404);
  out(['ok'=>true,'data'=>$row]);
} catch(Throwable $e){ out(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()],500); }
