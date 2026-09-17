<?php
/**
 * Endpoints de autenticación.
 * Contrato esperado por js/api.js (objeto mvAuth).
 */

function auth_registro(PDO $pdo): void
{
    $body = leerBody();

    $nombre            = trim($body['nombre'] ?? '');
    $email             = trim(strtolower($body['email'] ?? ''));
    $telefono          = trim($body['telefono'] ?? '');
    $domicilioCompleto = trim($body['domicilio_completo'] ?? '');
    $localidad         = trim($body['localidad'] ?? '');
    $codigoPostal      = trim($body['codigo_postal'] ?? '');
    $fechaNac          = trim($body['fecha_nacimiento'] ?? '');
    $password          = (string)($body['password'] ?? '');

    if ($nombre === '') error('El nombre es obligatorio.');
    if (!validarEmail($email)) error('Ingresá un email válido.');
    if ($domicilioCompleto === '') error('El domicilio completo es obligatorio.');
    if ($localidad === '') error('La localidad es obligatoria.');
    if (strlen($password) < 6) error('La contraseña debe tener al menos 6 caracteres.');

    if ($fechaNac !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac)) {
        $fechaNac = null;
    }
    $fechaNac = $fechaNac === '' ? null : $fechaNac;

    $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
    $existe->execute(['email' => $email]);
    if ($existe->fetch()) {
        error('Ya existe una cuenta registrada con ese email.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, telefono, domicilio_completo, localidad, codigo_postal,
                                fecha_nacimiento, password_hash, rol)
         VALUES (:nombre, :email, :telefono, :domicilio_completo, :localidad, :codigo_postal,
                 :fecha_nacimiento, :hash, "cliente")'
    );
    $stmt->execute([
        'nombre'             => $nombre,
        'email'              => $email,
        'telefono'           => $telefono ?: null,
        'domicilio_completo' => $domicilioCompleto,
        'localidad'          => $localidad,
        'codigo_postal'      => $codigoPostal ?: null,
        'fecha_nacimiento'   => $fechaNac,
        'hash'               => $hash,
    ]);
    $usuarioId = (int)$pdo->lastInsertId();

    // Ya no se loguea acá: la cuenta queda creada pero sin confirmar
    // (columna `confirmado` = 0 por default) hasta que confirme el
    // código de 6 dígitos que le mandamos al mail.
    $codigo = generarCodigoConfirmacion();
    $pdo->prepare(
        'INSERT INTO confirmaciones_mail (usuario_id, codigo, expira_en)
         VALUES (:usuario_id, :codigo, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
    )->execute(['usuario_id' => $usuarioId, 'codigo' => $codigo]);

    $respuesta = [
        'mensaje' => 'Cuenta creada. Te mandamos un código para confirmar tu mail.',
        'email'   => $email,
    ];

    // No hay servicio de envío de mails configurado todavía: devolvemos
    // el código acá mismo para poder probar el flujo completo (modo dev).
    // Cuando se configure un mailer real, quitar "dev_codigo" de la
    // respuesta y enviar el código por email en su lugar.
    $respuesta['dev_codigo'] = $codigo;

    responder($respuesta, 201);
}

function auth_login(PDO $pdo): void
{
    $body = leerBody();
    $email    = trim(strtolower($body['email'] ?? ''));
    $password = (string)($body['password'] ?? '');

    if (!validarEmail($email) || $password === '') {
        error('Ingresá tu email y contraseña.');
    }

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario || !password_verify($password, $usuario['password_hash'])) {
        error('Email o contraseña incorrectos.', 401);
    }
    if (!$usuario['activo']) {
        error('Tu cuenta está deshabilitada. Contactanos para más info.', 403);
    }
    // ⚠️ TEMPORAL - TESTING: se desactiva la exigencia de mail confirmado
    // para poder loguearse sin pasar por el código de confirmación.
    // Reactivar antes de pasar a producción descomentando estas 3 líneas:
    // if (!$usuario['confirmado']) {
    //     error('Todavía no confirmaste tu mail. Revisá el código que te mandamos al registrarte.', 403);
    // }

    $token = crearSesion($pdo, (int)$usuario['id']);

    responder([
        'token'   => $token,
        'usuario' => [
            'id'     => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'email'  => $usuario['email'],
            'rol'    => $usuario['rol'],
        ],
    ]);
}

function auth_logout(PDO $pdo): void
{
    $token = tokenDelHeader();
    if ($token) {
        $pdo->prepare('DELETE FROM sesiones WHERE token = :token')->execute(['token' => $token]);
    }
    responder(['mensaje' => 'Sesión cerrada.']);
}

function auth_me(PDO $pdo): void
{
    $usuario = usuarioAutenticado($pdo, true);
    unset($usuario['activo']);
    responder($usuario);
}

