<?php
/**
 * probar_auth_getnet.php — SOLO PARA TU PC. Diagnostica el error "invalid_client".
 * Prueba el pedido de token en 2 formatos y muestra datos ENMASCARADOS de las credenciales leídas.
 * No muestra el secret. BORRAR antes de subir a Hostinger.
 */
$cli = PHP_SAPI === 'cli';
if (!$cli && !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])) { http_response_code(404); exit; }
if (!$cli) header('Content-Type: text/plain; charset=utf-8');
$_SERVER['SERVER_NAME'] = 'localhost';
require_once __DIR__ . '/getnet.php';

function l($t = '') { echo $t . "\n"; }
$c = getnet_cfg();
$id = $c['client_id']; $sec = $c['client_secret'];

l("Archivo leído: " . realpath(__DIR__ . '/../config/getnet.config.php'));
l("auth_url: " . $c['auth_url']);
l(sprintf("client_id    : largo %d, empieza '%s', termina '%s'", strlen($id), substr($id, 0, 4), substr($id, -4)));
l(sprintf("client_secret: largo %d, empieza '%s', termina '%s'", strlen($sec), substr($sec, 0, 2), substr($sec, -2)));
l("¿secret con caracteres raros (espacios/saltos)? " . (preg_match('/[^\x21-\x7E]/', $sec) ? 'SÍ' : 'no'));
l("¿client_id empieza con 'cid_'? " . (strpos($id, 'cid_') === 0 ? 'sí' : 'no'));
l();

$variantes = [
    'A) credenciales en el cuerpo (como hoy)'      => [[], ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $sec]],
    'B) Authorization: Basic'                      => [['Authorization: Basic ' . base64_encode("$id:$sec")], ['grant_type' => 'client_credentials']],
    'C) cuerpo, client_id sin prefijo cid_'        => [[], ['grant_type' => 'client_credentials', 'client_id' => preg_replace('/^cid_/', '', $id), 'client_secret' => $sec]],
];
foreach ($variantes as $nombre => [$hdr, $campos]) {
    [$cod, $r] = getnet_http('POST', $c['auth_url'], array_merge(['Content-Type: application/x-www-form-urlencoded'], $hdr), http_build_query($campos));
    $ok = $cod === 200 && !empty($r['access_token']);
    l(sprintf("%-45s HTTP %d  %s", $nombre, $cod, $ok ? '✔ FUNCIONA' : json_encode($r, JSON_UNESCAPED_UNICODE)));
}
