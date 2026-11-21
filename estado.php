<?php
/**
 * estado.php - Consulta el estado actual de la partida en Dominó para Todos
 * Utiliza estrictamente la variable $conexion.
 * Anti-trampas: NUNCA envía las fichas de los rivales.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/logica_domino.php';

$codigo = isset($_GET['codigo']) ? strtoupper(trim($_GET['codigo'])) : '';
$token = isset($_GET['token']) ? trim($_GET['token']) : '';

if (empty($codigo)) {
    responder_json(['success' => false, 'error' => 'Código de partida no proporcionado.'], 400);
}

try {
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE codigo = ?");
    $stmtP->execute([$codigo]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partida) {
        responder_json(['success' => false, 'error' => 'Partida no encontrada.'], 404);
    }

    $partida_id = $partida['id'];

    // Si la partida está en curso y el turno actual es de un Bot, procesar turno
    if ($partida['estado'] === 'jugando' && !empty($partida['turno_jugador_id'])) {
        $stmtTurnoJ = $conexion->prepare("SELECT es_bot FROM domino_jugadores WHERE id = ?");
        $stmtTurnoJ->execute([$partida['turno_jugador_id']]);
        $es_bot_turno = (int)$stmtTurnoJ->fetchColumn();

        if ($es_bot_turno === 1) {
            $ahora = time();
            $ultimo_ts = intval($partida['ultimo_timestamp']);

            // Comprobar si el turno anterior fue de un bot o del jugador humano
            $stmtUltimoH = $conexion->prepare("SELECT j.es_bot FROM domino_historial h JOIN domino_jugadores j ON h.jugador_id = j.id WHERE h.partida_id = ? ORDER BY h.id DESC LIMIT 1");
            $stmtUltimoH->execute([$partida_id]);
            $ultimo_fue_bot = (int)$stmtUltimoH->fetchColumn();

            // Si el anterior fue un bot, esperar 1 segundo para cadencia de voz natural.
            // Si el anterior fue el jugador humano, procesar de inmediato (0s) sin demoras.
            $espera_necesaria = ($ultimo_fue_bot === 1) ? 1 : 0;
            if (($ahora - $ultimo_ts) >= $espera_necesaria) {
                procesar_turno_bot($conexion, $partida_id);

                // Recargar datos actualizados tras la jugada del bot
                $stmtP->execute([$codigo]);
                $partida = $stmtP->fetch(PDO::FETCH_ASSOC);
            }
        }
    }

    $stmtJ = $conexion->prepare("SELECT id, nombre, token, es_bot, IFNULL(tipo_bot, 'experto') as tipo_bot, posicion, pareja, fichas, puntuacion FROM domino_jugadores WHERE partida_id = ? ORDER BY posicion ASC");
    try {
        $stmtJ->execute([$partida_id]);
    } catch (Exception $eCol) {
        $stmtJ = $conexion->prepare("SELECT id, nombre, token, es_bot, 'experto' as tipo_bot, posicion, pareja, fichas, puntuacion FROM domino_jugadores WHERE partida_id = ? ORDER BY posicion ASC");
        $stmtJ->execute([$partida_id]);
    }
    $todos_jugadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    $jugadores_publicos = [];
    $mi_jugador = null;
    $mi_mano = [];

    $extremo_izq = $partida['extremo_izq'] !== null ? intval($partida['extremo_izq']) : null;
    $extremo_der = $partida['extremo_der'] !== null ? intval($partida['extremo_der']) : null;
    $pozo = json_decode($partida['pozo'], true) ?: [];
    $tablero = json_decode($partida['tablero_fichas'], true) ?: [];

    foreach ($todos_jugadores as $j) {
        $fichas_jugador = json_decode($j['fichas'], true) ?: [];
        $es_yo = (!empty($token) && $j['token'] === $token);

        if ($es_yo) {
            $mi_jugador = [
                'id' => intval($j['id']),
                'nombre' => $j['nombre'],
                'posicion' => intval($j['posicion']),
                'pareja' => $j['pareja'] !== null ? intval($j['pareja']) : null,
                'es_anfitrion' => ($partida['anfitrion_token'] === $token),
                'es_mi_turno' => ($partida['estado'] === 'jugando' && intval($partida['turno_jugador_id']) === intval($j['id']))
            ];
            $mi_mano = ordenar_mano_estrategica($fichas_jugador);
        }

        // Seguridad anti-trampas: solo cantidad numérica de fichas para rivales
        $jugadores_publicos[] = [
            'id' => intval($j['id']),
            'nombre' => $j['nombre'],
            'es_bot' => (bool)$j['es_bot'],
            'tipo_bot' => $j['tipo_bot'] ?? 'experto',
            'posicion' => intval($j['posicion']),
            'pareja' => $j['pareja'] !== null ? intval($j['pareja']) : null,
            'num_fichas' => count($fichas_jugador),
            'es_su_turno' => ($partida['estado'] === 'jugando' && intval($partida['turno_jugador_id']) === intval($j['id'])),
            'puntuacion' => intval($j['puntuacion'])
        ];
    }

    $mis_fichas_evaluadas = [];
    $total_jugables = 0;
    $es_mi_turno = ($mi_jugador && $mi_jugador['es_mi_turno']);

    if ($mi_jugador) {
        foreach ($mi_mano as $f) {
            $encaja_izq = ($extremo_izq === null || $f[0] === $extremo_izq || $f[1] === $extremo_izq);
            $encaja_der = ($extremo_der === null || $f[0] === $extremo_der || $f[1] === $extremo_der);
            $jugable = ($es_mi_turno && ($encaja_izq || $encaja_der));

            if ($jugable) {
                $total_jugables++;
            }

            $desc_base = describir_ficha($f);
            $aria_label = $desc_base;

            if ($es_mi_turno) {
                if ($encaja_izq && $encaja_der) {
                    $aria_label .= ", jugable por ambos extremos";
                } elseif ($encaja_izq) {
                    $aria_label .= ", jugable por la izquierda";
                } elseif ($encaja_der) {
                    $aria_label .= ", jugable por la derecha";
                } else {
                    $aria_label .= ", no jugable actualmente";
                }
            } else {
                $aria_label .= ", esperando turno";
            }

            $mis_fichas_evaluadas[] = [
                'ficha' => $f,
                'desc' => $desc_base,
                'aria_label' => $aria_label,
                'jugable' => $jugable,
                'encaja_izq' => $encaja_izq,
                'encaja_der' => $encaja_der
            ];
        }
    }

    $puede_robar = ($es_mi_turno && $total_jugables === 0 && count($pozo) > 0);
    $puede_pasar = ($es_mi_turno && $total_jugables === 0 && count($pozo) === 0);

    $nombre_turno = '';
    foreach ($jugadores_publicos as $jp) {
        if ($jp['es_su_turno']) {
            $nombre_turno = $jp['nombre'];
            break;
        }
    }

    $desde_historial_id = isset($_GET['desde_historial_id']) ? intval($_GET['desde_historial_id']) : -1;
    $nuevos_movimientos = [];
    if ($desde_historial_id >= 0) {
        $stmtH = $conexion->prepare("SELECT id, jugador_id, tipo_accion, descripcion FROM domino_historial WHERE partida_id = ? AND id > ? ORDER BY id ASC");
        $stmtH->execute([$partida_id, $desde_historial_id]);
        $nuevos_movimientos = $stmtH->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmtMaxH = $conexion->prepare("SELECT MAX(id) FROM domino_historial WHERE partida_id = ?");
    $stmtMaxH->execute([$partida_id]);
    $ultimo_historial_id = (int)$stmtMaxH->fetchColumn();

    $historial_completo = [];
    if ($partida['estado'] === 'terminada') {
        $stmtHFull = $conexion->prepare("SELECT id, jugador_id, tipo_accion, ficha, extremo, descripcion FROM domino_historial WHERE partida_id = ? ORDER BY id ASC");
        $stmtHFull->execute([$partida_id]);
        $historial_completo = $stmtHFull->fetchAll(PDO::FETCH_ASSOC);
    }

    responder_json([
        'success' => true,
        'ultimo_historial_id' => $ultimo_historial_id,
        'nuevos_movimientos' => $nuevos_movimientos,
        'historial_completo' => $historial_completo,
        'partida' => [
            'id' => $partida['id'],
            'codigo' => $partida['codigo'],
            'estado' => $partida['estado'],
            'modo' => $partida['modo'],
            'tipo_bot' => $partida['tipo_bot'] ?? 'experto',
            'max_jugadores' => intval($partida['max_jugadores']),
            'turno_jugador_id' => $partida['turno_jugador_id'] !== null ? intval($partida['turno_jugador_id']) : null,
            'nombre_turno' => $nombre_turno,
            'extremo_izq' => $extremo_izq,
            'extremo_der' => $extremo_der,
            'tablero_fichas' => $tablero,
            'pozo_count' => count($pozo),
            'ultimo_movimiento' => $partida['ultimo_movimiento'],
            'ultimo_comentario_ia' => $partida['ultimo_comentario_ia'],
            'ganador_id' => $partida['ganador_id'],
            'ganador_texto' => $partida['ganador_texto'],
            'puntos_ganador' => intval($partida['puntos_ganador']),
            'timestamp' => intval($partida['ultimo_timestamp'])
        ],
        'jugadores' => $jugadores_publicos,
        'mi_jugador' => $mi_jugador,
        'mi_mano' => $mis_fichas_evaluadas,
        'puede_robar' => $puede_robar,
        'puede_pasar' => $puede_pasar
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al obtener estado: ' . $e->getMessage()], 500);
}
