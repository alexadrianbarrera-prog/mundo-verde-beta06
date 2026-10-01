<?php
/**
 * Banner de anuncios rotativos (.anuncios-track en cada hoja .html).
 *
 * GET /anuncios                -> todos los activos, en orden.
 * GET /anuncios?pagina=arbustos -> solo los activos que aparecen en esa hoja
 *                                  (los marcados "todas" + los que la incluyen).
 *
 * A diferencia de /admin/anuncios (panel, requiere sesión de admin y trae
 * todos, activos e inactivos), este endpoint es público y ya viene filtrado,
 * para que el front no tenga que hacer ese trabajo.
 *
 * La columna `paginas` guarda los identificadores separados por coma, sin
 * espacios (ej.: "arbustos,aromaticas") o la palabra "todas". Los
 * identificadores son los mismos de PAGINAS_ANUNCIO en admin.html y de
 * MV_PAGINA_POR_ARCHIVO en js/api.js.
 */

function anuncios_listar(PDO $pdo): void
{
    $pagina = strtolower(trim($_GET['pagina'] ?? ''));

    if ($pagina !== '') {
        $stmt = $pdo->prepare(
            "SELECT id, texto, orden, paginas
             FROM anuncios
             WHERE activo = 1
               AND (FIND_IN_SET('todas', paginas) > 0 OR FIND_IN_SET(:pagina, paginas) > 0)
             ORDER BY orden, id"
        );
        $stmt->execute(['pagina' => $pagina]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, texto, orden, paginas
             FROM anuncios
             WHERE activo = 1
             ORDER BY orden, id'
        );
    }
    responder($stmt->fetchAll());
}

/**
 * Valida y normaliza el valor de `paginas` que manda el panel.
 * - Solo letras minúsculas, números y guion bajo, separados por coma.
 * - Si incluye "todas", queda solo "todas".
 * - Sin repetidos ni espacios.
 * Devuelve null si el valor no es válido o quedó vacío.
 */
function anuncios_normalizar_paginas(string $valor): ?string
{
    $items = array_values(array_unique(array_filter(array_map(
        fn($p) => strtolower(trim($p)),
        explode(',', $valor)
    ), fn($p) => $p !== '')));

    if (!$items) return null;
    foreach ($items as $p) {
        if (!preg_match('/^[a-z0-9_]{1,40}$/', $p)) return null;
    }
    if (in_array('todas', $items, true)) return 'todas';
    return implode(',', $items);
}
