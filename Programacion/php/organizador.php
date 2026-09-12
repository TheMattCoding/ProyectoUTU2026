<?php
require_once 'logica/auth.php';
require_once 'db.php';
require_once 'logica/gestorTorneos.php';
require_once 'logica/avanzarTorneo.php';

$pestanaActiva = $_POST['pestana_activa'] ?? 'torneos';
$idTorneoSeleccionado = $_POST['id_torneo'] ?? 0; // Guarda el torneo seleccionado en el select

requerirRol(['organizador', 'administrador']);

$rolActual = $_SESSION['rol'] ?? 'visitante';
$usuarioActual = $_SESSION['usuario'] ?? 'Visitante';
$idUsuarioActual = $_SESSION['id_usuario'] ?? 0;

// Obtener la ruta de la foto de perfil desde la sesión
$fotoPerfilRaw = $_SESSION['foto_perfil'] ?? $_SESSION['foto'] ?? null;
$fotoPerfilActual = null;

if (!empty($fotoPerfilRaw)) {
    if (strpos($fotoPerfilRaw, '../') === 0 || strpos($fotoPerfilRaw, 'http') === 0) {
        $fotoPerfilActual = $fotoPerfilRaw;
    } else {
        $fotoPerfilActual = '../' . ltrim($fotoPerfilRaw, '/');
    }
}

$mensajeExito = '';
$mensajeError = '';
$accion = $_POST['accion'] ?? '';

// 1. AUTO-INICIO AUTOMÁTICO (por fecha y hora)
verificarYAutoIniciarTorneos($pdo);

