<?php
require_once 'logica/auth.php';
require_once 'db.php';

$rolActual = $_SESSION['rol'] ?? 'visitante';

// Capturar término enviado por la URL (GET)
$busqueda = isset($_GET['query']) ? trim($_GET['query']) : '';
$torneos = [];

if (!empty($busqueda)) {
    // Buscar coincidencia en el nombre del torneo o nombre del módulo/disciplina
    $sql = "SELECT t.id_torneo, t.nombre_torneo, t.fecha_inicio, m.nombre_modulo AS disciplina
            FROM TORNEOS t
            LEFT JOIN MODULOS_COMPETENCIA m ON t.id_modulo = m.id_modulo
            WHERE t.nombre_torneo LIKE :busqueda OR m.nombre_modulo LIKE :busqueda
            ORDER BY t.fecha_inicio DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':busqueda' => '%' . $busqueda . '%']);
    $torneos = $stmt->fetchAll();
} else {
    // Si la búsqueda está vacía, mostramos los últimos torneos creados
    $sql = "SELECT t.id_torneo, t.nombre_torneo, t.fecha_inicio, m.nombre_modulo AS disciplina
            FROM TORNEOS t
            LEFT JOIN MODULOS_COMPETENCIA m ON t.id_modulo = m.id_modulo
            ORDER BY t.fecha_inicio DESC LIMIT 12";
    $stmt = $pdo->query($sql);
    $torneos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Plataforma de Torneos</title>
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/busqueda.css">
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
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
            <a href="calendario.php" class="sidebar-link">Calendario de torneos</a>

            <?php if (in_array($rolActual, ['organizador', 'administrador'])): ?>
                <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
                <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
            <?php endif; ?>

            <?php if ($rolActual === 'administrador'): ?>
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

    <!-- Navbar con campo de búsqueda dinámico -->
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
                <input type="text" class="search-input" placeholder="Buscar un torneo" aria-label="Buscar torneos" name="query" value="<?php echo htmlspecialchars($busqueda); ?>">
            </div>
        </form>

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
                            <p class="noti-text">Tu inscripción ha sido confirmada exitosamente.</p>
                            <span class="noti-time">Hace 10 min</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>

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
    
    <!-- Contenedor principal de resultados dinámicos -->
    <main class="main-container">
        <div class="cabecera-resultados">
            <h2 class="titulo-resultados">
                <?php if (!empty($busqueda)): ?>
                    Resultados para: <span class="palabra-clave">"<?php echo htmlspecialchars($busqueda); ?>"</span>
                <?php else: ?>
                    Todos los torneos disponibles
                <?php endif; ?>
            </h2>
        </div>

        <section class="lista-torneos">
            <?php if (empty($torneos)): ?>
                <p style="color: #aaa; margin: 20px 0; grid-column: 1 / -1;">
                    No se encontraron torneos que coincidan con tu búsqueda.
                </p>
            <?php else: ?>
                <?php foreach ($torneos as $torneo): ?>
                    <article class="tarjeta-torneo">
                        <div class="contenedor-imagen">
                            <img src="../img/torneo-ajedrez.jpg" alt="<?php echo htmlspecialchars($torneo['nombre_torneo']); ?>" class="imagen-torneo">
                            <div class="superposicion-tarjeta"></div>
                            <h3 class="titulo-torneo"><?php echo htmlspecialchars($torneo['nombre_torneo']); ?></h3>
                        </div>
                        <div class="info-tarjeta">
                            <span class="fecha-torneo">
                                <?php echo $torneo['fecha_inicio'] ? date('d/m', strtotime($torneo['fecha_inicio'])) : '--/--'; ?>
                            </span>
                            <a href="detalleTorneo.php?id=<?php echo $torneo['id_torneo']; ?>" class="btn btn-secondary btn-ver-mas">Ver más</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
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