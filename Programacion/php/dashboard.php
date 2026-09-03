<?php
require_once 'logica/auth.php';
requerirRol('administrador'); 

require_once 'db.php';

$mensaje = '';
$error = '';

// --- PROCESAMIENTO DE ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        switch ($_POST['accion']) {
            case 'asignar_organizador':
                $id_torneo = (int)$_POST['id_torneo'];
                $id_organizador = (int)$_POST['id_organizador'];
                $stmt = $pdo->prepare("UPDATE torneos SET id_organizador = ? WHERE id_torneo = ?");
                $stmt->execute([$id_organizador, $id_torneo]);
                $mensaje = "Organizador asignado con éxito al torneo.";
                break;

            case 'eliminar_torneo':
                $id_torneo = (int)$_POST['id_torneo'];
                $stmt = $pdo->prepare("DELETE FROM torneos WHERE id_torneo = ?");
                $stmt->execute([$id_torneo]);
                $mensaje = "Torneo eliminado correctamente.";
                break;

            case 'sacar_participante_torneo':
                $id_inscripcion = (int)$_POST['id_inscripcion'];
                $stmt = $pdo->prepare("DELETE FROM inscripciones_torneo WHERE id_inscripcion = ?");
                $stmt->execute([$id_inscripcion]);
                $mensaje = "Inscripción removida del torneo correctamente.";
                break;

            case 'editar_participante':
                $id_usuario = (int)$_POST['id_usuario'];
                $username = trim($_POST['username']);
                
                if (!empty($username)) {
                    $stmt = $pdo->prepare("UPDATE usuarios SET username = ? WHERE id_usuario = ?");
                    $stmt->execute([$username, $id_usuario]);
                    $mensaje = "Nombre de usuario actualizado correctamente.";
                } else {
                    $error = "El nombre de usuario no puede estar vacío.";
                }
                break;

            case 'eliminar_participante':
                $id_participante = (int)$_POST['id_participante'];
                $stmt = $pdo->prepare("DELETE FROM participantes WHERE id_participante = ?");
                $stmt->execute([$id_participante]);
                $mensaje = "Participante eliminado del sistema.";
                break;
        }
    } catch (PDOException $e) {
        $error = "Error en la base de datos: " . $e->getMessage();
    }
}

// --- CONSULTAS DE DATOS ADAPTADAS A LA NUEVA BD ---
$totalTorneos = $pdo->query("SELECT COUNT(*) FROM torneos")->fetchColumn() ?: 0;
// Contamos participantes/equipos registrados
$totalEquipos = $pdo->query("SELECT COUNT(*) FROM equipos")->fetchColumn() ?: 0;
$partidosPendientes = $pdo->query("SELECT COUNT(*) FROM enfrentamientos WHERE estado_enfrentamiento = 'pendiente'")->fetchColumn() ?: 0;

// Obtener organizadores (Rol 'organizador' o id_rol = 2)
$stmtOrg = $pdo->query("SELECT u.id_usuario, u.username FROM usuarios u INNER JOIN roles r ON u.id_rol = r.id_rol WHERE r.nombre_rol = 'organizador' OR u.id_rol = 2");
$organizadores = $stmtOrg->fetchAll();