// 2. PROCESAMIENTO DE ACCIONES DEL ORGANIZADOR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Cambiar estado manualmente o forzar inicio
    if ($accion === 'cambiar_estado_torneo') {
        $idTorneo = filter_var($_POST['id_torneo'] ?? 0, FILTER_VALIDATE_INT);
        $nuevoEstado = $_POST['nuevo_estado'] ?? '';

        if ($idTorneo && !empty($nuevoEstado)) {
            if ($nuevoEstado === 'en_curso') {
                // Forzar el inicio manual del torneo
                $res = iniciarTorneo($pdo, $idTorneo, true);
                if ($res['exito']) {
                    $mensajeExito = $res['mensaje'];
                } else {
                    $mensajeError = $res['mensaje'];
                }
            } else {
                // Actualización manual simple
                $stmtEst = $pdo->prepare("UPDATE torneos SET estado = ? WHERE id_torneo = ?");
                $stmtEst->execute([$nuevoEstado, $idTorneo]);
                $mensajeExito = "Estado del torneo actualizado a '$nuevoEstado'.";
            }
        }
    }

    // Inscripción manual de participantes
    if ($accion === 'inscribir_participante') {
        $idTorneo = filter_var($_POST['id_torneo'] ?? 0, FILTER_VALIDATE_INT);
        $idParticipante = filter_var($_POST['id_participante'] ?? 0, FILTER_VALIDATE_INT);

        if ($idTorneo && $idParticipante) {
            try {
                // Verificar si el participante ya se encuentra inscrito en el torneo
                $sqlVerificar = "SELECT COUNT(*) FROM INSCRIPCIONES_TORNEO 
                                 WHERE id_torneo = :id_torneo AND id_participante = :id_participante";
                $stmtVerificar = $pdo->prepare($sqlVerificar);
                $stmtVerificar->execute([
                    ':id_torneo'       => $idTorneo,
                    ':id_participante' => $idParticipante
                ]);

                if ($stmtVerificar->fetchColumn() > 0) {
                    $mensajeError = "El participante ya está inscrito en este torneo.";
                } else {
                    $sqlInscribir = "INSERT INTO INSCRIPCIONES_TORNEO (id_torneo, id_participante, estado_inscripcion) 
                                     VALUES (:id_torneo, :id_participante, 'confirmado')";
                    $stmtInscribir = $pdo->prepare($sqlInscribir);
                    $stmtInscribir->execute([
                        ':id_torneo'       => $idTorneo,
                        ':id_participante' => $idParticipante
                    ]);

                    $mensajeExito = "Participante inscrito correctamente en el torneo.";
                }
            } catch (PDOException $e) {
                $mensajeError = "Error al inscribir participante: " . $e->getMessage();
            }
        } else {
            $mensajeError = "Por favor, seleccioná un torneo y un participante válidos.";
        }
    }

    // Cargar/Guardar resultado individual
    if ($accion === 'guardar_resultado_individual') {
        $idEnfrentamiento = filter_var($_POST['id_enfrentamiento'] ?? 0, FILTER_VALIDATE_INT);
        $mLocal = filter_var($_POST['marcador_local'] ?? null, FILTER_VALIDATE_INT);
        $mVisita = filter_var($_POST['marcador_visita'] ?? null, FILTER_VALIDATE_INT);
        $idLocal = filter_var($_POST['id_local'] ?? 0, FILTER_VALIDATE_INT);
        $idVisitante = filter_var($_POST['id_visitante'] ?? 0, FILTER_VALIDATE_INT);
        $idTorneo = filter_var($_POST['id_torneo'] ?? 0, FILTER_VALIDATE_INT);

        if ($idEnfrentamiento && $mLocal !== false && $mVisita !== false) {
            try {
                $pdo->beginTransaction();

                $idGanador = null;
                if ($mLocal > $mVisita && $idLocal > 0) {
                    $idGanador = $idLocal;
                } elseif ($mVisita > $mLocal && $idVisitante > 0) {
                    $idGanador = $idVisitante;
                }

                $stmtEstado = $pdo->prepare("UPDATE enfrentamientos SET estado_enfrentamiento = 'finalizado' WHERE id_enfrentamiento = ?");
                $stmtEstado->execute([$idEnfrentamiento]);

                $stmtBuscar = $pdo->prepare("SELECT id_resultado, fecha_registro FROM resultados WHERE id_enfrentamiento = ?");
                $stmtBuscar->execute([$idEnfrentamiento]);
                $resExistente = $stmtBuscar->fetch(PDO::FETCH_ASSOC);

                if ($resExistente) {
                    $horasTranscurridas = (time() - strtotime($resExistente['fecha_registro'])) / 3600;
                    if ($horasTranscurridas > 48) {
                        throw new Exception("El plazo de 2 días para modificar este resultado ha expirado.");
                    }

                    $sqlUpdate = $pdo->prepare("
                        UPDATE resultados 
                        SET puntuacion = ?, id_ganador = ?, id_usuario_registro = ?, fecha_registro = NOW()
                        WHERE id_enfrentamiento = ?
                    ");
                    $sqlUpdate->execute([$mLocal, $idGanador, $idUsuarioActual, $idEnfrentamiento]);
                } else {
                    $sqlInsert = $pdo->prepare("
                        INSERT INTO resultados (id_enfrentamiento, puntuacion, id_ganador, id_usuario_registro, fecha_registro)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $sqlInsert->execute([$idEnfrentamiento, $mLocal, $idGanador, $idUsuarioActual]);
                }

                $pdo->commit();

                if ($idTorneo) {
                    verificarYAvanzarTorneo($pdo, $idTorneo);
                }

                $mensajeExito = "Marcador guardado correctamente.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $mensajeError = $e->getMessage();
            }
        } else {
            $mensajeError = "Por favor ingresá un marcador válido.";
        }
    }
}

// ==========================================
// CONSULTA DE TORNEOS, PARTIDOS Y PARTICIPANTES
// ==========================================
try {
    // 1. Obtener lista de Torneos
    if ($rolActual === 'administrador') {
        $sqlTorneos = "SELECT t.*, m.nombre_modulo AS disciplina 
                       FROM torneos t 
                       LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo 
                       ORDER BY t.id_torneo DESC";
        $stmtT = $pdo->prepare($sqlTorneos);
        $stmtT->execute();
    } else {
        $sqlTorneos = "SELECT t.*, m.nombre_modulo AS disciplina 
                       FROM torneos t 
                       LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo 
                       WHERE t.id_organizador = :id_organizador 
                       ORDER BY t.id_torneo DESC";
        $stmtT = $pdo->prepare($sqlTorneos);
        $stmtT->execute([':id_organizador' => $idUsuarioActual]);
    }
    $torneosAsignados = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener lista de Participantes registrados
    $stmtP = $pdo->query("SELECT id_participante, CONCAT(nombre, ' ', apellido) AS nombre_participante FROM participantes ORDER BY nombre ASC, apellido ASC");
    $listaParticipantes = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    $sqlPartidos = "SELECT 
                    e.id_enfrentamiento,
                    e.id_local,
                    e.id_visitante,
                    e.estado_enfrentamiento,
                    COALESCE(loc_p.nombre, 'Por definir') AS equipo_local,
                    COALESCE(vis_p.nombre, 'Por definir') AS equipo_visita,
                    r.nombre_ronda,
                    t.id_torneo,
                    t.nombre_torneo,
                    res.puntuacion AS marcador_local,
                    0 AS marcador_visita,
                    res.fecha_registro
                FROM enfrentamientos e
                INNER JOIN rondas r ON e.id_ronda = r.id_ronda
                INNER JOIN torneos t ON r.id_torneo = t.id_torneo
                LEFT JOIN equipos loc ON e.id_local = loc.id_equipo
                LEFT JOIN participantes loc_p ON loc.id_participante = loc_p.id_participante
                LEFT JOIN equipos vis ON e.id_visitante = vis.id_equipo
                LEFT JOIN participantes vis_p ON vis.id_participante = vis_p.id_participante
                LEFT JOIN resultados res ON e.id_enfrentamiento = res.id_enfrentamiento
                WHERE e.estado_enfrentamiento = 'pendiente'
                   OR (e.estado_enfrentamiento = 'finalizado' AND res.fecha_registro >= NOW() - INTERVAL 2 DAY)";

    if ($rolActual !== 'administrador') {
        $sqlPartidos .= " AND t.id_organizador = :id_organizador";
        $stmtPartidos = $pdo->prepare($sqlPartidos);
        $stmtPartidos->execute([':id_organizador' => $idUsuarioActual]);
    } else {
        $stmtPartidos = $pdo->prepare($sqlPartidos);
        $stmtPartidos->execute();
    }
    $partidosPendientes = $stmtPartidos->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $errorBaseDatos = "Error SQL: " . $e->getMessage();
    $torneosAsignados = [];
    $listaParticipantes = [];
    $partidosPendientes = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Panel de Organizador</title>
    
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/organizador.css">
    <script src="../js/organizador.js" defer></script>
</head>
<body>

    <?php if (isset($errorBaseDatos)): ?>
        <div class="mensaje-sql-error"><?= htmlspecialchars($errorBaseDatos) ?></div>
    <?php endif; ?>

    <!-- 5. Menú lateral -->
    <input type="checkbox" id="menu-toggle" class="menu-checkbox">

    <div class="sidebar">

        <!-- 5. Móvil cerrar menú -->
        <div class="sidebar-header">
            <span class="sidebar-title">Menú</span>
            <label for="menu-toggle" class="close-sidebar-btn" aria-label="Cerrar menú">X</label>
        </div>
        
        <nav class="sidebar-nav">
            <!-- Visible para todos (incluyendo visitantes) -->
            <a href="inicio.php" class="sidebar-link">Inicio</a>
            <a href="calendario.php" class="sidebar-link">Calendario de torneos</a>

            <!-- Solo Organizadores y Administradores -->
            <?php if (in_array($rolActual, ['organizador', 'administrador'])): ?>
                <a href="organizador.php" class="sidebar-link active">Panel Organizador</a>
            <?php endif; ?>

            <!-- Solo Administradores -->
            <?php if ($rolActual === 'administrador'): ?>
                <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
                <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <?php endif; ?>

            <!-- Solo Usuarios Registrados (no visitantes) -->
            <?php if ($rolActual !== 'visitante'): ?>
                <a href="configuracion.php" class="sidebar-link">Configuración</a>
            <?php endif; ?>
        </nav>
    </div>

    <label for="menu-toggle" class="sidebar-overlay"></label>

    <!-- Navbar -->
    <nav class="navbar" aria-label="Navegación principal">
        <label for="menu-toggle" class="nav-button" aria-label="Abrir menú de navegación">
            <div class="hamburger-box">
                <span class="line"></span>
                <span class="line"></span>
                <span class="line"></span>
            </div>
        </label>

        <form action="busquedaTorneo.php" method="GET" class="search-form" style="display: flex; flex: 1; max-width: 420px; margin: 0 12px;">
            <div class="search-container" style="margin: 0; width: 100%;">
                <svg class="search-google-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="#777777"/>
                </svg>
                <input type="text" class="search-input" placeholder="Buscar un torneo" aria-label="Buscar torneos" name="query">
            </div>
        </form>
            <a href="busquedaTorneo.php" class="btn-ver-torneos-nav">
                Ver torneos
            </a>
        <div class="notifications-dropdown">
            <input type="checkbox" id="noti-toggle" class="dropdown-checkbox">
            <label for="noti-toggle" class="notifications-dropdown-button" aria-label="Notificaciones">
                <div class="notifications-icon-wrapper">
                    <svg class="bell-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" fill="#cccccc"/>
                    </svg>
                    <span class="notification-dot"></span>
                </div>
            </label>
            <label for="noti-toggle" class="dropdown-overlay"></label>
            <div class="notifications-menu-card">
            <div class="notifications-menu-header">
                <span class="notifications-menu-title">Notificaciones</span>
            </div>
            <div class="notifications-menu-divider"></div>
            <div class="notifications-menu-list">
                <?php if (empty($mis_notis)): ?>
                    <div>No hay notificaciones.</div>
                <?php else: ?>
                    <?php foreach ($mis_notis as $n): ?>
                        <div>
                            <a href="<?= htmlspecialchars($n['enlace']) ?>" class="notification-item unread">
                                <div class="noti-indicator"></div>
                                <div class="noti-content">
                                    <p class="noti-text"><?= htmlspecialchars($n['mensaje']) ?></p>
                                </div>
                            </a>
                
                            <!-- Botón para borrar/descartar -->
                            <form action="logica/eliminarNotificacion.php" method="POST">
                                <input type="hidden" name="id_notificacion" value="<?= htmlspecialchars($n['id']) ?>">
                                <button class="eliminar_notificacion" type="submit" title="Eliminar notificación">&times;</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <!-- Menú de Usuario con Foto Dinámica -->
        <div class="profile-dropdown">
            <input type="checkbox" id="profile-toggle" class="dropdown-checkbox">
            <label for="profile-toggle" class="profile-dropdown-button" aria-label="Menú de usuario">
                <div class="user-avatar">
                    <?php if ($fotoPerfilActual): ?>
                        <img src="<?= htmlspecialchars($fotoPerfilActual) ?>" alt="Avatar" class="avatar-img" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                    <?php else: ?>
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                        </svg>
                    <?php endif; ?>
                </div>
            </label>
            <label for="profile-toggle" class="dropdown-overlay"></label>
            <div class="profile-menu-card">
                <div class="profile-menu-header">
                    <span class="profile-menu-name">
                        <?= htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Invitado') ?>
                    </span>
                </div>
                <div class="profile-menu-divider"></div>
                <nav class="profile-menu-links">
                    <?php if ($rolActual === 'visitante'): ?>
                        <a href="logica/login.php" class="profile-menu-item">Iniciar sesión</a>
                    <?php else: ?>
                        <a href="perfil.php" class="profile-menu-item">Perfil</a>
                        <div class="profile-menu-divider"></div>
                        <a href="logica/logout.php" class="profile-menu-item logout-item">Cierre de sesión</a>
                    <?php endif; ?>
                </nav>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-torneos" class="control-radio-pestana" <?php echo ($pestanaActiva === 'torneos') ? 'checked' : ''; ?>>
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-fixtures" class="control-radio-pestana" <?php echo ($pestanaActiva === 'fixtures') ? 'checked' : ''; ?>>
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-participantes" class="control-radio-pestana" <?php echo ($pestanaActiva === 'participantes') ? 'checked' : ''; ?>>
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-reportes" class="control-radio-pestana" <?php echo ($pestanaActiva === 'reportes') ? 'checked' : ''; ?>>
        <div class="contenedor-organizador">
            
            <aside class="pestanas-organizador">
                <h2 class="titulo-organizador">Panel Organizador</h2>
                <label for="radio-pestana-torneos" class="btn-pestana label-torneos">Torneos Asignados</label>
                <label for="radio-pestana-fixtures" class="btn-pestana label-fixtures">Cargar Resultados</label>
                <label for="radio-pestana-participantes" class="btn-pestana label-participantes">Inscribir Participantes</label>
                <label for="radio-pestana-reportes" class="btn-pestana label-reportes">Reportes del Torneo</label>
            </aside>

            <section class="tarjeta-contenido-organizador">
                
                <!-- Pestaña 1: Torneos Asignados -->
                <div class="seccion-organizador panel-torneos">
                    <div class="encabezado-seccion-enlinea">
                        <h3 class="titulo-seccion">Tus Competencias</h3>
                    </div>
                    <p class="subtitulo-seccion">Lista de torneos bajo tu estricta supervisión y desarrollo.</p>
                    
                    <!-- Alertas Pestaña Torneos -->
                    <?php if (!empty($mensajeExito) && $pestanaActiva === 'torneos'): ?>
                        <div style="background-color: #1b4332; color: #2ec4b6; border: 1px solid #2ec4b6; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                            ✓ <?php echo htmlspecialchars($mensajeExito); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mensajeError) && $pestanaActiva === 'torneos'): ?>
                        <div style="background-color: #4a151b; color: #ff6b6b; border: 1px solid #ff6b6b; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                            ✕ <?php echo htmlspecialchars($mensajeError); ?>
                        </div>
                    <?php endif; ?>

                    <div class="contenedor-tabla">
                        <table class="tabla-datos">
                            <thead>
                                <tr>
                                    <th>Nombre del Torneo</th>
                                    <th>Disciplina</th>
                                    <th>Estado</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($torneosAsignados)): ?>
                                    <?php foreach ($torneosAsignados as $itemTorneo): ?>
                                        <tr>
                                            <td data-etiqueta="Torneo">
                                                <strong><?php echo htmlspecialchars($itemTorneo['nombre_torneo'] ?? 'Sin nombre'); ?></strong>
                                            </td>
                                            <td data-etiqueta="Disciplina">
                                                <?php echo htmlspecialchars($itemTorneo['disciplina'] ?? 'General'); ?>
                                            </td>
                                            <td data-etiqueta="Estado">
                                                <?php 
                                                    $estado = strtolower($itemTorneo['estado'] ?? 'pendiente');
                                                    $claseInsignia = match($estado) {
                                                        'en curso' => 'insignia-exito',
                                                        'abierto', 'inscripciones' => 'insignia-advertencia',
                                                        default => 'insignia-secundario'
                                                    };
                                                ?>
                                                <span class="insignia <?php echo $claseInsignia; ?>">
                                                    <?php echo ucfirst($estado); ?>
                                                </span>
                                            </td>
                                            <td data-etiqueta="Acción">
                                                <div style="display: flex; gap: 6px; align-items: center;">
                                                    <a href="detalleTorneo.php?id=<?php echo $itemTorneo['id_torneo']; ?>" class="btn-secundario-chico">Ver Detalle</a>
                                                    <form action="organizador.php" method="POST" style="margin: 0;">
                                                        <input type="hidden" name="pestana_activa" value="torneos">
                                                        <input type="hidden" name="accion" value="cambiar_estado_torneo">
                                                        <input type="hidden" name="id_torneo" value="<?php echo $itemTorneo['id_torneo']; ?>">
                                                        <?php if ($estado === 'pendiente'): ?>
                                                            <input type="hidden" name="nuevo_estado" value="en_curso">
                                                            <button type="submit" class="btn-guardar" onclick="return confirm('¿Iniciar torneo manualmente con los participantes actuales?');">
                                                                Iniciar Torneo
                                                            </button>
                                                        <?php elseif ($estado === 'en_curso'): ?>
                                                            <input type="hidden" name="nuevo_estado" value="finalizado">
                                                            <button type="submit" class="btn-secundario-chico" style="background-color: #d90429; color: white;" onclick="return confirm('¿Desea marcar el torneo como finalizado?');">
                                                                Finalizar
                                                            </button>
                                                        <?php endif; ?>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="celda-tabla-vacia">No tienes torneos asignados actualmente.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pestaña 2: Fixtures y Resultados -->
                <div class="seccion-organizador panel-fixtures">
                    <h3 class="titulo-seccion">Gestión de Fixtures y Rondas</h3>
                    <p class="subtitulo-seccion">Cargá resultados individualmente o corregilos hasta 48hs después de guardados.</p>

                    <!-- Alertas Pestaña Fixtures -->
                    <?php if (!empty($mensajeExito) && $pestanaActiva === 'fixtures'): ?>
                        <div style="background-color: #1b4332; color: #2ec4b6; border: 1px solid #2ec4b6; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                            ✓ <?php echo htmlspecialchars($mensajeExito); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mensajeError) && $pestanaActiva === 'fixtures'): ?>
                        <div style="background-color: #4a151b; color: #ff6b6b; border: 1px solid #ff6b6b; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                            ✕ <?php echo htmlspecialchars($mensajeError); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($partidosPendientes)): ?>
                        <?php foreach ($partidosPendientes as $partido): ?>
                            <?php 
                                $idEnf = $partido['id_enfrentamiento'];
                                $esFinalizado = ($partido['estado_enfrentamiento'] === 'finalizado');
                            ?>
                            <form action="organizador.php" method="POST" class="formulario-organizador-partido" style="margin-bottom: 15px; border-bottom: 1px solid #333; padding-bottom: 15px;">
                                <input type="hidden" name="pestana_activa" value="fixtures">
                                <input type="hidden" name="accion" value="guardar_resultado_individual">
                                <input type="hidden" name="id_enfrentamiento" value="<?php echo $idEnf; ?>">
                                <input type="hidden" name="id_torneo" value="<?php echo $partido['id_torneo']; ?>">
                                <input type="hidden" name="id_local" value="<?php echo $partido['id_local']; ?>">
                                <input type="hidden" name="id_visitante" value="<?php echo $partido['id_visitante']; ?>">

                                <div class="etiqueta-partido-torneo" style="display: flex; justify-content: space-between;">
                                    <span><?php echo htmlspecialchars($partido['nombre_torneo']); ?> - <?php echo htmlspecialchars($partido['nombre_ronda']); ?></span>
                                    <?php if ($esFinalizado): ?>
                                        <span style="color: #2ec4b6; font-size: 0.85em;">Finalizado (Editable)</span>
                                    <?php endif; ?>
                                </div>

                                <div class="tarjeta-fila-partido">
                                    <span class="nombre-equipo texto-derecha"><?php echo htmlspecialchars($partido['equipo_local']); ?></span>
                                    <div class="entradas-marcador-partido">
                                        <input type="number" name="marcador_local" class="control-formulario-entrada entrada-marcador" value="<?php echo $partido['marcador_local'] ?? 0; ?>" min="0" required>
                                        <span class="divisor-marcador">vs</span>
                                        <input type="number" name="marcador_visita" class="control-formulario-entrada entrada-marcador" value="<?php echo $partido['marcador_visita'] ?? 0; ?>" min="0" required>
                                    </div>
                                    <span class="nombre-equipo texto-izquierda"><?php echo htmlspecialchars($partido['equipo_visita']); ?></span>
                                    
                                    <button type="submit" class="btn-guardar" style="margin-left: 10px; padding: 6px 12px;">
                                        <?php echo $esFinalizado ? 'Actualizar' : 'Guardar'; ?>
                                    </button>
                                </div>
                            </form>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="texto-sin-partidos">No hay partidos pendientes ni en plazo de modificación.</p>
                    <?php endif; ?>
                </div>

                <!-- Pestaña 3: Inscribir Participantes -->
                <div class="seccion-organizador panel-participantes">
                    <h3 class="titulo-seccion">Inscribir Participantes</h3>
                    <p class="subtitulo-seccion">Seleccioná un participante registrado para agregarlo al torneo.</p>

                    <!-- Alertas Pestaña Participantes -->
                    <?php if (!empty($mensajeExito) && $pestanaActiva === 'participantes'): ?>
                        <div style="background-color: #1b4332; color: #2ec4b6; border: 1px solid #2ec4b6; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                            ✓ <?php echo htmlspecialchars($mensajeExito); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mensajeError) && $pestanaActiva === 'participantes'): ?>
                        <div style="background-color: #4a151b; color: #ff6b6b; border: 1px solid #ff6b6b; padding: 10px; border-radius: 6px; margin-bottom: 15px;">
                            ✕ <?php echo htmlspecialchars($mensajeError); ?>
                        </div>
                    <?php endif; ?>

                    <form action="organizador.php" method="POST" class="formulario-organizador">
                        <input type="hidden" name="pestana_activa" value="participantes">
                        <input type="hidden" name="accion" value="inscribir_participante">

                        <div class="grupo-formulario">
                            <label for="id_torneo" class="etiqueta-formulario">Seleccionar Torneo</label>
                            <select name="id_torneo" id="id_torneo" class="control-formulario-entrada" required>
                                <option value="" disabled <?php echo empty($idTorneoSeleccionado) ? 'selected' : ''; ?>>Seleccioná un torneo</option>
                                <?php foreach ($torneosAsignados as $t): ?>
                                    <option value="<?php echo $t['id_torneo']; ?>" <?php echo ($idTorneoSeleccionado == $t['id_torneo']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t['nombre_torneo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grupo-formulario">
                            <label for="id_participante" class="etiqueta-formulario">Seleccionar Participante</label>
                            <select name="id_participante" id="id_participante" class="control-formulario-entrada" required>
                                <option value="" disabled selected>Seleccioná un participante</option>
                                <?php foreach ($listaParticipantes as $p): ?>
                                    <option value="<?php echo $p['id_participante']; ?>"><?php echo htmlspecialchars($p['nombre_participante']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-guardar">Registrar Inscripción</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña 4: Reportes -->
                <div class="seccion-organizador panel-reportes">
                    <h3 class="titulo-seccion">Métricas y Reportes Operativos</h3>
                    <p class="subtitulo-seccion">Exporta las planillas de juego o analiza los datos de rendimiento de la competencia.</p>
                    
                    <div class="cuadricula-acciones-reporte">
                        <div class="tarjeta-descarga-reporte">
                            <h5>Lista de Buena Fe (Inscritos)</h5>
                            <button type="button" class="btn-secundario-chico">Descargar PDF</button>
                        </div>
                        <div class="tarjeta-descarga-reporte">
                            <h5>Tabla de Goleadores / MVP</h5>
                            <button type="button" class="btn-secundario-chico">Exportar Excel</button>
                        </div>
                    </div>
                </div>

            </section>
        </div>
    </main>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="footer-content">
            <img src="../img/epsilonSoftware2.png" alt="Logo Epsilon Software" class="footer-logo">
        
            <div class="footer-right-group">
                <nav class="footer-links" aria-label="Enlaces de pie de página">
                    <button type="button" id="btn-seccion-nosotros" class="footer-link-btn">Sobre nosotros</button>
                    <button type="button" id="btn-seccion-ayuda" class="footer-link-btn">Ayuda</button>
                </nav>
                <p class="footer-copyright">&copy; 2026 Epsilon Software. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>

    <!-- Fondo Oscurecido para Modales -->
    <div id="fondo-seccion-nosotros" class="fondo-seccion"></div>

    <!-- Modal Sobre Nosotros -->
    <section id="seccion-sobre-nosotros" class="seccion-desplegable" aria-hidden="true">
        <div class="seccion-encabezado">
            <h3 class="seccion-titulo">Sobre Nosotros</h3>
            <button type="button" id="btn-cerrar-seccion-nosotros" class="btn-cerrar-seccion" aria-label="Cerrar sección">&times;</button>
        </div>

        <div class="seccion-contenido">
            <div class="logo-empresa-contenedor">
                <img src="../img/epsilonSoftware2.png" alt="Logo Epsilon Software" class="logo-modal">
            </div>

            <div class="bloque-nosotros">
                <h4 class="subtitulo-nosotros">Misión</h4>
                <p class="texto-nosotros">Proporcionar a comunidades y organizadores una plataforma intuitiva y eficiente para la gestión integral de torneos deportivos y de eSports, centralizando fixtures, inscripciones y resultados en un solo lugar.</p>
            </div>

            <div class="bloque-nosotros">
                <h4 class="subtitulo-nosotros">Visión</h4>
                <p class="texto-nosotros">Ser la solución digital referente en el desarrollo y automatización de eventos competitivos, impulsando el crecimiento del talento deportivo y gaming en la región.</p>
            </div>

            <div class="detalles-nosotros">
                <div class="item-detalle">
                    <span class="etiqueta-detalle">Desarrollado por:</span>
                    <span class="valor-detalle">Epsilon Software</span>
                </div>
                <div class="item-detalle">
                    <span class="etiqueta-detalle">Versión de la App:</span>
                    <span class="valor-detalle">v1.0.0</span>
                </div>
                <div class="item-detalle">
                    <span class="etiqueta-detalle">Contacto:</span>
                    <span class="valor-detalle">epsilonsoftwarecontacto@gmail.com</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Modal Ayuda y Soporte -->
    <section id="seccion-ayuda" class="seccion-desplegable" aria-hidden="true">
        <div class="seccion-encabezado">
            <h3 class="seccion-titulo">Centro de Ayuda</h3>
            <button type="button" id="btn-cerrar-seccion-ayuda" class="btn-cerrar-seccion" aria-label="Cerrar sección">&times;</button>
        </div>

        <div class="seccion-contenido">
            <!-- 1. Preguntas Frecuentes (FAQ) -->
            <div class="bloque-nosotros">
                <h4 class="subtitulo-nosotros">Preguntas Frecuentes</h4>
                
                <details class="item-faq">
                    <summary class="pregunta-faq">¿Cómo me inscribo a un torneo?</summary>
                    <p class="texto-nosotros">Ve a la sección de torneos, selecciona la competencia deseada y presiona en "Inscribirse".</p>
                </details>

                <details class="item-faq">
                    <summary class="pregunta-faq">¿Cómo edito la información de mi perfil?</summary>
                    <p class="texto-nosotros">Haz clic en la seccion de configuración del menú lateral y accede a la pestaña "Editar perfil" para actualizar tus datos personales.</p>
                </details>
            </div>

            <!-- 2. Soporte Técnico y Contacto Directo -->
            <div class="bloque-nosotros">
                <h4 class="subtitulo-nosotros">Soporte Técnico y Contacto Directo</h4>
                <div class="detalles-nosotros">
                    <div class="item-detalle">
                        <span class="etiqueta-detalle">Correo de soporte:</span>
                        <span class="valor-detalle">epsilonsoftwarecontacto@gmail.com</span>
                    </div>
                    <div class="item-detalle">
                        <span class="etiqueta-detalle">Horarios de atención:</span>
                        <span class="valor-detalle">Lunes a Viernes de 09:00 a 18:00 hs</span>
                    </div>
                </div>
            </div>

            <!-- 3 y 4. Guías, Tutoriales y Reporte de Errores -->
            <div class="bloque-nosotros">
                <h4 class="subtitulo-nosotros">Recursos y Reporte de Errores</h4>
                <p class="texto-nosotros">¿Encontraste un fallo o un error? Puedes notificarlo o consultar nuestra documentación oficial:</p>
                <div class="detalles-nosotros">
                    <div class="item-detalle">
                        <span class="etiqueta-detalle">Manual de usuario:</span>
                        <a href="#" class="valor-detalle enlace-ayuda" target="_blank" rel="noopener">Ver Guía en PDF</a>
                    </div>
                    <div class="item-detalle">
                        <span class="etiqueta-detalle">Reportar fallo (Bug):</span>
                        <a href="https://mail.google.com/mail/?view=cm&fs=1&to=epsilonsoftwarecontacto@gmail.com&su=Error&body=Descripción%20del%20error:%0A%0APágina/Sección:%0A%0APasos%20para%20reproducirlo:" class="valor-detalle enlace-ayuda" target="_blank" rel="noopener">Enviar reporte de error</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript -->
    <script src="../js/seccionSobreNosotros.js"></script>
    <script src="../js/seccionAyuda.js"></script>
</body>
</html>