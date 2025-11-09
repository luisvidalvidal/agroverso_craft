<?php
// api/predios/delete.php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$rootCore = realpath(__DIR__ . '/../../core');
require_once $rootCore.'/db.php';

function out($p,$c=200){ http_response_code($c); echo json_encode($p,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }

$raw = file_get_contents('php://input');
$in  = json_decode($raw,true) ?: [];
$id  = (int)($in['id'] ?? 0);
if ($id<=0) out(['ok'=>false,'error'=>'id requerido'],422);

try {
  $pdo = db(); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $st = $pdo->prepare("DELETE FROM predios WHERE id=?");
  $st->execute([$id]);
  out(['ok'=>true,'data'=>['id'=>$id]]);
} catch(Throwable $e){ out(['ok'=>false,'error'=>'Error interno: '.$e->getMessage()],500); }
