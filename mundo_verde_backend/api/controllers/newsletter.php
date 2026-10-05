<?php
/**
 * Newsletter + programa de referidos.
 * Cada suscriptor recibe un codigo_mio único. Si se suscribe usando el
 * código de otra persona (cod_ref), esa persona suma un referido exitoso.
 */
require_once __DIR__ . '/mailer.php';

function newsletter_suscribir(PDO $pdo): void
{
    $body = leerBody();

    $nombre = trim($body['nombre'] ?? '');
    $mail   = trim(strtolower($body['mail'] ?? ''));
    $origen = trim($body['origen'] ?? '');
    $codRef = trim(strtoupper($body['cod_ref'] ?? ''));

    if ($nombre === '') error('El nombre es obligatorio.');
    if (!validarEmail($mail)) error('Ingresá un email válido.');

    $existe = $pdo->prepare('SELECT id FROM newsletter_suscriptores WHERE mail = :mail');
    $existe->execute(['mail' => $mail]);
    if ($existe->fetch()) {
        error('Ese email ya está suscripto al newsletter.', 409);
    }

    $referidoPorId = null;
    if ($codRef !== '') {
        $stmtRef = $pdo->prepare('SELECT id FROM newsletter_suscriptores WHERE codigo_mio = :cod');
        $stmtRef->execute(['cod' => $codRef]);
        $referente = $stmtRef->fetch();
        if (!$referente) {
            error('El código de referido ingresado no existe. Revisalo e intentá de nuevo.', 400);
        }
        $referidoPorId = (int)$referente['id'];
    }

    $codigoMio = generarCodigoReferido($pdo);
    $codigoVerde = generarCodigoVerde($pdo);   // código propio para el descuento (distinto del de referidos)

    $stmt = $pdo->prepare(
        'INSERT INTO newsletter_suscriptores (nombre, mail, origen, codigo_mio, referido_por_id, acepta_tyc, codigo_verde)
         VALUES (:nombre, :mail, :origen, :codigo, :referido_por, 1, :codigo_verde)'
    );
    try {
        $stmt->execute([
            'nombre'       => $nombre,
            'mail'         => $mail,
            'origen'       => $origen ?: null,
            'codigo'       => $codigoMio,
            'referido_por' => $referidoPorId,
            'codigo_verde' => $codigoVerde,
        ]);
    } catch (PDOException $e) {
        // Dos suscripciones simultáneas con el mismo mail: gana la primera.
        if (esDuplicadoEnClave($e, 'mail')) {
            error('Ese email ya está suscripto al newsletter.', 409);
        }
        throw $e;
    }

    if ($referidoPorId) {
        $pdo->prepare(
            'UPDATE newsletter_suscriptores SET referidos_exitosos = referidos_exitosos + 1 WHERE id = :id'
        )->execute(['id' => $referidoPorId]);
    }

    // Si el envío falla no rompe la suscripción: queda registrado en el log.
    enviarMailNewsletter($mail, $nombre, $codigoMio, $codigoVerde);

    responder([
        'nombre'     => $nombre,
        'codigo_mio' => $codigoMio,
        'codigo_verde' => $codigoVerde,
        // Texto listo para mostrar en el frontend (incluye la nota sobre spam).
        'mensaje'    => '¡Listo, ya estás suscripto/a! Te enviamos un mail de confirmación con tu código de referido. ' .
                        'Si no lo ves en unos minutos, revisá la carpeta de Spam o Promociones. ' .
                        'Si está ahí, marcalo como "No es spam" y agregá viveunmundoverde@gmail.com ' .
                        'a tus contactos para que los próximos mails lleguen a tu bandeja principal.',
    ], 201);
}

function newsletter_mi_estado(PDO $pdo): void
{
    $mail = trim(strtolower($_GET['mail'] ?? ''));
    if (!validarEmail($mail)) error('Ingresá un email válido.');

    $stmt = $pdo->prepare(
        'SELECT id, nombre, codigo_mio, codigo_verde, codigo_usado_en, referidos_exitosos
         FROM newsletter_suscriptores WHERE mail = :mail'
    );
    $stmt->execute(['mail' => $mail]);
    $sub = $stmt->fetch();

    if (!$sub) {
        error('Ese email no está suscripto al newsletter todavía.', 404);
    }

    // Suscriptores anteriores a la migración no tienen Código Verde: se les
    // genera la primera vez que consultan su estado.
    if (empty($sub['codigo_verde'])) {
        $pdo->prepare('UPDATE newsletter_suscriptores SET codigo_verde = :c WHERE id = :id AND codigo_verde IS NULL')
            ->execute(['c' => generarCodigoVerde($pdo), 'id' => $sub['id']]);
        $re = $pdo->prepare('SELECT codigo_verde FROM newsletter_suscriptores WHERE id = :id');
        $re->execute(['id' => $sub['id']]);
        $sub['codigo_verde'] = $re->fetchColumn();
    }

    responder([
        'nombre'             => $sub['nombre'],
        'codigo_mio'         => $sub['codigo_mio'],       // para referir amigos
        'codigo_verde'       => $sub['codigo_verde'],     // para el descuento (único uso)
        'codigo_verde_usado' => $sub['codigo_usado_en'] !== null,
        'referidos_exitosos' => (int)$sub['referidos_exitosos'],
    ]);
}

