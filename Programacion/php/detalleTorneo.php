<?php
require_once 'logica/auth.php';
require_once 'db.php';

$rolActual = $_SESSION['rol'] ?? 'visitante';

$idTorneo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$idUsuarioActual = $_SESSION['id_usuario'] ?? null;

if (!$idTorneo) {
    header('Location: busquedaTorneo.php');
    exit;
}

// Consulta vinculando la tabla real INSCRIPCIONES_TORNEO
$sql = "SELECT t.*, 
               m.nombre_modulo AS disciplina,
               c.max_participantes,
               (SELECT COUNT(*) FROM INSCRIPCIONES_TORNEO i WHERE i.id_torneo = t.id_torneo) AS total_inscritos
        FROM TORNEOS t
        LEFT JOIN MODULOS_COMPETENCIA m ON t.id_modulo = m.id_modulo
        LEFT JOIN CONFIGURACION_TORNEO c ON t.id_torneo = c.id_torneo
        WHERE t.id_torneo = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $idTorneo]);
$torneo = $stmt->fetch();

if (!$torneo) {
    header('Location: busquedaTorneo.php');
    exit;
}

// Cálculo dinámico de cupos restando total_inscritos de max_participantes
$maxParticipantes = isset($torneo['max_participantes']) ? (int)$torneo['max_participantes'] : 0;
$totalInscritos   = (int)($torneo['total_inscritos'] ?? 0);

if ($maxParticipantes > 0) {
    $cuposDisponibles  = max(0, $maxParticipantes - $totalInscritos);
    $porcentajeOcupado = min(100, round(($totalInscritos / $maxParticipantes) * 100));
} else {
    $cuposDisponibles  = 'Ilimitados';
    $porcentajeOcupado = 0;
}

$nombreCreador = !empty($torneo['id_creador']) ? 'Organizador #' . $torneo['id_creador'] : 'Organización';
$fechaFormateada = !empty($torneo['fecha_inicio'] && !empty($torneo['hora_inicio'])) 
    ? date('d/m/Y - h:i A', strtotime($torneo['fecha_inicio'] . ' ' . $torneo['hora_inicio'])) 
    : 'Por confirmar';

