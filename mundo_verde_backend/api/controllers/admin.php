<?php
/**
 * Panel Superadmin: CRUD genérico sobre un conjunto whitelisteado de tablas.
 * Todo esto exige sesión de administrador (requerirAdmin).
 *
 * Por cada tabla se define:
 *   - pk: columna clave primaria
 *   - columnas: qué columnas se devuelven y en qué orden se muestran
 *   - editables: mapa columna -> tipo de input en el panel
 *        'text'                  -> <input type="text">
 *        'number'                -> <input type="number">
 *        'select:op1,op2,op3'    -> <select> con esas opciones
 *   - toggle_col: nombre de la columna booleana activo/inactivo (o null
 *     si esa tabla no tiene concepto de "ocultar" un registro)
 */
function tablasWhitelist(): array
{
    return [
        'usuarios' => [
            'pk'         => 'id',
            'columnas'   => ['id', 'nombre', 'email', 'telefono', 'domicilio_completo', 'localidad',
                             'codigo_postal', 'fecha_nacimiento', 'rol', 'activo', 'creado_en'],
            'editables'  => [
                'nombre'             => 'text',
                'email'              => 'text',
                'telefono'           => 'text',
                'domicilio_completo' => 'text',
                'localidad'          => 'text',
                'codigo_postal'      => 'text',
                'rol'                => 'select:cliente,vendedor,mantenimiento,admin',
            ],
            'toggle_col' => 'activo',
        ],
        'productos' => [
            'pk'         => 'id',
            'columnas'   => ['id', 'codigo', 'nombre', 'categoria', 'tipo', 'precio', 'stock', 'imagen', 'activo', 'creado_en'],
            'editables'  => [
                'nombre'    => 'text',
                'categoria' => 'select:arbustos,aromaticas,exterior,interior,insumos,servicios',
                'tipo'      => 'select:producto,servicio',
                'precio'    => 'number',
                'stock'     => 'number',
                'imagen'    => 'text',
            ],
            'toggle_col' => 'activo',
        ],
        'newsletter_suscriptores' => [
            'pk'         => 'id',
            'columnas'   => ['id', 'nombre', 'mail', 'origen', 'codigo_mio', 'referidos_exitosos', 'activo', 'creado_en'],
            'editables'  => [
                'nombre' => 'text',
                'origen' => 'text',
            ],
            'toggle_col' => 'activo',
        ],
        'pedidos' => [
            'pk'         => 'id',
            'columnas'   => ['id', 'cliente_nombre', 'cliente_email', 'cliente_celular', 'forma_entrega',
                             'retiro_nombre', 'retiro_dni',
                             'forma_pago', 'envio_localidad', 'subtotal', 'descuento', 'total',
                             'comprobante_url', 'estado', 'creado_en'],
            'editables'  => [
                'estado' => 'select:pendiente,pagado,confirmado,entregado,cancelado',
            ],
            'toggle_col' => null,
        ],
        'pedido_items' => [
            'pk'         => 'id',
            'columnas'   => ['id', 'pedido_id', 'codigo', 'nombre', 'precio_unitario', 'cantidad', 'subtotal'],
            'editables'  => [],
            'toggle_col' => null,
        ],
        // Banner de anuncios rotativos que se muestra en la web pública
        // (.anuncio en script.js/styles.css). "orden" define en qué
        // posición aparece cada mensaje dentro de la cinta; "activo" es
        // el toggle que usa el panel para ocultar un anuncio sin borrarlo.
        'anuncios' => [
            'pk'         => 'id',
            'columnas'   => ['id', 'texto', 'orden', 'activo'],
            'editables'  => [
                'texto' => 'text',
                'orden' => 'number',
            ],
            'toggle_col' => 'activo',
        ],
    ];
}

