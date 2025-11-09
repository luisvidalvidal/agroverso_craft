<?php
// core/helpers.php
function json_out($payload, int $code = 200) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  if ($raw === false || $raw === '') return [];
  $j = json_decode($raw, true);
  return is_array($j) ? $j : [];
}

function http_get_json(string $url): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_USERAGENT => 'AgroversoCraft/1.0 (+localhost)',
  ]);
  $res = curl_exec($ch);
  if ($res === false) {
    $err = curl_error($ch);
    curl_close($ch);
    throw new RuntimeException("HTTP error: $err");
  }
  curl_close($ch);
  $j = json_decode($res, true);
  if (!is_array($j)) throw new RuntimeException("Respuesta no JSON de $url");
  return $j;
}
