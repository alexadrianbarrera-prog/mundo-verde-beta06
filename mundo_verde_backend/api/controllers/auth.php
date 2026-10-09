<?php
/**
 * Endpoints de autenticación.
 * Contrato esperado por js/api.js (objeto mvAuth).
 */

require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/newsletter.php';   // newsletter_codigo_verde_pendiente()

/**
 * Código postal y fecha de nacimiento son obligatorios en el registro (y al
 * completarlo); el teléfono es opcional pero, si viene, tiene que ser válido.
 * Corta con error() si alguno falta o no es válido.
 */
function auth_validar_datos_personales(string $telefono, string $codigoPostal, string $fechaNac): void
{
    // El teléfono es opcional: solo se valida si viene completo.
    if ($telefono !== '' && !preg_match('/^\d{8,15}$/', $telefono)) {
        error('El teléfono debe tener entre 8 y 15 números.');
    }
    if (!preg_match('/^\d{4}$/', $codigoPostal)) {
        error('Ingresá tu código postal de 4 dígitos.');
    }
    $okFecha = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fechaNac, $m)
        && checkdate((int)$m[2], (int)$m[3], (int)$m[1])
        && (int)$m[1] >= 1900
        && $fechaNac <= date('Y-m-d');
    if (!$okFecha) error('Ingresá una fecha de nacimiento válida.');
}

/** Piso y Dpto son obligatorios (casa: "0" o "-"). Máx. 10 caracteres cada uno. */
function auth_validar_piso_dpto(string $piso, string $dpto): void
{
    if ($piso === '' || $dpto === '') {
        error('Completá Piso y Dpto (si es casa, poné 0 o -).');
    }
    if (mb_strlen($piso) > 10 || mb_strlen($dpto) > 10) {
        error('Piso y Dpto pueden tener hasta 10 caracteres.');
    }
}

function auth_registro(PDO $pdo): void
{
    $body = leerBody();

    $nombre            = trim($body['nombre'] ?? '');
    $email             = trim(strtolower($body['email'] ?? ''));
    $telefono          = trim($body['telefono'] ?? '');
    $domicilioCompleto = trim($body['domicilio_completo'] ?? '');
    $piso              = trim($body['piso'] ?? '');
    $dpto              = trim($body['dpto'] ?? '');
    $localidad         = trim($body['localidad'] ?? '');
    $codigoPostal      = trim($body['codigo_postal'] ?? '');
    $fechaNac          = trim($body['fecha_nacimiento'] ?? '');
    $password          = (string)($body['password'] ?? '');

    if ($nombre === '') error('El nombre es obligatorio.');
    if (!validarEmail($email)) error('Ingresá un email válido.');
    if ($domicilioCompleto === '') error('El domicilio completo es obligatorio.');
    auth_validar_piso_dpto($piso, $dpto);
    if ($localidad === '') error('La localidad es obligatoria.');
    auth_validar_datos_personales($telefono, $codigoPostal, $fechaNac);
    if (strlen($password) < 6) error('La contraseña debe tener al menos 6 caracteres.');

    $existe = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email');
    $existe->execute(['email' => $email]);
    if ($existe->fetch()) {
        error('Ya existe una cuenta registrada con ese email.', 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (nombre, email, telefono, domicilio_completo, piso, dpto, localidad, codigo_postal,
                                fecha_nacimiento, password_hash, rol)
         VALUES (:nombre, :email, :telefono, :domicilio_completo, :piso, :dpto, :localidad, :codigo_postal,
                 :fecha_nacimiento, :hash, "cliente")'
    );
    try {
        $stmt->execute([
            'nombre'             => $nombre,
            'email'              => $email,
            'telefono'           => $telefono ?: null,
            'domicilio_completo' => $domicilioCompleto,
            'piso'               => $piso,
            'dpto'               => $dpto,
            'localidad'          => $localidad,
            'codigo_postal'      => $codigoPostal ?: null,
            'fecha_nacimiento'   => $fechaNac,
            'hash'               => $hash,
        ]);
    } catch (PDOException $e) {
        // Dos registros simultáneos con el mismo mail: gana el primero.
        if (esDuplicadoEnClave($e, 'email')) {
            error('Ya existe una cuenta registrada con ese email.', 409);
        }
        throw $e;
    }
    $usuarioId = (int)$pdo->lastInsertId();

    // Login automático: la sesión se crea acá mismo y el token viaja en la
    // respuesta, así el frontend deja al usuario logueado sin pasar por login.html.
    $token = crearSesion($pdo, $usuarioId);

    $respuesta = [
        'mensaje' => 'Cuenta creada.',
        // Nombre sacado del mail (crixus@gmail.com -> "Crixus"): es el mismo
        // que lleva el mail de bienvenida, para que cartel y mail coincidan.
        'nombre_saludo' => nombreDesdeEmail($email),
        'token'   => $token,
        'usuario' => [
            'id'     => $usuarioId,
            'nombre' => $nombre,
            'email'  => $email,
            'rol'    => 'cliente',
            'perfil_completo' => 1,   // el registro común pide todos los datos
        ],
    ];

    // Se le responde al navegador YA (para que el cartel y el login aparezcan
    // al instante) y recién después se envía el mail de bienvenida, sin hacer
    // esperar al usuario por el SMTP. Si el envío falla NO se corta nada:
    // la cuenta ya está creada y el error queda en el log del servidor.
    responderYContinuar($respuesta, 201);
    enviarMailBienvenida($email, $nombre);
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
            'perfil_completo' => (int)($usuario['perfil_completo'] ?? 1),
        ],
        // Si es suscriptor y todavía no usó su 10%, el front lo deja aplicado.
        'codigo_verde_pendiente' => newsletter_codigo_verde_pendiente($pdo, $usuario['email']),
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

    $st = $pdo->prepare('SELECT perfil_completo FROM usuarios WHERE id = :id');
    $st->execute(['id' => $usuario['id']]);
    $pc = $st->fetchColumn();
    $usuario['perfil_completo'] = $pc === false ? 1 : (int)$pc;

    responder($usuario);
}

