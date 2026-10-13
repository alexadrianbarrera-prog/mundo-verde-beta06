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
$cfgOriginal = $cfg;

foreach (['client_id', 'client_secret', 'auth_url'] as $k) {
    if (empty($cfg[$k]) || str_contains($cfg[$k], 'CLIENT_') || str_contains($cfg[$k], 'TODO')) {
        exit("Completá '$k' en el bloque 'sandbox' de config/getnet.config.php\n");
    }
}

// ── Diagnóstico de formato (NO imprime los valores) ──
$id = $cfg['client_id']; $sec = $cfg['client_secret'];
echo "== Diagnóstico de las credenciales (sin mostrar valores)\n";
echo "client_id: " . strlen($id) . " caracteres" . ($id !== trim($id) ? " — TIENE ESPACIOS al inicio/fin" : "") . "\n";
echo "  formato cid_ + UUID: " . (preg_match('/^cid_[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($id)) ? "OK" : "NO COINCIDE (¿incompleto o con caracteres de más?)") . "\n";
echo "client_secret: " . strlen($sec) . " caracteres" . ($sec !== trim($sec) ? " — TIENE ESPACIOS al inicio/fin" : "") . "\n";
echo "  formato UUID: " . (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($sec)) ? "OK" : "no es un UUID estándar (puede ser normal)") . "\n";
echo "auth_url: " . $cfg['auth_url'] . "\n\n";

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

// ── Resultado de la ronda anterior: JSON → 400 "Invalid Content Type" (el servidor exige formulario)
//    y formulario → "invalid_client" (el servidor entiende el pedido pero rechaza las credenciales).
//    Ahora se prueban varias direcciones de autenticación posibles con formato formulario.
$candidatas = [
    'La de tu config'                 => trim($cfg['auth_url']),
    'Sandbox (api-sbx.pre)'           => 'https://api-sbx.pre.globalgetnet.com/authentication/oauth2/access_token',
    'Pre-producción (api.pre)'        => 'https://api.pre.globalgetnet.com/authentication/oauth2/access_token',
    'Sandbox (api-sbx)'               => 'https://api-sbx.globalgetnet.com/authentication/oauth2/access_token',
    'Producción (api)'                => 'https://api.globalgetnet.com/authentication/oauth2/access_token',
];
$vistas = [];
foreach ($candidatas as $etiqueta => $url) {
    if (isset($vistas[$url])) continue;   // no repetir la misma dirección
    $vistas[$url] = true;
    echo "################ $etiqueta\n$url\n\n";
    probar('Formulario (credenciales en el cuerpo)', $url,
        ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => trim($cfg['client_id']),
            'client_secret' => trim($cfg['client_secret']),
        ]));
    probar('Basic Auth (credenciales en el encabezado)', $url, [
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode(trim($cfg['client_id']) . ':' . trim($cfg['client_secret'])),
    ], http_build_query(['grant_type' => 'client_credentials']));
}
