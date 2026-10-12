<?php
/**
 * probar_getnet.php — SOLO PARA PROBAR EN TU PC. BORRALO cuando termines.
 *
 * Pide un token a Getnet con las credenciales de config/getnet.config.php
 * (bloque 'sandbox') y muestra el resultado SIN imprimir secretos.
 * Abrilo en: http://localhost/<tu-carpeta>/mundo_verde_backend/api/probar_getnet.php
 *
 * Prueba dos formatos de pedido de token (JSON y formulario), porque todavía
 * no está confirmado cuál usa Getnet. Mirá cuál devuelve HTTP 200.
 */
if (!in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])) {
    http_response_code(404);
    exit;
}
header('Content-Type: text/plain; charset=utf-8');

$archivo = __DIR__ . '/../config/getnet.config.php';
if (!is_file($archivo)) { exit("Falta config/getnet.config.php\n"); }
$cfg = (require $archivo)['sandbox'];

foreach (['client_id', 'client_secret', 'auth_url'] as $k) {
    if (empty($cfg[$k]) || str_contains($cfg[$k], 'CLIENT_') || str_contains($cfg[$k], 'TODO')) {
        exit("Completá '$k' en el bloque 'sandbox' de config/getnet.config.php\n");
    }
}

function probar(string $nombre, string $url, array $headers, string $body): void
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    echo "== $nombre\n";
    if ($resp === false) { echo "Error de conexión: $error\n\n"; return; }
    echo "HTTP $codigo\n";
    $json = json_decode($resp, true);
    if (is_array($json)) {
        // Solo nombres de campos y mensajes; el token en sí NO se imprime.
        echo "Campos: " . implode(', ', array_keys($json)) . "\n";
        foreach (['error', 'error_description', 'message', 'expires_in', 'token_type'] as $k) {
            if (isset($json[$k])) echo "$k: " . (is_scalar($json[$k]) ? $json[$k] : json_encode($json[$k])) . "\n";
        }
        if (isset($json['access_token'])) echo "access_token: recibido (" . strlen($json['access_token']) . " caracteres)\n";
    } else {
        echo "Respuesta no JSON (primeros 200 caracteres): " . substr($resp, 0, 200) . "\n";
    }
    echo "\n";
}

probar('Formato JSON', $cfg['auth_url'], ['Content-Type: application/json'], json_encode([
    'grant_type' => 'client_credentials',
    'client_id' => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
]));

probar('Formato formulario', $cfg['auth_url'], ['Content-Type: application/x-www-form-urlencoded'], http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => $cfg['client_id'],
    'client_secret' => $cfg['client_secret'],
]));