function newsletter_listar(PDO $pdo): void
{
    requerirAdmin($pdo);
    $stmt = $pdo->query(
        'SELECT id, nombre, mail, origen, codigo_mio, referido_por_id,
                referidos_exitosos, activo, creado_en
         FROM newsletter_suscriptores ORDER BY creado_en DESC'
    );
    responder($stmt->fetchAll());
}

function newsletter_toggle(PDO $pdo, string $id): void
{
    requerirAdmin($pdo);
    $stmt = $pdo->prepare(
        'UPDATE newsletter_suscriptores SET activo = NOT activo WHERE id = :id'
    );
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) error('No se encontró ese suscriptor.', 404);
    responder(['mensaje' => 'Estado actualizado.']);
}

function newsletter_eliminar(PDO $pdo, string $id): void
{
    requerirAdmin($pdo);
    $stmt = $pdo->prepare('DELETE FROM newsletter_suscriptores WHERE id = :id');
    $stmt->execute(['id' => $id]);
    if ($stmt->rowCount() === 0) error('No se encontró ese suscriptor.', 404);
    responder(['mensaje' => 'Suscriptor eliminado.']);
}

/** Genera un código alfanumérico de 6 caracteres que no exista todavía. */
function generarCodigoReferido(PDO $pdo): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O/1/I para evitar confusión
    do {
        $codigo = '';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $stmt = $pdo->prepare('SELECT id FROM newsletter_suscriptores WHERE codigo_mio = :c');
        $stmt->execute(['c' => $codigo]);
    } while ($stmt->fetch());

    return $codigo;
}

/* ══════════════════════════════════════════════════════
   CÓDIGO VERDE — descuento del carrito por estar suscripto
   El "Código Verde" (columna codigo_verde, formato MV-XXXXXX) es un código propio de cada
   suscriptor, DISTINTO del código de referidos (codigo_mio, que se comparte). Es de ÚNICO USO:
   al confirmarse un pedido con ese código se completa codigo_usado_en
   (ver pedidos_crear). Requiere la migración migracion_codigo_verde.sql.
   ══════════════════════════════════════════════════════ */

/** Deja solo A-Z, 0-9 y guion, en mayúsculas. */
function newsletter_normalizar_codigo(string $codigo): string
{
    return preg_replace('/[^A-Z0-9-]/', '', strtoupper(trim($codigo)));
}

/**
 * Estado de un Código Verde. Devuelve:
 *   ['ok' => true,  'id' => int, 'nombre' => string, 'codigo' => string]
 *   ['ok' => false, 'error' => 'mensaje para mostrar al cliente']
 */
function newsletter_estado_codigo_verde(PDO $pdo, string $codigo): array
{
    $codigo = newsletter_normalizar_codigo($codigo);
    if ($codigo === '') {
        return ['ok' => false, 'error' => 'Ingresá tu Código Verde.'];
    }

    $stmt = $pdo->prepare(
        'SELECT id, nombre, activo, codigo_usado_en
         FROM newsletter_suscriptores WHERE codigo_verde = :c'
    );
    $stmt->execute(['c' => $codigo]);
    $sub = $stmt->fetch();

    if (!$sub) {
        return ['ok' => false, 'error' => 'Ese Código Verde no existe. Revisalo (no es el mismo que tu código de referidos).'];
    }
    if (!(int)$sub['activo']) {
        return ['ok' => false, 'error' => 'Ese Código Verde no está activo.'];
    }
    if ($sub['codigo_usado_en'] !== null) {
        return ['ok' => false, 'error' => 'Ese Código Verde ya fue utilizado.'];
    }
    return ['ok' => true, 'id' => (int)$sub['id'], 'nombre' => $sub['nombre'], 'codigo' => $codigo];
}

/**
 * GET /newsletter/validar-codigo?codigo=XXXXXX
 * Siempre responde 200: { valido: true, nombre } o { valido: false, error }.
 * (Así el front distingue "código inválido" de "falló el servidor".)
 */
function newsletter_validar_codigo(PDO $pdo): void
{
    $est = newsletter_estado_codigo_verde($pdo, (string)($_GET['codigo'] ?? ''));
    if (!$est['ok']) {
        responder(['valido' => false, 'error' => $est['error']]);
        return;
    }
    responder(['valido' => true, 'nombre' => $est['nombre']]);
}

/** Código Verde: 'MV-' + 6 caracteres, único (columna codigo_verde). */
function generarCodigoVerde(PDO $pdo): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sin 0/O/1/I para evitar confusión
    do {
        $sufijo = '';
        for ($i = 0; $i < 6; $i++) {
            $sufijo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
        $codigo = 'MV-' . $sufijo;
        $stmt = $pdo->prepare('SELECT id FROM newsletter_suscriptores WHERE codigo_verde = :c');
        $stmt->execute(['c' => $codigo]);
    } while ($stmt->fetch());

    return $codigo;
}
