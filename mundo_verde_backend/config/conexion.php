<?php
/**
 * Conexión a la base de datos MySQL vía PDO.
 *
 * Configuración para Hostinger (rama "desarrollo" / servidor de Hostinger).
 * En Hostinger, cuando el PHP corre en el mismo servidor que la base de
 * datos, el host casi siempre es "localhost" (no la IP pública que se usa
 * para MySQL remoto).
 */

$host = 'localhost';
$db   = 'u366545585_Vivero80';
$user = 'u366545585_MundoVerde';
$pass = './TPeEXb7ZJ1v7Wv'; // <-- reemplazá esto por tu contraseña completa
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
        'error' => 'No se pudo conectar a la base de datos. Verificá el usuario, la contraseña ' .
                   'y que la base "u366545585_Vivero80" exista y tenga las tablas importadas.',
    ]);
    exit;
}
