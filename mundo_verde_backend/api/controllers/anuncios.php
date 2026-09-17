<?php
/**
 * Banner de anuncios rotativos (.anuncio en script.js/styles.css).
 * GET /anuncios -> solo los activos, en el orden en que deben mostrarse
 * en la cinta. A diferencia de /admin/anuncios (panel, requiere sesión
 * de admin y trae todos, activos e inactivos), este endpoint es público
 * y ya viene filtrado, para que el front no tenga que hacer ese trabajo.
 */

function anuncios_listar(PDO $pdo): void
{
    $stmt = $pdo->query(
        'SELECT id, texto, orden
         FROM anuncios
         WHERE activo = 1
         ORDER BY orden, id'
    );
    responder($stmt->fetchAll());
}
