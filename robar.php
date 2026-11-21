<?php
/**
 * robar.php - Roba una ficha del pozo en Dominó para Todos
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
        responder_json(['success' => false, 'error' => 'No puedes robar: ya tienes al menos una ficha jugable en tu mano.'], 400);
    }

    if (empty($pozo)) {
        responder_json(['success' => false, 'error' => 'El pozo está vacío. Debes pasar.'], 400);
    }

    $ficha_robada = array_shift($pozo);
    $mano[] = $ficha_robada;
    if (intval($jugador['es_bot']) === 0) {
        $mano = ordenar_mano_estrategica($mano);
    }

    $desc_robada = describir_ficha($ficha_robada);
    $mov_texto = "{$jugador['nombre']} ha robado una ficha del pozo.";

    $stmtUpJ = $conexion->prepare("UPDATE domino_jugadores SET fichas = ? WHERE id = ?");
    $stmtUpJ->execute([json_encode($mano), $jugador['id']]);

    $stmtH = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'robar', NULL, NULL, ?, ?, ?)");
    $stmtH->execute([$partida_id, $jugador['id'], $mov_texto, $extremo_izq, $extremo_der]);

    $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET pozo = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
    $stmtUpP->execute([json_encode($pozo), $mov_texto, time(), $partida_id]);

    $encaja_izq = ($extremo_izq === null || $ficha_robada[0] === $extremo_izq || $ficha_robada[1] === $extremo_izq);
    $encaja_der = ($extremo_der === null || $ficha_robada[0] === $extremo_der || $ficha_robada[1] === $extremo_der);
    $es_valida = ($encaja_izq || $encaja_der);

    if ($es_valida) {
        if ($encaja_izq && $encaja_der && $extremo_izq !== $extremo_der) {
            $validez_texto = "y es válida para jugar por ambos extremos";
        } elseif ($encaja_izq && !$encaja_der) {
            $validez_texto = "y es válida para jugar por la izquierda ({$extremo_izq})";
        } elseif ($encaja_der && !$encaja_izq) {
            $validez_texto = "y es válida para jugar por la derecha ({$extremo_der})";
        } else {
            $validez_texto = "y es válida para jugar";
        }
        $mensaje = "Has robado la {$desc_robada}, {$validez_texto}.";
    } else {
        $quedan = count($pozo);
        $siguiente = ($quedan > 0) ? "No es válida. Debes volver a robar." : "No es válida y no quedan más fichas. Debes pasar.";
        $mensaje = "Has robado la {$desc_robada}. {$siguiente}";
    }

    responder_json([
        'success' => true,
        'mensaje' => $mensaje,
        'ficha' => $ficha_robada,
        'desc' => $desc_robada,
        'es_valida' => $es_valida,
        'encaja_izq' => $encaja_izq,
        'encaja_der' => $encaja_der,
        'fichas_restantes_pozo' => count($pozo)
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al robar del pozo: ' . $e->getMessage()], 500);
}
