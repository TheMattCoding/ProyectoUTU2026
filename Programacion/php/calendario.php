<?php
require_once 'logica/auth.php';
require_once 'db.php';

$rolActual = $_SESSION['rol'] ?? 'visitante';
$idUsuarioActual = $_SESSION['id_usuario'] ?? null;

// 1. Obtener Torneos Globales
$stmtGlobal = $pdo->prepare("
    SELECT DISTINCT t.id_torneo, t.nombre_torneo, t.fecha_inicio, t.lugar, m.nombre_modulo AS disciplina
    FROM torneos t
    LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo
    WHERE t.fecha_inicio IS NOT NULL
    ORDER BY t.fecha_inicio ASC
");
$stmtGlobal->execute();
$torneosGlobales = $stmtGlobal->fetchAll(PDO::FETCH_ASSOC);

// 2. Obtener Torneos Propios
$torneosPropios = [];
if ($idUsuarioActual) {
    $stmtPropio = $pdo->prepare("
        SELECT DISTINCT t.id_torneo, t.nombre_torneo, t.fecha_inicio, t.lugar, m.nombre_modulo AS disciplina
        FROM torneos t
        LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo
        INNER JOIN inscripciones_torneo i ON t.id_torneo = i.id_torneo
        LEFT JOIN participantes p_directo ON i.id_participante = p_directo.id_participante
        LEFT JOIN equipos e ON i.id_equipo = e.id_equipo
        LEFT JOIN participantes p_equipo ON e.id_participante = p_equipo.id_participante
        WHERE (p_directo.id_usuario = :id_usuario1 OR p_equipo.id_usuario = :id_usuario2)
          AND t.fecha_inicio IS NOT NULL
        ORDER BY t.fecha_inicio ASC
    ");
    $stmtPropio->execute([
        ':id_usuario1' => $idUsuarioActual,
        ':id_usuario2' => $idUsuarioActual
    ]);
    $torneosPropios = $stmtPropio->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Plataforma de Torneos</title>
    
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/calendario.css">
</head>
<body>

    <!-- Menú lateral -->
    <input type="checkbox" id="menu-toggle" class="menu-checkbox">
    <div class="sidebar">
        <div class="sidebar-header">
            <span class="sidebar-title">Menú</span>
            <label for="menu-toggle" class="close-sidebar-btn" aria-label="Cerrar menú">X</label>
        </div>
        
        <nav class="sidebar-nav">
            <a href="inicio.php" class="sidebar-link">Inicio</a>
            <a href="calendario.php" class="sidebar-link active">Calendario de torneos</a>

            <?php if (in_array($rolActual, ['organizador', 'administrador'])): ?>
                <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
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
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" style="width: 18px; height: 18px; fill: currentColor; vertical-align: middle;">
                        <path d="M256 0C114.6 0 0 114.6 0 256S114.6 512 256 512c68.8 0 131.3-27.2 177.3-71.4 7.3-7 9.4-17.9 5.3-27.1s-13.7-14.9-23.8-14.1c-4.9 .4-9.8 .6-14.8 .6-101.6 0-184-82.4-184-184 0-72.1 41.5-134.6 102.1-164.8 9.1-4.5 14.3-14.3 13.1-24.4S322.6 8.5 312.7 6.3C294.4 2.2 275.4 0 256 0z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <label for="menu-toggle" class="sidebar-overlay"></label>

    <!-- 2. Navbar y Menú hamburguesa -->
<nav class="navbar" aria-label="Navegación principal">
    <label for="menu-toggle" class="nav-button" aria-label="Abrir menú de navegación">
        <div class="hamburger-box">
            <span class="line"></span>
            <span class="line"></span>
            <span class="line"></span>
        </div>
    </label>

    <!-- 3. Búsqueda de Torneo -->
    <form action="busquedaTorneo.php" method="GET" class="search-form" style="display: flex; flex: 1; max-width: 420px; margin: 0 12px;">
        <div class="search-container" style="margin: 0; width: 100%;">
            <svg class="search-google-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="#777777"/>
            </svg>
            <input type="text" class="search-input" placeholder="Buscar un torneo" aria-label="Buscar torneos" name="query">
        </div>
    </form>

    <!-- 4. Campana de Notificaciones -->
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
                <a href="#" class="notification-item">
                    <div class="noti-indicator"></div>
                    <div class="noti-content">
                        <p class="noti-text">El fixture del <strong>Torneo Relámpago</strong> ya se encuentra disponible.</p>
                        <span class="noti-time">Hace 2 horas</span>
                    </div>
                </a>
            </div>
        </div>
    </div>

    <!-- 5. Apartado de perfil -->
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
                <span class="profile-menu-name">
                    <?= htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Invitado') ?>
                </span>
            </div>
            <div class="profile-menu-divider"></div>
            <nav class="profile-menu-links">
                <?php if ($rolActual === 'visitante'): ?>
                    <a href="logica/login.php" class="profile-menu-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path d="M352 96l64 0c17.7 0 32 14.3 32 32l0 256c0 17.7-14.3 32-32 32l-64 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l64 0c53 0 96-43 96-96l0-256c0-53-43-96-96-96l-64 0c-17.7 0-32 14.3-32 32s14.3 32 32 32zm-9.4 182.6c12.5-12.5 12.5-32.8 0-45.3l-128-128c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L242.7 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l210.7 0-73.4 73.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l128-128z"/>
                        </svg> Iniciar sesión
                    </a>
                <?php else: ?>
                    <a href="perfil.php" class="profile-menu-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                        </svg> Perfil
                    </a>
                    <div class="profile-menu-divider"></div>
                    <a href="logica/logout.php" class="profile-menu-item logout-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path d="M377.9 105.9L468.1 196c11.1 11.1 11.1 29.1 0 40.2l-90.1 90.1c-11.5 11.5-30.1 11.5-41.6 0s-11.5-30.1 0-41.6l39.3-39.3L160 245.4c-16.3 0-29.4-13.2-29.4-29.4s13.2-29.4 29.4-29.4l215.7 0-39.3-39.3c-11.5-11.5-11.5-30.1 0-41.6s30.1-11.5 41.6 0zM120 96c0-13.3-10.7-24-24-24C43 72 0 115 0 168L0 344c0 53 43 96 96 96c13.3 0 24-10.7 24-24s-10.7-24-24-24c-26.5 0-48-21.5-48-48l0-176c0-26.5 21.5-48 48-48c13.3 0 24-10.7 24-24z"/>
                        </svg> Cierre de sesión
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</nav>

<main class="main-container">
    <div class="contenedor-grilla-calendario">
        
        <!-- Botones de alternancia de calendario -->
        <div class="selector-vista-calendario">
            <button type="button" class="btn-tab-cal activo" id="btn-tab-global">
                <i class="fas fa-globe"></i> Calendario Global
            </button>
            <?php if ($idUsuarioActual): ?>
                <button type="button" class="btn-tab-cal" id="btn-tab-propio">
                    <i class="fas fa-user-check"></i> Mi Calendario
                </button>
            <?php else: ?>
                <button type="button" class="btn-tab-cal deshabilitado" title="Inicia sesión para ver tu calendario" disabled>
                    <i class="fas fa-lock"></i> Mi Calendario
                </button>
            <?php endif; ?>
        </div>

        <header class="acciones-cabecera-calendario">
            <h2 class="fecha-actual-calendario" id="titulo-mes-anio">Cargando...</h2>
            <div class="navegacion-calendario">
                <button class="btn-nav-calendario" id="btn-prev-mes" aria-label="Mes anterior">‹</button>
                <button class="btn-nav-calendario" id="btn-next-mes" aria-label="Mes siguiente">›</button>
            </div>
        </header>
        
        <div class="dias-semana-calendario">
            <div>Dom</div><div>Lun</div><div>Mar</div><div>Mié</div><div>Jue</div><div>Vie</div><div>Sáb</div>
        </div>

        <div class="grilla-dias-calendario" id="grilla-dias"></div>
        <div class="agenda-movil-calendario" id="agenda-movil"></div>

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

<!-- Transferencia de los arrays PHP a JavaScript -->
<script>
    window.torneosGlobales = <?php echo json_encode($torneosGlobales); ?>;
    window.torneosPropios  = <?php echo json_encode($torneosPropios); ?>;
</script>
<script src="../js/calendario.js"></script>
</body>
</html>