<?php
/**
 * Catálogo público de productos y servicios.
 * GET /productos                    -> todos (activos e inactivos)
 * GET /productos?rubro=plantas      -> un rubro (plantas, articulos, talleres,
 *                                      ramos, servicios)
 * GET /productos?categoria=interior -> una categoría (ver catalogo_rubros.php)
 * Los dos filtros se pueden combinar.
 *
 * Nota: se incluyen los productos inactivos (activo = 0) a propósito,
 * junto con el campo "activo", para que el front pueda mostrar
 * "SIN STOCK" en vez de ocultar la tarjeta directamente. Si en algún
 * momento se quiere volver a ocultarlos del todo, hay que agregar
 * de nuevo "WHERE activo = 1" acá abajo.
 */
require_once __DIR__ . '/catalogo_rubros.php';

function productos_listar(PDO $pdo): void
{
    $rubro     = trim($_GET['rubro'] ?? '');
    $categoria = trim($_GET['categoria'] ?? '');

    $where  = [];
    $params = [];
    if ($rubro !== '') {
        $where[] = 'rubro = :rubro';
        $params['rubro'] = $rubro;
    }
    if ($categoria !== '') {
        $where[] = 'categoria = :categoria';
        $params['categoria'] = $categoria;
    }

    $sql = 'SELECT id, codigo, rubro, nombre, categoria, tipo, precio, imagen, activo
            FROM productos'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
         . ' ORDER BY rubro, categoria, nombre';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

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