/**
 * PATCH /usuario/me — completa los datos pendientes del registro corto
 * (el que se crea al suscribirse al newsletter). Exige sesión iniciada.
 * Domicilio y localidad son obligatorios (igual que en el registro común);
 * teléfono, código postal y fecha de nacimiento son opcionales y, si vienen
 * vacíos, NO pisan lo que ya estuviera guardado.
 */
function auth_completar_perfil(PDO $pdo): void
{
    $usuario = usuarioAutenticado($pdo, true);
    $body = leerBody();

    $telefono          = trim($body['telefono'] ?? '');
    $domicilioCompleto = trim($body['domicilio_completo'] ?? '');
    $piso              = trim($body['piso'] ?? '');
    $dpto              = trim($body['dpto'] ?? '');
    $localidad         = trim($body['localidad'] ?? '');
    $codigoPostal      = trim($body['codigo_postal'] ?? '');
    $fechaNac          = trim($body['fecha_nacimiento'] ?? '');

    if ($domicilioCompleto === '') error('El domicilio completo es obligatorio.');
    auth_validar_piso_dpto($piso, $dpto);
    if ($localidad === '') error('La localidad es obligatoria.');
    auth_validar_datos_personales($telefono, $codigoPostal, $fechaNac);

    $pdo->prepare(
        'UPDATE usuarios
            SET domicilio_completo = :dom,
                piso               = :piso,
                dpto               = :dpto,
                localidad          = :loc,
                telefono           = COALESCE(:tel, telefono),
                codigo_postal      = COALESCE(:cp, codigo_postal),
                fecha_nacimiento   = COALESCE(:fecha, fecha_nacimiento),
                perfil_completo    = 1
          WHERE id = :id'
    )->execute([
        'dom'   => $domicilioCompleto,
        'piso'  => $piso,
        'dpto'  => $dpto,
        'loc'   => $localidad,
        'tel'   => $telefono !== '' ? $telefono : null,
        'cp'    => $codigoPostal !== '' ? $codigoPostal : null,
        'fecha' => $fechaNac !== '' ? $fechaNac : null,
        'id'    => $usuario['id'],
    ]);

    $stmt = $pdo->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = :id');
    $stmt->execute(['id' => $usuario['id']]);
    $u = $stmt->fetch();

    responder([
        'mensaje' => 'Datos guardados.',
        'usuario' => [
            'id'              => (int)$u['id'],
            'nombre'          => $u['nombre'],
            'email'           => $u['email'],
            'rol'             => $u['rol'],
            'perfil_completo' => 1,
        ],
    ]);
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
