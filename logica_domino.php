<?php
/**
 * logica_domino.php - Reglas de Dominó para Todos y Motor Estratégico de Máquinas Expertas
 * Utiliza estrictamente la variable $conexion.
 */

require_once __DIR__ . '/config.php';

/**
 * Genera el conjunto completo de 28 fichas del dominó español (del 0-0 al 6-6)
 */
function generar_fichas_completas() {
    $fichas = [];
    for ($i = 0; $i <= 6; $i++) {
        for ($j = $i; $j <= 6; $j++) {
            $fichas[] = [$i, $j];
        }
    }
    return $fichas;
}

/**
 * Convierte un número del dominó a texto en español
 */
function nombre_numero($n) {
    $nombres = [
        0 => 'blanca',
        1 => 'uno',
        2 => 'dos',
        3 => 'tres',
        4 => 'cuatro',
        5 => 'cinco',
        6 => 'seis'
    ];
    return $nombres[$n] ?? (string)$n;
}

/**
 * Genera un texto descriptivo accesible para lectores de pantalla de una ficha
 * Ejemplos: "Ficha doble seis", "Ficha cuatro y dos", "Ficha doble blanca"
 */
function describir_ficha($ficha) {
    $p1 = ($ficha[0] === 0 || $ficha[0] === '0') ? 'blanca' : (string)$ficha[0];
    $p2 = ($ficha[1] === 0 || $ficha[1] === '0') ? 'blanca' : (string)$ficha[1];
    return "Ficha {$p1} {$p2}";
}

/**
 * Genera la descripción concisa de una ficha para locuciones rápidas
 * 0 se sustituye por "blanca" (ej: "blanca 4", "6 blanca", "2 3", "blanca blanca")
 */
function describir_ficha_corta($ficha) {
    $p1 = ($ficha[0] === 0 || $ficha[0] === '0') ? 'blanca' : (string)$ficha[0];
    $p2 = ($ficha[1] === 0 || $ficha[1] === '0') ? 'blanca' : (string)$ficha[1];
    return "{$p1} {$p2}";
}

/**
 * Suma los puntos totales de una mano de fichas
 */
function calcular_puntos_mano($fichas) {
    $total = 0;
    foreach ($fichas as $f) {
        $total += ($f[0] + $f[1]);
    }
    return $total;
}

/**
 * Busca qué fichas de una mano son jugables en los extremos dados
 */
function obtener_fichas_jugables($mano, $extremo_izq, $extremo_der) {
    $jugables = [];
    if ($extremo_izq === null || $extremo_der === null) {
        // Tablero vacío: todas son jugables
        foreach ($mano as $f) {
            $jugables[] = [
                'ficha' => $f,
                'izquierda' => true,
                'derecha' => true
            ];
        }
        return $jugables;
    }

    foreach ($mano as $f) {
        $encaja_izq = ($f[0] === $extremo_izq || $f[1] === $extremo_izq);
        $encaja_der = ($f[0] === $extremo_der || $f[1] === $extremo_der);
        if ($encaja_izq || $encaja_der) {
            $jugables[] = [
                'ficha' => $f,
                'izquierda' => $encaja_izq,
                'derecha' => $encaja_der
            ];
        }
    }
    return $jugables;
}

/**
 * Ordena las fichas de un jugador humano siguiendo la estrategia tradicional del dominó:
 * 1. Los dobles primero, ordenados por la fuerza de su palo (frecuencia descendente) y luego por valor del doble.
 * 2. Las fichas que compartan número con un doble en mano, ordenadas por el palo del doble más repetido.
 * 3. Los números repetidos sin doble (series de palos fuertes de apoyo).
 * 4. Las fichas huérfanas o solitarias (números que no se repiten, reservadas para el final).
 */