function auth_recuperar(PDO $pdo): void
{
    $body  = leerBody();
    $email = trim(strtolower($body['email'] ?? ''));
    if (!validarEmail($email)) error('Ingresá un email válido.');

    $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    // Por seguridad, respondemos igual exista o no la cuenta.
    $respuesta = ['mensaje' => 'Si el email está registrado, vas a poder continuar con el link de abajo.'];

    if ($usuario) {
        $token = generarToken();
        $pdo->prepare(
            'INSERT INTO password_resets (usuario_id, token, expira_en)
             VALUES (:usuario_id, :token, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
        )->execute(['usuario_id' => $usuario['id'], 'token' => $token]);

        // No hay servicio de envío de mails configurado todavía: devolvemos
        // el link acá mismo para poder probar el flujo completo (modo dev).
        // Cuando se configure un mailer real, quitar "dev_link" de la respuesta
        // y enviar el link por email en su lugar.
        $respuesta['dev_link'] = '../../restablecer.html?token=' . urlencode($token);
    }

    responder($respuesta);
}

function auth_restablecer(PDO $pdo): void
{
    $body     = leerBody();
    $token    = trim($body['token'] ?? '');
    $password = (string)($body['password'] ?? '');

    if ($token === '') error('Link inválido o vencido.');
    if (strlen($password) < 8) error('La contraseña debe tener al menos 8 caracteres.');

    $stmt = $pdo->prepare(
        'SELECT * FROM password_resets
         WHERE token = :token AND usado = 0 AND expira_en > NOW()'
    );
    $stmt->execute(['token' => $token]);
    $reset = $stmt->fetch();

    if (!$reset) {
        error('Ese link ya venció o ya fue usado. Pedí uno nuevo.', 400);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE usuarios SET password_hash = :hash WHERE id = :id')
        ->execute(['hash' => $hash, 'id' => $reset['usuario_id']]);

    $pdo->prepare('UPDATE password_resets SET usado = 1 WHERE id = :id')
        ->execute(['id' => $reset['id']]);

    // Invalidamos las sesiones anteriores por seguridad.
    $pdo->prepare('DELETE FROM sesiones WHERE usuario_id = :id')
        ->execute(['id' => $reset['usuario_id']]);

    responder(['mensaje' => 'Contraseña actualizada. Ya podés iniciar sesión.']);
}

function auth_confirmar_mail(PDO $pdo): void
{
    $body   = leerBody();
    $email  = trim(strtolower($body['email'] ?? ''));
    $codigo = trim($body['codigo'] ?? '');

    if (!validarEmail($email)) error('Ingresá un email válido.');
    if (!preg_match('/^\d{6}$/', $codigo)) error('El código debe tener 6 dígitos.');

    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    if (!$usuario) error('No encontramos una cuenta con ese mail.', 404);
    if ($usuario['confirmado']) error('Ese mail ya estaba confirmado. Iniciá sesión.', 409);

    $stmt = $pdo->prepare(
        'SELECT * FROM confirmaciones_mail
         WHERE usuario_id = :usuario_id AND codigo = :codigo AND usado = 0 AND expira_en > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute(['usuario_id' => $usuario['id'], 'codigo' => $codigo]);
    $confirmacion = $stmt->fetch();

    if (!$confirmacion) {
        error('Ese código es incorrecto o ya venció. Pedí uno nuevo.', 400);
    }

    $pdo->prepare('UPDATE usuarios SET confirmado = 1 WHERE id = :id')
        ->execute(['id' => $usuario['id']]);
    $pdo->prepare('UPDATE confirmaciones_mail SET usado = 1 WHERE id = :id')
        ->execute(['id' => $confirmacion['id']]);

    $token = crearSesion($pdo, (int)$usuario['id']);

    responder([
        'token'   => $token,
        'usuario' => [
            'id'     => (int)$usuario['id'],
            'nombre' => $usuario['nombre'],
            'email'  => $usuario['email'],
            'rol'    => $usuario['rol'],
        ],
    ]);
}

function auth_confirmar_mail_reenviar(PDO $pdo): void
{
    $body  = leerBody();
    $email = trim(strtolower($body['email'] ?? ''));
    if (!validarEmail($email)) error('Ingresá un email válido.');

    $stmt = $pdo->prepare('SELECT id, confirmado FROM usuarios WHERE email = :email');
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch();

    // Igual que en /recuperar: por seguridad respondemos parecido exista
    // o no la cuenta, y no importa si ya está confirmada.
    $respuesta = ['mensaje' => 'Si la cuenta existe y todavía no fue confirmada, te mandamos un código nuevo.'];

    if ($usuario && !$usuario['confirmado']) {
        // Invalida los códigos anteriores de esta cuenta que no se usaron.
        $pdo->prepare('UPDATE confirmaciones_mail SET usado = 1 WHERE usuario_id = :id AND usado = 0')
            ->execute(['id' => $usuario['id']]);

        $codigo = generarCodigoConfirmacion();
        $pdo->prepare(
            'INSERT INTO confirmaciones_mail (usuario_id, codigo, expira_en)
             VALUES (:usuario_id, :codigo, DATE_ADD(NOW(), INTERVAL 15 MINUTE))'
        )->execute(['usuario_id' => $usuario['id'], 'codigo' => $codigo]);

        // Modo dev: ver nota en auth_registro.
        $respuesta['dev_codigo'] = $codigo;
    }

    responder($respuesta);
}

/** Genera un código numérico de 6 dígitos (con ceros a la izquierda si hace falta). */
function generarCodigoConfirmacion(): string
{
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

/** Crea una fila en `sesiones` y devuelve el token generado. */
function crearSesion(PDO $pdo, int $usuarioId): string
{
    $token = generarToken();
    $pdo->prepare(
        'INSERT INTO sesiones (usuario_id, token, expira_en)
         VALUES (:usuario_id, :token, DATE_ADD(NOW(), INTERVAL 30 DAY))'
    )->execute(['usuario_id' => $usuarioId, 'token' => $token]);
    return $token;
}
