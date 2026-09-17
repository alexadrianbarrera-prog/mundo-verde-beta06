<?php
/**
 * Editor de archivos del sitio, reservado a los roles "admin" y
 * "mantenimiento". A propósito es MUY restrictivo:
 *
 *   - Solo permite tocar archivos .html, .css y .js.
 *   - Solo dentro de la raíz del sitio (donde vive login.html, css/, js/,
 *     etc), NUNCA dentro de mundo_verde_backend/ (ahí vive el PHP, la
 *     conexión a la base y los comprobantes subidos — si alguien pudiera
 *     escribir ahí podría, por ejemplo, subir código PHP y tomar control
 *     del servidor).
 *   - Toda ruta se valida contra path traversal (../, symlinks, etc).
 *
 * Estructura de carpetas asumida (la misma que ya usa el resto del backend,
 * ver comentario de API_BASE en js/api.js):
 *
 *   <raíz del sitio>/            <- acá vive login.html, css/, js/, img/...
 *   <raíz del sitio>/mundo_verde_backend/
 *       api/
 *           index.php, helpers.php, controllers/archivos.php (este archivo)
 *       config/conexion.php
 *       uploads/
 */

const ARCHIVOS_EXTENSIONES_PERMITIDAS = ['html', 'css', 'js'];
const ARCHIVOS_TAMANO_MAX = 2 * 1024 * 1024; // 2 MB, de sobra para html/css/js de mano

/** Raíz del sitio (un nivel arriba de mundo_verde_backend/). */
function archivos_raiz(): string
{
    $raiz = realpath(__DIR__ . '/../../../');
    if ($raiz === false) {
        error('No se pudo resolver la raíz del sitio.', 500);
    }
    return $raiz;
}

/**
 * Valida y resuelve una ruta relativa (la que manda el front, ej.
 * "css/styles.css" o "5_0_newsletter.html") contra la raíz del sitio.
 * Corta la ejecución si la ruta:
 *   - intenta salir de la raíz del sitio (".." o similar),
 *   - cae dentro de mundo_verde_backend/,
 *   - no termina en .html, .css o .js.
 * Devuelve la ruta absoluta en el filesystem.
 */
function archivos_resolver(string $rutaRelativa): string
{
    $raiz = archivos_raiz();
    $rutaRelativa = ltrim(str_replace('\\', '/', trim($rutaRelativa)), '/');

    if ($rutaRelativa === '' || str_contains($rutaRelativa, '..')) {
        error('Ruta inválida.', 400);
    }
    if (str_starts_with($rutaRelativa, 'mundo_verde_backend/')) {
        error('No se pueden editar archivos del backend desde este panel.', 403);
    }

    $extension = strtolower(pathinfo($rutaRelativa, PATHINFO_EXTENSION));
    if (!in_array($extension, ARCHIVOS_EXTENSIONES_PERMITIDAS, true)) {
        error('Solo se pueden editar archivos .html, .css o .js.', 400);
    }

    $rutaAbsoluta = $raiz . '/' . $rutaRelativa;

    // Confirmamos con realpath (sigue existiendo el archivo o, si es nuevo,
    // al menos su carpeta) que seguimos adentro de la raíz permitida. Esto
    // es lo que realmente frena un intento de path traversal disfrazado.
    $refExistente = realpath($rutaAbsoluta) ?: realpath(dirname($rutaAbsoluta));
    if ($refExistente === false || !str_starts_with($refExistente . '/', $raiz . '/')) {
        error('Ruta inválida.', 400);
    }

    return $rutaAbsoluta;
}

/** GET /archivos — lista todos los .html/.css/.js editables del sitio. */
function archivos_listar(PDO $pdo): void
{
    requerirRol($pdo, ['admin', 'mantenimiento'], 'No tenés permisos para acceder al editor de archivos.');
    $raiz = archivos_raiz();

    $resultado = [];
    $iterador = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($raiz, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterador as $archivo) {
        if (!$archivo->isFile()) continue;

        $rutaAbs = str_replace('\\', '/', $archivo->getPathname());
        $rutaRel = ltrim(substr($rutaAbs, strlen($raiz)), '/');

        if (str_starts_with($rutaRel, 'mundo_verde_backend/')) continue;

        $extension = strtolower(pathinfo($rutaRel, PATHINFO_EXTENSION));
        if (!in_array($extension, ARCHIVOS_EXTENSIONES_PERMITIDAS, true)) continue;

        $resultado[] = [
            'ruta' => $rutaRel,
            'tipo' => $extension,
            'peso' => $archivo->getSize(),
        ];
    }
    usort($resultado, fn($a, $b) => strcmp($a['ruta'], $b['ruta']));
    responder($resultado);
}

/** GET /archivos/contenido?ruta=... */
function archivos_leer(PDO $pdo): void
{
    requerirRol($pdo, ['admin', 'mantenimiento'], 'No tenés permisos para acceder al editor de archivos.');

    $rutaRelativa = trim($_GET['ruta'] ?? '');
    $rutaAbsoluta = archivos_resolver($rutaRelativa);

    if (!is_file($rutaAbsoluta)) error('El archivo no existe.', 404);

    responder([
        'ruta'      => $rutaRelativa,
        'contenido' => file_get_contents($rutaAbsoluta),
    ]);
}

/** POST /archivos/contenido — guarda contenido nuevo (crea el archivo si no existía). */
function archivos_guardar(PDO $pdo): void
{
    requerirRol($pdo, ['admin', 'mantenimiento'], 'No tenés permisos para acceder al editor de archivos.');
    $body = leerBody();

    $rutaRelativa = trim($body['ruta'] ?? '');
    $contenido    = $body['contenido'] ?? null;

    if ($rutaRelativa === '') error('Falta la ruta del archivo.');
    if ($contenido === null || !is_string($contenido)) error('Falta el contenido del archivo.');
    if (strlen($contenido) > ARCHIVOS_TAMANO_MAX) error('El archivo es demasiado grande (máx 2 MB).');

    $rutaAbsoluta = archivos_resolver($rutaRelativa);
    $existiaAntes = is_file($rutaAbsoluta);

    // Backup automático antes de pisar un archivo existente, para poder
    // revertir a mano si algo se rompe. No se hace backup en archivos
    // nuevos porque no hay nada previo que guardar.
    if ($existiaAntes) {
        $carpetaBackups = archivos_raiz() . '/mundo_verde_backend/uploads/backups_archivos/';
        if (!is_dir($carpetaBackups) && !mkdir($carpetaBackups, 0755, true) && !is_dir($carpetaBackups)) {
            error('No se pudo preparar el almacenamiento de backups.', 500);
        }
        $nombreBackup = str_replace('/', '__', $rutaRelativa) . '.' . date('Ymd_His') . '.bak';
        @copy($rutaAbsoluta, $carpetaBackups . $nombreBackup);
    } else {
        // Archivo nuevo: nos aseguramos de que la carpeta contenedora exista.
        $carpetaDestino = dirname($rutaAbsoluta);
        if (!is_dir($carpetaDestino) && !mkdir($carpetaDestino, 0755, true) && !is_dir($carpetaDestino)) {
            error('No se pudo crear la carpeta de destino.', 500);
        }
    }

    if (file_put_contents($rutaAbsoluta, $contenido) === false) {
        error('No se pudo guardar el archivo. Intentá de nuevo.', 500);
    }

    responder(['mensaje' => $existiaAntes ? 'Archivo actualizado.' : 'Archivo creado.']);
}
