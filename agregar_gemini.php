<?php
/**
 * agregar_gemini.php - Añade un jugador automático (Máquina Experta) a Dominó para Todos
 * Utiliza estrictamente la variable $conexion.
 */

require_once __DIR__ . '/config.php';

$entrada_raw = file_get_contents('php://input');
$datos = json_decode($entrada_raw, true) ?: $_POST;

$codigo = isset($datos['codigo']) ? strtoupper(trim($datos['codigo'])) : '';

if (empty($codigo)) {
    responder_json(['success' => false, 'error' => 'Código de sala no proporcionado.'], 400);
}

try {
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE codigo = ?");
    $stmtP->execute([$codigo]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partida) {
        responder_json(['success' => false, 'error' => 'No se encontró la partida especificada.'], 404);
    }

    if ($partida['estado'] !== 'esperando') {
        responder_json(['success' => false, 'error' => 'No se pueden añadir bots: la partida ya está en curso o terminada.'], 400);
    }

    $partida_id = $partida['id'];

    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM domino_jugadores WHERE partida_id = ?");
    $stmtCount->execute([$partida_id]);
    $num_actuales = (int)$stmtCount->fetchColumn();

    if ($num_actuales >= intval($partida['max_jugadores'])) {
        responder_json(['success' => false, 'error' => 'La sala ya está completa. No caben más jugadores.'], 400);
    }

    $tipo_bot = 'experto';

    // Contar bots existentes en la partida para asignar el número correlativo (Máquina 1, Máquina 2, Máquina 3)
    $stmtExp = $conexion->prepare("SELECT COUNT(*) FROM domino_jugadores WHERE partida_id = ? AND es_bot = 1");
    $stmtExp->execute([$partida_id]);
    $num_exp = (int)$stmtExp->fetchColumn();
    $nombres_maquinas = ["Máquina 1", "Máquina 2", "Máquina 3"];
    $nombre_bot = $nombres_maquinas[$num_exp] ?? ("Máquina " . ($num_exp + 1));

    $posicion = $num_actuales;
    $pareja = null;
    if ($partida['modo'] === 'parejas') {
        $pareja = ($posicion % 2 === 0) ? 1 : 2;
    }

    $bot_token = 'BOT_' . bin2hex(random_bytes(14));

    try {
        $stmtJ = $conexion->prepare("INSERT INTO domino_jugadores (partida_id, nombre, token, es_bot, tipo_bot, posicion, pareja, fichas) VALUES (?, ?, ?, 1, ?, ?, ?, '[]')");
        $stmtJ->execute([$partida_id, $nombre_bot, $bot_token, $tipo_bot, $posicion, $pareja]);
    } catch (Exception $eCol) {
        try {
            $conexion->exec("ALTER TABLE domino_jugadores ADD COLUMN tipo_bot VARCHAR(20) NOT NULL DEFAULT 'experto'");
        } catch (Exception $eAlt) {}
        $stmtJ = $conexion->prepare("INSERT INTO domino_jugadores (partida_id, nombre, token, es_bot, tipo_bot, posicion, pareja, fichas) VALUES (?, ?, ?, 1, ?, ?, ?, '[]')");
        $stmtJ->execute([$partida_id, $nombre_bot, $bot_token, $tipo_bot, $posicion, $pareja]);
    }
    $bot_id = $conexion->lastInsertId();

    responder_json([
        'success' => true,
        'bot_id' => $bot_id,
        'bot_nombre' => $nombre_bot,
        'tipo_bot' => $tipo_bot,
        'posicion' => $posicion,
        'pareja' => $pareja,
        'total_jugadores' => $num_actuales + 1
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al agregar Máquina: ' . $e->getMessage()], 500);
}
