<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth.php';

// Validar que sea un organizador o administrador
requerirRol(['organizador', 'administrador']);

$idUsuarioActual = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? 0;
$rolActual       = $_SESSION['rol'] ?? 'visitante';

$idTorneo = $_GET['id_torneo'] ?? $_POST['id_torneo'] ?? '';
$periodo  = $_GET['periodo'] ?? $_POST['periodo'] ?? 'completo';
$formato  = $_GET['formato'] ?? $_POST['formato'] ?? 'pdf';

if (empty($idTorneo)) {
    echo "<div style='background: #ffe3e3; padding: 15px; border: 1px solid #ff8787; font-family: monospace;'>";
    echo "<h3>DEPURACIÓN DE DATOS RECIBIDOS (GET):</h3>";
    var_dump($_GET);
    echo "</div>";
    die("<br><strong>Error: No se seleccionó ningún torneo o opción válida para el reporte.</strong>");
}

try {
    $params = [];
    $condicionPeriodo = "";
    if ($periodo === 'semanal') {
        $condicionPeriodo = " AND t.fecha_inicio >= (NOW() - INTERVAL 7 DAY)";
    } elseif ($periodo === 'mensual') {
        $condicionPeriodo = " AND t.fecha_inicio >= (NOW() - INTERVAL 1 MONTH)";
    }

    $sqlTorneos = "SELECT t.*, m.nombre_modulo AS disciplina, c.formato, c.max_participantes
                   FROM torneos t
                   LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo
                   LEFT JOIN configuracion_torneo c ON t.id_torneo = c.id_torneo
                   WHERE 1=1";

    if ($rolActual !== 'administrador' && !empty($idUsuarioActual)) {
        $sqlTorneos .= " AND t.id_organizador = :id_organizador";
        $params[':id_organizador'] = $idUsuarioActual;
    }

    if ($idTorneo !== 'todos') {
        $sqlTorneos .= " AND t.id_torneo = :id_torneo";
        $params[':id_torneo'] = $idTorneo;
    }

    $sqlTorneos .= $condicionPeriodo . " ORDER BY t.fecha_inicio DESC";

    $stmt = $pdo->prepare($sqlTorneos);
    $stmt->execute($params);
    $torneosReporte = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($torneosReporte) && $idTorneo !== 'todos') {
        $sqlTorneosAlt = "SELECT t.*, m.nombre_modulo AS disciplina, c.formato, c.max_participantes
                          FROM torneos t
                          LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo
                          LEFT JOIN configuracion_torneo c ON t.id_torneo = c.id_torneo
                          WHERE t.id_torneo = :id_torneo";
        $stmtAlt = $pdo->prepare($sqlTorneosAlt);
        $stmtAlt->execute([':id_torneo' => $idTorneo]);
        $torneosReporte = $stmtAlt->fetchAll(PDO::FETCH_ASSOC);
    }

    if (empty($torneosReporte)) {
        echo "<div style='font-family: Arial; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
        echo "<h3>No se encontraron datos para generar el reporte con los filtros seleccionados.</h3>";
        echo "<p>ID Torneo solicitado: <code>" . htmlspecialchars($idTorneo) . "</code> | ID Usuario: <code>" . htmlspecialchars($idUsuarioActual) . "</code></p>";
        echo "</div>";
        exit;
    }

    foreach ($torneosReporte as &$torneo) {
        $idT = $torneo['id_torneo'];

        // Participantes inscritos
        $stmtPart = $pdo->prepare("
            SELECT i.estado_inscripcion, 
                   COALESCE(CONCAT(p.nombre, ' ', p.apellido), u.username, 'Participante') AS nombre_participante
            FROM inscripciones_torneo i
            LEFT JOIN participantes p ON i.id_participante = p.id_participante
            LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
            WHERE i.id_torneo = ?
        ");
        $stmtPart->execute([$idT]);
        $torneo['participantes'] = $stmtPart->fetchAll(PDO::FETCH_ASSOC);

        // Partidos y rondas (sin depender de nombres de columnas de resultados específicos)
        $stmtPartidos = $pdo->prepare("
            SELECT r.nombre_ronda, 
                   COALESCE(loc_p.nombre, loc_u.username, 'Por definir') AS local_nombre,
                   COALESCE(vis_p.nombre, vis_u.username, 'Por definir') AS visita_nombre,
                   e.estado_enfrentamiento
            FROM enfrentamientos e
            INNER JOIN rondas r ON e.id_ronda = r.id_ronda
            LEFT JOIN equipos loc ON e.id_local = loc.id_equipo
            LEFT JOIN participantes loc_p ON loc.id_participante = loc_p.id_participante
            LEFT JOIN usuarios loc_u ON loc_p.id_usuario = loc_u.id_usuario
            LEFT JOIN equipos vis ON e.id_visitante = vis.id_equipo
            LEFT JOIN participantes vis_p ON vis.id_participante = vis_p.id_participante
            LEFT JOIN usuarios vis_u ON vis_p.id_usuario = vis_u.id_usuario
            WHERE r.id_torneo = ?
        ");
        $stmtPartidos->execute([$idT]);
        $torneo['partidos'] = $stmtPartidos->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($torneo);

} catch (PDOException $e) {
    die("Error al recopilar los datos del reporte: " . $e->getMessage());
}

if ($formato === 'excel') {
    $nombreArchivo = "Reporte_Torneos_" . date('Y-m-d') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    fputcsv($output, ['REPORTE DE TORNEOS - SGDM']);
    fputcsv($output, ['Fecha de emision:', date('d/m/Y H:i')]);
    fputcsv($output, []);

    foreach ($torneosReporte as $t) {
        fputcsv($output, ['TORNEO:', $t['nombre_torneo'], 'Disciplina:', $t['disciplina'], 'Estado:', $t['estado']]);
        fputcsv($output, ['Fecha Inicio:', $t['fecha_inicio'], 'Lugar:', $t['lugar'], 'Formato:', $t['formato']]);
        
        fputcsv($output, ['--- PARTICIPANTES ---']);
        fputcsv($output, ['Nombre', 'Estado Inscripcion']);
        foreach ($t['participantes'] as $p) {
            fputcsv($output, [$p['nombre_participante'], $p['estado_inscripcion']]);
        }

        fputcsv($output, ['--- PARTIDOS Y ENFRENTAMIENTOS ---']);
        fputcsv($output, ['Ronda', 'Local', 'Visitante', 'Estado']);
        foreach ($t['partidos'] as $pt) {
            fputcsv($output, [$pt['nombre_ronda'], $pt['local_nombre'], $pt['visita_nombre'], $pt['estado_enfrentamiento']]);
        }
        fputcsv($output, []);
        fputcsv($output, [str_repeat('=', 50)]);
    }
    fclose($output);
    exit;

} else {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>SGDM - Reporte del Torneo</title>
        <link rel="icon" type="image/jpeg" href="../../img/logoapp2.jpeg">
        <link rel="stylesheet" href="../../css/generarReporte.css">
    </head>
    <body>
        <button class="btn-imprimir" onclick="window.print()">Imprimir / Guardar PDF</button>

        <div class="encabezado-reporte">
            <div>
                <h1 class="titulo-rep">Reporte de Gestión de Torneos</h1>
                <span class="fecha-rep">Sistema de Gestión de Torneos (SGDM)</span>
            </div>
            <div class="fecha-rep">
                Generado el: <?php echo date('d/m/Y H:i'); ?>
            </div>
        </div>

        <?php foreach ($torneosReporte as $t): ?>
            <div class="bloque-torneo">
                <h3><?php echo htmlspecialchars($t['nombre_torneo']); ?></h3>
                
                <div class="info-grid">
                    <div class="info-item"><strong>Disciplina:</strong> <?php echo htmlspecialchars($t['disciplina'] ?? 'General'); ?></div>
                    <div class="info-item"><strong>Estado:</strong> <?php echo ucfirst($t['estado']); ?></div>
                    <div class="info-item"><strong>Formato:</strong> <?php echo htmlspecialchars($t['formato'] ?? 'Estándar'); ?></div>
                    <div class="info-item"><strong>Fecha Inicio:</strong> <?php echo htmlspecialchars($t['fecha_inicio'] ?? 'Por definir'); ?></div>
                    <div class="info-item"><strong>Lugar:</strong> <?php echo htmlspecialchars($t['lugar'] ?? 'No especificado'); ?></div>
                    <div class="info-item"><strong>Cupos Máximos:</strong> <?php echo $t['max_participantes'] > 0 ? $t['max_participantes'] : 'Ilimitados'; ?></div>
                </div>

                <h4 style="margin: 15px 0 5px 0; font-size: 14px; color: #333;">Nómina de Participantes (<?php echo count($t['participantes']); ?>)</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Participante / Equipo</th>
                            <th>Estado de Inscripción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($t['participantes'])): ?>
                            <?php foreach ($t['participantes'] as $p): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($p['nombre_participante']); ?></td>
                                    <td><?php echo ucfirst($p['estado_inscripcion']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="2" style="text-align: center; color: #777;">No hay participantes registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h4 style="margin: 15px 0 5px 0; font-size: 14px; color: #333;">Historial de Partidos</h4>
                <table>
                    <thead>
                        <tr>
                            <th>Ronda</th>
                            <th>Local</th>
                            <th>Visitante</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($t['partidos'])): ?>
                            <?php foreach ($t['partidos'] as $pt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($pt['nombre_ronda']); ?></td>
                                    <td><?php echo htmlspecialchars($pt['local_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($pt['visita_nombre']); ?></td>
                                    <td><?php echo ucfirst($pt['estado_enfrentamiento']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align: center; color: #777;">No hay partidos programados o generados todavía.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

    </body>
    </html>
    <?php
    exit;
}