// Obtener torneos
$stmtTorneos = $pdo->query("
    SELECT t.*, u.username AS organizador_nombre 
    FROM torneos t 
    LEFT JOIN usuarios u ON t.id_organizador = u.id_usuario 
    ORDER BY t.id_torneo DESC
");
$torneos = $stmtTorneos->fetchAll();

// Obtener participantes globales
$stmtParticipantes = $pdo->query("
    SELECT p.*, u.id_usuario, u.username, u.email 
    FROM participantes p 
    INNER JOIN usuarios u ON p.id_usuario = u.id_usuario 
    ORDER BY p.id_participante DESC
");
$participantes = $stmtParticipantes->fetchAll();

// Obtener inscripciones relacionando adecuadamente equipos -> participante o participante directo
$stmtInscripciones = $pdo->query("
    SELECT 
        i.id_inscripcion, 
        t.nombre_torneo, 
        i.estado_inscripcion,
        COALESCE(p_directo.nombre, p_equipo.nombre) AS nombre,
        COALESCE(p_directo.apellido, p_equipo.apellido) AS apellido
    FROM inscripciones_torneo i
    JOIN torneos t ON i.id_torneo = t.id_torneo
    LEFT JOIN participantes p_directo ON i.id_participante = p_directo.id_participante
    LEFT JOIN equipos e ON i.id_equipo = e.id_equipo
    LEFT JOIN participantes p_equipo ON e.id_participante = p_equipo.id_participante
    ORDER BY i.id_inscripcion DESC
");
$inscripciones = $stmtInscripciones->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Panel Administrador</title>
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/dashboard.css">
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
            <a href="organizador.php" class="sidebar-link">Panel Organizador</a>
            <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
            <a href="dashboard.php" class="sidebar-link active">Panel Administrador</a>
            <a href="configuracion.php" class="sidebar-link">Configuración</a>
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
                    <span class="profile-menu-name">
                        <?= htmlspecialchars($_SESSION['nombre'] ?? $_SESSION['usuario'] ?? 'Invitado') ?>
                    </span>
                </div>
                <div class="profile-menu-divider"></div>
                <nav class="profile-menu-links">
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
                </nav>
            </div>
        </div>
    </nav>

    <main class="main-container">
        
        <header class="cabecera-panel">
            <h2>Panel de Control Administrador</h2>
            <p class="estado-sistema">● Servidor Activo</p>
        </header>

        <?php if ($mensaje): ?>
            <div class="alert-mensaje exito">
                <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert-mensaje error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <article class="isla-organizador-unica">
            
            <!-- 1. Estadísticas del Sistema (KPIs) -->
            <section class="seccion-kpis">
                <div class="tarjeta-kpi">
                    <h3>Torneos Creados</h3>
                    <p class="valor-kpi"><?= $totalTorneos ?></p>
                </div>
                <div class="tarjeta-kpi">
                    <h3>Equipos / Registros</h3>
                    <p class="valor-kpi texto-exito"><?= $totalEquipos ?></p>
                </div>
                <div class="tarjeta-kpi">
                    <h3>Partidos Pendientes</h3>
                    <p class="valor-kpi texto-peligro"><?= $partidosPendientes ?></p>
                </div>
            </section>

            <hr class="divisor-isla">

            <!-- 2. Gestión de Torneos -->
            <section class="seccion-tabla">
                <h3>Gestión de Torneos</h3>

                <div class="barra-filtros">
                    <input type="text" id="buscar-torneo" class="input-busqueda-tabla" placeholder="Buscar torneo por nombre...">
                    <select id="ordenar-torneo" class="select-ordenar-tabla">
                        <option value="defecto">Ordenar por...</option>
                        <option value="nombre-asc">Nombre (A - Z)</option>
                        <option value="nombre-desc">Nombre (Z - A)</option>
                        <option value="fecha-desc">Fecha (Más reciente primero)</option>
                        <option value="fecha-asc">Fecha (Más antiguo primero)</option>
                    </select>
                </div>

                <div class="contenedor-tabla-adaptable">
                    <table class="tabla-panel" id="tabla-torneos">
                        <thead>
                            <tr>
                                <th>Torneo</th>
                                <th>Fecha Inicio</th>
                                <th>Estado</th>
                                <th>Organizador Asignado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($torneos as $torneo): ?>
                                <tr data-nombre="<?= strtolower(htmlspecialchars($torneo['nombre_torneo'])) ?>" data-fecha="<?= htmlspecialchars($torneo['fecha_inicio'] ?? '0000-00-00') ?>">
                                    <td><strong><?= htmlspecialchars($torneo['nombre_torneo']) ?></strong></td>
                                    <td><?= htmlspecialchars($torneo['fecha_inicio'] ?? 'Sin fecha') ?></td>
                                    <td><span class="etiqueta-estado-exito"><?= htmlspecialchars($torneo['estado']) ?></span></td>
                                    <td>
                                        <form method="POST" class="form-inline">
                                            <input type="hidden" name="accion" value="asignar_organizador">
                                            <input type="hidden" name="id_torneo" value="<?= $torneo['id_torneo'] ?>">
                                            <select name="id_organizador" class="select-tabla">
                                                <option value="">Sin asignar</option>
                                                <?php foreach ($organizadores as $org): ?>
                                                    <option value="<?= $org['id_usuario'] ?>" <?= $torneo['id_organizador'] == $org['id_usuario'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($org['username']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-accion btn-guardar">Guardar</button>
                                        </form>
                                    </td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este torneo?');" class="form-accion-inline">
                                            <input type="hidden" name="accion" value="eliminar_torneo">
                                            <input type="hidden" name="id_torneo" value="<?= $torneo['id_torneo'] ?>">
                                            <button type="submit" class="btn-accion btn-eliminar">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($torneos)): ?>
                                <tr><td colspan="5" class="tabla-vacia">No hay torneos registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="divisor-isla">

            <!-- 3. Sacar Participantes de un Torneo -->
            <section class="seccion-tabla">
                <h3>Inscripciones a Torneos</h3>

                <div class="barra-filtros">
                    <input type="text" id="buscar-inscripcion" class="input-busqueda-tabla" placeholder="Buscar por torneo o participante...">
                    <select id="ordenar-inscripcion" class="select-ordenar-tabla">
                        <option value="defecto">Ordenar por...</option>
                        <option value="torneo-asc">Nombre del Torneo (A - Z)</option>
                        <option value="torneo-desc">Nombre del Torneo (Z - A)</option>
                        <option value="participante-asc">Participante (A - Z)</option>
                    </select>
                </div>

                <div class="contenedor-tabla-adaptable">
                    <table class="tabla-panel" id="tabla-inscripciones">
                        <thead>
                            <tr>
                                <th>Torneo</th>
                                <th>Participante</th>
                                <th>Estado Inscripción</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inscripciones as $insc): 
                                $nombreSujeto = ($insc['nombre'] || $insc['apellido']) 
                                    ? trim($insc['nombre'] . ' ' . $insc['apellido']) 
                                    : 'Sin Identificar';
                            ?>
                                <tr data-torneo="<?= strtolower(htmlspecialchars($insc['nombre_torneo'])) ?>" data-sujeto="<?= strtolower(htmlspecialchars($nombreSujeto)) ?>">
                                    <td><?= htmlspecialchars($insc['nombre_torneo']) ?></td>
                                    <td><?= htmlspecialchars($nombreSujeto) ?></td>
                                    <td><span class="etiqueta-estado-exito"><?= htmlspecialchars($insc['estado_inscripcion']) ?></span></td>
                                    <td>
                                        <form method="POST" onsubmit="return confirm('¿Remover participante de este torneo?');">
                                            <input type="hidden" name="accion" value="sacar_participante_torneo">
                                            <input type="hidden" name="id_inscripcion" value="<?= $insc['id_inscripcion'] ?>">
                                            <button type="submit" class="btn-accion btn-sacar">Sacar del Torneo</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($inscripciones)): ?>
                                <tr><td colspan="4" class="tabla-vacia">No hay inscripciones registradas.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="divisor-isla">

            <!-- 4. Gestión de Participantes -->
            <section class="seccion-tabla">
                <h3>Gestión Global de Participantes</h3>
                <div class="contenedor-tabla-adaptable">
                    <table class="tabla-panel">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Nombre y Apellido</th>
                                <th>CI</th>
                                <th>Teléfono</th>
                                <th>Correo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($participantes as $part): ?>
                                <tr>
                                    <form method="POST">
                                        <input type="hidden" name="accion" value="editar_participante">
                                        <input type="hidden" name="id_usuario" value="<?= $part['id_usuario'] ?>">
                                        <td>
                                            <input type="text" name="username" value="<?= htmlspecialchars($part['username']) ?>" required class="input-tabla width-sm">
                                        </td>
                                        <td>
                                            <?= htmlspecialchars($part['nombre'] . ' ' . $part['apellido']) ?>
                                        </td>
                                        <td class="texto-secundario">
                                            <?= htmlspecialchars($part['ci'] ?? '-') ?>
                                        </td>
                                        <td class="texto-secundario">
                                            <?= htmlspecialchars($part['telefono'] ?? '-') ?>
                                        </td>
                                        <td class="texto-secundario">
                                            <?= htmlspecialchars($part['email']) ?>
                                        </td>
                                        <td>
                                            <button type="submit" class="btn-accion btn-guardar">Guardar</button>
                                    </form>
                                            <form method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este participante permanentemente?');" class="form-accion-inline">
                                                <input type="hidden" name="accion" value="eliminar_participante">
                                                <input type="hidden" name="id_participante" value="<?= $part['id_participante'] ?>">
                                                <button type="submit" class="btn-accion btn-eliminar">Eliminar</button>
                                            </form>
                                        </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($participantes)): ?>
                                <tr><td colspan="6" class="tabla-vacia">No hay participantes registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </article>

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
    
    <script src="../js/dashboard.js"></script>
</body>
</html>