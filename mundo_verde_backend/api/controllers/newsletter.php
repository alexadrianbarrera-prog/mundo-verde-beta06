<?php
/**
 * Newsletter + programa de referidos.
 * Cada suscriptor recibe un codigo_mio único. Si se suscribe usando el
 * código de otra persona (cod_ref), esa persona suma un referido exitoso.
 */

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

    $stmt = $pdo->prepare(
        'INSERT INTO newsletter_suscriptores (nombre, mail, origen, codigo_mio, referido_por_id, acepta_tyc)
         VALUES (:nombre, :mail, :origen, :codigo, :referido_por, 1)'
    );
    $stmt->execute([
        'nombre'       => $nombre,
        'mail'         => $mail,
        'origen'       => $origen ?: null,
        'codigo'       => $codigoMio,
        'referido_por' => $referidoPorId,
    ]);

    if ($referidoPorId) {
        $pdo->prepare(
            'UPDATE newsletter_suscriptores SET referidos_exitosos = referidos_exitosos + 1 WHERE id = :id'
        )->execute(['id' => $referidoPorId]);
    }

    responder([
        'nombre'     => $nombre,
        'codigo_mio' => $codigoMio,
    ], 201);
}

function newsletter_mi_estado(PDO $pdo): void
{
    $mail = trim(strtolower($_GET['mail'] ?? ''));
    if (!validarEmail($mail)) error('Ingresá un email válido.');

    $stmt = $pdo->prepare(
        'SELECT nombre, codigo_mio, referidos_exitosos
         FROM newsletter_suscriptores WHERE mail = :mail'
    );
    $stmt->execute(['mail' => $mail]);
    $sub = $stmt->fetch();

    if (!$sub) {
        error('Ese email no está suscripto al newsletter todavía.', 404);
    }
    responder($sub);
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
