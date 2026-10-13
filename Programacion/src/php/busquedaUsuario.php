<?php
require_once 'logica/auth.php';
require_once 'db.php';
require_once 'logica/notificaciones.php';

$idUsuarioActual = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;
$mis_notis = [];
$cant_sin_leer = 0;

if ($idUsuarioActual) {
    $mis_notis = obtenerMisNotificaciones($pdo, $idUsuarioActual);
    $cant_sin_leer = contarNoLeidas($pdo, $idUsuarioActual);
}

$rolActual = $_SESSION['rol'] ?? 'visitante';
$busqueda  = trim($_GET['query'] ?? '');

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

// Búsqueda de usuarios por nombre de usuario o nombre/apellido
$paramBusqueda = '%' . $busqueda . '%';

$sql = "SELECT u.id_usuario, u.username, u.email, u.foto_perfil, 
               COALESCE(r.nombre_rol, 'usuario') AS rol,
               p.nombre, p.apellido
        FROM usuarios u
        LEFT JOIN roles r ON u.id_rol = r.id_rol
        LEFT JOIN participantes p ON u.id_usuario = p.id_usuario
        WHERE u.username LIKE :q1 
           OR p.nombre LIKE :q2 
           OR p.apellido LIKE :q3
        ORDER BY u.username ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':q1' => $paramBusqueda,
    ':q2' => $paramBusqueda,
    ':q3' => $paramBusqueda
]);

$usuariosEncontrados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Buscar Usuarios</title>
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/busquedaUsuario.css">
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
            <!-- Visible para todos (incluyendo visitantes) -->
            <a href="inicio.php" class="sidebar-link">Inicio</a>
            <a href="calendario.php" class="sidebar-link">Calendario de torneos</a>
            <a href="busquedaUsuario.php" class="sidebar-link active">Buscar Usuarios</a>
            <a href="resultadosTorneo.php" class="sidebar-link">Resultados y Posiciones</a>

            <!-- Solo Organizadores y Administradores -->
            <?php if (in_array($rolActual, ['organizador', 'administrador'])): ?>
                <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
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

        <!-- Notificaciones -->
        <div class="notifications-dropdown">
            <input type="checkbox" id="noti-toggle" class="dropdown-checkbox">

            <label for="noti-toggle" class="notifications-dropdown-button" aria-label="Notificaciones">
                <div class="notifications-icon-wrapper">
                    <svg class="bell-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z" fill="#cccccc"/>
                    </svg>
                    <?php if ($cant_sin_leer > 0): ?>
                        <span class="notification-dot"></span>
                    <?php endif; ?>
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
            <div class="notifications-empty">
                <div class="notifications-empty-icon">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        <line x1="2" y1="2" x2="22" y2="22"></line>
                    </svg>
                </div>
                <span class="notifications-empty-title">Estás al día</span>
                <span class="notifications-empty-desc">No tenés notificaciones pendientes por el momento.</span>
            </div>
        <?php else: ?>
            <?php foreach ($mis_notis as $n): ?>
                <div>
                    <a href="<?= htmlspecialchars($n['enlace']) ?>" class="notification-item unread">
                        <div class="noti-indicator"></div>
                        <div class="noti-content">
                            <p class="noti-text"><?= htmlspecialchars($n['mensaje']) ?></p>
                        </div>
                    </a>
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

        <!-- Perfil -->
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
        <div class="cabecera-resultados">
            <h2 class="titulo-resultados">Búsqueda de Usuarios</h2>
        </div>

        <form action="busquedaUsuario.php" method="GET" class="form-busqueda-usuario">
            <input type="text" name="query" class="input-busqueda-user" placeholder="Ingresa nombre de usuario..." value="<?= htmlspecialchars($busqueda) ?>">
            <button type="submit" class="btn-buscar-user">Buscar</button>
        </form>

        <section class="grid-usuarios">
            <?php if (empty($usuariosEncontrados)): ?>
                <p style="color: #aaa; grid-column: 1 / -1;">
                    No se encontraron usuarios que coincidan con la búsqueda.
                </p>
            <?php else: ?>
                <?php foreach ($usuariosEncontrados as $user): 
                    $fotoUser = null;
                    if (!empty($user['foto_perfil'])) {
                        $fotoUser = (strpos($user['foto_perfil'], '../') === 0 || strpos($user['foto_perfil'], 'http') === 0) 
                            ? $user['foto_perfil'] 
                            : '../' . ltrim($user['foto_perfil'], '/');
                    }
                    $nombreCompleto = trim(($user['nombre'] ?? '') . ' ' . ($user['apellido'] ?? ''));
                    $rolClase = strtolower($user['rol']);
                ?>
                    <article class="tarjeta-usuario">
                        <div class="avatar-usuario-busqueda">
                            <?php if ($fotoUser): ?>
                                <img src="<?= htmlspecialchars($fotoUser) ?>" alt="<?= htmlspecialchars($user['username']) ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                            <?php else: ?>
                                <svg class="avatar-svg" style="width: 45px; height: 45px; fill: #888;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                    <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                                </svg>
                            <?php endif; ?>
                        </div>
                        <div class="username-card">@<?= htmlspecialchars($user['username']) ?></div>
                        <?php if ($nombreCompleto): ?>
                            <div class="nombre-completo-card"><?= htmlspecialchars($nombreCompleto) ?></div>
                        <?php endif; ?>
                        <span class="badge-rol <?= htmlspecialchars($rolClase) ?>">
                            <?= htmlspecialchars(ucfirst($user['rol'])) ?>
                        </span>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
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

    <!-- Modal Ayuda -->
    <section id="seccion-ayuda" class="seccion-desplegable" aria-hidden="true">
        <div class="seccion-encabezado">
            <h3 class="seccion-titulo">Centro de Ayuda</h3>
            <button type="button" id="btn-cerrar-seccion-ayuda" class="btn-cerrar-seccion" aria-label="Cerrar sección">&times;</button>
        </div>

        <div class="seccion-contenido">
            <div class="bloque-nosotros">
                <h4 class="subtitulo-nosotros">Preguntas Frecuentes</h4>
                <details class="item-faq">
                    <summary class="pregunta-faq">¿Cómo busco a un usuario?</summary>
                    <p class="texto-nosotros">Escribe el nombre de usuario en el campo de búsqueda y presiona "Buscar".</p>
                </details>
            </div>
        </div>
    </section>

    <script src="../js/seccionSobreNosotros.js"></script>
    <script src="../js/seccionAyuda.js"></script>

</body>
</html>