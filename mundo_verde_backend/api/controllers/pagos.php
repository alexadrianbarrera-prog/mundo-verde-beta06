<?php
/**
 * Pagos con tarjeta vía Getnet.
 *
 * Rutas (registradas en api/index.php):
 *   POST /pagos/tarjeta   → pagos_crear()    crea el pago y devuelve la URL de Getnet
 *   POST /pagos/webhook   → pagos_webhook()  aviso de Getnet (PÚBLICO: se protege con Basic + ?k=)
 *   GET  /pagos/estado    → pagos_estado()   lo usa pago_resultado.html (?id=..&ref=MV-..)
 *
 * Flujo: el front primero crea el pedido con POST /pedidos (forma_pago='tarjeta',
 * queda 'pendiente'), después llama a /pagos/tarjeta con el id del pedido.
 * El total SIEMPRE sale de la base, nunca del navegador.
 *
 * Pedidos de tarjeta abandonados: si el cliente cierra Getnet o vuelve atrás, el
 * pedido queda 'pendiente' reteniendo su Código Verde. Se resuelve en tres capas
 * (ver pagos_cerrar_pendiente):
 *   1) Al pedir/validar ese Código Verde, el pedido retenedor se cierra en el acto.
 *   2) Cada pedido nuevo cancela de paso los de tarjeta pendientes de más de 60 min.
 *   3) Opcional: api/cron_pagos.php hace lo mismo desde un cron.
 * Antes de cancelar se le pregunta a Getnet (si hay endpoint de consulta), y un pago aprobado que
 * llegue tarde sobre un pedido cancelado lo reactiva como 'pagado' (la plata
 * entró, gana el pago).
 */

require_once __DIR__ . '/../getnet.php';

const PAGOS_MINUTOS_VENCIMIENTO = 60;

function pagos_crear(PDO $pdo): void
{
    $usuario = usuarioAutenticado($pdo, false); // el checkout admite invitados
    $body    = leerBody();

    $pedidoId = (int)($body['pedido_id'] ?? 0);
    if ($pedidoId <= 0) error('Pedido inválido.');

    $stmt = $pdo->prepare(
        "SELECT p.id, p.usuario_id, p.forma_pago, p.estado, p.total, p.referencia_pago, p.cliente_nombre,
                COALESCE(NULLIF(TRIM(p.cliente_email), ''), u.email) AS email
         FROM pedidos p
         LEFT JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = :id"
    );
    $stmt->execute(['id' => $pedidoId]);
    $p = $stmt->fetch();
    if (!$p) error('No se encontró el pedido.', 404);

    // Misma regla que pedidos_subir_comprobante(): pedidos de usuarios logueados
    // solo los paga el dueño o un admin; los de invitados quedan abiertos.
    if ($p['usuario_id'] !== null) {
        $esDueno = $usuario && (int)$usuario['id'] === (int)$p['usuario_id'];
        $esAdmin = $usuario && $usuario['rol'] === 'admin';
        if (!$esDueno && !$esAdmin) error('No podés pagar este pedido.', 403);
    }

    if ($p['forma_pago'] !== 'tarjeta')  error('Este pedido no es de pago con tarjeta.', 409);
    if ($p['estado'] !== 'pendiente')    error('Este pedido ya no admite pago.', 409);
    if ((float)$p['total'] <= 0)         error('El total del pedido es inválido.', 409);
    if (empty($p['referencia_pago']))    error('El pedido no tiene número de referencia.', 409);
    if (!validarEmail($p['email'] ?? '')) error('El pedido no tiene un mail válido para el comprobante.', 409);

    try {
        $r = getnet_crear_pago([
            'id'         => (int)$p['id'],
            'referencia' => $p['referencia_pago'],
            'total'      => (float)$p['total'],
            'email'      => $p['email'],
            'nombre'     => trim((string)($p['cliente_nombre'] ?? '')),
        ]);
    } catch (Throwable $e) {
        error_log('[pagos_crear] ' . $e->getMessage());
        error('No se pudo iniciar el pago. Intentá de nuevo en unos minutos.', 502);
    }

    $api      = getnet_cfg()['api'];
    $getnetId = getnet_extraer($r, $api['campo_pago_id']);
    $url      = getnet_extraer($r, $api['campo_checkout_url']);
    if (!$getnetId || !$url) {
        error_log('[pagos_crear] respuesta inesperada de Getnet: ' . json_encode($r));
        error('No se pudo iniciar el pago. Intentá de nuevo en unos minutos.', 502);
    }

    $pdo->prepare('UPDATE pedidos SET getnet_id = :g WHERE id = :id')
        ->execute(['g' => (string)$getnetId, 'id' => $pedidoId]);

    responder(['url' => $url]);
}

