<?php
/**
 * config.php - Configuración de Dominó para Todos
 * Incluye la conexión PDO existente ($conexion) y funciones comunes.
 */

// Incluir el archivo de conexión existente en la misma carpeta
if (file_exists(__DIR__ . '/conexion.php')) {
    require_once __DIR__ . '/conexion.php';
}

// Garantizar que la variable de conexión siempre sea $conexion
if (!isset($conexion) && isset($conn)) {
    $conexion = $conn;
}

// Configuración de cabeceras para respuestas JSON en los endpoints (sin caché para actualización instantánea)
function responder_json($datos, $codigo_http = 200) {
    http_response_code($codigo_http);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}
