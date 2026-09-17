<?php
/**
 * Catálogo público de productos y servicios.
 * GET /productos            -> todos (activos e inactivos)
 * GET /productos?categoria= -> filtrados por categoría (arbustos, aromaticas,
 *                               exterior, interior, insumos, servicios)
 *
 * Nota: se incluyen los productos inactivos (activo = 0) a propósito,
 * junto con el campo "activo", para que el front pueda mostrar
 * "SIN STOCK" en vez de ocultar la tarjeta directamente. Si en algún
 * momento se quiere volver a ocultarlos del todo, hay que agregar
 * de nuevo "WHERE activo = 1" acá abajo.
 */

function productos_listar(PDO $pdo): void
{
    $categoria = trim($_GET['categoria'] ?? '');

    if ($categoria !== '') {
        $stmt = $pdo->prepare(
            'SELECT id, codigo, nombre, categoria, tipo, precio, imagen, activo
             FROM productos
             WHERE categoria = :categoria
             ORDER BY nombre'
        );
        $stmt->execute(['categoria' => $categoria]);
    } else {
        $stmt = $pdo->query(
            'SELECT id, codigo, nombre, categoria, tipo, precio, imagen, activo
             FROM productos
             ORDER BY categoria, nombre'
        );
    }

    $productos = $stmt->fetchAll();
    foreach ($productos as &$p) {
        $p['precio'] = (float)$p['precio'];
        // MySQL devuelve tinyint como string ('0'/'1'); en JS un string
        // '0' es "truthy", así que sin este cast el front nunca iba a
        // detectar los productos inactivos.
        $p['activo'] = (int)$p['activo'];
    }
    responder($productos);
}
