<?php
/**
 * getnet.php — cliente HTTP de Getnet (token OAuth + crear/consultar pago).
 *
 * Los puntos marcados "TODO(doc)" dependen de la documentación oficial de
 * Getnet Argentina para el producto que elijas (Web Checkout / API directa):
 * path exacto, nombres de campos y formato del monto. No están inventados a
 * propósito: confirmalos en docs.globalgetnet.com o con consultasecommerce@getnet.com.ar
 *
 * La config vive en config/getnet.config.php (NO versionado, se sube a mano a
 * Hostinger igual que config_mail.php). Plantilla: config/getnet.config.example.php
 */

function getnet_cfg(): array
{
    static $cfg = null;
    if ($cfg !== null) return $cfg;

    $archivo = __DIR__ . '/../config/getnet.config.php';
    if (!is_file($archivo)) {
        throw new RuntimeException('Falta config/getnet.config.php');
    }
    $base = require $archivo;

    // Misma regla que config/conexion.php: en tu PC siempre sandbox.
    $esLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);
    $entorno = $esLocal ? 'sandbox' : ($base['entorno'] ?? 'sandbox');

    // Defensivo: un espacio al copiar/pegar las credenciales rompe la autenticación.
    foreach (['client_id', 'client_secret', 'auth_url', 'api_base'] as $k) {
        if (isset($base[$entorno][$k])) $base[$entorno][$k] = trim($base[$entorno][$k]);
    }

    $cfg = array_merge($base[$entorno], [
        'entorno'       => $entorno,
        'return_url'    => $base['return_url'],
        'webhook_url'   => $base['webhook_url'],
        'webhook_token' => $base['webhook_token'],
    ]);
    return $cfg;
}

function getnet_http(string $metodo, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('cURL Getnet: ' . $err);
    }
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [$codigo, json_decode($resp, true)];
}

function getnet_token(): string
{
    $c = getnet_cfg();
    $cache = sys_get_temp_dir() . '/mv_getnet_token_' . $c['entorno'] . '.json';

    if (is_file($cache)) {
        $t = json_decode((string)file_get_contents($cache), true);
        if (is_array($t) && ($t['exp'] ?? 0) > time() + 60) return $t['token'];
    }

    // Confirmado con pruebas: el servidor responde "Invalid Content Type" si se envía JSON,
    // así que se pide en application/x-www-form-urlencoded.
    [$codigo, $r] = getnet_http('POST', $c['auth_url'], ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
        ]));

    if ($codigo !== 200 || empty($r['access_token'])) {
        throw new RuntimeException("Getnet auth falló (HTTP $codigo)");
    }

    // Si no se puede escribir el cache (permisos), simplemente se pide token cada vez.
    @file_put_contents($cache, json_encode([
        'token' => $r['access_token'],
        'exp'   => time() + (int)($r['expires_in'] ?? 300),
    ]));
    return $r['access_token'];
}

/**
 * $pedido: ['id','referencia','total','email'].
 * Devuelve la respuesta decodificada de Getnet (esperá un id de transacción
 * y una URL de pago; ver pagos_crear() en controllers/pagos.php).
 */
function getnet_crear_pago(array $pedido): array
{
    $c = getnet_cfg();

    // TODO(doc): path, nombres de campos y formato del monto (¿centavos o decimal?).
    $payload = [
        'amount'           => (int)round($pedido['total'] * 100),
        'currency'         => 'ARS',
        'reference'        => $pedido['referencia'],            // MV-DDMM-NNNN
        'return_url'       => $c['return_url'] . '?id=' . $pedido['id'] . '&ref=' . urlencode($pedido['referencia']),
        'notification_url' => $c['webhook_url'] . '?k=' . urlencode($c['webhook_token']),
        'customer'         => ['email' => $pedido['email']],
    ];

    [$codigo, $r] = getnet_http('POST', $c['api_base'] . '/TODO/path-crear-pago', [
        'Authorization: Bearer ' . getnet_token(),
        'Content-Type: application/json',
    ], json_encode($payload));

    if ($codigo >= 300 || !is_array($r)) {
        throw new RuntimeException("Getnet crear pago falló (HTTP $codigo)");
    }
    return $r;
}

function getnet_obtener_pago(string $getnetId): array
{
    $c = getnet_cfg();

    // TODO(doc): path real de consulta de pago.
    [$codigo, $r] = getnet_http('GET', $c['api_base'] . '/TODO/path-consultar-pago/' . rawurlencode($getnetId), [
        'Authorization: Bearer ' . getnet_token(),
    ]);

    if ($codigo >= 300 || !is_array($r)) {
        throw new RuntimeException("Getnet consulta falló (HTTP $codigo)");
    }
    return $r;
}
