<?php
/**
 * getnet.php — cliente de Getnet Web Checkout (Argentina): token OAuth + "payment intent".
 *
 * Contrato tomado de la documentación oficial (docs.globalgetnet.com → Web Checkout):
 *   · Token:      POST {api_base}/authentication/oauth2/access_token   (form-urlencoded, dura 60 min)
 *   · Crear pago: POST {api_base}/payment-intent  → { payment_intent_id, redirect_url }   (monto en CENTAVOS)
 *   · Aviso:      Getnet hace POST a la URL de webhook que se carga en el Portal de Comercios,
 *                 con "Authorization: Basic usuario:contraseña". Trae order_id, payment_intent_id y
 *                 payment.result.status ("Authorized" | "Denied"; QR: "Approved" | "Denied").
 *   · Hosts:      homologación https://api.pre.globalgetnet.com · producción https://api.globalgetnet.com
 *
 * Lo que la documentación pública NO muestra y hay que confirmar con probar_pago_getnet.php:
 *   · el prefijo exacto del path de "payment-intent" (el Swagger solo dice /payment-intent),
 *   · si existe un endpoint para consultar el estado de un pago (consultar_pago_path; vacío = no se usa:
 *     el webhook autenticado alcanza, verificando referencia, monto y moneda).
 * Todo eso se corrige en el bloque 'api' de config/getnet.config.php, sin tocar código.
 *
 * La config vive en config/getnet.config.php (NO versionado, se sube a mano a Hostinger).
 * Plantilla: config/getnet.config.example.php
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

    $api = array_merge([
        'crear_pago_path'     => '/payment-intent',   // POST. Prefijo exacto: confirmar con probar_pago_getnet.php
        'consultar_pago_path' => '',                  // GET con {id}. Vacío = no hay consulta (la doc pública no la muestra)
        'monto'               => 'centavos',          // CONFIRMADO: entero, los 2 últimos dígitos son centavos
        'moneda'              => 'ARS',
        'expira'              => '1h',                // vence el checkout (mín. 15m): coincide con la limpieza de pedidos (60 min)
        // Campos de la RESPUESTA de "crear pago" (confirmados):
        'campo_pago_id'       => 'payment_intent_id',
        'campo_checkout_url'  => 'redirect_url',
        // Campos del AVISO (webhook) y de la consulta (confirmados en la doc del webhook):
        'campo_webhook_id'    => 'payment_intent_id',
        'campo_referencia'    => 'order_id',
        'campo_estado'        => 'payment.result.status',
        'campo_monto'         => 'payment.amount',
        'campo_moneda'        => 'payment.currency',
        // Estados (se comparan en minúsculas). Solo terminales: ni "pending" ni nada que permita reintentar.
        'estados_aprobado'    => ['authorized', 'approved'],   // tarjeta: Authorized · QR: Approved
        'estados_fallido'     => ['denied'],
    ], $base['api'] ?? []);
    $api['estados_aprobado'] = array_map('strtolower', $api['estados_aprobado']);
    $api['estados_fallido']  = array_map('strtolower', $api['estados_fallido']);

    $cfg = array_merge($base[$entorno], [
        'entorno'          => $entorno,
        'return_url'       => $base['return_url'],
        'webhook_token'    => $base['webhook_token'] ?? '',
        'webhook_usuario'  => $base['webhook_usuario'] ?? '',     // el mismo usuario/clave que cargás en el Portal de Comercios
        'webhook_password' => $base['webhook_password'] ?? '',
        'api'              => $api,
    ]);
    return $cfg;
}

/** Lee $arr['a']['b'] con la ruta 'a.b'. Devuelve null si no existe. */
function getnet_extraer($arr, ?string $ruta)
{
    if ($ruta === null || $ruta === '') return null;
    foreach (explode('.', $ruta) as $k) {
        if (!is_array($arr) || !array_key_exists($k, $arr)) return null;
        $arr = $arr[$k];
    }
    return $arr;
}

function getnet_path(string $clave): string
{
    $p = (string)(getnet_cfg()['api'][$clave] ?? '');
    if ($p === '' || stripos($p, 'TODO') !== false || stripos($p, 'COMPLETAR') !== false) {
        throw new RuntimeException("Getnet: falta completar api.$clave en config/getnet.config.php");
    }
    return $p;
}

/** ¿Hay endpoint para consultar el estado de un pago? (si no, se confía en el webhook autenticado) */
function getnet_consulta_disponible(): bool
{
    $p = (string)(getnet_cfg()['api']['consultar_pago_path'] ?? '');
    return $p !== '' && stripos($p, 'TODO') === false && stripos($p, 'COMPLETAR') === false;
}

