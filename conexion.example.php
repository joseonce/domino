<?php
/**
 * conexion.example.php - Plantilla de conexión a la base de datos MySQL
 * Renombra este archivo a conexion.php y completa con tus credenciales reales.
 */

$host = "localhost";
$user = "tu_usuario_de_db";
$pass = "tu_contrasena_de_db";
$db   = "nombre_de_tu_base_de_datos";
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $conexion = new PDO($dsn, $user, $pass, $options);
    $conexion->exec("SET NAMES 'utf8mb4'");
} catch (\PDOException $e) {
    die('Error de conexión a la base de datos: ' . $e->getMessage());
}
?>
