<?php
/**
 * Punto de entrada único para la conexión a la base de datos.
 *
 * Detecta automáticamente si el PHP está corriendo en tu PC (local)
 * o en el servidor de Hostinger, y carga el archivo de configuración
 * correspondiente. Así este mismo conexion.php sirve para los dos
 * entornos sin tener que tocar nada a mano.
 */

$esLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1']);

if ($esLocal) {
    require __DIR__ . '/conexion.local.php';
} else {
    require __DIR__ . '/conexion.produccion.php';
}