/**
 * Centraliza qué rol puede hacer qué acción sobre qué tabla del panel.
 *
 * Regla general: todo el panel es exclusivo del superadmin (rol 'admin').
 * Excepciones para el Vendedor:
 *   - LISTAR y EDITAR "pedidos" (que, según tablasWhitelist(), solo tiene
 *     "estado" como campo editable -> el vendedor nunca puede tocar
 *     montos, items ni datos del cliente, solo el estado de la venta).
 *   - LISTAR (solo lectura) "productos", para poder consultar precio y
 *     stock al armar una venta. Crear, editar, eliminar y toggle de
 *     productos siguen siendo exclusivos del admin.
 *
 * Mantenimiento no tiene ninguna acción habilitada acá: su acceso es a
 * nivel servidor (deploy, logs, backend/frontend), no a través de esta
 * API de datos.
 */
function permisoTabla(PDO $pdo, string $tabla, string $accion): array
{
    if ($tabla === 'pedidos' && in_array($accion, ['listar', 'editar'], true)) {
        return requerirRol($pdo, ['admin', 'vendedor']);
    }
    if ($tabla === 'productos' && $accion === 'listar') {
        return requerirRol($pdo, ['admin', 'vendedor']);
    }
    return requerirAdmin($pdo);
}

/**
 * GET /admin/tablas
 * Devuelve la metadata completa (no solo los nombres) para que el panel
 * pueda armar columnas, inputs editables y botón de toggle sin hardcodear
 * nada del lado del frontend.
 *
 * El vendedor también puede llamar este endpoint, pero solo recibe la
 * metadata de "pedidos" (su vista habitual, con edición de estado) y de
 * "productos" en modo solo lectura (para consultar precio/stock).
 */
function admin_tablas(PDO $pdo): void
{
    $usuario = requerirRol($pdo, ['admin', 'vendedor']);

    $tablas = tablasWhitelist();
    if ($usuario['rol'] === 'vendedor') {
        $tablas = array_intersect_key($tablas, ['pedidos' => true, 'productos' => true]);
    }

    $salida = [];
    foreach ($tablas as $tabla => $meta) {
        // El vendedor puede ver "productos" pero no tocarlo: no hay acciones
        // de alta/edición/baja en el front. Igual mandamos "editables"
        // completo (no vacío) porque el panel lo usa también para armar los
        // filtros de Categoría/Tipo en la barra de herramientas; es
        // "solo_lectura" quien le dice al front que no debe pintar inputs.
        $soloLectura = ($usuario['rol'] === 'vendedor' && $tabla === 'productos');

        $salida[$tabla] = [
            'pk'          => $meta['pk'],
            'columnas'    => $meta['columnas'],
            // Si no hay columnas editables, forzamos objeto vacío "{}" en vez
            // de array vacío "[]" para que el JSON sea consistente en el front.
            // Se manda completo (no vacío) aun en modo solo_lectura: el front
            // lo necesita para armar los filtros de columnas tipo "select"
            // (Categoría, Tipo); "solo_lectura" es lo que evita que se
            // conviertan en inputs editables.
            'editables'   => empty($meta['editables']) ? new stdClass() : $meta['editables'],
            'toggle_col'  => $meta['toggle_col'],
            'solo_lectura'=> $soloLectura,
        ];
    }
    responder($salida);
}

function admin_listar(PDO $pdo, string $tabla): void
{
    $meta = tablaValidaOError($tabla);
    permisoTabla($pdo, $tabla, 'listar');

    $columnas = implode(', ', array_map(fn($c) => "`$c`", $meta['columnas']));
    $stmt = $pdo->query("SELECT $columnas FROM `$tabla` ORDER BY `{$meta['pk']}` DESC LIMIT 500");
    $filas = $stmt->fetchAll();

    // Para "pedidos" adjuntamos el detalle de items (qué compró/contrató),
    // así el panel puede mostrar el detalle completo de cada venta sin
    // pedidos extra por cada fila del lado del cliente.
    if ($tabla === 'pedidos' && $filas) {
        $stmtItems = $pdo->prepare(
            'SELECT codigo, nombre, precio_unitario, cantidad, subtotal
             FROM pedido_items WHERE pedido_id = :id ORDER BY id'
        );
        foreach ($filas as &$fila) {
            $stmtItems->execute(['id' => $fila['id']]);
            $fila['items'] = $stmtItems->fetchAll();
        }
        unset($fila);
    }

    responder($filas);
}

