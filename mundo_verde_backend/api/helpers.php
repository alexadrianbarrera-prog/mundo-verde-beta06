<?php
/**
 * Funciones auxiliares compartidas por todos los endpoints.
 */

function responder($data, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function error(string $mensaje, int $codigo = 400): void
{
    responder(['error' => $mensaje], $codigo);
}

/** Lee y decodifica el body JSON de la request. */
function leerBody(): array
{
    // Cuando la request viene como multipart/form-data (ej: pedido con
    // comprobante adjunto), PHP ya consume el body y lo vuelca en $_POST /
    // $_FILES — php://input queda vacío. En ese caso el JSON del pedido
    // viaja en el campo de texto "datos" (ver api.js → mvPedidos.crear).
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    if (stripos($contentType, 'multipart/form-data') === 0 || stripos($contentType, 'multipart/form-data;') !== false) {
        if (!isset($_POST['datos'])) return [];
        $data = json_decode($_POST['datos'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            error('El campo "datos" debe ser JSON válido.', 400);
        }
        return $data;
    }

    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        error('El cuerpo de la petición debe ser JSON válido.', 400);
    }
    return $data;
}

/** Extrae el token Bearer del header Authorization (si viene). */
function tokenDelHeader(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? apache_request_headers()['Authorization']
        ?? null;

    if (!$header) return null;
    if (preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Valida el token contra la tabla `sesiones` y devuelve el usuario logueado
 * (sin password_hash). Si $obligatorio es true y no hay sesión válida, corta
 * la ejecución con 401.
 */
function usuarioAutenticado(PDO $pdo, bool $obligatorio = true): ?array
{
    $token = tokenDelHeader();
    if (!$token) {
        if ($obligatorio) error('No autenticado. Iniciá sesión para continuar.', 401);
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.nombre, u.email, u.telefono, u.rol, u.activo
         FROM sesiones s
         JOIN usuarios u ON u.id = s.usuario_id
         WHERE s.token = :token AND s.expira_en > NOW()'
    );
    $stmt->execute(['token' => $token]);
    $usuario = $stmt->fetch();

    if (!$usuario || !$usuario['activo']) {
        if ($obligatorio) error('Tu sesión expiró o no es válida. Iniciá sesión de nuevo.', 401);
        return null;
    }
    return $usuario;
}

/**
 * Exige sesión válida y que el rol del usuario esté dentro de los
 * permitidos. Se usa para dar permisos parciales (ej: el vendedor solo
 * puede tocar ciertas acciones) sin duplicar la lógica de autenticación.
 */
function requerirRol(PDO $pdo, array $rolesPermitidos): array
{
    $usuario = usuarioAutenticado($pdo, true);
    if (!in_array($usuario['rol'], $rolesPermitidos, true)) {
        error('No tenés permisos para hacer esto.', 403);
    }
    return $usuario;
}

/** Igual que usuarioAutenticado, pero además exige rol admin (superadmin). */
function requerirAdmin(PDO $pdo): array
{
    return requerirRol($pdo, ['admin']);
}

function generarToken(): string
{
    return bin2hex(random_bytes(32));
}

function validarEmail(?string $email): bool
{
    return $email && filter_var($email, FILTER_VALIDATE_EMAIL);
}