function ordenar_mano_estrategica($fichas) {
    if (empty($fichas) || count($fichas) <= 1) {
        return $fichas;
    }

    // 1. Frecuencias por número (0..6) y presencia de dobles en la mano
    $conteo = array_fill(0, 7, 0);
    $tiene_doble = array_fill(0, 7, false);

    foreach ($fichas as $f) {
        $a = intval($f[0]);
        $b = intval($f[1]);
        $conteo[$a]++;
        if ($a !== $b) {
            $conteo[$b]++;
        } else {
            $tiene_doble[$a] = true;
        }
    }

    // Clasificación en 4 grupos estratégicos
    $grupo_dobles = [];
    $grupo_acomp_dobles = [];
    $grupo_repetidos = [];
    $grupo_huerfanos = [];

    foreach ($fichas as $f) {
        $a = intval($f[0]);
        $b = intval($f[1]);

        if ($a === $b) {
            // Grupo 1: Dobles
            $grupo_dobles[] = [
                'ficha' => $f,
                'palo_doble' => $a,
                'frecuencia' => $conteo[$a]
            ];
        } elseif ($tiene_doble[$a] || $tiene_doble[$b]) {
            // Grupo 2: Fichas que acompañan a un doble en mano
            if ($tiene_doble[$a] && $tiene_doble[$b]) {
                if ($conteo[$a] > $conteo[$b]) {
                    $palo_doble = $a; $otro = $b;
                } elseif ($conteo[$b] > $conteo[$a]) {
                    $palo_doble = $b; $otro = $a;
                } else {
                    $palo_doble = max($a, $b); $otro = min($a, $b);
                }
            } elseif ($tiene_doble[$a]) {
                $palo_doble = $a; $otro = $b;
            } else {
                $palo_doble = $b; $otro = $a;
            }

            $grupo_acomp_dobles[] = [
                'ficha' => $f,
                'frec_doble' => $conteo[$palo_doble],
                'palo_doble' => $palo_doble,
                'frec_otro' => $conteo[$otro],
                'otro' => $otro
            ];
        } elseif (max($conteo[$a], $conteo[$b]) >= 2) {
            // Grupo 3: Números repetidos sin doble
            if ($conteo[$a] > $conteo[$b]) {
                $palo_rep = $a; $otro = $b;
            } elseif ($conteo[$b] > $conteo[$a]) {
                $palo_rep = $b; $otro = $a;
            } else {
                $palo_rep = max($a, $b); $otro = min($a, $b);
            }

            $grupo_repetidos[] = [
                'ficha' => $f,
                'frec_rep' => $conteo[$palo_rep],
                'palo_rep' => $palo_rep,
                'frec_otro' => $conteo[$otro],
                'otro' => $otro
            ];
        } else {
            // Grupo 4: Fichas huérfanas / solitarias (ningún número se repite en la mano)
            $grupo_huerfanos[] = [
                'ficha' => $f,
                'puntos' => ($a + $b),
                'max_val' => max($a, $b)
            ];
        }
    }

    // Ordenar Grupo 1: Dobles (frecuencia DESC, valor de doble DESC)
    usort($grupo_dobles, function($x, $y) {
        if ($x['frecuencia'] !== $y['frecuencia']) {
            return $y['frecuencia'] <=> $x['frecuencia'];
        }
        return $y['palo_doble'] <=> $x['palo_doble'];
    });

    // Ordenar Grupo 2: Acompañantes de dobles (frecuencia doble DESC, palo doble DESC, frec otro DESC, otro DESC)
    usort($grupo_acomp_dobles, function($x, $y) {
        if ($x['frec_doble'] !== $y['frec_doble']) {
            return $y['frec_doble'] <=> $x['frec_doble'];
        }
        if ($x['palo_doble'] !== $y['palo_doble']) {
            return $y['palo_doble'] <=> $x['palo_doble'];
        }
        if ($x['frec_otro'] !== $y['frec_otro']) {
            return $y['frec_otro'] <=> $x['frec_otro'];
        }
        return $y['otro'] <=> $x['otro'];
    });

    // Ordenar Grupo 3: Repetidos sin doble (frecuencia repetido DESC, palo repetido DESC, frec otro DESC, otro DESC)
    usort($grupo_repetidos, function($x, $y) {
        if ($x['frec_rep'] !== $y['frec_rep']) {
            return $y['frec_rep'] <=> $x['frec_rep'];
        }
        if ($x['palo_rep'] !== $y['palo_rep']) {
            return $y['palo_rep'] <=> $x['palo_rep'];
        }
        if ($x['frec_otro'] !== $y['frec_otro']) {
            return $y['frec_otro'] <=> $x['frec_otro'];
        }
        return $y['otro'] <=> $x['otro'];
    });

    // Ordenar Grupo 4: Huérfanas (puntos DESC, max_val DESC)
    usort($grupo_huerfanos, function($x, $y) {
        if ($x['puntos'] !== $y['puntos']) {
            return $y['puntos'] <=> $x['puntos'];
        }
        return $y['max_val'] <=> $x['max_val'];
    });

    // Unir todos los grupos en el orden estratégico definitivo
    $resultado = [];
    foreach ($grupo_dobles as $item) $resultado[] = $item['ficha'];
    foreach ($grupo_acomp_dobles as $item) $resultado[] = $item['ficha'];
    foreach ($grupo_repetidos as $item) $resultado[] = $item['ficha'];
    foreach ($grupo_huerfanos as $item) $resultado[] = $item['ficha'];

    return $resultado;
}

/**
 * Determina el siguiente jugador en turno según la posición circular
 */
function obtener_siguiente_jugador($conexion, $partida_id, $jugador_actual_id) {
    $stmt = $conexion->prepare("SELECT id, posicion FROM domino_jugadores WHERE partida_id = ? ORDER BY posicion ASC");
    $stmt->execute([$partida_id]);
    $jugadores = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($jugadores)) return null;

    $indice_actual = 0;
    foreach ($jugadores as $idx => $j) {
        if ($j['id'] == $jugador_actual_id) {
            $indice_actual = $idx;
            break;
        }
    }

    $siguiente_idx = ($indice_actual + 1) % count($jugadores);
    return $jugadores[$siguiente_idx]['id'];
}

/**
 * Verifica si hay cierre o tranca (cuando todos los jugadores pasan consecutivamente)
 */
function verificar_tranca($conexion, $partida_id, $num_jugadores) {
    $stmt = $conexion->prepare("SELECT tipo_accion FROM domino_historial WHERE partida_id = ? ORDER BY id DESC LIMIT ?");
    $stmt->bindValue(1, (int)$partida_id, PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$num_jugadores, PDO::PARAM_INT);
    $stmt->execute();
    $ultimas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($ultimas) < $num_jugadores) {
        return false;
    }

    foreach ($ultimas as $accion) {
        if ($accion !== 'pasar') {
            return false;
        }
    }
    return true;
}

/**
 * Finaliza la partida por Dominó (mano vacía) o por Tranca/Cierre (menor puntuación)
 */
