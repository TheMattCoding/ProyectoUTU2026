<?php
require_once 'logica/auth.php';
require_once 'db.php';

requerirRol(['organizador', 'administrador']);

$rolActual = $_SESSION['rol'] ?? 'visitante';
$usuarioActual = $_SESSION['usuario'] ?? 'Visitante';
$idUsuarioActual = $_SESSION['id_usuario'] ?? 0;

// Consulta de torneos asignados según el rol
try {
    if ($rolActual === 'administrador') {
        // El administrador ve todos los torneos
        $sql = "SELECT t.*, m.nombre_modulo AS disciplina 
                FROM TORNEOS t 
                LEFT JOIN MODULOS_COMPETENCIA m ON t.id_modulo = m.id_modulo 
                ORDER BY t.id_torneo DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
    } else {
        // El organizador ve solo los torneos que creó/tiene asignados
        $sql = "SELECT t.*, m.nombre_modulo AS disciplina 
                FROM TORNEOS t 
                LEFT JOIN MODULOS_COMPETENCIA m ON t.id_modulo = m.id_modulo 
                WHERE t.id_creador = :id_creador 
                ORDER BY t.id_torneo DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id_creador' => $idUsuarioActual]);
    }
    $torneosAsignados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $torneosAsignados = [];
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

        <form action="busquedaTorneo.php" method="GET" class="search-form" style="display: flex; flex: 1; max-width: 420px; margin: 0 12px;">
            <div class="search-container" style="margin: 0; width: 100%;">
                <svg class="search-google-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" fill="#777777"/>
                </svg>
                <input type="text" class="search-input" placeholder="Buscar un torneo" aria-label="Buscar torneos" name="query">
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
                            <p class="noti-text">Tu inscripción para la <strong>Copa de Invierno</strong> ha sido confirmada exitosamente.</p>
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
                    <span class="profile-menu-name"><?php echo htmlspecialchars($usuarioActual); ?></span>
                </div>
                <div class="profile-menu-divider"></div>
                <nav class="profile-menu-links">
                    <a href="perfil.php" class="profile-menu-item">Perfil</a>
                    <div class="profile-menu-divider"></div>
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
                                                <a href="detalleTorneo.php?id=<?php echo $itemTorneo['id_torneo']; ?>" class="btn-secundario-chico" style="text-decoration:none;">Ver Detalle</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" style="text-align: center; padding: 20px;">No tienes torneos asignados actualmente.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pestaña: Fixtures y Resultados -->
                <div class="seccion-organizador panel-fixtures">
                    <h3 class="titulo-seccion">Gestión de Fixtures y Rondas</h3>
                    <p class="subtitulo-seccion">Controla el estado de las llaves y digita las puntuaciones oficiales.</p>
                    
                    <div class="cuadricula-fila-formulario">
                        <div class="grupo-formulario">
                            <label class="etiqueta-formulario">Seleccionar Torneo</label>
                            <select class="control-formulario-seleccion">
                                <?php foreach ($torneosAsignados as $itemTorneo): ?>
                                    <option value="<?php echo $itemTorneo['id_torneo']; ?>">
                                        <?php echo htmlspecialchars($itemTorneo['nombre_torneo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="grupo-formulario">
                            <label class="etiqueta-formulario">Fase / Jornada Actual</label>
                            <select class="control-formulario-seleccion">
                                <option>Fecha 1 - Serie A</option>
                                <option>Fecha 2 - Serie A</option>
                            </select>
                        </div>
                    </div>

                    <div class="grupo-checkbox caja-estado-ronda">
                        <label class="contenedor-interruptor">
                            <input type="checkbox" id="interruptor-estado-ronda" checked>
                            <span class="deslizador"></span>
                            <span class="etiqueta-interruptor"><strong>Ronda Publicada</strong> (Visible para la comunidad)</span>
                        </label>
                    </div>

                    <h4 class="encabezado-subseccion">Enfrentamientos de la Fecha</h4>
                    <form action="#" method="POST" class="formulario-organizador">
                        <div class="tarjeta-fila-partido">
                            <span class="nombre-equipo texto-derecha">Equipo Alpha</span>
                            <div class="entradas-marcador-partido">
                                <input type="number" class="control-formulario-entrada entrada-marcador" value="2" min="0">
                                <span class="divisor-marcador">vs</span>
                                <input type="number" class="control-formulario-entrada entrada-marcador" value="1" min="0">
                            </div>
                            <span class="nombre-equipo texto-izquierda">Equipo Beta</span>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-guardar">Guardar marcadores</button>
                        </div>
                    </form>
                </div>

                <!-- Pestaña: Inscribir Participantes -->
                <div class="seccion-organizador panel-participantes">
                    <h3 class="titulo-seccion">Inscripción Manual de Equipos</h3>
                    <p class="subtitulo-seccion">Agrega participantes directamente al torneo sin pasar por la pasarela pública.</p>
                    
                    <form action="#" method="POST" class="formulario-organizador">
                        <div class="cuadricula-fila-formulario">
                            <div class="grupo-formulario">
                                <label for="torneo-destino" class="etiqueta-formulario">Asignar al Torneo</label>
                                <select id="torneo-destino" class="control-formulario-seleccion">
                                    <?php foreach ($torneosAsignados as $itemTorneo): ?>
                                        <option value="<?php echo $itemTorneo['id_torneo']; ?>">
                                            <?php echo htmlspecialchars($itemTorneo['nombre_torneo']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="grupo-formulario">
                                <label for="nombre-participante" class="etiqueta-formulario">Nombre del Competidor / Equipo</label>
                                <input type="text" id="nombre-participante" class="control-formulario-entrada" placeholder="Ej: UTU FC o Juan Pérez">
                            </div>
                        </div>

                        <div class="acciones-formulario">
                            <button type="submit" class="btn-guardar">Confirmar Registro</button>
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
                    <a href="#" class="footer-link">Help</a>
                </nav>
                <p class="footer-copyright">&copy; 2026 Epsilon Software. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
</body>
</html>