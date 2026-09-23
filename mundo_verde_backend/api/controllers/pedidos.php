<?php
/**
 * Pedidos generados desde el checkout del carrito.
 * El precio de cada ítem SIEMPRE se toma de la base de datos (nunca del
 * precio que mande el front), para que nadie pueda manipular el total
 * editando el JS del navegador.
 */

const UMBRAL_DESCUENTO_EFECTIVO = 30000;
const DESCUENTO_EFECTIVO = 0.10;

// Comprobante de pago: formatos aceptados (extensión -> mime real esperado)
// y peso máximo permitido.
const EXTENSIONES_COMPROBANTE = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'webp' => 'image/webp',
    'pdf'  => 'application/pdf',
];
const TAMANO_MAX_COMPROBANTE = 5 * 1024 * 1024; // 5 MB

function pedidos_crear(PDO $pdo): void
{
    $usuario = usuarioAutenticado($pdo, false); // el checkout admite invitados
    $body = leerBody();

    $items = $body['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        error('El carrito está vacío.');
    }

    $formaEntrega = $body['forma_entrega'] ?? 'retiro';
    $formaPago    = $body['forma_pago'] ?? 'efectivo';
    if (!in_array($formaEntrega, ['retiro', 'envio'], true)) error('Forma de entrega inválida.');
    if (!in_array($formaPago, ['efectivo', 'transferencia', 'tarjeta'], true)) error('Forma de pago inválida.');

    $cliente = $body['cliente'] ?? [];
    $envio   = $body['envio'] ?? [];
    $retiro  = $body['retiro'] ?? [];

    if ($formaEntrega === 'envio') {
        foreach (['domicilio', 'localidad', 'cp'] as $campo) {
            if (empty($envio[$campo])) error("Falta el campo de envío: $campo.");
        }
    }

    // Igual que con el envío: si retira por local, siempre hace falta saber
    // quién retira (nombre y DNI), sin importar si el comprador está
    // logueado o es invitado — puede mandar a otra persona a buscar la planta.
    if ($formaEntrega === 'retiro') {
        $retiroNombre = trim($retiro['nombre'] ?? '');
        $retiroDni    = trim($retiro['dni'] ?? '');

        if ($retiroNombre === '') error('Falta el nombre de quien retira.');
        if ($retiroDni === '' || !preg_match('/^\d{7,8}$/', $retiroDni)) {
            error('El DNI de quien retira debe tener 7 u 8 dígitos.');
        }
    }

    // Resolvemos cada ítem contra la base (precio real + nombre real).
    $itemsResueltos = [];
    $subtotal = 0.0;
    foreach ($items as $item) {
        $codigo   = trim($item['codigo'] ?? '');
        $cantidad = (int)($item['cantidad'] ?? 0);
        if ($codigo === '' || $cantidad <= 0) error('Ítem de pedido inválido.');

        $stmt = $pdo->prepare('SELECT id, nombre, precio FROM productos WHERE codigo = :codigo AND activo = 1');
        $stmt->execute(['codigo' => $codigo]);
        $producto = $stmt->fetch();
        if (!$producto) error("El producto \"$codigo\" ya no está disponible.", 400);

        $precioUnit = (float)$producto['precio'];
        $sub = $precioUnit * $cantidad;
        $subtotal += $sub;

        $itemsResueltos[] = [
            'producto_id' => $producto['id'],
            'codigo'      => $codigo,
            'nombre'      => $producto['nombre'],
            'precio'      => $precioUnit,
            'cantidad'    => $cantidad,
            'subtotal'    => $sub,
        ];
    }

    // Mismo descuento que aplica el front: 10% con efectivo + retiro en local
    // a partir de $30.000 de subtotal.
    $descuento = 0.0;
    if ($formaPago === 'efectivo' && $formaEntrega === 'retiro' && $subtotal >= UMBRAL_DESCUENTO_EFECTIVO) {
        $descuento = round($subtotal * DESCUENTO_EFECTIVO, 2);
    }
    $total = $subtotal - $descuento;

    $pedidoId = 0; // se inicializa acá para que el editor no marque "variable posiblemente indefinida" más abajo
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO pedidos (usuario_id, cliente_nombre, cliente_email, cliente_celular,
                                   forma_entrega, forma_pago, envio_domicilio, envio_localidad, envio_cp,
                                   retiro_nombre, retiro_dni,
                                   subtotal, descuento, total)
             VALUES (:usuario_id, :cliente_nombre, :cliente_email, :cliente_celular,
                     :forma_entrega, :forma_pago, :envio_domicilio, :envio_localidad, :envio_cp,
                     :retiro_nombre, :retiro_dni,
                     :subtotal, :descuento, :total)'
        );
        $stmt->execute([
            'usuario_id'      => $usuario['id'] ?? null,
            'cliente_nombre'  => $cliente['nombre'] ?? null,
            'cliente_email'   => $cliente['email'] ?? null,
            'cliente_celular' => $cliente['celular'] ?? null,
            'forma_entrega'   => $formaEntrega,
            'forma_pago'      => $formaPago,
            'envio_domicilio' => $envio['domicilio'] ?? null,
            'envio_localidad' => $envio['localidad'] ?? null,
            'envio_cp'        => $envio['cp'] ?? null,
            'retiro_nombre'   => $formaEntrega === 'retiro' ? trim($retiro['nombre']) : null,
            'retiro_dni'      => $formaEntrega === 'retiro' ? trim($retiro['dni']) : null,
            'subtotal'        => $subtotal,
            'descuento'       => $descuento,
            'total'           => $total,
        ]);
        $pedidoId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare(
            'INSERT INTO pedido_items (pedido_id, producto_id, codigo, nombre, precio_unitario, cantidad, subtotal)
             VALUES (:pedido_id, :producto_id, :codigo, :nombre, :precio, :cantidad, :subtotal)'
        );
        foreach ($itemsResueltos as $it) {
            $stmtItem->execute([
                'pedido_id'   => $pedidoId,
                'producto_id' => $it['producto_id'],
                'codigo'      => $it['codigo'],
                'nombre'      => $it['nombre'],
                'precio'      => $it['precio'],
                'cantidad'    => $it['cantidad'],
                'subtotal'    => $it['subtotal'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        error('No se pudo registrar el pedido. Intentá de nuevo.', 500);
    }

    responder([
        'id'              => $pedidoId,
        'subtotal'        => $subtotal,
        'descuento'       => $descuento,
        'total'           => $total,
        'estado'          => 'pendiente',
        'comprobante_url' => null,
        'retiro_nombre'   => $formaEntrega === 'retiro' ? trim($retiro['nombre']) : null,
        'retiro_dni'      => $formaEntrega === 'retiro' ? trim($retiro['dni']) : null,
        'items'           => $itemsResueltos,
    ], 201);
}

function pedidos_listar(PDO $pdo): void
{
    $usuario = usuarioAutenticado($pdo, true);

    if ($usuario['rol'] === 'admin') {
        $stmt = $pdo->query('SELECT * FROM pedidos ORDER BY creado_en DESC');
    } else {
        $stmt = $pdo->prepare('SELECT * FROM pedidos WHERE usuario_id = :id ORDER BY creado_en DESC');
        $stmt->execute(['id' => $usuario['id']]);
    }
    $pedidos = $stmt->fetchAll();

    $stmtItems = $pdo->prepare('SELECT codigo, nombre, precio_unitario, cantidad, subtotal FROM pedido_items WHERE pedido_id = :id');
    foreach ($pedidos as &$p) {
        $p['subtotal']  = (float)$p['subtotal'];
        $p['descuento'] = (float)$p['descuento'];
        $p['total']     = (float)$p['total'];
        $stmtItems->execute(['id' => $p['id']]);
        $p['items'] = $stmtItems->fetchAll();
    }

    responder($pedidos);
}

/**
 * POST /pedidos/{id}/comprobante
 * El cliente adjunta el ticket/comprobante de su pago (transferencia,
 * depósito, etc). En cuanto el archivo se guarda con éxito, el pedido pasa
 * a estado "pagado" automáticamente (salvo que ya esté cancelado o en un
 * estado posterior del flujo, que no se tocan).
 */
function pedidos_subir_comprobante(PDO $pdo, string $id): void
{
    $usuario = usuarioAutenticado($pdo, false); // igual que el checkout, admite invitados

    $pedidoId = (int)$id;
    if ($pedidoId <= 0) error('Pedido inválido.');

    $stmt = $pdo->prepare('SELECT id, usuario_id, estado FROM pedidos WHERE id = :id');
    $stmt->execute(['id' => $pedidoId]);
    $pedido = $stmt->fetch();
    if (!$pedido) error('No se encontró el pedido.', 404);

    // Si el pedido pertenece a un usuario logueado, solo ese usuario o un
    // admin pueden adjuntar el comprobante. Los pedidos de invitados
    // (usuario_id NULL) quedan abiertos, igual que el resto del checkout.
    if ($pedido['usuario_id'] !== null) {
        $esDueno = $usuario && (int)$usuario['id'] === (int)$pedido['usuario_id'];
        $esAdmin = $usuario && $usuario['rol'] === 'admin';
        if (!$esDueno && !$esAdmin) {
            error('No podés subir el comprobante de este pedido.', 403);
        }
    }

    if (empty($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK) {
        error('Adjuntá el comprobante de pago (campo "comprobante").');
    }
    $archivo = $_FILES['comprobante'];

    if ($archivo['size'] > TAMANO_MAX_COMPROBANTE) {
        error('El comprobante no puede pesar más de 5 MB.');
    }

    $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
    if (!isset(EXTENSIONES_COMPROBANTE[$extension])) {
        error('Formato no permitido. Subí una imagen (jpg, png, webp) o un PDF.');
    }

    // Se valida el contenido real del archivo (no solo la extensión que
    // manda el navegador), para evitar que suban un .php disfrazado de .jpg.
    $mimeReal = mime_content_type($archivo['tmp_name']);
    $mimeEsperado = EXTENSIONES_COMPROBANTE[$extension];
    $mimeValido = $mimeReal === $mimeEsperado
        || ($mimeEsperado === 'image/jpeg' && $mimeReal === 'image/jpg'); // por las dudas de algún php.ini viejo
    if (!$mimeValido) {
        error('El archivo no parece ser una imagen o PDF válido.');
    }

    $carpetaDestino = __DIR__ . '/../../uploads/comprobantes/';
    if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0755, true) && !is_dir($carpetaDestino)) {
        error('No se pudo preparar el almacenamiento de comprobantes.', 500);
    }

    $nombreArchivo = 'pedido_' . $pedidoId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $rutaDestino   = $carpetaDestino . $nombreArchivo;

    if (!move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
        error('No se pudo guardar el comprobante. Intentá de nuevo.', 500);
    }

    // URL pública relativa (el frontend arma la base igual que ya hace con
    // las imágenes de productos).
    $comprobanteUrl = 'mundo_verde_backend/uploads/comprobantes/' . $nombreArchivo;

    // No se pisan estados "finales": si ya está cancelado, confirmado o
    // entregado, el comprobante se guarda igual, pero el estado no cambia.
    $nuevoEstado = $pedido['estado'];
    if (in_array($pedido['estado'], ['pendiente', 'pagado'], true)) {
        $nuevoEstado = 'pagado';
    }

    $stmt = $pdo->prepare('UPDATE pedidos SET comprobante_url = :url, estado = :estado WHERE id = :id');
    $stmt->execute([
        'url'    => $comprobanteUrl,
        'estado' => $nuevoEstado,
        'id'     => $pedidoId,
    ]);

    responder([
        'mensaje'         => 'Comprobante recibido. Tu pedido quedó marcado como pagado.',
        'comprobante_url' => $comprobanteUrl,
        'estado'          => $nuevoEstado,
    ]);
}
