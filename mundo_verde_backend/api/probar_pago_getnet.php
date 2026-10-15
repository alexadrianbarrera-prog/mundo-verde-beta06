<?php
/**
 * probar_pago_getnet.php — SOLO PARA TU PC. Pega contra el sandbox REAL de Getnet (Web Checkout) y muestra
 * qué responde, para confirmar lo que la documentación pública no deja claro:
 *   1) que las credenciales autentican,
 *   2) el path exacto de "payment-intent" (prueba la configurada y variantes con prefijo),
 *   3) qué campos devuelve (payment_intent_id / redirect_url),
 *   4) opcional: si existe un endpoint para consultar un pago.
 * No toca tu base de datos y no cobra nada (es un pago de prueba de $100 en sandbox).
 * BORRAR antes de subir a producción (se niega a correr fuera de localhost / consola).
 *
 *   php api/probar_pago_getnet.php                 → token + crea un pago de prueba (prueba paths hasta dar con el correcto)
 *   php api/probar_pago_getnet.php consultar <id>  → prueba paths de consulta para ese payment_intent_id
 * o desde el navegador: http://localhost/mundo_verde_backend/api/probar_pago_getnet.php
 */
$cli = PHP_SAPI === 'cli';
if (!$cli && !in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'])) { http_response_code(404); exit; }
if (!$cli) header('Content-Type: text/plain; charset=utf-8');
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'localhost';
if ($cli) $_SERVER['SERVER_NAME'] = 'localhost';

require_once __DIR__ . '/getnet.php';

function linea($t = '') { echo $t . "\n"; }
function recorte($r, $max = 700) { $s = json_encode($r, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); return strlen($s) > $max ? substr($s, 0, $max) . '…' : $s; }

try {
    $c = getnet_cfg();
    linea("Entorno: {$c['entorno']}   api_base: {$c['api_base']}");
    linea();

    try { getnet_token(); linea("✔ 1) Token OK: las credenciales autentican."); }
    catch (Throwable $e) {
        linea("✘ 1) No se pudo autenticar: " . $e->getMessage());
        linea("   Revisá client_id / client_secret (sin espacios) y que auth_url sea https://api.pre.globalgetnet.com/authentication/oauth2/access_token");
        exit;
    }
    linea();

    $accion = $cli ? ($GLOBALS['argv'][1] ?? '') : ($_GET['accion'] ?? '');
    $idArg  = $cli ? ($GLOBALS['argv'][2] ?? '') : ($_GET['id'] ?? '');
    $auth   = ['Authorization: Bearer ' . getnet_token()];

    if ($accion === 'consultar' && $idArg !== '') {
        linea("2) Probando paths de consulta para $idArg (un 404 = no existe; 200 = encontrado; 401/403 = existe pero sin permiso):");
        foreach (['/payment-intent/{id}', '/digital-checkout/v1/payment-intent/{id}', '/web-checkout/v1/payment-intent/{id}', '/dpy/web-checkout/v1/payment-intent/{id}'] as $p) {
            [$cod, $r] = getnet_http('GET', $c['api_base'] . str_replace('{id}', rawurlencode($idArg), $p), $auth);
            linea(sprintf("   %-52s HTTP %d", $p, $cod));
            if ($cod === 200) { linea(recorte($r)); linea("\n✔ Poné consultar_pago_path => '$p' en el bloque 'api' de tu config."); exit; }
        }
        linea("\nNinguna dio 200: la API pública no expone consulta; el aviso (webhook) alcanza. Dejá consultar_pago_path vacío.");
        exit;
    }

    $ref = 'MV-' . date('dm') . '-' . random_int(1000, 9999);
    $intent = getnet_armar_payment_intent(['id' => 0, 'referencia' => $ref, 'total' => 100.00, 'email' => 'prueba@example.com', 'nombre' => 'Prueba Mundo Verde']);
    linea("2) Cuerpo que se envía:\n" . recorte($intent, 1200));
    linea();

    $configurado = (string)($c['api']['crear_pago_path'] ?? '');
    $candidatos = array_values(array_unique(array_filter([$configurado, '/payment-intent', '/digital-checkout/v1/payment-intent', '/web-checkout/v1/payment-intent', '/dpy/web-checkout/v1/payment-intent'],
        fn($p) => $p !== '' && stripos($p, 'COMPLETAR') === false && stripos($p, 'TODO') === false)));

    linea("   Probando paths (404 = no existe; 400/422 = existe pero rechazó el cuerpo; 401/403 = credenciales/permisos; 2xx = creado):");
    $hallado = null;
    foreach ($candidatos as $p) {
        [$cod, $r] = getnet_http('POST', $c['api_base'] . $p, array_merge($auth, ['Content-Type: application/json']), json_encode($intent));
        linea(sprintf("   %-48s HTTP %d", $p, $cod));
        if ($cod >= 200 && $cod < 300) { $hallado = [$p, $r]; break; }
        if ($cod !== 404) linea("      → respuesta: " . recorte($r, 500));
    }
    linea();

    if (!$hallado) {
        linea("✘ Ningún path creó el pago. Si alguno respondió 400/422, ese es el path correcto y el mensaje de arriba dice qué campo falta");
        linea("  (por ejemplo customer.document_type / document_number): pasame ese mensaje. Si todos dieron 404/401, escribile a");
        linea("  consultasecommerce@getnet.com.ar pidiendo la URL base y el path de 'payment-intent' para Web Checkout Argentina.");
        exit;
    }

    [$path, $r] = $hallado;
    linea("✔ 3) Pago creado en $path\n" . recorte($r));
    $id  = getnet_extraer($r, $c['api']['campo_pago_id']);
    $url = getnet_extraer($r, $c['api']['campo_checkout_url']);
    linea();
    linea("   campo_pago_id ('{$c['api']['campo_pago_id']}')           = " . json_encode($id));
    linea("   campo_checkout_url ('{$c['api']['campo_checkout_url']}') = " . json_encode($url));
    linea();
    if ($path !== $configurado) linea("→ Poné crear_pago_path => '$path' en el bloque 'api' de tu config/getnet.config.php.");
    if ($id && $url) {
        linea("→ Abrí esta URL, pagá con una tarjeta de prueba de Getnet y comprobá que tu webhook reciba el aviso:\n   $url");
        linea("→ Opcional: php api/probar_pago_getnet.php consultar $id   (para ver si Getnet ofrece consulta de estado)");
    } else {
        linea("✘ La respuesta no trae los campos esperados: corregí campo_pago_id / campo_checkout_url con los nombres reales de arriba.");
    }
} catch (Throwable $e) {
    linea("✘ " . $e->getMessage());
}
