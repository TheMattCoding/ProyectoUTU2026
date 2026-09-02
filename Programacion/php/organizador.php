<?php
require_once 'logica/auth.php';
require_once 'db.php';

requerirRol(['organizador', 'administrador']);

$rolActual = $_SESSION['rol'] ?? 'visitante';
$usuarioActual = $_SESSION['usuario'] ?? 'Visitante';
$idUsuarioActual = $_SESSION['id_usuario'] ?? 0;

$mensajeExito = '';
$mensajeError = '';
$accion = $_POST['accion'] ?? '';

// ==========================================
// AUTO-ACTIVAR PRIMERA RONDA SI LLEGÓ LA FECHA
// ==========================================
try {
    $sqlAutoActivar = "UPDATE rondas r
                       INNER JOIN torneos t ON r.id_torneo = t.id_torneo
                       SET r.estado_ronda = 'en_curso'
                       WHERE t.fecha_inicio <= CURDATE() 
                         AND r.numero_ronda = 1 
                         AND r.estado_ronda = 'pendiente'";
    $pdo->query($sqlAutoActivar);
} catch (PDOException $e) {
    // Silencioso o log de error
}

// ==========================================
// PROCESAMIENTO DE FORMULARIOS (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Inscripción manual de participantes
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

    // 2. Cargar/Guardar resultados múltiples
    if ($accion === 'guardar_resultados') {
        $resultados = $_POST['resultados'] ?? [];

        if (!empty($resultados) && is_array($resultados)) {
            try {
                $pdo->beginTransaction();

                $sqlEstado = "UPDATE enfrentamientos 
                              SET estado_enfrentamiento = 'finalizado' 
                              WHERE id_enfrentamiento = :id";
                $stmtEstado = $pdo->prepare($sqlEstado);

                $sqlResultado = "INSERT INTO resultados (id_enfrentamiento, puntuacion_local, puntuacion_visitante, id_usuario_registro)
                                 VALUES (:id, :m_local, :m_visita, :id_usuario)
                                 ON DUPLICATE KEY UPDATE 
                                    puntuacion_local = VALUES(puntuacion_local), 
                                    puntuacion_visitante = VALUES(puntuacion_visitante),
                                    id_usuario_registro = VALUES(id_usuario_registro)";
                $stmtResultado = $pdo->prepare($sqlResultado);

                foreach ($resultados as $idEnfrentamiento => $datos) {
                    $mLocal = filter_var($datos['local'] ?? null, FILTER_VALIDATE_INT);
                    $mVisita = filter_var($datos['visita'] ?? null, FILTER_VALIDATE_INT);

                    if ($idEnfrentamiento && $mLocal !== false && $mVisita !== false) {
                        $stmtEstado->execute([':id' => $idEnfrentamiento]);

                        $stmtResultado->execute([
                            ':id'         => $idEnfrentamiento,
                            ':m_local'    => $mLocal,
                            ':m_visita'   => $mVisita,
                            ':id_usuario' => $idUsuarioActual
                        ]);
                    }
                }

                $pdo->commit();
                $mensajeExito = "Marcadores guardados con éxito.";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $mensajeError = "Error al guardar los marcadores: " . $e->getMessage();
            }
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

    // 2. Obtener lista de Participantes y Equipos
    $stmtP = $pdo->query("SELECT id_participante, CONCAT(nombre, ' ', apellido) AS nombre_participante FROM PARTICIPANTES ORDER BY nombre ASC, apellido ASC");
    $listaParticipantes = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    $stmtE = $pdo->query("SELECT id_equipo, nombre_equipo FROM equipos ORDER BY nombre_equipo ASC");
    $listaEquipos = $stmtE->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener partidos pendientes
    $sqlPartidos = "SELECT 
                        e.id_enfrentamiento,
                        COALESCE(loc.nombre_equipo, 'Por definir') AS equipo_local,
                        COALESCE(vis.nombre_equipo, 'Por definir') AS equipo_visita,
                        r.nombre_ronda,
                        t.nombre_torneo,
                        res.puntuacion_local AS marcador_local,
                        res.puntuacion_visitante AS marcador_visita
                    FROM enfrentamientos e
                    INNER JOIN rondas r ON e.id_ronda = r.id_ronda
                    INNER JOIN torneos t ON r.id_torneo = t.id_torneo
                    LEFT JOIN equipos loc ON e.id_local = loc.id_equipo
                    LEFT JOIN equipos vis ON e.id_visitante = vis.id_equipo
                    LEFT JOIN resultados res ON e.id_enfrentamiento = res.id_enfrentamiento
                    WHERE e.estado_enfrentamiento != 'finalizado'";

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

    <!-- Menú lateral -->
    <input type="checkbox" id="menu-toggle" class="menu-checkbox">

    <div class="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Menú</span>
            <label for="menu-toggle" class="close-sidebar-btn" aria-label="Cerrar menú">X</label>
        </div>
        
        <nav class="sidebar-nav">
            <a href="inicio.php" class="sidebar-link">Inicio</a>
            <a href="calendario.php" class="sidebar-link">Calendario de torneos</a>

            <?php if (in_array($rolActual, ['organizador', 'administrador'])): ?>
                <a href="organizador.php" class="sidebar-link active">Panel Organizador</a>
            <?php endif; ?>

            <?php if ($rolActual === 'administrador'): ?>
                <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
                <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <?php endif; ?>

            <?php if ($rolActual !== 'visitante'): ?>
                <a href="configuracion.php" class="sidebar-link">Configuración</a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="theme-switch-container">
                <span class="theme-label">Modo Oscuro</span>
                <button class="theme-toggle-btn" aria-label="Cambiar tema">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="icono-tema">
                        <path d="M256 0C114.6 0 0 114.6 0 256S114.6 512 256 512c68.8 0 131.3-27.2 177.3-71.4 7.3-7 9.4-17.9 5.3-27.1s-13.7-14.9-23.8-14.1c-4.9 .4-9.8 .6-14.8 .6-101.6 0-184-82.4-184-184 0-72.1 41.5-134.6 102.1-164.8 9.1-4.5 14.3-14.3 13.1-24.4S322.6 8.5 312.7 6.3C294.4 2.2 275.4 0 256 0z"/>
                    </svg>
                </button>
            </div>
        </div>
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

        <!-- Busqueda de Torneo -->
        <form action="busquedaTorneo.php" method="GET" class="search-form navbar-search-form">
            <div class="search-container navbar-search-container">
                <svg class="search-google-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="#777777"/>
                </svg>
                <input type="text" class="search-input" placeholder="Buscar un torneo" aria-label="Buscar torneos" name="query">
            </div>
        </form>

        <!-- Notificaciones -->
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
                    <a href="#" class="notification-item unread">
                        <div class="noti-indicator"></div>
                        <div class="noti-content">
                            <p class="noti-text">Tu inscripción para la <strong>Copa de Invierno</strong> ha sido confirmada exitosamente.</p>
                            <span class="noti-time">Hace 10 min</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Perfil -->
        <div class="profile-dropdown">
            <input type="checkbox" id="profile-toggle" class="dropdown-checkbox">
            <label for="profile-toggle" class="profile-dropdown-button" aria-label="Menú de usuario">
                <div class="user-avatar">
                    <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                    </svg>
                </div>
            </label>
            <label for="profile-toggle" class="dropdown-overlay"></label>
            <div class="profile-menu-card">
                <div class="profile-menu-header">
                    <span class="profile-menu-name"><?= htmlspecialchars($usuarioActual) ?></span>
                </div>
                <div class="profile-menu-divider"></div>
                <nav class="profile-menu-links">
                    <a href="perfil.php" class="profile-menu-item">Perfil</a>
                    <a href="logica/logout.php" class="profile-menu-item logout-item">Cierre de sesión</a>
                </nav>
            </div>
        </div>
    </nav>

    <main class="main-container">
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-torneos" checked class="control-radio-pestana">
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-fixtures" class="control-radio-pestana">
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-participantes" class="control-radio-pestana">
        <input type="radio" name="grupo-pestanas-organizador" id="radio-pestana-reportes" class="control-radio-pestana">

        <div class="contenedor-organizador">
            
            <aside class="pestanas-organizador">
                <h2 class="titulo-organizador">Panel Organizador</h2>
                <label for="radio-pestana-torneos" class="btn-pestana label-torneos">Torneos Asignados</label>
                <label for="radio-pestana-fixtures" class="btn-pestana label-fixtures">Cargar Resultados</label>
                <label for="radio-pestana-participantes" class="btn-pestana label-participantes">Inscribir Participantes</label>
                <label for="radio-pestana-reportes" class="btn-pestana label-reportes">Reportes del Torneo</label>
            </aside>

            <section class="tarjeta-contenido-organizador">
                
                <!-- Pestaña: Torneos Asignados -->
                <div class="seccion-organizador panel-torneos">
                    <div class="encabezado-seccion-enlinea">
                        <h3 class="titulo-seccion">Tus Competencias</h3>
                    </div>
                    <p class="subtitulo-seccion">Lista de torneos bajo tu estricta supervisión y desarrollo.</p>
                    
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
                                                    $estado = strtolower($itemTorneo['estado'] ?? 'proximamente');
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
                                                <a href="detalleTorneo.php?id=<?php echo $itemTorneo['id_torneo']; ?>" class="btn-secundario-chico">Ver Detalle</a>
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

                <!-- Pestaña: Fixtures y Resultados -->
                <div class="seccion-organizador panel-fixtures">
                    <h3 class="titulo-seccion">Gestión de Fixtures y Rondas</h3>
                    <p class="subtitulo-seccion">Controlá el estado de las llaves y digitá las puntuaciones oficiales.</p>

                    <form action="organizador.php" method="POST" class="formulario-organizador">
                        <input type="hidden" name="accion" value="guardar_resultados">

                        <?php if (!empty($partidosPendientes)): ?>
                            <?php foreach ($partidosPendientes as $partido): ?>
                                <?php $idEnf = $partido['id_enfrentamiento']; ?>
                                <div class="etiqueta-partido-torneo">
                                    <?php echo htmlspecialchars($partido['nombre_torneo']); ?> - <?php echo htmlspecialchars($partido['nombre_ronda']); ?>
                                </div>
                                <div class="tarjeta-fila-partido">
                                    <span class="nombre-equipo texto-derecha"><?php echo htmlspecialchars($partido['equipo_local'] ?? 'Equipo Local'); ?></span>
                                    <div class="entradas-marcador-partido">
                                        <input type="number" name="resultados[<?php echo $idEnf; ?>][local]" class="control-formulario-entrada entrada-marcador" value="<?php echo $partido['marcador_local'] ?? 0; ?>" min="0" required>
                                        <span class="divisor-marcador">vs</span>
                                        <input type="number" name="resultados[<?php echo $idEnf; ?>][visita]" class="control-formulario-entrada entrada-marcador" value="<?php echo $partido['marcador_visita'] ?? 0; ?>" min="0" required>
                                    </div>
                                    <span class="nombre-equipo texto-izquierda"><?php echo htmlspecialchars($partido['equipo_visita'] ?? 'Equipo Visitante'); ?></span>
                                </div>
                            <?php endforeach; ?>

                            <div class="acciones-formulario">
                                <button type="submit" class="btn-guardar">Guardar marcadores</button>
                            </div>
                        <?php else: ?>
                            <p class="texto-sin-partidos">No hay partidos pendientes para cargar resultados en la ronda activa.</p>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Pestaña: Inscribir Participantes -->
                <div class="seccion-organizador panel-participantes">
                    <h3 class="titulo-seccion">Inscribir Participantes</h3>
                    <p class="subtitulo-seccion">Seleccioná un participante registrado para agregarlo al torneo.</p>

                    <?php if (!empty($mensajeExito)): ?>
                        <div class="mensaje-alerta alerta-exito">
                            ✓ <?php echo htmlspecialchars($mensajeExito); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($mensajeError)): ?>
                        <div class="mensaje-alerta alerta-error">
                            ✕ <?php echo htmlspecialchars($mensajeError); ?>
                        </div>
                    <?php endif; ?>

                    <form action="organizador.php" method="POST" class="formulario-organizador">
                        <input type="hidden" name="accion" value="inscribir_participante">

                        <div class="grupo-formulario">
                            <label for="id_torneo" class="etiqueta-formulario">Seleccionar Torneo</label>
                            <select name="id_torneo" id="id_torneo" class="control-formulario-entrada" required>
                                <option value="" disabled selected>Seleccioná un torneo</option>
                                <?php foreach ($torneosAsignados as $t): ?>
                                    <option value="<?php echo $t['id_torneo']; ?>"><?php echo htmlspecialchars($t['nombre_torneo']); ?></option>
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

                <!-- Pestaña: Reportes -->
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

    <footer class="main-footer">
        <div class="footer-content">
            <img src="../img/epsilonSoftware2.png" alt="Logo Epsilon Software" class="footer-logo">
        
            <div class="footer-right-group">
                <nav class="footer-links" aria-label="Enlaces de pie de página">
                    <a href="#" class="footer-link">Sobre nosotros</a>
                    <a href="#" class="footer-link">Ayuda</a>
                </nav>
                <p class="footer-copyright">&copy; 2026 Epsilon Software. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
</body>
</html>