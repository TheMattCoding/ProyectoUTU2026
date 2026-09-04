<?php
require_once 'logica/auth.php';
requerirRol(['administrador']);
require_once 'db.php'; // Incluye la conexión a la base de datos

$rolActual = $_SESSION['rol'] ?? 'visitante';
$usuarioActual = $_SESSION['usuario'] ?? 'Visitante';
$idUsuarioActual = $_SESSION['id_usuario'] ?? 1; // ID del usuario autenticado (o 1 por defecto)

$mensaje = '';
$tipoMensaje = '';

// Función auxiliar para determinar el nombre de la ronda
function obtenerNombreRonda($numeroActual, $totalRondas) {
    $distanciaAlFinal = $totalRondas - $numeroActual;

    return match ($distanciaAlFinal) {
        0 => 'Final',
        1 => 'Semifinal',
        2 => 'Cuartos de Final',
        3 => 'Octavos de Final',
        default => "Ronda $numeroActual"
    };
}

// Procesar el formulario cuando se envía mediante POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre_torneo'] ?? '');
    $disciplina = trim($_POST['disciplina'] ?? '');
    $formato = $_POST['formato'] ?? '';
    $modalidad = $_POST['modalidad'] ?? '';
    $fecha = $_POST['fecha_inicio'] ?? '';
    $hora = $_POST['hora_inicio'] ?? '';
    $cantidad = !empty($_POST['max_participantes']) ? (int)$_POST['max_participantes'] : NULL;
    $cantRondas = !empty($_POST['cantidad_rondas']) ? (int)$_POST['cantidad_rondas'] : 1;
    $privacidad = $_POST['privacidad'] ?? '';
    $descripcion = trim($_POST['descripcion'] ?? '');

    if (!empty($nombre) && !empty($fecha) && !empty($disciplina)) {
        try {
            $pdo->beginTransaction();

            // 1. Verificar o insertar el módulo de competencia (disciplina)
            $stmtMod = $pdo->prepare("SELECT id_modulo FROM modulos_competencia WHERE nombre_modulo = ?");
            $stmtMod->execute([$disciplina]);
            $modulo = $stmtMod->fetch();

            if ($modulo) {
                $idModulo = $modulo['id_modulo'];
            } else {
                $stmtInsMod = $pdo->prepare("INSERT INTO modulos_competencia (nombre_modulo, descripcion) VALUES (?, ?)");
                $stmtInsMod->execute([$disciplina, "Módulo de $disciplina"]);
                $idModulo = $pdo->lastInsertId();
            }

            // 2. Insertar en la tabla TORNEOS
            $sqlTorneo = "INSERT INTO torneos (nombre_torneo, descripcion, id_modulo, id_organizador, lugar, fecha_inicio, hora_inicio, estado, privacidad) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)";
            $stmtTorneo = $pdo->prepare($sqlTorneo);
            $stmtTorneo->execute([
                $nombre,
                $descripcion,
                $idModulo,
                $idUsuarioActual,
                'Montevideo',
                $fecha,
                $hora,
                $privacidad
            ]);
            $idTorneo = $pdo->lastInsertId();

            // 3. Insertar la configuración del torneo
            $sqlConfig = "INSERT INTO configuracion_torneo (id_torneo, max_participantes, formato) VALUES (?, ?, ?)";
            $stmtConfig = $pdo->prepare($sqlConfig);
            $stmtConfig->execute([$idTorneo, $cantidad, $formato]);

            // 4. Crear automáticamente las rondas del torneo
            $sqlRonda = "INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) VALUES (?, ?, ?, ?)";
            $stmtRonda = $pdo->prepare($sqlRonda);

            for ($i = 1; $i <= $cantRondas; $i++) {
                $nombreRonda = obtenerNombreRonda($i, $cantRondas);
                
                // Si la fecha de inicio es HOY o anterior, la primera ronda inicia "en_curso"
                $estadoInicial = ($i === 1 && $fecha <= date('Y-m-d')) ? 'en_curso' : 'pendiente';

                $stmtRonda->execute([$idTorneo, $i, 'Ronda ' . $i, $estadoInicial]);
            }

            $pdo->commit();

            $mensaje = "¡El torneo '$nombre' se ha creado correctamente!";
            $tipoMensaje = "exito";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $mensaje = "Error en la base de datos: " . $e->getMessage();
            $tipoMensaje = "error";
        }
    } else {
        $mensaje = "Por favor completa todos los campos requeridos.";
        $tipoMensaje = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Crear Torneo</title>
    
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/formularioTorneo.css">
    <script src="../js/formularioTorneo.js" defer></script>
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
                <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
            <?php endif; ?>

            <?php if ($rolActual === 'administrador'): ?>
                <a href="formularioTorneo.php" class="sidebar-link active">Crea tu torneo</a>
                <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <?php endif; ?>

            <?php if ($rolActual !== 'visitante'): ?>
                <a href="configuracion.php" class="sidebar-link">Configuración</a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="theme-switch-container">
                <span class="theme-label">Modo Oscuro</span>
                <button class="theme-toggle-btn" id="btn-theme-toggle" aria-label="Cambiar tema">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" class="icono-svg-tema">
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
        <div class="isla-formulario-unica">

            <div class="contenedor-logo-formulario">
                <img src="../img/logoapp2.jpeg" alt="Logo" class="app-logo">
                <h2>CREAR NUEVO TORNEO</h2>
            </div>

            <!-- Cartel de alerta si hay un mensaje -->
            <?php if (!empty($mensaje)): ?>
                <div class="alerta-mensaje <?= $tipoMensaje === 'exito' ? 'exito' : 'error' ?>">
                    <?= htmlspecialchars($mensaje) ?>
                </div>
            <?php endif; ?>

            <!-- Formulario de Creación de Torneo -->
            <form action="formularioTorneo.php" method="POST" enctype="multipart/form-data">

                <div class="columnas-flex-formulario">

                    <div class="columna-formulario">
                        <div class="grupo-formulario">
                            <label for="nombre">Nombre del Torneo</label>
                            <input type="text" id="nombre" name="nombre_torneo" placeholder="Ej: Torneo Relámpago" required>
                        </div>

                        <div class="grupo-formulario">
                            <label for="disciplina">Disciplina</label>
                            <input type="text" id="disciplina" name="disciplina" placeholder="Ej: Ajedrez" required>
                        </div>

                        <div class="fila-formulario">
                            <div class="grupo-formulario columna-expandible">
                                <label for="formato">Formato de Clasificación</label>
                                <select id="formato" name="formato" required>
                                    <option value="" disabled selected>Seleccione el formato</option>
                                    <option value="eliminatoria">Eliminación directa</option>
                                    <option value="liga">Liga (Todos contra todos)</option>
                                </select>
                            </div>
                            <div class="grupo-formulario columna-expandible">
                                <label for="modalidad">Modalidad</label>
                                <select id="modalidad" name="modalidad" required>
                                    <option value="equipos">Por Equipos</option>
                                    <option value="individual">Individual</option>
                                </select>
                            </div>
                        </div>

                        <div class="fila-formulario">
                            <div class="grupo-formulario columna-expandible">
                                <label for="fecha">Fecha de Inicio</label>
                                <input type="date" id="fecha" name="fecha_inicio" required>
                            </div>
                            <div class="grupo-formulario columna-expandible">
                                <label for="hora_inicio">Hora de Inicio</label>
                                <input type="time" id="hora_inicio" name="hora_inicio" required>
                            </div>
                        </div>

                        <div class="fila-formulario">
                            <div class="grupo-formulario columna-expandible">
                                <label for="cantidad">Cantidad de Equipos</label>
                                <input type="number" id="cantidad" name="max_participantes" placeholder="Ej: 16">
                            </div>
                            <div class="grupo-formulario columna-expandible">
                                <label for="cantidad_rondas">Cantidad de Rondas</label>
                                <input type="number" id="cantidad_rondas" name="cantidad_rondas" min="1" max="20" value="1" required>
                            </div>
                        </div>

                        <div class="grupo-formulario columna-expandible">
                            <label for="privacidad">Privacidad</label>
                            <select id="privacidad" name="privacidad" required>
                                <option value="" disabled selected>Seleccione la privacidad</option>
                                <option value="publico">Público</option>
                                <option value="privado">Privado</option>
                            </select>
                        </div>
                    </div>

                    <div class="columna-formulario columna-derecha-ajustada">
                        <div class="grupo-formulario contenedor-area-texto">
                            <label for="descripcion">Descripción del Torneo</label>
                            <textarea id="descripcion" name="descripcion" placeholder="Escribe las reglas o detalles del torneo..."></textarea>
                        </div>
                        
                        <div class="grupo-formulario">
                            <label for="portada-torneo">Portada del Torneo</label>
                            <div class="contenedor-subir-imagen">
                                <input type="file" id="portada-torneo" name="portada" accept="image/*" class="input-archivo-oculto">
                                <label for="portada-torneo" class="boton-subir-archivo">
                                    <svg class="icono-subir" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                        <path d="M288 109.3L288 352c0 17.7-14.3 32-32 32s-32-14.3-32-32l0-242.7-51.3 51.3c-12.5 12.5-32.8 12.5-45.3 0s-12.5-32.8 0-45.3l105.4-105.4c12.5-12.5 32.8-12.5 45.3 0l105.4 105.4c12.5 12.5 12.5 32.8 0 45.3s-32.8 12.5-45.3 0L288 109.3zM64 352l128 0c0 35.3 28.7 64 64 64s64-28.7 64-64l128 0c35.3 0 64 28.7 64 64l0 32c0 35.3-28.7 64-64 64L64 512c-35.3 0-64-28.7-64-64l0-32c0-35.3 28.7-64 64-64zm312 80a24 24 0 1 0 0-48 24 24 0 1 0 0 48z"/>
                                    </svg>
                                    <span id="texto-subir-archivo">Seleccionar Imagen</span>
                                </label>
                            </div>
                        </div>
                        
                        <div class="grupo-formulario">
                            <label>Ubicación del Torneo</label>
                            <div class="contenedor-mapa">
                                <iframe src="https://maps.google.com/maps?q=Montevideo&t=&z=13&ie=UTF8&iwloc=&output=embed" allowfullscreen="" loading="lazy"></iframe>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grupo-botones-formulario">
                    <button type="button" id="btn-cancelar" class="boton-formulario boton-cancelar">Cancelar</button>
                    <button type="submit" class="boton-formulario boton-enviar">Crear Torneo</button>
                </div>
            </form>
        </div>
    </main>

<!-- 7. Footer -->
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

    <!-- JavaScript del Footer -->
    <script src="../js/seccionSobreNosotros.js"></script>
    <script src="../js/seccionAyuda.js"></script>
</body>
</html>