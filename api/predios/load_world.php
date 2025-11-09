<?php
require_once __DIR__.'/../../core/db.php';
require_once __DIR__.'/../../core/helpers.php';
try{
  $pdo=db(); $in=read_json_body(); $id=(int)($in['predio_id']??0);
  if(!$id) json_out(['ok'=>false,'error'=>'predio_id requerido'],400);
  $stmt=$pdo->prepare("SELECT grid_json FROM predios WHERE id=?"); $stmt->execute([$id]);
  $row=$stmt->fetch();
  $grid = $row && $row['grid_json'] ? json_decode($row['grid_json'],true) : null;
  if(!$grid){ // grid vacío por defecto
    $ROWS=20;$COLS=20; $grid=array_fill(0,$ROWS,array_fill(0,$COLS,'soil'));
  }
  json_out(['ok'=>true,'data'=>$grid]);
}catch(Throwable $e){ json_out(['ok'=>false,'error'=>$e->getMessage()],500); }