function finalizar_partida($conexion, $partida_id, $motivo, $ganador_jugador_id = null) {
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE id = ?");
    $stmtP->execute([$partida_id]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);
    if (!$partida) return;

    $stmtJ = $conexion->prepare("SELECT * FROM domino_jugadores WHERE partida_id = ? ORDER BY posicion ASC");
    $stmtJ->execute([$partida_id]);
    $jugadores = $stmtJ->fetchAll(PDO::FETCH_ASSOC);

    $puntos_por_jugador = [];
    $puntos_por_pareja = [1 => 0, 2 => 0];

    foreach ($jugadores as $j) {
        $mano = json_decode($j['fichas'], true) ?: [];
        $pts = calcular_puntos_mano($mano);
        $puntos_por_jugador[$j['id']] = $pts;
        if (!empty($j['pareja'])) {
            $puntos_por_pareja[$j['pareja']] += $pts;
        }
    }

    $ganador_id = null;
    $ganador_texto = '';
    $puntos_ganados = 0;

    if ($motivo === 'domino' && $ganador_jugador_id) {
        $ganador = null;
        foreach ($jugadores as $j) {
            if ($j['id'] == $ganador_jugador_id) {
                $ganador = $j;
                break;
            }
        }

        $ganador_id = $ganador_jugador_id;
        if ($partida['modo'] === 'parejas' && !empty($ganador['pareja'])) {
            $pareja_ganadora = $ganador['pareja'];
            $pareja_perdedora = ($pareja_ganadora == 1) ? 2 : 1;
            $puntos_ganados = $puntos_por_pareja[$pareja_perdedora];
            $ganador_texto = "¡DOMINÓ! La Pareja {$pareja_ganadora} gana la ronda (cerrada por {$ganador['nombre']}). Puntos sumados: {$puntos_ganados}.";
        } else {
            // Modo individual
            foreach ($puntos_por_jugador as $jid => $pts) {
                if ($jid != $ganador_id) {
                    $puntos_ganados += $pts;
                }
            }
            $ganador_texto = "¡DOMINÓ! {$ganador['nombre']} ha ganado la ronda al colocar todas sus fichas. Puntos sumados: {$puntos_ganados}.";
        }
    } elseif ($motivo === 'tranca') {
        // Cierre o tranca: gana quien tenga menos puntos en mano
        if ($partida['modo'] === 'parejas') {
            $pts1 = $puntos_por_pareja[1];
            $pts2 = $puntos_por_pareja[2];
            if ($pts1 < $pts2) {
                $pareja_ganadora = 1;
                $puntos_ganados = $pts2;
                $ganador_texto = "¡TRANCA! La partida se ha cerrado. Gana la Pareja 1 con {$pts1} puntos frente a {$pts2} puntos de la Pareja 2. Puntos sumados: {$puntos_ganados}.";
            } elseif ($pts2 < $pts1) {
                $pareja_ganadora = 2;
                $puntos_ganados = $pts1;
                $ganador_texto = "¡TRANCA! La partida se ha cerrado. Gana la Pareja 2 con {$pts2} puntos frente a {$pts1} puntos de la Pareja 1. Puntos sumados: {$puntos_ganados}.";
            } else {
                $ganador_texto = "¡TRANCA Y EMPATE! Ambas parejas empataron con {$pts1} puntos en la tranca.";
            }
        } else {
            // Modo individual
            asort($puntos_por_jugador);
            $min_pts = reset($puntos_por_jugador);
            $mejor_jid = key($puntos_por_jugador);

            $ganadores_empatados = [];
            foreach ($puntos_por_jugador as $jid => $pts) {
                if ($pts === $min_pts) {
                    $ganadores_empatados[] = $jid;
                }
            }

            if (count($ganadores_empatados) === 1) {
                $ganador_id = $mejor_jid;
                $nombre_g = '';
                foreach ($jugadores as $j) {
                    if ($j['id'] == $ganador_id) $nombre_g = $j['nombre'];
                }
                foreach ($puntos_por_jugador as $jid => $pts) {
                    if ($jid != $ganador_id) $puntos_ganados += $pts;
                }
                $ganador_texto = "¡TRANCA! La partida se ha cerrado. Gana {$nombre_g} con solo {$min_pts} puntos en mano. Puntos sumados: {$puntos_ganados}.";
            } else {
                $nombres_empate = [];
                foreach ($jugadores as $j) {
                    if (in_array($j['id'], $ganadores_empatados)) {
                        $nombres_empate[] = $j['nombre'];
                    }
                }
                $ganador_texto = "¡TRANCA Y EMPATE! Empate a {$min_pts} puntos entre: " . implode(' y ', $nombres_empate) . ".";
            }
        }
    }

    $stmtUp = $conexion->prepare("UPDATE domino_partidas SET estado = 'terminada', ganador_id = ?, ganador_texto = ?, puntos_ganador = ?, ultimo_movimiento = ? WHERE id = ?");
    $stmtUp->execute([$ganador_id, $ganador_texto, $puntos_ganados, $ganador_texto, $partida_id]);
}

/**
 * Motor Estratégico de Dominó de Nivel Maestro
 * Basado en los tratados de Dr. Ignacio Zaibert y normativas de la FED y FID:
 * 1. La Carta Vista (Conteo exacto de 28 fichas: vistas en mesa y mano vs. vivas por cada número 0-6).
 * 2. Mapeo y Detección de Fallas (Registro estricto de pases de cada jugador).
 * 3. Salida y Dominio de Palo Fuerte (Apoyo estricto al compañero; ataque a la salida rival).
 * 4. Ataque al Rival Siguiente (El contrincante a la izquierda que juega a continuación).
 * 5. Protección de la Pareja (Nunca castigar los palos del compañero ni ahogarlo).
 * 6. Cuadratura Dominante vs. Suicida (Cerrar ambos extremos al mismo número sólo con control numérico).
 * 7. Evaluación Matemática del Tranque (Cálculo de pip points y fichas restantes para asegurar victoria).
 * 8. Descarga de Dobles Solos y Fichas Pesadas (Soltar dobles vulnerables antes de que se tranquen).
 * 9. Conservación de Llaves y Flexibilidad para el próximo turno.
 * 10. Recta Final y Facilitación de Dominó del Compañero.
 */