// Verificación corregida usando la relación de id_usuario -> id_participante
$yaInscrito = false;
if ($idUsuarioActual && $idTorneo) {
    $stmtPart = $pdo->prepare("SELECT id_participante FROM PARTICIPANTES WHERE id_usuario = :id_usuario");
    $stmtPart->execute([':id_usuario' => $idUsuarioActual]);
    $idParticipante = $stmtPart->fetchColumn();

    if ($idParticipante) {
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM INSCRIPCIONES_TORNEO WHERE id_torneo = :id_torneo AND id_participante = :id_participante");
        $stmtCheck->execute([
            ':id_torneo'       => $idTorneo,
            ':id_participante' => $idParticipante
        ]);
        $yaInscrito = $stmtCheck->fetchColumn() > 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - <?php echo htmlspecialchars($torneo['nombre_torneo']); ?></title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/detalle.css">
</head>
<body>

<div class="contenedor-celular">

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
                <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
            <?php endif; ?>

            <?php if ($rolActual === 'administrador'): ?>
                <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
                <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <?php endif; ?>

            <?php if ($rolActual !== 'visitante'): ?>
                <a href="equipo.php" class="sidebar-link">Equipos</a>
                <a href="configuracion.php" class="sidebar-link">Configuración</a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="theme-switch-container">
                <span class="theme-label">Modo Oscuro</span>
                <button class="theme-toggle-btn" aria-label="Cambiar tema">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 18px; height: 18px; fill: currentColor; vertical-align: middle;">
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

        <!-- Búsqueda de Torneo -->
        <form action="busquedaTorneo.php" method="GET" class="search-form" style="display: flex; flex: 1; max-width: 420px; margin: 0 12px;">
            <div class="search-container" style="margin: 0; width: 100%;">
                <svg class="search-google-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="#777777"/>
                </svg>
                <input type="text" class="search-input" placeholder="Buscar un torneo" aria-label="Buscar torneos" name="query">
            </div>
        </form>

        <!-- Campana de Notificaciones -->
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

        <!-- Apartado de perfil -->
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
                    <span class="profile-menu-name">Usuario</span>
                </div>
                <div class="profile-menu-divider"></div>
                <nav class="profile-menu-links">
                    <a href="logica/login.php" class="profile-menu-item">Iniciar sesión</a>
                    <a href="perfil.php" class="profile-menu-item">Perfil</a>
                    <div class="profile-menu-divider"></div>
                    <a href="logica/logout.php" class="profile-menu-item logout-item">Cierre de sesión</a>
                </nav>
            </div>
        </div>
    </nav>

    <!-- Contenido dinámico del Torneo -->
    <main class="contenido-principal">

        <div class="portada-torneo">
            <img src="../img/torneo-ajedrez.jpg" alt="<?php echo htmlspecialchars($torneo['nombre_torneo']); ?>">
            <div class="superposicion-portada">
                <span class="categoria-torneo"><?php echo htmlspecialchars($torneo['disciplina'] ?? 'General'); ?></span>
                <h1 class="nombre-torneo"><?php echo htmlspecialchars($torneo['nombre_torneo']); ?></h1>
            </div>
        </div>

        <section class="seccion-info-rapida">
            <div class="elemento-info">
                <i class="fas fa-map-marker-alt"></i>
                <span><?php echo htmlspecialchars($torneo['lugar'] ?? 'Ubicación no especificada'); ?></span>
            </div>
            <div class="elemento-info">
                <i class="fas fa-calendar-alt"></i>
                <span><?php echo $fechaFormateada; ?></span>
            </div>
        </section>

        <section class="bloque-descripcion">
            <h2 class="titulo-seccion">Descripción</h2>
            <p class="texto-descripcion">
                <?php echo nl2br(htmlspecialchars($torneo['descripcion'] ?? 'Sin descripción disponible.')); ?>
            </p>
        </section>

        <section class="rejilla-info-tecnica">
            <div class="tarjeta-tecnica">
                <div class="etiqueta-tarjeta-tecnica">Formato</div>
                <div class="valor-tarjeta-tecnica"><?php echo htmlspecialchars($torneo['formato'] ?? 'Estándar'); ?></div>
            </div>
            <div class="tarjeta-tecnica">
                <div class="etiqueta-tarjeta-tecnica">Creador</div>
                <div class="valor-tarjeta-tecnica"><?php echo htmlspecialchars($nombreCreador); ?></div>
            </div>
        </section>

        <section class="contenedor-cupos">
            <div class="encabezado-cupos">
                <span>Cupos Disponibles: <strong><?php echo htmlspecialchars($cuposDisponibles); ?></strong></span>
                <span><?php echo $totalInscritos; ?> / <?php echo $maxParticipantes > 0 ? $maxParticipantes : '∞'; ?> participantes</span>
            </div>
            <div class="barra-progreso">
                <div class="relleno-progreso" style="width: <?php echo $porcentajeOcupado; ?>%;"></div>
            </div>
        </section>

        <!-- Botón dinámico -->
        <?php if ($yaInscrito): ?>
            <button type="button" id="btn-inscrito" class="btn-principal btn-deshabilitado" disabled>
                ✓ YA ESTÁS PARTICIPANDO
            </button>
        <?php else: ?>
            <button type="button" id="btn-abrir-modal" class="btn-principal">
                INSCRIBIRSE AHORA
            </button>
        <?php endif; ?>

        <!-- Ventana Flotante Modal -->
        <div id="modal-inscripcion" class="modal-overlay">
            <div class="modal-contenido">
                <button type="button" id="btn-cerrar-modal" class="modal-cerrar">&times;</button>
                <h3>Inscripción al Torneo</h3>
                <p style="margin-bottom: 15px; color: #ccc;">Ingresa los datos para confirmar tu participación.</p>
            
                <form action="logica/inscribir.php" method="POST">
                    <input type="hidden" name="id_torneo" value="<?php echo $idTorneo; ?>">
                
                    <div class="grupo-entrada" style="margin-bottom: 15px;">
                        <label for="nombre_equipo" style="display:block; margin-bottom: 5px;">Nombre del Equipo / Participante:</label>
                        <input type="text" id="nombre_equipo" name="nombre_equipo" required placeholder="Ej: Epsilon FC / Tu Nombre" class="control-formulario-entrada" style="width: 100%; padding: 10px; border-radius: 6px;">
                    </div>

                    <button type="submit" class="btn-principal">
                        CONFIRMAR INSCRIPCIÓN
                    </button>
                </form>
            </div>
        </div>

        <!-- Botón dinámico -->
        <?php if ($yaInscrito): ?>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <form action="logica/cancelarInscripcion.php" method="POST" onsubmit="return confirm('¿Estás seguro de que deseas cancelar tu inscripción a este torneo?');" style="flex: 1; min-width: 200px;">
                    <input type="hidden" name="id_torneo" value="<?php echo $idTorneo; ?>">
                    <button type="submit" class="btn-principal" style="background-color: #e63946; border-color: #e63946; color: white;">
                        CANCELAR INSCRIPCIÓN
                    </button>
                </form>
            </div>
        <?php endif; ?>
       
        <?php if (isset($_GET['estado']) && $_GET['estado'] === 'inscrito'): ?>
            <div style="background-color: #1b4332; color: #2ec4b6; border: 1px solid #2ec4b6; padding: 12px; border-radius: 100px; margin-bottom: 20px; text-align: center;">
                ✓ ¡Te has inscrito exitosamente al torneo!
            </div>
        <?php elseif (isset($_GET['estado']) && $_GET['estado'] === 'cancelado'): ?>
            <div style="background-color: #3d2314; color: #ffb703; border: 1px solid #ffb703; padding: 12px; border-radius: 100px; margin-bottom: 20px; text-align: center;">
                ✓ Has cancelado tu inscripción al torneo.
            </div>
        <?php elseif (isset($_GET['estado']) && $_GET['estado'] === 'error'): ?>
            <div style="background-color: #4a151b; color: #ff6b6b; border: 1px solid #ff6b6b; padding: 12px; border-radius: 100px; margin-bottom: 20px; text-align: center;">
                ✕ Ocurrió un error al procesar tu inscripción. Inténtalo nuevamente.
            </div>
        <?php endif; ?>


        <script src="../js/modalInscripcion.js"></script>
    </main>

    <!-- Footer -->
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

</div> 
</body>
</html>