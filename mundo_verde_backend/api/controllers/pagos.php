<?php
/**
 * Pagos con tarjeta vía Getnet.
 *
 * Rutas (registradas en api/index.php):
 *   POST /pagos/tarjeta   → pagos_crear()    crea el pago y devuelve la URL de Getnet
 *   POST /pagos/webhook   → pagos_webhook()  notificación de Getnet (PÚBLICO, sin login)
 *   GET  /pagos/estado    → pagos_estado()   lo usa pago_resultado.html (?id=..&ref=MV-..)
 *
 * Este archivo REEMPLAZA al esqueleto anterior de Mercado Pago (que era un
 * script suelto, no funciones, y no estaba conectado al router).
 *
 * Flujo: el front primero crea el pedido con POST /pedidos (forma_pago='tarjeta',
 * queda 'pendiente'), después llama a /pagos/tarjeta con el id del pedido.
 * El total SIEMPRE sale de la base, nunca del navegador.
 */

require_once __DIR__ . '/../getnet.php';

// TODO(doc): valores reales de estado que devuelve Getnet al consultar un pago.
const GETNET_ESTADOS_APROBADO = ['approved', 'paid'];
// Solo estados TERMINALES. Un "rechazado" puede no serlo si Getnet deja reintentar
// en el mismo checkout: confirmalo antes de agregarlo acá.
const GETNET_ESTADOS_FALLIDO  = ['cancelled', 'expired', 'failed'];

function pagos_crear(PDO $pdo): void
{
    $usuario = usuarioAutenticado($pdo, false); // el checkout admite invitados
    $body    = leerBody();

    $pedidoId = (int)($body['pedido_id'] ?? 0);
    if ($pedidoId <= 0) error('Pedido inválido.');

    $stmt = $pdo->prepare(
        'SELECT p.id, p.usuario_id, p.forma_pago, p.estado, p.total, p.referencia_pago,
                COALESCE(p.cliente_email, u.email) AS email
         FROM pedidos p
         LEFT JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = :id'
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

    try {
        $r = getnet_crear_pago([
            'id'         => (int)$p['id'],
            'referencia' => $p['referencia_pago'],
            'total'      => (float)$p['total'],
            'email'      => $p['email'] ?? '',
        ]);
    } catch (Throwable $e) {
        error_log('[pagos_crear] ' . $e->getMessage());
        error('No se pudo iniciar el pago. Intentá de nuevo en unos minutos.', 502);
    }

    // TODO(doc): nombres reales de los campos de la respuesta.
    $getnetId = $r['id'] ?? null;
    $url      = $r['checkout_url'] ?? null;
    if (!$getnetId || !$url) {
        error_log('[pagos_crear] respuesta inesperada de Getnet: ' . json_encode($r));
        error('No se pudo iniciar el pago. Intentá de nuevo en unos minutos.', 502);
    }

    $pdo->prepare('UPDATE pedidos SET getnet_id = :g WHERE id = :id')
        ->execute(['g' => (string)$getnetId, 'id' => $pedidoId]);

    responder(['url' => $url]);
}

/**
 * Getnet avisa acá. NO se confía en el body: solo sirve para saber qué pago
 * consultar, y el estado real se pide a Getnet. Idempotente (Getnet puede
 * reenviar la notificación).
 */
function pagos_webhook(PDO $pdo): void
{
    // Barrera simple contra requests ajenas (la URL con ?k= se la damos a Getnet al crear el pago).
    $esperado = getnet_cfg()['webhook_token'] ?? '';
    if ($esperado === '' || !hash_equals($esperado, (string)($_GET['k'] ?? ''))) {
        error('No autorizado.', 401);
    }

    $n = leerBody();
    $getnetId = (string)($n['id'] ?? ''); // TODO(doc): campo real del webhook
    if ($getnetId === '') error('Notificación inválida.', 400);

    $stmt = $pdo->prepare('SELECT id, estado FROM pedidos WHERE getnet_id = :g');
    $stmt->execute(['g' => $getnetId]);
    $pedido = $stmt->fetch();
    if (!$pedido) responder(['ok' => true]); // no es nuestro / ya no existe: 200 para que no reintente

    try {
        $pago = getnet_obtener_pago($getnetId);
    } catch (Throwable $e) {
        error_log('[pagos_webhook] ' . $e->getMessage());
        error('No se pudo verificar el pago.', 502); // Getnet reintenta
    }
    $estadoGetnet = strtolower((string)($pago['status'] ?? '')); // TODO(doc): campo real

    $mandarRemito = false;

    if (in_array($estadoGetnet, GETNET_ESTADOS_APROBADO, true)) {
        $up = $pdo->prepare("UPDATE pedidos SET estado = 'pagado' WHERE id = :id AND estado = 'pendiente'");
        $up->execute(['id' => $pedido['id']]);
        $mandarRemito = $up->rowCount() === 1; // solo la primera vez

    } elseif (in_array($estadoGetnet, GETNET_ESTADOS_FALLIDO, true)) {
        $pdo->beginTransaction();
        try {
            $up = $pdo->prepare("UPDATE pedidos SET estado = 'cancelado' WHERE id = :id AND estado = 'pendiente'");
            $up->execute(['id' => $pedido['id']]);
            if ($up->rowCount() === 1) {
                // El Código Verde se consume al crear el pedido: si el pago falla, se libera.
                $pdo->prepare(
                    'UPDATE newsletter_suscriptores
                     SET codigo_usado_en = NULL, codigo_usado_pedido_id = NULL
                     WHERE codigo_usado_pedido_id = :id'
                )->execute(['id' => $pedido['id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            error_log('[pagos_webhook] ' . $e->getMessage());
            error('No se pudo actualizar el pedido.', 500);
        }
    }

    // Se responde 200 ya mismo y el mail sale después, para no demorar a Getnet.
    responderYContinuar(['ok' => true]);

    if ($mandarRemito) {
        try {
            pagos_enviar_remito($pdo, (int)$pedido['id']);
        } catch (Throwable $e) {
            error_log('[pagos_webhook][remito] ' . $e->getMessage());
        }
    }
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
        'SELECT p.*, COALESCE(p.cliente_email, u.email) AS email_final
         FROM pedidos p LEFT JOIN usuarios u ON u.id = p.usuario_id
         WHERE p.id = :id'
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