function admin_editar(PDO $pdo, string $tabla, string $pk): void
{
    $meta = tablaValidaOError($tabla);
    permisoTabla($pdo, $tabla, 'editar');
    $body = leerBody();

    $cambios = array_intersect_key($body, $meta['editables']);
    if (empty($cambios)) {
        error('No se envió ningún campo editable válido para esta tabla.');
    }

    // Validación básica según el tipo declarado (evita, por ejemplo, guardar
    // texto libre en una columna "select" o un precio no numérico).
    foreach ($cambios as $col => $valor) {
        $tipo = $meta['editables'][$col];
        if ($tipo === 'number') {
            if ($valor === '' || !is_numeric($valor)) {
                error("El campo \"$col\" tiene que ser un número.");
            }
        } elseif (str_starts_with($tipo, 'select:')) {
            $opciones = explode(',', substr($tipo, 7));
            if (!in_array($valor, $opciones, true)) {
                error("Valor inválido para \"$col\".");
            }
        } else {
            $valor = trim((string)$valor);
            if ($valor === '') {
                error("El campo \"$col\" no puede quedar vacío.");
            }
        }
        $cambios[$col] = $valor;
    }

    $sets = [];
    $params = ['pk' => $pk];
    foreach ($cambios as $col => $valor) {
        $sets[] = "`$col` = :$col";
        $params[$col] = $valor;
    }

    $sql = "UPDATE `$tabla` SET " . implode(', ', $sets) . " WHERE `{$meta['pk']}` = :pk";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        error('No se encontró el registro o no hubo cambios.', 404);
    }
    responder(['mensaje' => 'Registro actualizado.']);
}

/**
 * POST /admin/{tabla}
 * Alta genérica para tablas whitelisteadas. La tabla "usuarios" tiene un
 * flujo especial (ver admin_crear_usuario) porque necesita email único y
 * contraseña, cosas que no forman parte de "editables".
 */
function admin_crear(PDO $pdo, string $tabla): void
{
    $meta = tablaValidaOError($tabla);
    permisoTabla($pdo, $tabla, 'crear');
    $body = leerBody();

    if ($tabla === 'usuarios') {
        admin_crear_usuario($pdo, $body);
        return;
    }

    $datos = array_intersect_key($body, $meta['editables']);
    if (empty($datos)) {
        error('No se envió ningún campo válido para crear el registro.');
    }

    foreach ($datos as $col => $valor) {
        $tipo = $meta['editables'][$col];
        if ($tipo === 'number') {
            if ($valor === '' || !is_numeric($valor)) {
                error("El campo \"$col\" tiene que ser un número.");
            }
        } elseif (str_starts_with($tipo, 'select:')) {
            $opciones = explode(',', substr($tipo, 7));
            if (!in_array($valor, $opciones, true)) {
                error("Valor inválido para \"$col\".");
            }
        } else {
            $valor = trim((string)$valor);
            if ($valor === '') {
                error("El campo \"$col\" no puede quedar vacío.");
            }
        }
        $datos[$col] = $valor;
    }

    $columnas   = array_map(fn($c) => "`$c`", array_keys($datos));
    $marcadores = array_map(fn($c) => ":$c", array_keys($datos));

    $sql = "INSERT INTO `$tabla` (" . implode(', ', $columnas) . ") VALUES (" . implode(', ', $marcadores) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($datos);

    responder(['mensaje' => 'Registro creado.', 'id' => (int)$pdo->lastInsertId()], 201);
}

