<?php
/**
 * pasar.php - Pasa el turno en Dominó para Todos
 * Utiliza estrictamente la variable $conexion.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logica_domino.php';

$entrada_raw = file_get_contents('php://input');
$datos = json_decode($entrada_raw, true) ?: $_POST;

$codigo = isset($datos['codigo']) ? strtoupper(trim($datos['codigo'])) : '';
$token = isset($datos['token']) ? trim($datos['token']) : '';

if (empty($codigo) || empty($token)) {
    responder_json(['success' => false, 'error' => 'Parámetros incompletos.'], 400);
}

try {
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE codigo = ? FOR UPDATE");
    $stmtP->execute([$codigo]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partida || $partida['estado'] !== 'jugando') {
        responder_json(['success' => false, 'error' => 'La partida no está en curso.'], 400);
    }

    $partida_id = $partida['id'];

    $stmtJ = $conexion->prepare("SELECT * FROM domino_jugadores WHERE partida_id = ? AND token = ?");
    $stmtJ->execute([$partida_id, $token]);
    $jugador = $stmtJ->fetch(PDO::FETCH_ASSOC);

    if (!$jugador) {
        responder_json(['success' => false, 'error' => 'Jugador no identificado.'], 403);
    }

    if (intval($partida['turno_jugador_id']) !== intval($jugador['id'])) {
        responder_json(['success' => false, 'error' => 'No es tu turno.'], 400);
    }

    $mano = json_decode($jugador['fichas'], true) ?: [];
    $pozo = json_decode($partida['pozo'], true) ?: [];
    $extremo_izq = $partida['extremo_izq'] !== null ? intval($partida['extremo_izq']) : null;
    $extremo_der = $partida['extremo_der'] !== null ? intval($partida['extremo_der']) : null;

    $jugables = obtener_fichas_jugables($mano, $extremo_izq, $extremo_der);
    if (!empty($jugables)) {
        responder_json(['success' => false, 'error' => 'No puedes pasar: tienes al menos una ficha jugable.'], 400);
    }

    if (!empty($pozo)) {
        responder_json(['success' => false, 'error' => 'No puedes pasar mientras queden fichas en el pozo. Debes robar.'], 400);
    }

    $mov_texto = "{$jugador['nombre']} pasa.";
    $ahora = time();

    $stmtH = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'pasar', NULL, NULL, ?, ?, ?)");
    $stmtH->execute([$partida_id, $jugador['id'], $mov_texto, $extremo_izq, $extremo_der]);

    $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM domino_jugadores WHERE partida_id = ?");
    $stmtCount->execute([$partida_id]);
    $num_jugadores = (int)$stmtCount->fetchColumn();

    if (verificar_tranca($conexion, $partida_id, $num_jugadores)) {
        $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
        $stmtUpP->execute([$mov_texto, $ahora, $partida_id]);

        finalizar_partida($conexion, $partida_id, 'tranca');

        responder_json([
            'success' => true,
            'mensaje' => 'Has pasado. ¡La partida se ha cerrado por Tranca!',
            'tranca' => true
        ]);
    }

    $siguiente_id = obtener_siguiente_jugador($conexion, $partida_id, $jugador['id']);

    $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
    $stmtUpP->execute([$siguiente_id, $mov_texto, $ahora, $partida_id]);

    responder_json([
        'success' => true,
        'mensaje' => 'Has pasado.',
        'siguiente_turno_id' => $siguiente_id
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al pasar: ' . $e->getMessage()], 500);
}
