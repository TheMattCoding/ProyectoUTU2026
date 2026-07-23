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

    <!-- 5. Menú lateral -->
    <input type="checkbox" id="menu-toggle" class="menu-checkbox">

    <div class="sidebar">

        <!--5. Movil cerrar menú -->
        <div class="sidebar-header">
            <span class="sidebar-title">Menú</span>
            <label for="menu-toggle" class="close-sidebar-btn" aria-label="Cerrar menú">X</label>
        </div>
        
        <nav class="sidebar-nav">
            <a href="inicio.php" class="sidebar-link">Inicio</a>
            <a href="calendario.php" class="sidebar-link">Calendario de torneos</a>
            <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
            <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
            <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <a href="configuracion.php" class="sidebar-link">Configuración</a>
        </nav>

        <!--5. Modo Oscuro-Claro -->
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

    <!--2. Navbar y Menú hamburgesa -->
    <nav class="navbar" aria-label="Navegación principal">
        <label for="menu-toggle" class="nav-button" aria-label="Abrir menú de navegación">
            <div class="hamburger-box">
                <span class="line"></span>
                <span class="line"></span>
                <span class="line"></span>
            </div>
        </label>

        <!--3. Busqueda de Torneo -->
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

            <!--4. Checkbox oculto-->
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
    
            <!--4. Apartado de Notificaciones -->
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

        <!-- 4. Apartado de perfil -->
        <div class="profile-dropdown">
    
            <!--4. Checkbox de perfil-->
            <input type="checkbox" id="profile-toggle" class="dropdown-checkbox">
    
            <label for="profile-toggle" class="profile-dropdown-button" aria-label="Menú de usuario">
                <div class="user-avatar">
                    <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                        <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                    </svg>
                </div>
            </label>
    
            <label for="profile-toggle" class="dropdown-overlay"></label>
    
            <!--4. Menú de perfil -->
            <div class="profile-menu-card">
                <div class="profile-menu-header">
                    <span class="profile-menu-name">Usuario</span>
                </div>
                <div class="profile-menu-divider"></div>
                <nav class="profile-menu-links">
                    <a href="login.html" class="profile-menu-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path d="M352 96l64 0c17.7 0 32 14.3 32 32l0 256c0 17.7-14.3 32-32 32l-64 0c-17.7 0-32 14.3-32 32s14.3 32 32 32l64 0c53 0 96-43 96-96l0-256c0-53-43-96-96-96l-64 0c-17.7 0-32 14.3-32 32s14.3 32 32 32zm-9.4 182.6c12.5-12.5 12.5-32.8 0-45.3l-128-128c-12.5-12.5-32.8-12.5-45.3 0s-12.5 32.8 0 45.3L242.7 224 32 224c-17.7 0-32 14.3-32 32s14.3 32 32 32l210.7 0-73.4 73.4c-12.5 12.5-12.5 32.8 0 45.3s32.8 12.5 45.3 0l128-128z"/>
                        </svg> Iniciar sesión
                    </a>
                    <a href="perfil.php" class="profile-menu-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                        </svg> Perfil
                    </a>
                    <div class="profile-menu-divider"></div>
                    <a href="#" class="profile-menu-item logout-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path d="M377.9 105.9L468.1 196c11.1 11.1 11.1 29.1 0 40.2l-90.1 90.1c-11.5 11.5-30.1 11.5-41.6 0s-11.5-30.1 0-41.6l39.3-39.3L160 245.4c-16.3 0-29.4-13.2-29.4-29.4s13.2-29.4 29.4-29.4l215.7 0-39.3-39.3c-11.5-11.5-11.5-30.１ 0-4１．６s30．１-１１．５ ４１．６ ０zM１２０ ９６c０-１３．３-１０．７-２４-２４-２４C４３ ７２ ０ １１５ ０ １６８L０ ３４４c０ ５３ ４３ ９６ ９６ ９６c１３．３ ０ ２４-１０．７ ２４-２４s-１０．７-２４-２４-２４c-２６．５ ０-４８-２１．５-４８-４８l０-１７６c０-２６．５ ２１．５-４８ ４８-４８c１３．３ ０ ２４-１０．７ ２４-２４z"/>
                        </svg> Cierre de sesión
                    </a>
                </nav>
            </div>
        </div>
    </nav>
    
    <!-- 6. Contenedor principal y vista de celular -->
    <main class="main-container">
       
        <!--9. Lista de torneos (Encabezado)-->
        <div class="cabecera-resultados">
            <h2 class="titulo-resultados">Resultados para: <span class="palabra-clave">"Torneo"</span></h2>
        </div>
        <!-- 9. Lista de Torneos y Resultados (Lista) -->
        <section class="lista-torneos">
        
            <article class="tarjeta-torneo">
                <div class="contenedor-imagen">
                    <img src="../img/torneo-ajedrez.jpg" alt="Torneo de Ajedrez" class="imagen-torneo">
                    <div class="superposicion-tarjeta"></div>
                    <h3 class="titulo-torneo">Torneo de Ajedrez</h3>
                </div>
                <div class="info-tarjeta">
                    <span class="fecha-torneo">26/7</span>
                    <a href="detalleTorneo.php" class="btn btn-secondary btn-ver-mas">Ver más</a>
                </div>
            </article>

            <article class="tarjeta-torneo">
                <div class="contenedor-imagen">
                    <img src="../img/torneo-futbol.jpg" alt="Torneo de Fútbol" class="imagen-torneo">
                    <div class="superposicion-tarjeta"></div>
                    <h3 class="titulo-torneo">Torneo de Fútbol</h3>
                </div>
                <div class="info-tarjeta">
                    <span class="fecha-torneo">3/8</span>
                    <a href="detalleTorneo.php" class="btn btn-secondary btn-ver-mas">Ver más</a>
                </div>
            </article>

            <article class="tarjeta-torneo">
                <div class="contenedor-imagen">
                    <div class="marcador-imagen"></div>
                    <h3 class="titulo-torneo">Próximo Torneo</h3>
                </div>
                <div class="info-tarjeta">
                    <span class="fecha-torneo">--/--</span>
                    <a href="detalleTorneo.php" class="btn btn-secondary btn-ver-mas">Ver más</a>
                </div>
            </article>

        </section>
    </main>

     <!--7. Footer -->
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