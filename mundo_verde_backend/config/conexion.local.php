<?php
/**
 * Conexión a la base de datos MySQL vía PDO.
 *
 * Configuración para DESARROLLO LOCAL (XAMPP/WAMP en tu PC).
 * Este archivo NUNCA debería subirse a Hostinger ni usarse en producción.
 */

$host = 'localhost';
$db   = 'mundo_verde';   // nombre real de tu base local
$user = 'root';          // usuario típico de XAMPP
$pass = './TPeEXb7ZJ1v7Wv';               // contraseña del MySQL local
$charset = 'utf8mb4';

// Misma hora argentina que en producción
date_default_timezone_set('America/Argentina/Buenos_Aires');

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$opciones = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '-03:00'",
];

try {
    $pdo = new PDO($dsn, $user, $pass, $opciones);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'error' => 'No se pudo conectar a la base de datos local. Verificá que XAMPP/MySQL esté ' .
                   'corriendo y que la base "mundo_verde" exista con las tablas importadas.',
    ]);
    exit;
}