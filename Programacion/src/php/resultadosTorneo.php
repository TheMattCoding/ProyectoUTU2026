<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'logica/auth.php';
require_once 'db.php';
require_once 'logica/notificaciones.php';

$rolActual = $_SESSION['rol'] ?? 'visitante';
$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;
$fotoPerfil = $_SESSION['foto_perfil'] ?? null;

$mis_notis = [];
$cant_sin_leer = 0;

if ($idUsuario) {
    $mis_notis = obtenerMisNotificaciones($pdo, $idUsuario);
    $cant_sin_leer = contarNoLeidas($pdo, $idUsuario);
    
    if (empty($fotoPerfil) && isset($pdo)) {
        try {
            $stmtFoto = $pdo->prepare("SELECT foto_perfil FROM usuarios WHERE id_usuario = ?");
            $stmtFoto->execute([$idUsuario]);
            $fotoPerfil = $stmtFoto->fetchColumn();
            $_SESSION['foto_perfil'] = $fotoPerfil;
        } catch (Throwable $e) {}
    }
}

$idTorneoSeleccionado = isset($_GET['id_torneo']) ? (int)$_GET['id_torneo'] : 0;

// 1. Obtener la lista de todos los torneos disponibles para el selector
$sqlTorneos = "SELECT id_torneo, nombre_torneo, estado FROM torneos ORDER BY id_torneo DESC";
$stmtTorneos = $pdo->query($sqlTorneos);
$listaTorneos = $stmtTorneos->fetchAll(PDO::FETCH_ASSOC);

// Si no se pasó id_torneo, tomar el primero de la lista si existe
if ($idTorneoSeleccionado === 0 && !empty($listaTorneos)) {
    $idTorneoSeleccionado = $listaTorneos[0]['id_torneo'];
}

