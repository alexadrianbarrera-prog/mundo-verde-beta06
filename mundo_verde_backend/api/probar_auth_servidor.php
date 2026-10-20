<?php
/**
 * probar_auth_servidor.php — TEMPORAL. Subir a mundo_verde_backend/api/, abrir UNA vez y BORRAR.
 * Prueba desde el servidor (Hostinger) si Getnet acepta las credenciales del entorno configurado.
 * Protegido con el webhook_token: .../api/probar_auth_servidor.php?k=TU_WEBHOOK_TOKEN
 * No imprime secretos ni el token de acceso: solo el código HTTP y el motivo.
 */
header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$cfgFile = __DIR__ . '/../config/getnet.config.php';
if (!is_file($cfgFile)) { http_response_code(404); exit("Falta getnet.config.php\n"); }
$cfgAll = require $cfgFile;

$k = (string)($_GET['k'] ?? '');
if ($k === '' || !hash_equals((string)($cfgAll['webhook_token'] ?? ''), $k)) {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/getnet.php';

$c = getnet_cfg();
$id = $c['client_id']; $sec = $c['client_secret'];

echo "Entorno configurado : " . ($cfgAll['entorno'] ?? '?') . "\n";
echo "auth_url            : " . $c['auth_url'] . "\n";
printf("client_id           : largo %d, empieza '%s', termina '%s'\n", strlen($id), substr($id, 0, 4), substr($id, -4));
printf("client_secret       : largo %d, termina '%s'\n", strlen($sec), substr($sec, -2));
echo "IP de salida        : " . (@file_get_contents('https://api.ipify.org') ?: 'no disponible') . "\n\n";

[$cod, $r] = getnet_http(
    'POST',
    $c['auth_url'],
    ['Content-Type: application/x-www-form-urlencoded'],
    http_build_query(['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $sec])
);

if ($cod === 200 && !empty($r['access_token'])) {
    echo "HTTP 200  ✔ FUNCIONA: Getnet aceptó las credenciales (token recibido, no se muestra).\n";
} else {
    $txt = json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    echo "HTTP $cod  ✘ " . substr((string)$txt, 0, 600) . "\n";
}
echo "\nBORRÁ este archivo del servidor ahora.\n";
