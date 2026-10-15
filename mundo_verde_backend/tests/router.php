<?php
// Router para `php -S` (reemplaza al .htaccess/mod_rewrite de Apache): manda todo /api/* a api/index.php con PATH_INFO.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/mundo_verde_backend/api(/.*)?$#', $uri, $m)) {
    $_SERVER['SCRIPT_NAME'] = '/mundo_verde_backend/api/index.php';
    $_SERVER['PATH_INFO']   = $m[1] ?? '';
    require __DIR__ . '/../api/index.php';
    return true;
}
http_response_code(404);