/**
 * Alta de personal (vendedor/mantenimiento/admin) desde el panel superadmin.
 * A diferencia de /registro (que usan los clientes), acá no hay
 * confirmación de mail: la cuenta queda activa y confirmada de una, y se
 * genera una contraseña temporal que el superadmin le pasa a la persona
 * por fuera del sistema (todavía no hay envío de mail configurado).
 */
function admin_crear_usuario(PDO $pdo, array $body): void
{
    $nombre = trim($body['nombre'] ?? '');
    $email  = trim(strtolower($body['email'] ?? ''));
    $rol    = trim($body['rol'] ?? '');

    if ($nombre === '') error('El nombre es obligatorio.');
    if (!validarEmail($email)) error('Ingresá un email válido.');
    if (!in_array($rol, ['vendedor', 'mantenimiento', 'admin'], true)) {
        error('Rol inválido. Desde acá solo se crean cuentas de vendedor, mantenimiento o admin.');
    }

    $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
    $existe->execute(['email' => $email]);
    if ($existe->fetch()) {
        error('Ya existe una cuenta registrada con ese email.', 409);
    }

    $passwordTemporal = bin2hex(random_bytes(4)); // 8 caracteres
    $hash = password_hash($passwordTemporal, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, telefono, domicilio_completo, localidad, codigo_postal,
                                fecha_nacimiento, password_hash, rol, activo, confirmado)
         VALUES (:nombre, :email, :telefono, :domicilio, :localidad, :cp, :fecha_nac, :hash, :rol, 1, 1)'
    );
    $stmt->execute([
        'nombre'    => $nombre,
        'email'     => $email,
        'telefono'  => trim($body['telefono'] ?? '') ?: null,
        'domicilio' => trim($body['domicilio_completo'] ?? '') ?: null,
        'localidad' => trim($body['localidad'] ?? '') ?: null,
        'cp'        => trim($body['codigo_postal'] ?? '') ?: null,
        'fecha_nac' => trim($body['fecha_nacimiento'] ?? '') ?: null,
        'hash'      => $hash,
        'rol'       => $rol,
    ]);

    responder([
        'mensaje'           => 'Cuenta creada.',
        'id'                => (int)$pdo->lastInsertId(),
        'password_temporal' => $passwordTemporal, // mostrar una sola vez en el panel
    ], 201);
}

function admin_toggle(PDO $pdo, string $tabla, string $pk): void
{
    $meta = tablaValidaOError($tabla);
    permisoTabla($pdo, $tabla, 'toggle');
    if (!$meta['toggle_col']) {
        error("La tabla \"$tabla\" no tiene un estado activo/inactivo para alternar.");
    }

    $col = $meta['toggle_col'];
    $stmt = $pdo->prepare("UPDATE `$tabla` SET `$col` = NOT `$col` WHERE `{$meta['pk']}` = :pk");
    $stmt->execute(['pk' => $pk]);
    if ($stmt->rowCount() === 0) error('No se encontró el registro.', 404);
    responder(['mensaje' => 'Estado actualizado.']);
}

function admin_eliminar(PDO $pdo, string $tabla, string $pk): void
{
    $meta = tablaValidaOError($tabla);
    permisoTabla($pdo, $tabla, 'eliminar');

    $stmt = $pdo->prepare("DELETE FROM `$tabla` WHERE `{$meta['pk']}` = :pk");
    $stmt->execute(['pk' => $pk]);
    if ($stmt->rowCount() === 0) error('No se encontró el registro.', 404);
    responder(['mensaje' => 'Registro eliminado.']);
}

function tablaValidaOError(string $tabla): array
{
    $whitelist = tablasWhitelist();
    if (!isset($whitelist[$tabla])) {
        error("La tabla \"$tabla\" no existe o no está habilitada para el panel admin.", 404);
    }
    return $whitelist[$tabla];
}
