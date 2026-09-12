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
$conexion = $pdo;

requerirLogin();

$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;
$rolActual = $_SESSION['rol'] ?? 'visitante';

// Obtener datos actualizados del usuario y su perfil de participante (incluyendo foto_perfil)
$usuarioDatos = [];
if ($idUsuario) {
    $stmt = $conexion->prepare("
        SELECT u.username, u.email, p.nombre, p.apellido, p.telefono, p.ci, u.foto_perfil 
        FROM usuarios u 
        LEFT JOIN participantes p ON u.id_usuario = p.id_usuario 
        WHERE u.id_usuario = :id
    ");
    $stmt->execute([':id' => $idUsuario]);
    $usuarioDatos = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

$username   = $usuarioDatos['username'] ?? $_SESSION['usuario'] ?? 'Usuario';
$email      = $usuarioDatos['email'] ?? $_SESSION['correo'] ?? '';
$nombre     = $usuarioDatos['nombre'] ?? $_SESSION['nombre'] ?? '';
$apellido   = $usuarioDatos['apellido'] ?? $_SESSION['apellido'] ?? '';
$telefono   = $usuarioDatos['telefono'] ?? $_SESSION['telefono'] ?? '';
$fotoPerfil = $usuarioDatos['foto_perfil'] ?? $_SESSION['foto_perfil'] ?? null;

// Mensajes de feedback y persistencia de estado
$mensajeExito = $_SESSION['mensaje_exito'] ?? null;
$mensajeError = $_SESSION['mensaje_error'] ?? null;
$pestanaActiva = $_SESSION['pestana_activa'] ?? 'perfil';
$ultimoInputId = $_SESSION['ultimo_input_id'] ?? null;

unset($_SESSION['mensaje_exito'], $_SESSION['mensaje_error'], $_SESSION['pestana_activa'], $_SESSION['ultimo_input_id']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Configuración</title>
    
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/configuracion.css">
</head>
<body>

    <!-- Menú lateral -->
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
                <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
            <?php endif; ?>

            <!-- Solo Administradores -->
            <?php if ($rolActual === 'administrador'): ?>
                <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
                <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <?php endif; ?>

            <!-- Solo Usuarios Registrados (no visitantes) -->
            <?php if ($rolActual !== 'visitante'): ?>
                <a href="configuracion.php" class="sidebar-link active">Configuración</a>
            <?php endif; ?>
        </nav>
    </div>

    <label for="menu-toggle" class="sidebar-overlay"></label>

    <!-- Navbar y Menú hamburguesa -->
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
            <a href="busquedaTorneo.php" class="btn-ver-torneos-nav">
                Ver torneos
            </a>

        <!-- Campana de Notificaciones -->
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

        <!-- Perfil en Navbar -->
        <div class="profile-dropdown">
            <input type="checkbox" id="profile-toggle" class="dropdown-checkbox">

            <label for="profile-toggle" class="profile-dropdown-button" aria-label="Menú de usuario">
                <div class="user-avatar">
                    <?php if (!empty($fotoPerfil) && file_exists('../' . $fotoPerfil)): ?>
                        <img src="../<?= htmlspecialchars($fotoPerfil) ?>" alt="Avatar" class="img-avatar">
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

    <main class="contenedor-principal">

        <!-- Control de pestañas con estado activo recordado -->
        <input type="radio" name="grupo-pestanas-config" id="radio-pestana-perfil" class="control-radio-pestana" <?= ($pestanaActiva === 'perfil') ? 'checked' : '' ?>>
        <input type="radio" name="grupo-pestanas-config" id="radio-pestana-seguridad" class="control-radio-pestana" <?= ($pestanaActiva === 'seguridad') ? 'checked' : '' ?>>
        <input type="radio" name="grupo-pestanas-config" id="radio-pestana-notificaciones" class="control-radio-pestana" <?= ($pestanaActiva === 'notificaciones') ? 'checked' : '' ?>>
        <input type="radio" name="grupo-pestanas-config" id="radio-pestana-borrar" class="control-radio-pestana" <?= ($pestanaActiva === 'borrar') ? 'checked' : '' ?>>

        <div class="envoltura-configuracion">
            
            <aside class="pestanas-configuracion">
                <h2 class="titulo-configuracion">Configuración</h2>
                <label for="radio-pestana-perfil" class="btn-pestana etiqueta-perfil">Editar Perfil</label>
                <label for="radio-pestana-seguridad" class="btn-pestana etiqueta-seguridad">Cuenta y seguridad</label>
                <label for="radio-pestana-notificaciones" class="btn-pestana etiqueta-notificaciones">Notificaciones</label>
                <label for="radio-pestana-borrar" class="btn-pestana btn-pestana-peligro etiqueta-borrar">Borrar cuenta</label>
            </aside>

            <section class="tarjeta-contenido-config">
                
                <!-- 1. Editar Perfil -->              
                <div id="perfil" class="seccion-configuracion panel-perfil">
                    <h3 class="titulo-seccion">Información del Perfil</h3>
                    <p class="subtitulo-seccion">Personaliza tu identidad dentro de la plataforma de torneos.</p>

                    <!-- Alertas específicas de la pestaña Perfil -->
                    <?php if ($mensajeExito && $pestanaActiva === 'perfil'): ?>
                        <div class="alerta alerta-exito"><?= htmlspecialchars($mensajeExito) ?></div>
                    <?php endif; ?>
                    <?php if ($mensajeError && $pestanaActiva === 'perfil'): ?>
                        <div class="alerta alerta-error"><?= htmlspecialchars($mensajeError) ?></div>
                    <?php endif; ?>
        
                    <form action="logica/actualizarConfiguracion.php" method="POST" enctype="multipart/form-data" id="form-perfil" class="formulario-configuracion">
                        <input type="hidden" name="accion" value="actualizar_perfil">
                        <input type="hidden" name="pestana_activa" value="perfil">
                        <input type="hidden" name="ultimo_input_id" class="campo-ultimo-input" value="">
            
                        <!-- Avatar y Cambio de Foto -->
                        <div class="contenedor-edicion-avatar">
                            <div class="avatar-usuario avatar-grande" id="avatar-preview-container">
                                <?php if (!empty($fotoPerfil) && file_exists('../' . $fotoPerfil)): ?>
                                    <img src="../<?= htmlspecialchars($fotoPerfil) ?>" alt="Foto de Perfil" id="foto-preview" class="img-avatar">
                                <?php else: ?>
                                    <img src="" alt="Foto de Perfil" id="foto-preview" class="img-avatar" style="display: none;">
                                    <svg id="svg-default-avatar" class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                                        <path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z" />
                                    </svg>
                                <?php endif; ?>
                            </div>

                            <!-- Input oculto para cargar la imagen -->
                            <input type="file" id="foto-perfil-input" name="foto_perfil" accept="image/png, image/jpeg, image/jpg, image/webp" style="display: none;">
                            <label for="foto-perfil-input" class="btn-secundario-sm" style="cursor: pointer; display: inline-block;">Cambiar foto</label>
                        </div>

                        <div class="cuadrícula-fila-formulario">
                            <div class="grupo-formulario">
                                <label for="nombre-usuario" class="etiqueta-formulario">Nombre de usuario (Máx. 20)</label>
                                <input type="text" id="nombre-usuario" name="nombre_usuario" class="control-input-formulario" value="<?= htmlspecialchars($username) ?>" maxlength="20" required>
                            </div>
                            <div class="grupo-formulario">
                                <label for="correo" class="etiqueta-formulario">Correo Electrónico (@gmail.com)</label>
                                <input type="email" id="correo" name="correo" class="control-input-formulario" value="<?= htmlspecialchars($email) ?>" pattern="[a-zA-Z0-9._%+-]+@gmail\.com$" required>
                            </div>
                        </div>

                        <div class="cuadrícula-fila-formulario">
                            <div class="grupo-formulario">
                                <label for="nombre" class="etiqueta-formulario">Nombre (Máx. 15)</label>
                                <input type="text" id="nombre" name="nombre" class="control-input-formulario" value="<?= htmlspecialchars($nombre) ?>" placeholder="Tu nombre" maxlength="15" required>
                            </div>
                            <div class="grupo-formulario">
                                <label for="apellido" class="etiqueta-formulario">Apellido (Máx. 15)</label>
                                <input type="text" id="apellido" name="apellido" class="control-input-formulario" value="<?= htmlspecialchars($apellido) ?>" placeholder="Tu apellido" maxlength="15" required>
                            </div>
                        </div>

                        <div class="grupo-formulario">
                            <label for="telefono" class="etiqueta-formulario">Teléfono / Celular (9 dígitos)</label>
                            <input type="tel" id="telefono" name="telefono" class="control-input-formulario" value="<?= htmlspecialchars($telefono) ?>" placeholder="Ej: 099123456" maxlength="9" minlength="9" required>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-guardar">Guardar perfil</button>
                        </div>
                    </form>
                </div>
                
                <!-- 2. Cuenta y Seguridad -->
                <div id="seguridad" class="seccion-configuracion panel-seguridad">
                    <h3 class="titulo-seccion">Seguridad de la Cuenta</h3>
                    <p class="subtitulo-seccion">Gestiona tus credenciales de acceso de forma segura.</p>

                    <!-- Alertas específicas de la pestaña Seguridad -->
                    <?php if ($mensajeExito && $pestanaActiva === 'seguridad'): ?>
                        <div class="alerta alerta-exito"><?= htmlspecialchars($mensajeExito) ?></div>
                    <?php endif; ?>
                    <?php if ($mensajeError && $pestanaActiva === 'seguridad'): ?>
                        <div class="alerta alerta-error"><?= htmlspecialchars($mensajeError) ?></div>
                    <?php endif; ?>
                    
                    <form action="logica/actualizarConfiguracion.php" method="POST" id="form-seguridad" class="formulario-configuracion">
                        <input type="hidden" name="accion" value="cambiar_password">
                        <input type="hidden" name="pestana_activa" value="seguridad">
                        <input type="hidden" name="ultimo_input_id" class="campo-ultimo-input" value="">

                        <div class="grupo-formulario">
                            <label for="contrasena-actual" class="etiqueta-formulario">Contraseña actual</label>
                            <input type="password" id="contrasena-actual" name="contrasena_actual" class="control-input-formulario" placeholder="••••••••" required>
                        </div>

                        <div class="cuadrícula-fila-formulario">
                            <div class="grupo-formulario">
                                <label for="nueva-contrasena" class="etiqueta-formulario">Nueva contraseña</label>
                                <input type="password" id="nueva-contrasena" name="nueva_contrasena" class="control-input-formulario" placeholder="Mínimo 6 caracteres" required>
                            </div>
                            <div class="grupo-formulario">
                                <label for="confirmar-contrasena" class="etiqueta-formulario">Confirmar nueva contraseña</label>
                                <input type="password" id="confirmar-contrasena" name="confirmar_contrasena" class="control-input-formulario" placeholder="Repite la contraseña" required>
                            </div>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-guardar">Actualizar contraseña</button>
                        </div>
                    </form>
                </div>

                <!-- 3. Notificaciones -->
                <div id="notificaciones" class="seccion-configuracion panel-notificaciones">
                    <h3 class="titulo-seccion">Preferencias de Alertas</h3>
                    <p class="subtitulo-seccion">Elige qué eventos del torneo querés recibir.</p>

                    <!-- Alertas específicas de Notificaciones -->
                    <?php if ($mensajeExito && $pestanaActiva === 'notificaciones'): ?>
                        <div class="alerta alerta-exito"><?= htmlspecialchars($mensajeExito) ?></div>
                    <?php endif; ?>
                    <?php if ($mensajeError && $pestanaActiva === 'notificaciones'): ?>
                        <div class="alerta alerta-error"><?= htmlspecialchars($mensajeError) ?></div>
                    <?php endif; ?>
                    
                    <form action="logica/actualizarConfiguracion.php" method="POST" class="formulario-configuracion">
                        <input type="hidden" name="accion" value="guardar_notificaciones">
                        <input type="hidden" name="pestana_activa" value="notificaciones">
                        <input type="hidden" name="ultimo_input_id" class="campo-ultimo-input" value="">
                        
                        <div class="grupo-checkbox">
                            <label class="contenedor-interruptor">
                                <input type="checkbox" id="noti_fixtures" name="noti_fixtures" checked>
                                <span class="deslizador"></span>
                                <span class="etiqueta-interruptor">Publicación de Fixtures</span>
                            </label>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-guardar">Guardar alertas</button>
                        </div>
                    </form>
                </div>

                <!-- 4. Borrar cuenta -->
                <div id="borrar-cuenta" class="seccion-configuracion panel-borrar">
                    <h3 class="titulo-seccion titulo-peligro">Eliminar Cuenta Permanentemente</h3>
                    <p class="subtitulo-seccion">Esta acción es irreversible. Se perderán tus datos de usuario en el sistema.</p>

                    <!-- Alertas específicas de Borrar Cuenta -->
                    <?php if ($mensajeExito && $pestanaActiva === 'borrar'): ?>
                        <div class="alerta alerta-exito"><?= htmlspecialchars($mensajeExito) ?></div>
                    <?php endif; ?>
                    <?php if ($mensajeError && $pestanaActiva === 'borrar'): ?>
                        <div class="alerta alerta-error"><?= htmlspecialchars($mensajeError) ?></div>
                    <?php endif; ?>
                    
                    <form action="logica/actualizarConfiguracion.php" method="POST" id="form-borrar-cuenta" class="formulario-configuracion">
                        <input type="hidden" name="accion" value="borrar_cuenta">
                        <input type="hidden" name="pestana_activa" value="borrar">
                        <input type="hidden" name="ultimo_input_id" class="campo-ultimo-input" value="">
                        
                        <div class="grupo-formulario">
                            <label for="contrasena-borrado" class="etiqueta-formulario">Escriba su contraseña actual para confirmar el borrado</label>
                            <input type="password" id="contrasena-borrado" name="contrasena_borrado" class="control-input-formulario" placeholder="••••••••" required>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-peligro">Confirmar Eliminación</button>
                        </div>
                    </form>
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
    <script src="../js/configuracion.js"></script>

    <!-- Script de captura y restauración de foco en inputs -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let ultimoInputIdGuardado = <?= json_encode($ultimoInputId) ?>;

            // Registrar eventos de foco en todos los inputs
            document.querySelectorAll('.formulario-configuracion input, .formulario-configuracion select').forEach(function(elem) {
                elem.addEventListener('focus', function() {
                    if (this.id) {
                        ultimoInputIdGuardado = this.id;
                        document.querySelectorAll('.campo-ultimo-input').forEach(function(hidden) {
                            hidden.value = elem.id;
                        });
                    }
                });
            });

            // Asegurar que el input hidden tenga el valor antes de enviar
            document.querySelectorAll('.formulario-configuracion').forEach(function(form) {
                form.addEventListener('submit', function() {
                    const hidden = form.querySelector('.campo-ultimo-input');
                    if (hidden && ultimoInputIdGuardado) {
                        hidden.value = ultimoInputIdGuardado;
                    }
                });
            });

            // Reenfocar el input tras la recarga
            if (ultimoInputIdGuardado) {
                const elementoTarget = document.getElementById(ultimoInputIdGuardado);
                if (elementoTarget) {
                    elementoTarget.focus();
                    if (elementoTarget.setSelectionRange && ['text', 'email', 'tel', 'password'].includes(elementoTarget.type)) {
                        const largoTexto = elementoTarget.value.length;
                        elementoTarget.setSelectionRange(largoTexto, largoTexto);
                    }
                }
            }
        });
    </script>
</body>
</html>