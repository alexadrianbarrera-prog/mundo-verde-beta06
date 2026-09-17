<?php
/**
 * Front controller de la API de Mundo Verde.
 * Todas las requests a /mundo_verde_backend/api/* pasan por acá
 * (ver .htaccess) y se despachan según el método + la ruta.
 */

// --- CORS (útil si el front y el backend terminan en puertos/dominios distintos) ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/../config/conexion.php'; // define $pdo
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/controllers/auth.php';
require_once __DIR__ . '/controllers/productos.php';
require_once __DIR__ . '/controllers/anuncios.php';
require_once __DIR__ . '/controllers/newsletter.php';
require_once __DIR__ . '/controllers/pedidos.php';
require_once __DIR__ . '/controllers/admin.php';

// --- Determinar la ruta pedida (todo lo que viene después de /api) ---
$pathInfo = $_SERVER['PATH_INFO'] ?? '';
if ($pathInfo === '') {
    // Fallback por si el servidor no pobló PATH_INFO: lo derivamos de la URI.
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $uriPath   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (str_starts_with($uriPath, $scriptDir)) {
        $pathInfo = substr($uriPath, strlen($scriptDir));
    }
}

$segmentos = array_values(array_filter(explode('/', $pathInfo), fn($s) => $s !== ''));
$metodo    = $_SERVER['REQUEST_METHOD'];

try {
    despachar($pdo, $metodo, $segmentos);
} catch (Throwable $e) {
    // Nunca exponer detalles internos del error al cliente.
    error_log('[mundo_verde_backend] ' . $e->getMessage());
    error('Ocurrió un error inesperado en el servidor.', 500);
}

/**
 * Tabla de ruteo. $segmentos ya viene sin barras (["productos"],
 * ["newsletter", "5", "toggle"], ["admin", "usuarios", "3"], etc).
 */
function despachar(PDO $pdo, string $metodo, array $segmentos): void
{
    $r0 = $segmentos[0] ?? '';
    $r1 = $segmentos[1] ?? '';
    $r2 = $segmentos[2] ?? '';
    $r3 = $segmentos[3] ?? '';

    // ── Autenticación ──────────────────────────────────────
    if ($r0 === 'registro' && $metodo === 'POST')               { auth_registro($pdo); return; }
    if ($r0 === 'login' && $metodo === 'POST')                  { auth_login($pdo); return; }
    if ($r0 === 'logout' && $metodo === 'POST')                 { auth_logout($pdo); return; }
    if ($r0 === 'usuario' && $r1 === 'me' && $metodo === 'GET') { auth_me($pdo); return; }
    if ($r0 === 'recuperar' && $metodo === 'POST')               { auth_recuperar($pdo); return; }
    if ($r0 === 'restablecer' && $metodo === 'POST')             { auth_restablecer($pdo); return; }

    // ── Newsletter ──────────────────────────────────────────
    if ($r0 === 'newsletter' && $r1 === 'mi-estado' && $metodo === 'GET')             { newsletter_mi_estado($pdo); return; }
    if ($r0 === 'newsletter' && $r1 !== '' && $r2 === 'toggle' && $metodo === 'PATCH') { newsletter_toggle($pdo, $r1); return; }
    if ($r0 === 'newsletter' && $r1 !== '' && $r2 === '' && $metodo === 'DELETE')      { newsletter_eliminar($pdo, $r1); return; }
    if ($r0 === 'newsletter' && $r1 === '' && $metodo === 'POST')                      { newsletter_suscribir($pdo); return; }
    if ($r0 === 'newsletter' && $r1 === '' && $metodo === 'GET')                       { newsletter_listar($pdo); return; }

    // ── Productos ───────────────────────────────────────────
    if ($r0 === 'productos' && $metodo === 'GET') { productos_listar($pdo); return; }

    // ── Anuncios (banner rotativo, público) ────────────────
    if ($r0 === 'anuncios' && $metodo === 'GET') { anuncios_listar($pdo); return; }

    // ── Pedidos ─────────────────────────────────────────────
    if ($r0 === 'pedidos' && $metodo === 'POST') { pedidos_crear($pdo); return; }
    if ($r0 === 'pedidos' && $metodo === 'GET')  { pedidos_listar($pdo); return; }
    if ($r0 === 'pedidos' && $r1 !== '' && $r2 === 'comprobante' && $metodo === 'POST') { pedidos_subir_comprobante($pdo, $r1); return; }

    // ── Admin (CRUD genérico whitelisteado) ────────────────
    if ($r0 === 'admin' && $r1 === 'tablas' && $metodo === 'GET')                        { admin_tablas($pdo); return; }
    if ($r0 === 'admin' && $r1 !== '' && $r2 === '' && $metodo === 'GET')                { admin_listar($pdo, $r1); return; }
    if ($r0 === 'admin' && $r1 !== '' && $r2 === '' && $metodo === 'POST')               { admin_crear($pdo, $r1); return; }
    if ($r0 === 'admin' && $r1 !== '' && $r2 !== '' && $r3 === 'toggle' && $metodo === 'PATCH') { admin_toggle($pdo, $r1, $r2); return; }
    if ($r0 === 'admin' && $r1 !== '' && $r2 !== '' && $metodo === 'PATCH')              { admin_editar($pdo, $r1, $r2); return; }
    if ($r0 === 'admin' && $r1 !== '' && $r2 !== '' && $metodo === 'DELETE')             { admin_eliminar($pdo, $r1, $r2); return; }

    error('Ruta no encontrada: ' . $metodo . ' /' . implode('/', $segmentos), 404);
}