/** Usuario y clave del encabezado "Authorization: Basic ..." (Getnet los manda con cada aviso). */
function pagos_leer_basic(): array
{
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('apache_request_headers')) {
        $todos = array_change_key_case(apache_request_headers(), CASE_LOWER);
        $h = $todos['authorization'] ?? '';
    }
    if (stripos($h, 'Basic ') === 0) {
        $d = base64_decode(substr($h, 6), true);
        if ($d !== false && strpos($d, ':') !== false) return explode(':', $d, 2);
    }
    if (isset($_SERVER['PHP_AUTH_USER'])) return [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? ''];
    return [null, null];
}

/**
 * Barreras del webhook. Se exigen TODAS las que estén configuradas:
 *   · Basic (webhook_usuario / webhook_password): lo que Getnet manda según la doc.
 *   · ?k=<webhook_token> en la URL cargada en el Portal de Comercios (capa extra).
 * Sin ninguna configurada se rechaza todo.
 */
function pagos_webhook_autorizado(): bool
{
    $c = getnet_cfg();
    $token = (string)($c['webhook_token'] ?? '');
    $user  = (string)($c['webhook_usuario'] ?? '');
    $pass  = (string)($c['webhook_password'] ?? '');

    if ($token === '' && $user === '') return false;
    if ($token !== '' && !hash_equals($token, (string)($_GET['k'] ?? ''))) return false;
    if ($user !== '') {
        [$u, $p] = pagos_leer_basic();
        if ($u === null || !hash_equals($user, (string)$u) || !hash_equals($pass, (string)$p)) return false;
    }
    return true;
}

/**
 * Getnet avisa acá cuando un pago se autoriza o se rechaza (URL cargada en el Portal de Comercios).
 * Idempotente: Getnet reintenta hasta 72 h si no recibe un 200.
 *
 * Seguridad: el aviso viene autenticado (Basic + ?k=). Además se verifica que referencia (order_id),
 * monto y moneda coincidan con el pedido guardado. Si existe endpoint de consulta (consultar_pago_path),
 * el estado se confirma contra Getnet en lugar de creerle al cuerpo del aviso.
 */
