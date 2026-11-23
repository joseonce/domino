<?php
/**
 * jugar.php - Coloca una ficha jugada por un jugador en Dominó para Todos
 * Utiliza estrictamente la variable $conexion.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logica_domino.php';

$entrada_raw = file_get_contents('php://input');
$datos = json_decode($entrada_raw, true) ?: $_POST;

$codigo = isset($datos['codigo']) ? strtoupper(trim($datos['codigo'])) : '';
$token = isset($datos['token']) ? trim($datos['token']) : '';
$ficha_input = $datos['ficha'] ?? null;
$extremo = isset($datos['extremo']) ? strtolower(trim($datos['extremo'])) : '';

if (empty($codigo) || empty($token) || $ficha_input === null || !in_array($extremo, ['izquierda', 'derecha'])) {
    responder_json(['success' => false, 'error' => 'Parámetros inválidos o incompletos.'], 400);
}

$ficha = null;
if (is_array($ficha_input) && count($ficha_input) === 2) {
    $ficha = [intval($ficha_input[0]), intval($ficha_input[1])];
} elseif (is_string($ficha_input) && strpos($ficha_input, '-') !== false) {
    $partes = explode('-', $ficha_input);
    $ficha = [intval($partes[0]), intval($partes[1])];
}

if (!$ficha) {
    responder_json(['success' => false, 'error' => 'Formato de ficha no válido.'], 400);
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
        responder_json(['success' => false, 'error' => 'No es tu turno de jugar.'], 400);
    }

    $mano = json_decode($jugador['fichas'], true) ?: [];
    $tablero = json_decode($partida['tablero_fichas'], true) ?: [];
    $extremo_izq = $partida['extremo_izq'] !== null ? intval($partida['extremo_izq']) : null;
    $extremo_der = $partida['extremo_der'] !== null ? intval($partida['extremo_der']) : null;

    $tiene_ficha = false;
    $indice_encontrado = -1;
    foreach ($mano as $idx => $m) {
        if (($m[0] === $ficha[0] && $m[1] === $ficha[1]) || ($m[0] === $ficha[1] && $m[1] === $ficha[0])) {
            $tiene_ficha = true;
            $indice_encontrado = $idx;
            break;
        }
    }

    if (!$tiene_ficha) {
        responder_json(['success' => false, 'error' => 'No posees esa ficha en tu mano.'], 400);
    }

    $ficha_colocada = [];
    $nuevo_izq = $extremo_izq;
    $nuevo_der = $extremo_der;

    if ($extremo === 'izquierda') {
        if ($ficha[1] === $extremo_izq) {
            $nuevo_izq = $ficha[0];
            $ficha_colocada = [$ficha[0], $ficha[1]];
        } elseif ($ficha[0] === $extremo_izq) {
            $nuevo_izq = $ficha[1];
            $ficha_colocada = [$ficha[1], $ficha[0]];
        } else {
            responder_json(['success' => false, 'error' => "La ficha no encaja por la izquierda (se requiere un {$extremo_izq})."], 400);
        }
        array_unshift($tablero, $ficha_colocada);
    } else {
        if ($ficha[0] === $extremo_der) {
            $nuevo_der = $ficha[1];
            $ficha_colocada = [$ficha[0], $ficha[1]];
        } elseif ($ficha[1] === $extremo_der) {
            $nuevo_der = $ficha[0];
            $ficha_colocada = [$ficha[1], $ficha[0]];
        } else {
            responder_json(['success' => false, 'error' => "La ficha no encaja por la derecha (se requiere un {$extremo_der})."], 400);
        }
        $tablero[] = $ficha_colocada;
    }

    array_splice($mano, $indice_encontrado, 1);

    if (intval($jugador['es_bot']) === 0) {
        $mano = ordenar_mano_estrategica($mano);
    }

    $desc_corta = describir_ficha_corta($ficha);
    $desc_mesa = "Mesa " . describir_ficha_corta([$nuevo_izq, $nuevo_der]);
    $mov_texto = "{$jugador['nombre']} pone {$desc_corta} por la {$extremo}. {$desc_mesa}.";
    $ficha_str = "{$ficha[0]}-{$ficha[1]}";
    $ahora = time();

    $stmtUpJ = $conexion->prepare("UPDATE domino_jugadores SET fichas = ? WHERE id = ?");
    $stmtUpJ->execute([json_encode($mano), $jugador['id']]);

    $stmtH = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'jugar', ?, ?, ?, ?, ?)");
    $stmtH->execute([$partida_id, $jugador['id'], $ficha_str, $extremo, $mov_texto, $nuevo_izq, $nuevo_der]);

    if (count($mano) === 0) {
        $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET tablero_fichas = ?, extremo_izq = ?, extremo_der = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
        $stmtUpP->execute([json_encode($tablero), $nuevo_izq, $nuevo_der, $mov_texto, $ahora, $partida_id]);

        finalizar_partida($conexion, $partida_id, 'domino', $jugador['id']);

        responder_json([
            'success' => true,
            'mensaje' => '¡Ficha jugada con éxito! Has ganado la ronda por Dominó.',
            'victoria' => true
        ]);
    }

    $siguiente_id = obtener_siguiente_jugador($conexion, $partida_id, $jugador['id']);

    $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET tablero_fichas = ?, extremo_izq = ?, extremo_der = ?, turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
    $stmtUpP->execute([json_encode($tablero), $nuevo_izq, $nuevo_der, $siguiente_id, $mov_texto, $ahora, $partida_id]);

    responder_json([
        'success' => true,
        'mensaje' => 'Ficha jugada con éxito.',
        'nuevo_izq' => $nuevo_izq,
        'nuevo_der' => $nuevo_der,
        'desc_mesa' => $desc_mesa,
        'siguiente_turno_id' => $siguiente_id
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al procesar jugada: ' . $e->getMessage()], 500);
}