function getnet_http(string $metodo, string $url, array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json', 'User-Agent: MundoVerde/1.0 (+https://vivemundoverde.com)'], $headers),
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

    $json = json_decode($resp, true);
    // Si la respuesta no es JSON (p. ej. un 403 de un firewall con HTML o vacío), se devuelve el texto
    // para poder diagnosticar en vez de perderlo.
    if ($json === null && $codigo >= 400) {
        $json = ['_respuesta_cruda' => $resp === '' ? '(cuerpo vacío)' : substr(strip_tags((string)$resp), 0, 400)];
    }
    return [$codigo, $json];
}

function getnet_token(): string
{
    $c = getnet_cfg();
    // El cache incluye un hash de la URL+client_id: si cambian las credenciales no se reutiliza un token viejo.
    $cache = sys_get_temp_dir() . '/mv_getnet_token_' . $c['entorno'] . '_' . substr(sha1($c['auth_url'] . $c['client_id']), 0, 10) . '.json';

    if (is_file($cache)) {
        $t = json_decode((string)file_get_contents($cache), true);
        if (is_array($t) && ($t['exp'] ?? 0) > time() + 60) return $t['token'];
    }

    // Confirmado (doc oficial y pruebas): el servidor exige application/x-www-form-urlencoded.
    [$codigo, $r] = getnet_http('POST', $c['auth_url'], ['Content-Type: application/x-www-form-urlencoded'],
        http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $c['client_id'],
            'client_secret' => $c['client_secret'],
        ]));

    if ($codigo !== 200 || empty($r['access_token'])) {
        // Se agrega lo que respondió Getnet (error / error_description): es lo que explica el 400/401.
        // Nunca incluye tus credenciales; son datos de la respuesta del servidor.
        $detalle = is_array($r) ? json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'sin cuerpo JSON';
        error_log("Getnet auth falló (HTTP $codigo): $detalle");
        throw new RuntimeException("Getnet auth falló (HTTP $codigo): $detalle");
    }

    // Si no se puede escribir el cache (permisos), simplemente se pide token cada vez.
    @file_put_contents($cache, json_encode([
        'token' => $r['access_token'],
        'exp'   => time() + (int)($r['expires_in'] ?? 3599),
    ]));
    return $r['access_token'];
}

/**
 * Arma el cuerpo del "payment intent" (ver doc: Quickstart → Step 3).
 * $pedido: ['id','referencia','total','email','nombre'].
 */
function getnet_armar_payment_intent(array $pedido): array
{
    $c = getnet_cfg();
    $api = $c['api'];

    $monto = $api['monto'] === 'decimal'
        ? round((float)$pedido['total'], 2)
        : (int)round($pedido['total'] * 100);

    $vuelta = $c['return_url'] . '?id=' . $pedido['id'] . '&ref=' . urlencode($pedido['referencia']);

    $intent = [
        'mode'     => 'instant',
        'order_id' => $pedido['referencia'],               // MV-DDMM-NNNN: vuelve en el webhook para conciliar
        'configurations' => [
            'success_url' => $vuelta,                      // pago_resultado.html confirma el estado real
            'error_url'   => $vuelta,
        ],
        'payment'  => ['currency' => $api['moneda'], 'amount' => $monto],
        'product'  => [[
            'product_type' => 'cash_carry',
            'title'        => 'Pedido ' . $pedido['referencia'] . ' - Vivero Mundo Verde',
            'value'        => $monto,
            'quantity'     => 1,
        ]],
        'customer' => array_filter([
            'name'  => $pedido['nombre'] ?? '',
            'email' => $pedido['email'],
        ], fn($v) => $v !== '' && $v !== null),
    ];
    if (!empty($api['expira'])) $intent['expires_at'] = $api['expira'];
    return $intent;
}

/** Crea el pago en Getnet. Devuelve la respuesta decodificada (con payment_intent_id y redirect_url). */
function getnet_crear_pago(array $pedido): array
{
    $c = getnet_cfg();

    [$codigo, $r] = getnet_http('POST', $c['api_base'] . getnet_path('crear_pago_path'), [
        'Authorization: Bearer ' . getnet_token(),
        'Content-Type: application/json',
    ], json_encode(getnet_armar_payment_intent($pedido)));

    if ($codigo >= 300 || !is_array($r)) {
        $detalle = is_array($r) ? substr(json_encode($r, JSON_UNESCAPED_UNICODE), 0, 400) : '';
        throw new RuntimeException("Getnet crear pago falló (HTTP $codigo) $detalle");
    }
    return $r;
}

function getnet_obtener_pago(string $getnetId): array
{
    $c = getnet_cfg();

    $path = str_replace('{id}', rawurlencode($getnetId), getnet_path('consultar_pago_path'));
    [$codigo, $r] = getnet_http('GET', $c['api_base'] . $path, [
        'Authorization: Bearer ' . getnet_token(),
    ]);

    if ($codigo >= 300 || !is_array($r)) {
        throw new RuntimeException("Getnet consulta falló (HTTP $codigo)");
    }
    return $r;
}

/**
 * Normaliza un aviso (webhook) o una consulta de Getnet:
 *   ['estado' => 'authorized' (minúsculas), 'monto' => float|null (pesos), 'referencia' => string|null,
 *    'moneda' => string|null, 'pago_id' => string|null]
 */
function getnet_normalizar(array $datos): array
{
    $api = getnet_cfg()['api'];

    $monto = getnet_extraer($datos, $api['campo_monto']);
    if ($monto !== null && is_numeric($monto)) {
        $monto = $api['monto'] === 'decimal' ? (float)$monto : ((float)$monto) / 100;
    } else {
        $monto = null;
    }
    $ref    = getnet_extraer($datos, $api['campo_referencia']);
    $moneda = getnet_extraer($datos, $api['campo_moneda']);
    $id     = getnet_extraer($datos, $api['campo_webhook_id']);

    return [
        'estado'     => strtolower((string)getnet_extraer($datos, $api['campo_estado'])),
        'monto'      => $monto,
        'referencia' => $ref !== null ? (string)$ref : null,
        'moneda'     => $moneda !== null ? strtoupper((string)$moneda) : null,
        'pago_id'    => $id !== null ? (string)$id : null,
    ];
}

/** Consulta un pago en Getnet (si hay endpoint de consulta) y lo devuelve normalizado. */
function getnet_pago_normalizado(string $getnetId): array
{
    return getnet_normalizar(getnet_obtener_pago($getnetId));
}