function decidir_jugada_experta(
    $mano,
    $extremo_izq,
    $extremo_der,
    $fichas_pozo_count,
    $tablero = [],
    $jugadores = [],
    $historial = [],
    $mi_id = 0,
    $modo = 'individual'
) {
    $jugables = obtener_fichas_jugables($mano, $extremo_izq, $extremo_der);

    if (empty($jugables)) {
        if ($fichas_pozo_count > 0) {
            return [
                'accion' => 'robar',
                'ficha' => null,
                'extremo' => null,
                'comentario' => null
            ];
        }
        return [
            'accion' => 'pasar',
            'ficha' => null,
            'extremo' => null,
            'comentario' => null
        ];
    }

    // 1. CONTEO DE CARTA VISTA (28 fichas en total: 7 fichas por cada palo del 0 al 6)
    $vistas = array_fill(0, 7, 0);
    $conteo_mano = array_fill(0, 7, 0);

    // Fichas en nuestra mano
    foreach ($mano as $f) {
        $conteo_mano[$f[0]]++;
        if ($f[0] !== $f[1]) {
            $conteo_mano[$f[1]]++;
        }
        $vistas[$f[0]]++;
        if ($f[0] !== $f[1]) {
            $vistas[$f[1]]++;
        }
    }

    // Fichas visibles en el tablero
    foreach ($tablero as $f) {
        $vistas[$f[0]]++;
        if ($f[0] !== $f[1]) {
            $vistas[$f[1]]++;
        }
    }

    // Fichas vivas (aún no vistas, en manos de otros jugadores o en el pozo)
    $vivas = [];
    for ($k = 0; $k <= 6; $k++) {
        $vivas[$k] = max(0, 7 - $vistas[$k]);
    }

    // 2. IDENTIFICACIÓN DE ASIENTOS Y ROLES
    $mi_jugador = null;
    $num_jugadores = count($jugadores);
    foreach ($jugadores as $j) {
        if ($j['id'] == $mi_id) {
            $mi_jugador = $j;
            break;
        }
    }

    $mi_posicion = $mi_jugador ? intval($mi_jugador['posicion']) : 0;
    $mi_pareja = $mi_jugador ? $mi_jugador['pareja'] : null;

    $id_companero = null;
    $id_rival_izq = null; // Rival que juega inmediatamente después
    $id_rival_der = null; // Rival que jugó inmediatamente antes

    $fichas_restantes_por_jugador = [];

    foreach ($jugadores as $j) {
        $j_id = $j['id'];
        $j_pos = intval($j['posicion']);
        $fichas_arr = json_decode($j['fichas'], true) ?: [];
        $fichas_restantes_por_jugador[$j_id] = count($fichas_arr);

        if ($j_id != $mi_id) {
            if ($modo === 'parejas' && $j['pareja'] == $mi_pareja) {
                $id_companero = $j_id;
            }
            if ($num_jugadores > 1) {
                if ($j_pos === (($mi_posicion + 1) % $num_jugadores)) {
                    $id_rival_izq = $j_id;
                }
                if ($j_pos === (($mi_posicion - 1 + $num_jugadores) % $num_jugadores)) {
                    $id_rival_der = $j_id;
                }
            }
        }
    }

    // 3. ANÁLISIS DE LA SALIDA (Zaibert) Y MAPEO DE FALLAS (Pases pasados)
    $palo_salida_comp = null;
    $palo_salida_rival = null;
    $fallas_jugadores = []; // [jugador_id => [palo => true]]

    foreach ($historial as $h) {
        // Registrar primer movimiento de la mano (Salida)
        if ($h['tipo_accion'] === 'jugar' && $palo_salida_comp === null && $palo_salida_rival === null) {
            $f_parts = explode('-', $h['ficha'] ?? '');
            if (count($f_parts) === 2) {
                $palo_ini = intval($f_parts[0]);
                if ($modo === 'parejas' && isset($h['pareja']) && $h['pareja'] == $mi_pareja) {
                    $palo_salida_comp = $palo_ini;
                } else {
                    $palo_salida_rival = $palo_ini;
                }
            }
        }

        // Mapeo seguro de fallas cuando un jugador pasa
        if ($h['tipo_accion'] === 'pasar') {
            $jid = $h['jugador_id'];
            if (!isset($fallas_jugadores[$jid])) {
                $fallas_jugadores[$jid] = [];
            }
            if ($h['extremo_izq_momento'] !== null) {
                $fallas_jugadores[$jid][intval($h['extremo_izq_momento'])] = true;
            }
            if ($h['extremo_der_momento'] !== null) {
                $fallas_jugadores[$jid][intval($h['extremo_der_momento'])] = true;
            }
        }
    }

    // 4. EVALUACIÓN Y PUNTUACIÓN TÁCTICA DE CADA OPCIÓN
    $opciones_evaluadas = [];

    foreach ($jugables as $opcion) {
        $f = $opcion['ficha'];
        $puntos_ficha = $f[0] + $f[1];
        $es_doble = ($f[0] === $f[1]);

        $extremos = [];
        if ($extremo_izq === null && $extremo_der === null) {
            $extremos[] = 'derecha';
        } else {
            if ($opcion['izquierda']) $extremos[] = 'izquierda';
            if ($opcion['derecha']) $extremos[] = 'derecha';
        }

        foreach ($extremos as $ext) {
            $tactica = 0.0;

            // Determinar extremos resultantes L' y R'
            $nuevo_L = $extremo_izq;
            $nuevo_R = $extremo_der;
            $palo_abierto = null;

            if ($extremo_izq === null && $extremo_der === null) {
                $nuevo_L = $f[0];
                $nuevo_R = $f[1];
                $palo_abierto = $f[0];
            } elseif ($ext === 'izquierda') {
                $palo_abierto = ($f[1] === $extremo_izq) ? $f[0] : $f[1];
                $nuevo_L = $palo_abierto;
            } else {
                $palo_abierto = ($f[0] === $extremo_der) ? $f[1] : $f[0];
                $nuevo_R = $palo_abierto;
            }

            // Calcular mano restante si jugamos esta ficha
            $mis_restantes = $conteo_mano;
            $mis_restantes[$f[0]] = max(0, $mis_restantes[$f[0]] - 1);
            if ($f[0] !== $f[1]) {
                $mis_restantes[$f[1]] = max(0, $mis_restantes[$f[1]] - 1);
            }

            $puntos_restantes_mano = 0;
            $fichas_restantes_mano = count($mano) - 1;
            foreach ($mano as $mf) {
                if ($mf === $f) continue;
                $puntos_restantes_mano += ($mf[0] + $mf[1]);
            }

            // --- REGLA 1: SALIDA EN MESA VACÍA ---
            if ($extremo_izq === null && $extremo_der === null) {
                if ($f[0] === 6 && $f[1] === 6) {
                    $tactica += 200; // Doble 6 obligado de salida
                } elseif ($es_doble) {
                    $tactica += 100 + ($f[0] * 10);
                } else {
                    $tactica += ($conteo_mano[$f[0]] + $conteo_mano[$f[1]]) * 15 + $puntos_ficha;
                }
            }

            // --- REGLA 2: CUADRATURA (Dejar L' == R' == K) ---
            if ($nuevo_L !== null && $nuevo_R !== null && $nuevo_L === $nuevo_R) {
                $K = $nuevo_L;
                $mis_K = $mis_restantes[$K];
                $vivas_K = $vivas[$K];

                if ($mis_K >= 2 && $mis_K >= $vivas_K) {
                    // Cuadratura Dominante: Controlamos el juego
                    $tactica += 130 + ($mis_K * 20);
                } elseif ($mis_K === 0 && $vivas_K > 0) {
                    // Cuadratura Suicida: No tenemos fichas y los rivales sí
                    $tactica -= 160;
                } elseif ($mis_K === 1 && $vivas_K >= 2) {
                    $tactica -= 50;
                }
            }

            // --- REGLA 3: TRANQUE / CIERRE MATEMÁTICO ---
            $tranca_L = ($nuevo_L !== null && $vivas[$nuevo_L] === 0 && $mis_restantes[$nuevo_L] === 0);
            $tranca_R = ($nuevo_R !== null && $vivas[$nuevo_R] === 0 && $mis_restantes[$nuevo_R] === 0);
            $provoca_tranca = ($nuevo_L !== null && $nuevo_R !== null) && ($tranca_L && $tranca_R);

            if ($provoca_tranca) {
                if ($modo === 'parejas') {
                    $fichas_comp = $id_companero ? ($fichas_restantes_por_jugador[$id_companero] ?? 3) : 3;
                    $fichas_riv1 = $id_rival_izq ? ($fichas_restantes_por_jugador[$id_rival_izq] ?? 3) : 3;
                    $fichas_riv2 = $id_rival_der ? ($fichas_restantes_por_jugador[$id_rival_der] ?? 3) : 3;

                    if ($puntos_restantes_mano <= 7 && $fichas_comp <= min($fichas_riv1, $fichas_riv2)) {
                        $tactica += 220 - ($puntos_restantes_mano * 8); // Tranque ganador
                    } else {
                        $tactica -= 260 + ($puntos_restantes_mano * 5); // Tranque perdedor
                    }
                } else {
                    if ($puntos_restantes_mano <= 5) {
                        $tactica += 200 - ($puntos_restantes_mano * 10);
                    } else {
                        $tactica -= 250 + ($puntos_restantes_mano * 6);
                    }
                }
            }

            // --- REGLA 4: ATAQUE AL RIVAL SIGUIENTE (A nuestra izquierda) ---
            if ($id_rival_izq && isset($fallas_jugadores[$id_rival_izq])) {
                $falla_nuevo = isset($fallas_jugadores[$id_rival_izq][$palo_abierto]);
                $otro_extremo = ($ext === 'izquierda') ? $nuevo_R : $nuevo_L;
                $falla_otro = ($otro_extremo !== null && isset($fallas_jugadores[$id_rival_izq][$otro_extremo]));

                if ($falla_nuevo && $falla_otro) {
                    $tactica += 110; // Provocar paso forzado del rival
                } elseif ($falla_nuevo) {
                    $tactica += 55;
                }
            }

            // --- REGLA 5: PROTECCIÓN Y APOYO AL COMPAÑERO (Parejas - Zaibert) ---
            if ($modo === 'parejas' && $id_companero) {
                if (isset($fallas_jugadores[$id_companero])) {
                    $comp_falla_nuevo = isset($fallas_jugadores[$id_companero][$palo_abierto]);
                    $otro_extremo = ($ext === 'izquierda') ? $nuevo_R : $nuevo_L;
                    $comp_falla_otro = ($otro_extremo !== null && isset($fallas_jugadores[$id_companero][$otro_extremo]));

                    if ($comp_falla_nuevo && $comp_falla_otro) {
                        $tactica -= 180; // No dejar al compañero sin jugada en ambos lados
                    } elseif ($comp_falla_nuevo) {
                        $tactica -= 75; // No castigar el palo que no tiene el compañero
                    }
                }

                // Apoyo a la salida del compañero
                if ($palo_salida_comp !== null) {
                    if ($palo_abierto === $palo_salida_comp) {
                        $tactica += 65; // Repetir palo del compañero
                    }
                    $extremo_cubierto = ($ext === 'izquierda') ? $extremo_izq : $extremo_der;
                    if ($extremo_cubierto === $palo_salida_comp) {
                        $tactica -= 50; // No tapar el palo fuerte del compañero
                    }
                }

                // Facilitar dominó del compañero si le quedan pocas fichas
                $fichas_comp = $fichas_restantes_por_jugador[$id_companero] ?? 7;
                if ($fichas_comp <= 2) {
                    if (!isset($fallas_jugadores[$id_companero][$palo_abierto])) {
                        $tactica += 45;
                    }
                }
            }

            // --- REGLA 6: ATAQUE A LA SALIDA RIVAL ---
            if ($palo_salida_rival !== null) {
                $extremo_cubierto = ($ext === 'izquierda') ? $extremo_izq : $extremo_der;
                if ($extremo_cubierto === $palo_salida_rival) {
                    $tactica += 45; // Matar la salida del contrario
                }
            }

            // --- REGLA 7: GESTIÓN DE DOBLES (Descarga de dobles solos y pesados) ---
            if ($es_doble) {
                $d = $f[0];
                if ($mis_restantes[$d] === 0) {
                    $tactica += 70 + ($d * 5); // Doble solo: máxima prioridad para evitar ahorcamiento
                } else {
                    $tactica += 35 + ($d * 3.5);
                }
            }

            // --- REGLA 8: DESCARGA DE PUNTOS Y RECTA FINAL ---
            $tactica += ($puntos_ficha * 1.8);
            if ($fichas_restantes_mano <= 2) {
                $tactica += ($puntos_ficha * 2.5);
            }

            // --- REGLA 9: CONSERVACIÓN DE LLAVES Y FLEXIBILIDAD ---
            $llaves_L = $nuevo_L !== null ? $mis_restantes[$nuevo_L] : 0;
            $llaves_R = $nuevo_R !== null ? $mis_restantes[$nuevo_R] : 0;

            if ($fichas_restantes_mano > 0) {
                if ($llaves_L === 0 && $llaves_R === 0) {
                    $tactica -= 45; // Quedar ciegos para el siguiente turno
                } else {
                    $tactica += ($llaves_L + $llaves_R) * 14;
                }
            }

            // Control de nuestro propio palo fuerte
            $tactica += ($mis_restantes[$palo_abierto] * 12);

            // Control de palos muertos (fichas vivas ajenas == 0)
            if ($vivas[$palo_abierto] === 0 && $mis_restantes[$palo_abierto] > 0) {
                $tactica += 40;
            }

            $opciones_evaluadas[] = [
                'ficha' => $f,
                'extremo' => $ext,
                'puntuacion' => $tactica
            ];
        }
    }

    usort($opciones_evaluadas, function($a, $b) {
        return $b['puntuacion'] <=> $a['puntuacion'];
    });

    $mejor = $opciones_evaluadas[0];

    return [
        'accion' => 'jugar',
        'ficha' => $mejor['ficha'],
        'extremo' => $mejor['extremo'],
        'comentario' => null
    ];
}

