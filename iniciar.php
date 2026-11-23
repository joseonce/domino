<?php
/**
 * iniciar.php - Inicia la partida en Dominó para Todos
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
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE codigo = ?");
    $stmtP->execute([$codigo]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partida) {
        responder_json(['success' => false, 'error' => 'Partida no encontrada.'], 404);
    }

    if ($partida['anfitrion_token'] !== $token) {
        responder_json(['success' => false, 'error' => 'Solo el anfitrión puede iniciar la partida.'], 403);
    }

    if ($partida['estado'] !== 'esperando') {
        responder_json(['success' => false, 'error' => 'La partida ya ha sido iniciada.'], 400);
    }

    $partida_id = $partida['id'];

    $stmtJ = $conexion->prepare("SELECT * FROM domino_jugadores WHERE partida_id = ? ORDER BY posicion ASC");
    $stmtJ->execute([$partida_id]);
    $jugadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    $num_jugadores = count($jugadores);
    $max_jugadores = intval($partida['max_jugadores']);

    if ($num_jugadores !== $max_jugadores) {
        responder_json([
            'success' => false,
            'error' => "Se necesitan exactamente {$max_jugadores} jugadores para comenzar esta sala (actualmente hay {$num_jugadores}). Invita a más jugadores o añade máquinas."
        ], 400);
    }

    if ($partida['modo'] === 'parejas' && $num_jugadores !== 4) {
        responder_json(['success' => false, 'error' => 'El modo por parejas requiere exactamente 4 jugadores.'], 400);
    }

    // 1. Generar y barajar las 28 fichas con comprobación de la regla oficial de 5 dobles
    $anuncios_rebaraje = [];
    $intentos_baraje = 0;

    do {
        $intentos_baraje++;
        $todas_fichas = generar_fichas_completas();
        shuffle($todas_fichas);

        $manos = [];
        foreach ($jugadores as $j) {
            $manos[$j['id']] = array_splice($todas_fichas, 0, 7);
        }
        $pozo = $todas_fichas;

        // Comprobar si algún jugador (humano o máquina) tiene 5 o más dobles
        $hay_5_dobles = false;
        foreach ($jugadores as $j) {
            $num_dobles = 0;
            foreach ($manos[$j['id']] as $f) {
                if ($f[0] === $f[1]) {
                    $num_dobles++;
                }
            }
            if ($num_dobles >= 5) {
                $hay_5_dobles = true;
                $anuncios_rebaraje[] = [
                    'jugador_id' => $j['id'],
                    'texto' => "{$j['nombre']} obtuvo {$num_dobles} dobles. Por regla oficial se anula la mano, se vuelven a barajar las fichas y se reinicia el reparto."
                ];
                break;
            }
        }
    } while ($hay_5_dobles && $intentos_baraje < 20);

    // 2. Salida automática con el doble más alto
    $dobles_orden = [6, 5, 4, 3, 2, 1, 0];
    $salida_jugador_id = null;
    $ficha_salida = null;

    foreach ($dobles_orden as $d) {
        foreach ($jugadores as $j) {
            foreach ($manos[$j['id']] as $f) {
                if ($f[0] === $d && $f[1] === $d) {
                    $salida_jugador_id = $j['id'];
                    $ficha_salida = [$d, $d];
                    break 3;
                }
            }
        }
    }

    if ($ficha_salida === null) {
        $max_puntos = -1;
        foreach ($jugadores as $j) {
            foreach ($manos[$j['id']] as $f) {
                $pts = $f[0] + $f[1];
                if ($pts > $max_puntos) {
                    $max_puntos = $pts;
                    $salida_jugador_id = $j['id'];
                    $ficha_salida = $f;
                }
            }
        }
    }

    $nombre_salida = '';
    foreach ($jugadores as $j) {
        if ($j['id'] == $salida_jugador_id) {
            $nombre_salida = $j['nombre'];
            break;
        }
    }

    $nueva_mano_salida = [];
    $quitada = false;
    foreach ($manos[$salida_jugador_id] as $f) {
        if (!$quitada && (($f[0] === $ficha_salida[0] && $f[1] === $ficha_salida[1]) || ($f[0] === $ficha_salida[1] && $f[1] === $ficha_salida[0]))) {
            $quitada = true;
        } else {
            $nueva_mano_salida[] = $f;
        }
    }
    $manos[$salida_jugador_id] = $nueva_mano_salida;

    foreach ($jugadores as $j) {
        $mano_a_guardar = $manos[$j['id']];
        if (intval($j['es_bot']) === 0) {
            $mano_a_guardar = ordenar_mano_estrategica($mano_a_guardar);
        }
        $stmtUpJ = $conexion->prepare("UPDATE domino_jugadores SET fichas = ? WHERE id = ?");
        $stmtUpJ->execute([json_encode($mano_a_guardar), $j['id']]);
    }

    // Registrar en el historial cualquier anulación previa por 5 dobles
    foreach ($anuncios_rebaraje as $ar) {
        $stmtHRebaraje = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'pasar', NULL, 'centro', ?, NULL, NULL)");
        $stmtHRebaraje->execute([$partida_id, $ar['jugador_id'], $ar['texto']]);
    }

    $tablero = [$ficha_salida];
    $extremo_izq = $ficha_salida[0];
    $extremo_der = $ficha_salida[1];

    $desc_salida = describir_ficha_corta($ficha_salida);
    $desc_mesa = "Mesa " . describir_ficha_corta([$extremo_izq, $extremo_der]);
    $movimiento_salida = "{$nombre_salida} pone {$desc_salida}. {$desc_mesa}.";

    $ficha_salida_str = "{$ficha_salida[0]}-{$ficha_salida[1]}";
    $stmtH = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'salida', ?, 'centro', ?, ?, ?)");
    $stmtH->execute([$partida_id, $salida_jugador_id, $ficha_salida_str, $movimiento_salida, $extremo_izq, $extremo_der]);

    $siguiente_turno_id = obtener_siguiente_jugador($conexion, $partida_id, $salida_jugador_id);

    $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET estado = 'jugando', tablero_fichas = ?, pozo = ?, extremo_izq = ?, extremo_der = ?, turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
    $stmtUpP->execute([json_encode($tablero), json_encode($pozo), $extremo_izq, $extremo_der, $siguiente_turno_id, $movimiento_salida, time(), $partida_id]);

    responder_json([
        'success' => true,
        'mensaje' => 'Partida iniciada con éxito.',
        'salida_jugador' => $nombre_salida,
        'ficha_salida' => $ficha_salida,
        'desc_salida' => $desc_salida,
        'desc_mesa' => $desc_mesa,
        'movimiento_salida' => $movimiento_salida,
        'anuncios_rebaraje' => array_column($anuncios_rebaraje, 'texto'),
        'siguiente_turno_id' => $siguiente_turno_id
    ]);

} catch (Exception $e) {
    responder_json(['success' => false, 'error' => 'Error al iniciar la partida: ' . $e->getMessage()], 500);
}