function pagos_webhook(PDO $pdo): void
{
    if (!pagos_webhook_autorizado()) error('No autorizado.', 401);

    $aviso = getnet_normalizar(leerBody());
    if ($aviso['pago_id'] === null && $aviso['referencia'] === null) error('Notificación inválida.', 400);

    $pedido = false;
    if ($aviso['pago_id'] !== null) {
        $stmt = $pdo->prepare('SELECT id, estado, total, referencia_pago FROM pedidos WHERE getnet_id = :g');
        $stmt->execute(['g' => $aviso['pago_id']]);
        $pedido = $stmt->fetch();
    }
    if (!$pedido && $aviso['referencia'] !== null) {   // por si el id no llegó a guardarse
        $stmt = $pdo->prepare("SELECT id, estado, total, referencia_pago FROM pedidos WHERE referencia_pago = :r AND forma_pago = 'tarjeta'");
        $stmt->execute(['r' => strtoupper($aviso['referencia'])]);
        $pedido = $stmt->fetch();
    }
    if (!$pedido) responder(['ok' => true]); // no es nuestro / ya no existe: 200 para que no reintente

    if (getnet_consulta_disponible() && $aviso['pago_id'] !== null) {
        try {
            $real = getnet_pago_normalizado($aviso['pago_id']);
        } catch (Throwable $e) {
            error_log('[pagos_webhook] ' . $e->getMessage());
            error('No se pudo verificar el pago.', 502); // Getnet reintenta
        }
        foreach ($real as $k => $v) if ($v !== null && $v !== '') $aviso[$k] = $v;
    }

    // Referencia, monto y moneda tienen que coincidir con el pedido. Si no, NO se toca (y se avisa en el log).
    if ($aviso['referencia'] !== null && strtoupper($aviso['referencia']) !== strtoupper((string)$pedido['referencia_pago'])) {
        error_log("[pagos_webhook] REFERENCIA distinta en pedido {$pedido['id']}: Getnet={$aviso['referencia']} base={$pedido['referencia_pago']}");
        responder(['ok' => true]);
    }
    if ($aviso['monto'] !== null && abs($aviso['monto'] - (float)$pedido['total']) > 0.009) {
        error_log("[pagos_webhook] MONTO distinto en pedido {$pedido['id']}: Getnet={$aviso['monto']} base={$pedido['total']}");
        responder(['ok' => true]);
    }
    $moneda = getnet_cfg()['api']['moneda'];
    if ($aviso['moneda'] !== null && $aviso['moneda'] !== strtoupper($moneda)) {
        error_log("[pagos_webhook] MONEDA distinta en pedido {$pedido['id']}: Getnet={$aviso['moneda']} esperada=$moneda");
        responder(['ok' => true]);
    }

    // Para APROBAR un pedido solo con el aviso (sin consulta a Getnet), el aviso tiene que traer referencia y monto.
    if (in_array($aviso['estado'], getnet_cfg()['api']['estados_aprobado'], true)
        && ($aviso['referencia'] === null || $aviso['monto'] === null)) {
        error_log("[pagos_webhook] aviso aprobado SIN referencia o monto en pedido {$pedido['id']}: revisar api.campo_referencia / api.campo_monto");
        error('Notificación incompleta.', 400);
    }

    try {
        $r = pagos_aplicar_resultado($pdo, (int)$pedido['id'], $aviso['estado']);
    } catch (Throwable $e) {
        error_log('[pagos_webhook] ' . $e->getMessage());
        error('No se pudo actualizar el pedido.', 500);
    }

    // Se responde 200 ya mismo y el mail sale después, para no demorar a Getnet.
    responderYContinuar(['ok' => true]);

    if ($r['remito']) {
        try {
            pagos_enviar_remito($pdo, (int)$pedido['id']);
        } catch (Throwable $e) {
            error_log('[pagos_webhook][remito] ' . $e->getMessage());
        }
    }
}

/**
 * Aplica el estado que informó Getnet a un pedido. Idempotente.
 *   aprobado → 'pagado'  (desde 'pendiente'; o desde 'cancelado': pago tardío, gana el pago)
 *   fallido  → 'cancelado' (solo desde 'pendiente') y libera el Código Verde
 * Devuelve ['estado' => 'pagado'|'cancelado'|null (sin cambios), 'remito' => bool].
 */
function pagos_aplicar_resultado(PDO $pdo, int $pedidoId, string $estadoGetnet): array
{
    $api = getnet_cfg()['api'];
    $estadoGetnet = strtolower($estadoGetnet);

    if (in_array($estadoGetnet, $api['estados_aprobado'], true)) {
        $up = $pdo->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id AND estado = 'pendiente'");
        $up->execute(['id' => $pedidoId]);
        if ($up->rowCount() === 1) return ['estado' => 'pagado', 'remito' => true];

        $tarde = $pdo->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id AND estado = 'cancelado'");
        $tarde->execute(['id' => $pedidoId]);
        if ($tarde->rowCount() === 1) {
            error_log("[pagos] Pago aprobado sobre pedido cancelado #$pedidoId: se reactiva como pagado.");
            return ['estado' => 'pagado', 'remito' => true];
        }
        return ['estado' => null, 'remito' => false];
    }

    if (in_array($estadoGetnet, $api['estados_fallido'], true)) {
        return ['estado' => pagos_cancelar_pendiente($pdo, $pedidoId) ? 'cancelado' : null, 'remito' => false];
    }

    return ['estado' => null, 'remito' => false]; // todavía en curso
}

