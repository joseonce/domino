<?php
/**
 * crear.php - Crea una nueva partida en Dominó para Todos
 * Utiliza estrictamente la variable $conexion.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logica_domino.php';

// Limpieza automática preventiva de partidas antiguas (recolector de basura)
purgar_partidas_antiguas($conexion);

$entrada_raw = file_get_contents('php://input');
$datos = json_decode($entrada_raw, true) ?: $_POST;

$nombre = isset($datos['nombre']) ? trim(strip_tags($datos['nombre'])) : '';
$max_jugadores = isset($datos['max_jugadores']) ? intval($datos['max_jugadores']) : 4;
$modo = isset($datos['modo']) && $datos['modo'] === 'parejas' && $max_jugadores === 4 ? 'parejas' : 'individual';
$tipo_bot = 'experto';

if (empty($nombre)) {
    responder_json(['success' => false, 'error' => 'Debes introducir tu nombre para crear la partida.'], 400);
}

if (!in_array($max_jugadores, [2, 3, 4])) {
    $max_jugadores = 4;
}

$caracteres = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
$codigo = '';
for ($i = 0; $i < 6; $i++) {
    $codigo .= $caracteres[rand(0, strlen($caracteres) - 1)];
}

$anfitrion_token = bin2hex(random_bytes(16));

try {
    try {
        $stmtP = $conexion->prepare("INSERT INTO domino_partidas (codigo, anfitrion_token, max_jugadores, modo, tipo_bot, estado, tablero_fichas, pozo, ultimo_timestamp) VALUES (?, ?, ?, ?, ?, 'esperando', '[]', '[]', ?)");
        $stmtP->execute([$codigo, $anfitrion_token, $max_jugadores, $modo, $tipo_bot, time()]);
    } catch (Exception $eCol) {
        try {
            $conexion->exec("ALTER TABLE domino_partidas ADD COLUMN tipo_bot VARCHAR(20) NOT NULL DEFAULT 'experto'");
        } catch (Exception $eAlt) {}
        $stmtP = $conexion->prepare("INSERT INTO domino_partidas (codigo, anfitrion_token, max_jugadores, modo, tipo_bot, estado, tablero_fichas, pozo, ultimo_timestamp) VALUES (?, ?, ?, ?, ?, 'esperando', '[]', '[]', ?)");
        $stmtP->execute([$codigo, $anfitrion_token, $max_jugadores, $modo, $tipo_bot, time()]);
    }
    $partida_id = $conexion->lastInsertId();

    $pareja = ($modo === 'parejas') ? 1 : null;
    $stmtJ = $conexion->prepare("INSERT INTO domino_jugadores (partida_id, nombre, token, es_bot, posicion, pareja, fichas) VALUES (?, ?, ?, 0, 0, ?, '[]')");
    $stmtJ->execute([$partida_id, $nombre, $anfitrion_token, $pareja]);
    $jugador_id = $conexion->lastInsertId();

    responder_json([
        'success' => true,
        'codigo' => $codigo,
        'token' => $anfitrion_token,
        'partida_id' => $partida_id,
        'jugador_id' => $jugador_id,
        'nombre' => $nombre,
        'es_anfitrion' => true,
        'max_jugadores' => $max_jugadores,
        'modo' => $modo,
        'tipo_bot' => $tipo_bot
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al crear la partida: ' . $e->getMessage()], 500);
}