function decidir_jugada_fallback($fichas_mano, $extremo_izq, $extremo_der, $fichas_pozo) {
    return decidir_jugada_experta($fichas_mano, $extremo_izq, $extremo_der, $fichas_pozo);
}

/**
 * Procesa el turno de una Máquina Experta utilizando $conexion
 */
function procesar_turno_bot($conexion, $partida_id) {
    $stmtP = $conexion->prepare("SELECT * FROM domino_partidas WHERE id = ? FOR UPDATE");
    $stmtP->execute([$partida_id]);
    $partida = $stmtP->fetch(PDO::FETCH_ASSOC);

    if (!$partida || $partida['estado'] !== 'jugando') {
        return false;
    }

    $turno_id = $partida['turno_jugador_id'];
    $stmtJ = $conexion->prepare("SELECT * FROM domino_jugadores WHERE id = ? AND partida_id = ?");
    $stmtJ->execute([$turno_id, $partida_id]);
    $bot = $stmtJ->fetch(PDO::FETCH_ASSOC);

    if (!$bot || intval($bot['es_bot']) !== 1) {
        return false;
    }

    $ahora = time();
    $mano = json_decode($bot['fichas'], true) ?: [];
    $pozo = json_decode($partida['pozo'], true) ?: [];
    $tablero = json_decode($partida['tablero_fichas'], true) ?: [];
    $extremo_izq = $partida['extremo_izq'] !== null ? intval($partida['extremo_izq']) : null;
    $extremo_der = $partida['extremo_der'] !== null ? intval($partida['extremo_der']) : null;

    // Obtener todos los jugadores ordenados por posición
    $stmtTodos = $conexion->prepare("SELECT id, nombre, posicion, pareja, es_bot, fichas FROM domino_jugadores WHERE partida_id = ? ORDER BY posicion ASC");
    $stmtTodos->execute([$partida_id]);
    $todos_jugadores = $stmtTodos->fetchAll(PDO::FETCH_ASSOC);

    // Obtener historial completo de la partida para análisis de cartas vistas, salida y fallas
    $stmtH = $conexion->prepare("SELECT h.*, j.posicion, j.pareja, j.nombre FROM domino_historial h JOIN domino_jugadores j ON h.jugador_id = j.id WHERE h.partida_id = ? ORDER BY h.id ASC");
    $stmtH->execute([$partida_id]);
    $historial_completo = $stmtH->fetchAll(PDO::FETCH_ASSOC);

    $decision = decidir_jugada_experta(
        $mano,
        $extremo_izq,
        $extremo_der,
        count($pozo),
        $tablero,
        $todos_jugadores,
        $historial_completo,
        $bot['id'],
        $partida['modo']
    );

    $comentario = null;

    if ($decision['accion'] === 'jugar') {
        $f_jugada = $decision['ficha'];
        $ext = $decision['extremo'];

        $nueva_mano = [];
        $eliminada = false;
        foreach ($mano as $m) {
            if (!$eliminada && (($m[0] === $f_jugada[0] && $m[1] === $f_jugada[1]) || ($m[0] === $f_jugada[1] && $m[1] === $f_jugada[0]))) {
                $eliminada = true;
            } else {
                $nueva_mano[] = $m;
            }
        }

        $ficha_colocada = [];
        if ($ext === 'izquierda') {
            if ($f_jugada[1] === $extremo_izq) {
                $nuevo_extremo_izq = $f_jugada[0];
                $ficha_colocada = [$f_jugada[0], $f_jugada[1]];
            } else {
                $nuevo_extremo_izq = $f_jugada[1];
                $ficha_colocada = [$f_jugada[1], $f_jugada[0]];
            }
            array_unshift($tablero, $ficha_colocada);
            $extremo_izq = $nuevo_extremo_izq;
        } else {
            if ($f_jugada[0] === $extremo_der) {
                $nuevo_extremo_der = $f_jugada[1];
                $ficha_colocada = [$f_jugada[0], $f_jugada[1]];
            } else {
                $nuevo_extremo_der = $f_jugada[0];
                $ficha_colocada = [$f_jugada[1], $f_jugada[0]];
            }
            $tablero[] = $ficha_colocada;
            $extremo_der = $nuevo_extremo_der;
        }

        $ficha_str = "{$f_jugada[0]}-{$f_jugada[1]}";
        $desc_corta = describir_ficha_corta($f_jugada);
        $mov_texto = "{$bot['nombre']} pone {$desc_corta} por la {$ext}.";

        $stmtUpB = $conexion->prepare("UPDATE domino_jugadores SET fichas = ? WHERE id = ?");
        $stmtUpB->execute([json_encode($nueva_mano), $bot['id']]);

        $stmtHIns = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, comentario_ia, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'jugar', ?, ?, ?, NULL, ?, ?)");
        $stmtHIns->execute([$partida_id, $bot['id'], $ficha_str, $ext, $mov_texto, $extremo_izq, $extremo_der]);

        if (count($nueva_mano) === 0) {
            $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET tablero_fichas = ?, extremo_izq = ?, extremo_der = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
            $stmtUpP->execute([json_encode($tablero), $extremo_izq, $extremo_der, $mov_texto, $ahora, $partida_id]);
            finalizar_partida($conexion, $partida_id, 'domino', $bot['id']);
            return true;
        }

        $sig_id = obtener_siguiente_jugador($conexion, $partida_id, $bot['id']);
        $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET tablero_fichas = ?, extremo_izq = ?, extremo_der = ?, turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
        $stmtUpP->execute([json_encode($tablero), $extremo_izq, $extremo_der, $sig_id, $mov_texto, $ahora, $partida_id]);

        return true;

    } elseif ($decision['accion'] === 'robar') {
        // La máquina roba hasta conseguir una ficha que encaje o vaciar el pozo en este turno
        $robo_alguna = false;
        while (!empty($pozo)) {
            $ficha_robada = array_shift($pozo);
            $mano[] = $ficha_robada;
            $robo_alguna = true;

            $jugables_tras_robo = obtener_fichas_jugables($mano, $extremo_izq, $extremo_der);
            if (!empty($jugables_tras_robo)) {
                $decision_jugada = decidir_jugada_experta(
                    $mano,
                    $extremo_izq,
                    $extremo_der,
                    count($pozo),
                    $tablero,
                    $todos_jugadores,
                    $historial_completo,
                    $bot['id'],
                    $partida['modo']
                );

                if ($decision_jugada['accion'] === 'jugar') {
                    $f_jugada = $decision_jugada['ficha'];
                    $ext = $decision_jugada['extremo'];

                    $nueva_mano = [];
                    $retirada = false;
                    foreach ($mano as $m) {
                        if (!$retirada && (($m[0] === $f_jugada[0] && $m[1] === $f_jugada[1]) || ($m[0] === $f_jugada[1] && $m[1] === $f_jugada[0]))) {
                            $retirada = true;
                            continue;
                        }
                        $nueva_mano[] = $m;
                    }

                    if ($ext === 'izquierda') {
                        if ($f_jugada[1] === $extremo_izq) {
                            $nuevo_extremo_izq = $f_jugada[0];
                            $ficha_colocada = [$f_jugada[0], $f_jugada[1]];
                        } else {
                            $nuevo_extremo_izq = $f_jugada[1];
                            $ficha_colocada = [$f_jugada[1], $f_jugada[0]];
                        }
                        array_unshift($tablero, $ficha_colocada);
                        $extremo_izq = $nuevo_extremo_izq;
                    } else {
                        if ($f_jugada[0] === $extremo_der) {
                            $nuevo_extremo_der = $f_jugada[1];
                            $ficha_colocada = [$f_jugada[0], $f_jugada[1]];
                        } else {
                            $nuevo_extremo_der = $f_jugada[0];
                            $ficha_colocada = [$f_jugada[1], $f_jugada[0]];
                        }
                        $tablero[] = $ficha_colocada;
                        $extremo_der = $nuevo_extremo_der;
                    }

                    $ficha_str = "{$f_jugada[0]}-{$f_jugada[1]}";
                    $desc_corta = describir_ficha_corta($f_jugada);
                    $mov_texto = "{$bot['nombre']} roba y pone {$desc_corta} por la {$ext}.";

                    $stmtUpB = $conexion->prepare("UPDATE domino_jugadores SET fichas = ? WHERE id = ?");
                    $stmtUpB->execute([json_encode($nueva_mano), $bot['id']]);

                    $stmtHIns = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, comentario_ia, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'jugar', ?, ?, ?, NULL, ?, ?)");
                    $stmtHIns->execute([$partida_id, $bot['id'], $ficha_str, $ext, $mov_texto, $extremo_izq, $extremo_der]);

                    if (count($nueva_mano) === 0) {
                        $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET tablero_fichas = ?, pozo = ?, extremo_izq = ?, extremo_der = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
                        $stmtUpP->execute([json_encode($tablero), json_encode($pozo), $extremo_izq, $extremo_der, $mov_texto, $ahora, $partida_id]);
                        finalizar_partida($conexion, $partida_id, 'domino', $bot['id']);
                        return true;
                    }

                    $sig_id = obtener_siguiente_jugador($conexion, $partida_id, $bot['id']);
                    $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET tablero_fichas = ?, pozo = ?, extremo_izq = ?, extremo_der = ?, turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
                    $stmtUpP->execute([json_encode($tablero), json_encode($pozo), $extremo_izq, $extremo_der, $sig_id, $mov_texto, $ahora, $partida_id]);

                    return true;
                }
            }
        }

        // Si vació el pozo y no tiene ficha válida: pasa en una sola jugada limpia
        $mov_texto = $robo_alguna ? "{$bot['nombre']} roba y pasa." : "{$bot['nombre']} pasa.";

        $stmtUpB = $conexion->prepare("UPDATE domino_jugadores SET fichas = ? WHERE id = ?");
        $stmtUpB->execute([json_encode($mano), $bot['id']]);

        $stmtHIns = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, comentario_ia, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'pasar', NULL, NULL, ?, NULL, ?, ?)");
        $stmtHIns->execute([$partida_id, $bot['id'], $mov_texto, $extremo_izq, $extremo_der]);

        $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM domino_jugadores WHERE partida_id = ?");
        $stmtCount->execute([$partida_id]);
        $num_jugadores = (int)$stmtCount->fetchColumn();

        if (verificar_tranca($conexion, $partida_id, $num_jugadores)) {
            $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET pozo = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
            $stmtUpP->execute([json_encode($pozo), $mov_texto, $ahora, $partida_id]);
            finalizar_partida($conexion, $partida_id, 'tranca');
            return true;
        }

        $sig_id = obtener_siguiente_jugador($conexion, $partida_id, $bot['id']);
        $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET pozo = ?, turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
        $stmtUpP->execute([json_encode($pozo), $sig_id, $mov_texto, $ahora, $partida_id]);

        return true;

    } elseif ($decision['accion'] === 'pasar') {
        $mov_texto = "{$bot['nombre']} pasa.";

        $stmtHIns = $conexion->prepare("INSERT INTO domino_historial (partida_id, jugador_id, tipo_accion, ficha, extremo, descripcion, comentario_ia, extremo_izq_momento, extremo_der_momento) VALUES (?, ?, 'pasar', NULL, NULL, ?, NULL, ?, ?)");
        $stmtHIns->execute([$partida_id, $bot['id'], $mov_texto, $extremo_izq, $extremo_der]);

        $stmtCount = $conexion->prepare("SELECT COUNT(*) FROM domino_jugadores WHERE partida_id = ?");
        $stmtCount->execute([$partida_id]);
        $num_jugadores = (int)$stmtCount->fetchColumn();

        if (verificar_tranca($conexion, $partida_id, $num_jugadores)) {
            $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
            $stmtUpP->execute([$mov_texto, $ahora, $partida_id]);
            finalizar_partida($conexion, $partida_id, 'tranca');
            return true;
        }

        $sig_id = obtener_siguiente_jugador($conexion, $partida_id, $bot['id']);
        $stmtUpP = $conexion->prepare("UPDATE domino_partidas SET turno_jugador_id = ?, ultimo_movimiento = ?, ultimo_comentario_ia = NULL, ultimo_timestamp = ? WHERE id = ?");
        $stmtUpP->execute([$sig_id, $mov_texto, $ahora, $partida_id]);

        return true;
    }

    return false;
}