/** Cancela un pedido 'pendiente' y libera su Código Verde, todo en una transacción. true si lo canceló. */
function pagos_cancelar_pendiente(PDO $pdo, int $pedidoId): bool
{
    $pdo->beginTransaction();
    try {
        $up = $pdo->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = :id AND estado = 'pendiente'");
        $up->execute(['id' => $pedidoId]);
        $cancelado = $up->rowCount() === 1;
        if ($cancelado) {
            // El Código Verde se consume al crear el pedido: si el pago falla, se libera.
            $pdo->prepare(
                'UPDATE newsletter_suscriptores
                 SET codigo_usado_en = NULL, codigo_usado_pedido_id = NULL
                 WHERE codigo_usado_pedido_id = :id'
            )->execute(['id' => $pedidoId]);
        }
        $pdo->commit();
        return $cancelado;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Cierra un pedido de tarjeta pendiente que ya no se va a pagar (abandonado o reemplazado).
 * Antes le pregunta a Getnet: si el pago en realidad está aprobado, lo marca 'pagado' (y
 * manda el remito) y devuelve false. Si no se pudo consultar a Getnet, se cancela igual:
 * un pago aprobado que llegue después reactiva el pedido (ver pagos_aplicar_resultado).
 * true = quedó cancelado y su Código Verde liberado.
 */
function pagos_cerrar_pendiente(PDO $pdo, array $pedido): bool
{
    if (!empty($pedido['getnet_id']) && getnet_consulta_disponible()) {
        try {
            $pago = getnet_pago_normalizado((string)$pedido['getnet_id']);
            $r = pagos_aplicar_resultado($pdo, (int)$pedido['id'], $pago['estado']);
            if ($r['estado'] === 'pagado') {
                if ($r['remito']) {
                    try { pagos_enviar_remito($pdo, (int)$pedido['id']); }
                    catch (Throwable $e) { error_log('[pagos_cerrar_pendiente][remito] ' . $e->getMessage()); }
                }
                return false;
            }
            if ($r['estado'] === 'cancelado') return true; // Getnet ya lo daba por fallido
        } catch (Throwable $e) {
            error_log('[pagos_cerrar_pendiente] no se pudo consultar a Getnet: ' . $e->getMessage());
        }
    }
    return pagos_cancelar_pendiente($pdo, (int)$pedido['id']);
}

/**
 * Si el Código Verde de este suscriptor está retenido por un pedido de tarjeta
 * todavía pendiente (el cliente abandonó Getnet), cierra ese pedido y libera el código.
 * Devuelve true si el código quedó libre.
 */
function pagos_liberar_codigo_retenido(PDO $pdo, int $suscriptorId): bool
{
    $stmt = $pdo->prepare(
        'SELECT p.id, p.estado, p.forma_pago, p.getnet_id
         FROM newsletter_suscriptores s
         JOIN pedidos p ON p.id = s.codigo_usado_pedido_id
         WHERE s.id = :id AND s.codigo_usado_en IS NOT NULL'
    );
    $stmt->execute(['id' => $suscriptorId]);
    $p = $stmt->fetch();
    if (!$p || $p['forma_pago'] !== 'tarjeta' || $p['estado'] !== 'pendiente') return false;

    return pagos_cerrar_pendiente($pdo, $p);
}

/** Cancela (con las mismas reglas) los pedidos de tarjeta pendientes de más de $minutos. Devuelve cuántos cerró. */
function pagos_cancelar_vencidos(PDO $pdo, int $minutos = PAGOS_MINUTOS_VENCIMIENTO, int $max = 20): int
{
    $minutos = max(1, $minutos);
    $max     = max(1, $max);
    $filas = $pdo->query(
        "SELECT id, estado, forma_pago, getnet_id FROM pedidos
         WHERE forma_pago = 'tarjeta' AND estado = 'pendiente'
           AND creado_en < (NOW() - INTERVAL $minutos MINUTE)
         ORDER BY id LIMIT $max"
    )->fetchAll();

    $n = 0;
    foreach ($filas as $p) {
        try {
            if (pagos_cerrar_pendiente($pdo, $p)) $n++;
        } catch (Throwable $e) {
            error_log('[pagos_cancelar_vencidos] pedido ' . $p['id'] . ': ' . $e->getMessage());
        }
    }
    return $n;
}

/** GET /pagos/estado?id=12&ref=MV-1012-0003 → solo el estado (id + ref juntos, no se puede enumerar). */
function pagos_estado(PDO $pdo): void
{
    $id  = (int)($_GET['id'] ?? 0);
    $ref = strtoupper(trim((string)($_GET['ref'] ?? '')));
    if ($id <= 0 || $ref === '') error('Faltan datos.');

    $stmt = $pdo->prepare('SELECT estado FROM pedidos WHERE id = :id AND referencia_pago = :ref');
    $stmt->execute(['id' => $id, 'ref' => $ref]);
    $p = $stmt->fetch();
    if (!$p) error('No se encontró el pedido.', 404);

    responder(['estado' => $p['estado']]);
}

/**
 * Remito por mail para pedidos con tarjeta: se manda recién cuando el pago
 * se confirma (en pedidos_crear() se omite para forma_pago='tarjeta').
 * Reconstruye el mismo array que arma pedidos_crear() para enviarMailPedido().
 */
function pagos_enviar_remito(PDO $pdo, int $pedidoId): void
{
    $stmt = $pdo->prepare(
        "SELECT p.*, COALESCE(NULLIF(TRIM(p.cliente_email), ''), u.email) AS email_final
         FROM pedidos p LEFT JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = :id"
    );
    $stmt->execute(['id' => $pedidoId]);
    $p = $stmt->fetch();
    if (!$p) return;

    $stmtItems = $pdo->prepare(
        'SELECT producto_id, codigo, nombre, precio_unitario AS precio, cantidad, subtotal
         FROM pedido_items WHERE pedido_id = :id'
    );
    $stmtItems->execute(['id' => $pedidoId]);
    $items = $stmtItems->fetchAll();

    $inaug = (float)$p['descuento_inauguracion'];
    $news  = (float)$p['descuento_newsletter'];
    $efec  = max(0.0, (float)$p['descuento'] - $inaug - $news);

    $fecha = (new DateTime($p['creado_en'], new DateTimeZone('America/Argentina/Buenos_Aires')))->format('d/m/Y H:i');

    require_once __DIR__ . '/mailer.php';
    enviarMailPedido([
        'id'              => $pedidoId,
        'referencia'      => $p['referencia_pago'],
        'fecha'           => $fecha,
        'email'           => $p['email_final'] ?? '',
        'nombre'          => $p['cliente_nombre'] ?? '',
        'forma_entrega'   => $p['forma_entrega'],
        'forma_pago'      => $p['forma_pago'],
        'retiro_nombre'   => $p['retiro_nombre'] ?? '',
        'retiro_dni'      => $p['retiro_dni'] ?? '',
        'envio_domicilio' => $p['envio_domicilio'] ?? '',
        'envio_localidad' => $p['envio_localidad'] ?? '',
        'envio_cp'        => $p['envio_cp'] ?? '',
        'items'           => $items,
        'subtotal'        => (float)$p['subtotal'],
        'descuentos'      => [
            ['Descuento efectivo + retiro', $efec],
            ['Descuento de inauguración',   $inaug],
            ['Código Verde (newsletter)',   $news],
        ],
        'total'           => (float)$p['total'],
    ]);
}
