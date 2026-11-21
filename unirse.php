<?php
/**
 * unirse.php - Permite a un jugador unirse a Dominó para Todos
 * Utiliza estrictamente la variable $conexion.
 */

require_once __DIR__ . '/config.php';

$entrada_raw = file_get_contents('php://input');
$datos = json_decode($entrada_raw, true) ?: $_POST;

$codigo = isset($datos['codigo']) ? strtoupper(trim($datos['codigo'])) : '';
$nombre = isset($datos['nombre']) ? trim(strip_tags($datos['nombre'])) : '';

if (empty($codigo) || empty($nombre)) {
    responder_json(['success' => false, 'error' => 'Debes indicar el código de sala y tu nombre.'], 400);
}

try {
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE codigo = ?");
    $stmtP->execute([$codigo]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partida) {
        responder_json(['success' => false, 'error' => 'No se encontró ninguna sala con ese código.'], 404);
    }

    // Si la partida ya ha finalizado, permitir cargarla en modo estudio
    if ($partida['estado'] === 'terminada') {
        responder_json([
            'success' => true,
            'partida_terminada' => true,
            'codigo' => $codigo,
            'mensaje' => 'Esta partida ya ha finalizado. Cargando el visor de estudio de la partida...'
        ]);
    }

    if ($partida['estado'] === 'jugando') {
        responder_json(['success' => false, 'error' => 'La partida ya está en curso y no admite nuevos participantes.'], 400);
    }

    $partida_id = $partida['id'];

    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM domino_jugadores WHERE partida_id = ?");
    $stmtCount->execute([$partida_id]);
    $num_actuales = (int)$stmtCount->fetchColumn();

    if ($num_actuales >= intval($partida['max_jugadores'])) {
        responder_json(['success' => false, 'error' => 'La sala ya está completa.'], 400);
    }

    $posicion = $num_actuales;
    $pareja = null;
    if ($partida['modo'] === 'parejas') {
        $pareja = ($posicion % 2 === 0) ? 1 : 2;
    }

    $token = bin2hex(random_bytes(16));

    $stmtJ = $conexion->prepare("INSERT INTO domino_jugadores (partida_id, nombre, token, es_bot, posicion, pareja, fichas) VALUES (?, ?, ?, 0, ?, ?, '[]')");
    $stmtJ->execute([$partida_id, $nombre, $token, $posicion, $pareja]);
    $jugador_id = $conexion->lastInsertId();

    responder_json([
        'success' => true,
        'codigo' => $codigo,
        'token' => $token,
        'partida_id' => $partida_id,
        'jugador_id' => $jugador_id,
        'nombre' => $nombre,
        'es_anfitrion' => false,
        'posicion' => $posicion,
        'pareja' => $pareja,
        'modo' => $partida['modo']
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al unirse: ' . $e->getMessage()], 500);
}