/**
 * Mantenimiento y recolector de basura automático:
 * Purga partidas terminadas de más de 24 horas y partidas inactivas de más de 12 horas,
 * eliminando en cascada su historial, jugadores y partidas para mantener la BD ligera.
 */
function purgar_partidas_antiguas($conexion) {
    try {
        $limite_terminadas = date('Y-m-d H:i:s', time() - (24 * 3600));
        $limite_inactivas = date('Y-m-d H:i:s', time() - (12 * 3600));

        $stmtObsoletas = $conexion->prepare("
            SELECT id FROM domino_partidas 
            WHERE (estado = 'terminada' AND actualizado_en < ?)
               OR (estado IN ('esperando', 'jugando') AND actualizado_en < ?)
            LIMIT 50
        ");
        $stmtObsoletas->execute([$limite_terminadas, $limite_inactivas]);
        $ids = $stmtObsoletas->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($ids)) {
            $inClause = implode(',', array_map('intval', $ids));
            $conexion->exec("DELETE FROM domino_historial WHERE partida_id IN ($inClause)");
            $conexion->exec("DELETE FROM domino_jugadores WHERE partida_id IN ($inClause)");
            $conexion->exec("DELETE FROM domino_partidas WHERE id IN ($inClause)");
        }
    } catch (Exception $e) {
        // Ejecución silenciosa y segura en segundo plano sin interrumpir al usuario
    }
}
