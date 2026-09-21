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
$pass = 'GreenLife80';               // contraseña típica de XAMPP (vacía) - ajustá si la tuya es distinta
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
        'error' => 'No se pudo conectar a la base de datos local. Verificá que XAMPP/MySQL esté ' .
                   'corriendo y que la base "mundo_verde" exista con las tablas importadas.',
    ]);
    exit;
}