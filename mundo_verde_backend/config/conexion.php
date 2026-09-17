<?php
/**
 * Conexión a la base de datos MySQL vía PDO.
 * XAMPP se reseteó a su instalación de fábrica: usuario "root" con
 * contraseña vacía, MySQL escuchando en 127.0.0.1:3306. Coincide con
 * lo que usa phpMyAdmin (ver config.inc.php).
 * Si en algún momento le ponés contraseña a root, actualizala acá.
 */

$host = '127.0.0.1';
$db   = 'mundo_verde';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opciones);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'No se pudo conectar a la base de datos. Verificá que MySQL esté ' .
                   'corriendo en XAMPP y que la base "mundo_verde" exista (importá mundo_verde.sql).',
    ]);
    exit;
}