// 2. Obtener información del torneo seleccionado
$torneoActual = null;
if ($idTorneoSeleccionado > 0) {
    $stmtT = $pdo->prepare("SELECT t.*, m.nombre_modulo 
                            FROM torneos t 
                            LEFT JOIN modulos_competencia m ON t.id_modulo = m.id_modulo 
                            WHERE t.id_torneo = :id");
    $stmtT->execute([':id' => $idTorneoSeleccionado]);
    $torneoActual = $stmtT->fetch(PDO::FETCH_ASSOC);
}

// 3. Obtener la tabla de posiciones / inscritos del torneo
$posiciones = [];
if ($idTorneoSeleccionado > 0) {
    $sqlPosiciones = "SELECT i.posicion_final, 
                             COALESCE(CONCAT(p.nombre, ' ', p.apellido), u.username, CONCAT('Equipo ', i.id_equipo)) AS competidor,
                             i.estado_inscripcion
                      FROM inscripciones_torneo i
                      LEFT JOIN participantes p ON i.id_participante = p.id_participante
                      LEFT JOIN usuarios u ON p.id_usuario = u.id_usuario
                      WHERE i.id_torneo = :id_torneo
                      ORDER BY CASE WHEN i.posicion_final IS NULL THEN 1 ELSE 0 END, i.posicion_final ASC";
    $stmtPos = $pdo->prepare($sqlPosiciones);
    $stmtPos->execute([':id_torneo' => $idTorneoSeleccionado]);
    $posiciones = $stmtPos->fetchAll(PDO::FETCH_ASSOC);
}

// 4. Obtener rondas y enfrentamientos con sus resultados (solo lectura)
$rondasConEnfrentamientos = [];
if ($idTorneoSeleccionado > 0) {
    $sqlRondas = "SELECT r.id_ronda, r.numero_ronda, r.nombre_ronda, r.estado_ronda
                  FROM rondas r
                  WHERE r.id_torneo = :id_torneo
                  ORDER BY r.numero_ronda ASC";
    $stmtRondas = $pdo->prepare($sqlRondas);
    $stmtRondas->execute([':id_torneo' => $idTorneoSeleccionado]);
    $rondas = $stmtRondas->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rondas as $ronda) {
        $sqlEnfrentamientos = "SELECT e.id_enfrentamiento, e.estado_enfrentamiento,
                                      loc.id_equipo AS id_local,
                                      vis.id_equipo AS id_visitante,
                                      COALESCE(CONCAT(p_loc.nombre, ' ', p_loc.apellido), 'Local') AS equipo_local,
                                      COALESCE(CONCAT(p_vis.nombre, ' ', p_vis.apellido), 'Visitante') AS equipo_visita,
                                      res.puntuacion_local AS marcador_local, 
                                      res.puntuacion_visitante AS marcador_visita, 
                                      res.id_ganador
                               FROM enfrentamientos e
                               LEFT JOIN equipos loc ON e.id_local = loc.id_equipo
                               LEFT JOIN participantes p_loc ON loc.id_participante = p_loc.id_participante
                               LEFT JOIN equipos vis ON e.id_visitante = vis.id_equipo
                               LEFT JOIN participantes p_vis ON vis.id_participante = p_vis.id_participante
                               LEFT JOIN resultados res ON e.id_enfrentamiento = res.id_enfrentamiento
                               WHERE e.id_ronda = :id_ronda";
        $stmtEnf = $pdo->prepare($sqlEnfrentamientos);
        $stmtEnf->execute([':id_ronda' => $ronda['id_ronda']]);
        $enfrentamientos = $stmtEnf->fetchAll(PDO::FETCH_ASSOC);

        $rondasConEnfrentamientos[] = [
            'info' => $ronda,
            'enfrentamientos' => $enfrentamientos
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Resultados y Tabla de Posiciones</title>
    
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/resultadosTorneo.css">
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
            <a href="busquedaUsuario.php" class="sidebar-link">Buscar Usuarios</a>
            <a href="resultadosTorneo.php" class="sidebar-link active">Resultados y Posiciones</a>

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

        <a href="busquedaTorneo.php" class="btn-ver-torneos-nav">Ver torneos</a>

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

        <!-- Apartado de Perfil -->
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

    <!-- Contenido Principal -->
    <main class="main-container">
        
        <!-- Encabezado y Selector de Torneo -->
        <header class="page-header-resultados">
            <div>
                <h2 class="titulo-pagina">Resultados y Tabla de Posiciones</h2>
                <p class="subtitulo-pagina">Consulta las puntuaciones y la clasificación oficial del torneo.</p>
            </div>

            <form method="GET" action="resultadosTorneo.php" class="form-selector-torneo">
                <label for="id_torneo" class="label-selector">Seleccionar Torneo:</label>
                <select name="id_torneo" id="id_torneo" class="select-torneo" onchange="this.form.submit()">
                    <?php foreach ($listaTorneos as $t): ?>
                        <option value="<?= $t['id_torneo'] ?>" <?= ($t['id_torneo'] == $idTorneoSeleccionado) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['nombre_torneo']) ?> (<?= ucfirst($t['estado']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </header>

        <?php if ($torneoActual): ?>
            
            <!-- Resumen del Torneo -->
            <section class="card-resumen-torneo">
                <div class="info-torneo-header">
                    <h3 class="nombre-torneo-titulo"><?= htmlspecialchars($torneoActual['nombre_torneo']) ?></h3>
                    <span class="badge-estado <?= strtolower($torneoActual['estado']) ?>">
                        <?= htmlspecialchars(ucfirst($torneoActual['estado'])) ?>
                    </span>
                </div>
                <div class="detalles-inline">
                    <span><strong>Módulo:</strong> <?= htmlspecialchars($torneoActual['nombre_modulo'] ?? 'General') ?></span>
                    <span><strong>Lugar:</strong> <?= htmlspecialchars($torneoActual['lugar'] ?? 'Por definir') ?></span>
                    <span><strong>Fecha:</strong> <?= htmlspecialchars($torneoActual['fecha_inicio'] ?? '-') ?></span>
                </div>
            </section>

            <!-- Grid Principal -->
            <div class="grid-resultados">
                
                <!-- 1. Tabla de Posiciones -->
                <section class="seccion-posiciones">
                    <h3 class="seccion-titulo-resultados">
                        <svg class="icono-titulo-svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path>
                            <path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path>
                            <path d="M4 22h16"></path>
                            <path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path>
                            <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path>
                            <path d="M18 2H6v7a6 6 0 0 0 12 0V2z"></path>
                        </svg>
                        Clasificación General
                    </h3>
                    
                    <div class="tabla-contenedor">
                        <table class="tabla-posiciones">
                            <thead>
                                <tr>
                                    <th class="col-pos">#</th>
                                    <th>Competidor / Equipo</th>
                                    <th class="col-estado">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($posiciones)): ?>
                                    <tr>
                                        <td colspan="3" class="sin-datos">No hay participantes registrados en este torneo.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($posiciones as $index => $pos): 
                                        $numPos = $pos['posicion_final'] ?? ($index + 1);
                                        $clasePodio = '';
                                        if ($numPos == 1) $clasePodio = 'podio-oro';
                                        elseif ($numPos == 2) $clasePodio = 'podio-plata';
                                        elseif ($numPos == 3) $clasePodio = 'podio-bronce';
                                    ?>
                                        <tr class="<?= $clasePodio ?>">
                                            <td class="col-pos font-bold">
                                                <?php if ($numPos == 1): ?>
                                                    <svg class="icono-podio podio-oro-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="9" r="6" fill="#D4AF37" stroke="#B89628"/>
                                                        <path d="M9 14.5L7 22l5-2.5L17 22l-2-7.5" stroke="#D4AF37"/>
                                                        <text x="12" y="11.5" font-size="8" font-weight="900" fill="#111" text-anchor="middle">1</text>
                                                    </svg>
                                                <?php elseif ($numPos == 2): ?>
                                                    <svg class="icono-podio podio-plata-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="9" r="6" fill="#C0C0C0" stroke="#999999"/>
                                                        <path d="M9 14.5L7 22l5-2.5L17 22l-2-7.5" stroke="#C0C0C0"/>
                                                        <text x="12" y="11.5" font-size="8" font-weight="900" fill="#111" text-anchor="middle">2</text>
                                                    </svg>
                                                <?php elseif ($numPos == 3): ?>
                                                    <svg class="icono-podio podio-bronce-svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="9" r="6" fill="#CD7F32" stroke="#A05A1C"/>
                                                        <path d="M9 14.5L7 22l5-2.5L17 22l-2-7.5" stroke="#CD7F32"/>
                                                        <text x="12" y="11.5" font-size="8" font-weight="900" fill="#111" text-anchor="middle">3</text>
                                                    </svg>
                                                <?php else: ?>
                                                    <?= $numPos ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="nombre-competidor font-bold">
                                                <?= htmlspecialchars($pos['competidor']) ?>
                                            </td>
                                            <td class="col-estado">
                                                <span class="badge-inscripcion <?= strtolower($pos['estado_inscripcion']) ?>">
                                                    <?= htmlspecialchars(ucfirst($pos['estado_inscripcion'])) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- 2. Enfrentamientos y Resultados por Ronda -->
                <section class="seccion-enfrentamientos">
                    <h3 class="seccion-titulo-resultados">
                        <svg class="icono-titulo-svg" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14.5 17.5L3 6V3h3l11.5 11.5"></path>
                            <path d="M13 19l6-6"></path>
                            <path d="M16 16l4 4"></path>
                            <path d="M9.5 17.5L21 6V3h-3L6.5 14.5"></path>
                            <path d="M11 19l-6-6"></path>
                            <path d="M8 16l-4 4"></path>
                        </svg>
                        Enfrentamientos y Marcadores
                    </h3>

                    <?php if (empty($rondasConEnfrentamientos)): ?>
                        <div class="card-vacio">No hay rondas ni enfrentamientos programados aún.</div>
                    <?php else: ?>
                        <?php foreach ($rondasConEnfrentamientos as $rondaBlock): ?>
                            <?php 
                                if (empty($rondaBlock['enfrentamientos'])) {
                                    continue; 
                                }
                            ?>
                            <div class="bloque-ronda">
                                <h4 class="nombre-ronda-header">
                                    <?= htmlspecialchars($rondaBlock['info']['nombre_ronda'] ?: 'Ronda ' . $rondaBlock['info']['numero_ronda']) ?>
                                    <span class="badge-ronda <?= strtolower($rondaBlock['info']['estado_ronda']) ?>">
                                        <?= htmlspecialchars(ucfirst($rondaBlock['info']['estado_ronda'])) ?>
                                    </span>
                                </h4>

                                <div class="lista-enfrentamientos">
                                    <?php foreach ($rondaBlock['enfrentamientos'] as $partido): ?>
                                        <div class="tarjeta-partido">
                                            <div class="equipo local">
                                                <span class="nombre"><?= htmlspecialchars($partido['equipo_local']) ?></span>
                                            </div>
                                            
                                            <div class="marcador-contenedor">
                                                <?php if ($partido['estado_enfrentamiento'] === 'finalizado'): ?>
                                                    <span class="puntos"><?= htmlspecialchars($partido['marcador_local'] ?? 0) ?></span>
                                                    <span class="separador">-</span>
                                                    <span class="puntos"><?= htmlspecialchars($partido['marcador_visita'] ?? 0) ?></span>
                                                    <div class="estado-tag finalizado">FINALIZADO</div>
                                                <?php else: ?>
                                                    <span class="vs">VS</span>
                                                    <div class="estado-tag pendiente">PENDIENTE</div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="equipo visitante">
                                                <span class="nombre"><?= htmlspecialchars($partido['equipo_visita']) ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </section>

            </div>

        <?php else: ?>
            <div class="card-vacio">No se encontró ningún torneo disponible.</div>
        <?php endif; ?>

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
                    <summary class="pregunta-faq">¿Cómo me inscribo a un torneo?</summary>
                    <p class="texto-nosotros">Ve a la sección de torneos, selecciona la competencia deseada y presiona en "Inscribirse".</p>
                </details>

                <details class="item-faq">
                    <summary class="pregunta-faq">¿Cómo edito la información de mi perfil?</summary>
                    <p class="texto-nosotros">Haz clic en la sección de configuración del menú lateral y accede a la pestaña "Editar perfil" para actualizar tus datos personales.</p>
                </details>
            </div>

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

    <script src="../js/seccionSobreNosotros.js"></script>
    <script src="../js/seccionAyuda.js"></script>
</body>
</html>