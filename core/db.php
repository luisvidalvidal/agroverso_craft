<?php
// core/db.php
// Uso: $pdo = db();  -> retorna un PDO listo

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $confPath = __DIR__ . '/../config/config.php';
  if (!file_exists($confPath)) {
    http_response_code(500);
    die('Falta config.php en /config (copia config.sample.php).');
  }
  $cfg = require $confPath;
  $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
    $cfg['db']['host'], $cfg['db']['name'], $cfg['db']['charset'] ?? 'utf8mb4'
  );

  $pdo = new PDO($dsn, $cfg['db']['user'], $cfg['db']['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);
  return $pdo;
}
