<?php
/**
 * instalar.php - Instalador de Tablas para Dominó para Todos
 * Ejecuta CREATE TABLE IF NOT EXISTS con la variable $conexion.
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

$mensaje = '';
$tipo_mensaje = 'info';

if (!isset($conexion) || !$conexion) {
    die("Error crítico: No se encontró la conexión PDO (\$conexion). Verifica conexion.php.");
}

try {
    // 1. Tabla de partidas
    $sql_partidas = "CREATE TABLE IF NOT EXISTS `domino_partidas` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `codigo` VARCHAR(10) NOT NULL UNIQUE,
        `anfitrion_token` VARCHAR(64) NOT NULL,
        `max_jugadores` TINYINT NOT NULL DEFAULT 4,
        `modo` ENUM('individual', 'parejas') NOT NULL DEFAULT 'individual',
        `tipo_bot` VARCHAR(20) NOT NULL DEFAULT 'experto',
        `estado` ENUM('esperando', 'jugando', 'terminada') NOT NULL DEFAULT 'esperando',
        `turno_jugador_id` INT NULL,
        `extremo_izq` TINYINT NULL,
        `extremo_der` TINYINT NULL,
        `tablero_fichas` LONGTEXT NULL COMMENT 'JSON de fichas en tablero',
        `pozo` LONGTEXT NULL COMMENT 'JSON de fichas restantes en pozo',
        `ultimo_movimiento` TEXT NULL,
        `ultimo_comentario_ia` VARCHAR(255) NULL,
        `ultimo_timestamp` BIGINT NOT NULL DEFAULT 0,
        `ganador_id` INT NULL,
        `ganador_texto` VARCHAR(255) NULL,
        `puntos_ganador` INT NOT NULL DEFAULT 0,
        `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // 2. Tabla de jugadores
    $sql_jugadores = "CREATE TABLE IF NOT EXISTS `domino_jugadores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `partida_id` INT NOT NULL,
        `nombre` VARCHAR(60) NOT NULL,
        `token` VARCHAR(64) NOT NULL,
        `es_bot` TINYINT(1) NOT NULL DEFAULT 0,
        `tipo_bot` VARCHAR(20) NOT NULL DEFAULT 'experto',
        `posicion` TINYINT NOT NULL DEFAULT 0,
        `pareja` TINYINT NULL COMMENT '1 o 2 en modo parejas',
        `fichas` LONGTEXT NOT NULL COMMENT 'JSON de fichas en mano',
        `puntuacion` INT NOT NULL DEFAULT 0,
        `activo` TINYINT(1) NOT NULL DEFAULT 1,
        `ultimo_acceso` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (`partida_id`),
        INDEX (`token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    // 3. Tabla de historial de jugadas
    $sql_historial = "CREATE TABLE IF NOT EXISTS `domino_historial` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `partida_id` INT NOT NULL,
        `jugador_id` INT NOT NULL,
        `tipo_accion` ENUM('salida', 'jugar', 'robar', 'pasar') NOT NULL,
        `ficha` VARCHAR(10) NULL COMMENT 'Ej: 6-6',
        `extremo` ENUM('izquierda', 'derecha', 'centro') NULL,
        `descripcion` VARCHAR(255) NOT NULL,
        `comentario_ia` VARCHAR(255) NULL,
        `extremo_izq_momento` TINYINT NULL,
        `extremo_der_momento` TINYINT NULL,
        `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (`partida_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conexion->exec($sql_partidas);
    $conexion->exec($sql_jugadores);
    $conexion->exec($sql_historial);

    // Migraciones automáticas seguras para instalaciones existentes
    try {
        $conexion->exec("ALTER TABLE domino_partidas ADD COLUMN tipo_bot VARCHAR(20) NOT NULL DEFAULT 'experto'");
    } catch (Exception $e) {}

    try {
        $conexion->exec("ALTER TABLE domino_jugadores ADD COLUMN tipo_bot VARCHAR(20) NOT NULL DEFAULT 'experto'");
    } catch (Exception $e) {}

    $mensaje = "¡Instalación completada con éxito! Las tablas de Dominó para Todos (domino_partidas, domino_jugadores, domino_historial) están listas.";
    $tipo_mensaje = 'exito';

} catch (PDOException $e) {
    $mensaje = "Error al crear las tablas: " . htmlspecialchars($e->getMessage());
    $tipo_mensaje = 'error';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalador de Base de Datos - Dominó para Todos</title>
    <style>
        :root {
            --bg-color: #0f172a;
            --card-bg: #1e293b;
            --text-color: #f8fafc;
            --accent: #38bdf8;
            --success: #22c55e;
            --error: #ef4444;
            --focus-outline: 3px solid #ffbf47;
        }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            line-height: 1.6;
        }
        .contenedor {
            background-color: var(--card-bg);
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 650px;
            width: 100%;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5);
            border: 2px solid #334155;
        }
        h1 {
            color: var(--accent);
            margin-top: 0;
            font-size: 1.8rem;
        }
        .mensaje {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            margin: 1.5rem 0;
        }
        .mensaje.exito {
            background-color: #14532d;
            border-left: 6px solid var(--success);
            color: #dcfce7;
        }
        .mensaje.error {
            background-color: #7f1d1d;
            border-left: 6px solid var(--error);
            color: #fee2e2;
        }
        .boton {
            display: inline-block;
            background-color: var(--accent);
            color: #0f172a;
            font-weight: bold;
            padding: 0.85rem 1.75rem;
            border-radius: 8px;
            text-decoration: none;
            transition: transform 0.15s ease, background-color 0.15s;
        }
        .boton:hover, .boton:focus {
            background-color: #7dd3fc;
            outline: var(--focus-outline);
            outline-offset: 3px;
        }
        .aviso-seguridad {
            background-color: #334155;
            padding: 1rem;
            border-radius: 6px;
            font-size: 0.95rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <main class="contenedor" role="main">
        <h1>Instalador de Base de Datos - Dominó para Todos</h1>
        
        <div class="mensaje <?php echo $tipo_mensaje; ?>" role="status" aria-live="polite">
            <p><?php echo $mensaje; ?></p>
        </div>

        <?php if ($tipo_mensaje === 'exito'): ?>
            <p>La estructura de la base de datos está verificada y lista para recibir partidas multijugador con humanos y Máquinas Expertas en <strong>Dominó para Todos</strong>.</p>
            <div class="aviso-seguridad">
                <strong>Recomendación de seguridad:</strong> Puedes eliminar o restringir el archivo <code>instalar.php</code> tras esta ejecución inicial.
            </div>
            <p style="margin-top: 2rem;">
                <a href="index.html" class="boton">Ir al Juego Dominó para Todos</a>
            </p>
        <?php else: ?>
            <p>Revisa la configuración en <code>conexion.php</code> y asegúrate de que el usuario de MySQL tenga permisos <code>CREATE TABLE</code>.</p>
        <?php endif; ?>
    </main>
</body>
</html>
