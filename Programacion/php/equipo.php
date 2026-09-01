<?php
require_once 'logica/auth.php';
require_once 'db.php';

requerirLogin();

$rolActual       = $_SESSION['rol'] ?? 'visitante';
$idUsuarioActual = $_SESSION['id_usuario'] ?? null;
$nombreUsuario   = $_SESSION['usuario'] ?? 'Usuario';
$esAdmin         = ($rolActual === 'administrador' || ($_SESSION['id_rol'] ?? 0) == 1);

$mensajeExito = '';
$mensajeError = '';

// Obtener el id_participante correspondiente al usuario logueado
$idParticipanteActual = null;
if ($idUsuarioActual) {
    try {
        $stmtMiPerfil = $pdo->prepare("SELECT id_participante FROM PARTICIPANTES WHERE id_usuario = :id_usuario LIMIT 1");
        $stmtMiPerfil->execute([':id_usuario' => $idUsuarioActual]);
        $resPerfil = $stmtMiPerfil->fetch(PDO::FETCH_ASSOC);
        if ($resPerfil) {
            $idParticipanteActual = $resPerfil['id_participante'];
        }
    } catch (PDOException $e) {
        // Manejo silencioso o logger de errores
    }
}

// --------------------------------------------------------------------------
// PROCESAR: Crear Equipo (Solo Administradores)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_crear_equipo'])) {
    if (!$esAdmin) {
        $mensajeError = "No tienes permisos suficientes. Solo los administradores pueden crear equipos.";
    } else {
        $nombreEquipo    = trim($_POST['nombre_equipo'] ?? '');
        $idParticipante = filter_input(INPUT_POST, 'id_participante_inicial', FILTER_VALIDATE_INT);

        if (!empty($nombreEquipo)) {
            try {
                $pdo->beginTransaction();

                $stmtCrear = $pdo->prepare("INSERT INTO EQUIPOS (nombre_equipo, id_creador) VALUES (:nombre, :id_creador)");
                $stmtCrear->execute([
                    ':nombre'     => $nombreEquipo,
                    ':id_creador' => $idUsuarioActual
                ]);
                $idNuevoEquipo = $pdo->lastInsertId();

                if ($idParticipante) {
                    $stmtMiembro = $pdo->prepare("INSERT INTO INTEGRANTES_EQUIPO (id_equipo, id_participante) VALUES (:id_equipo, :id_participante)");
                    $stmtMiembro->execute([
                        ':id_equipo'       => $idNuevoEquipo,
                        ':id_participante' => $idParticipante
                    ]);
                }

                $pdo->commit();
                $mensajeExito = "El equipo <strong>" . htmlspecialchars($nombreEquipo) . "</strong> ha sido creado exitosamente.";
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $mensajeError = "Error en la base de datos: " . $e->getMessage();
            }
        } else {
            $mensajeError = "El nombre del equipo no puede estar vacío.";
        }
    }
}

// --------------------------------------------------------------------------
// PROCESAR: Unirse a un Equipo
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_unirse_equipo'])) {
    $idEquipoUnirse = filter_input(INPUT_POST, 'id_equipo', FILTER_VALIDATE_INT);

    if (!$idParticipanteActual) {
        $mensajeError = "No se encontró un perfil de participante asociado a tu usuario. Contacta a un administrador.";
    } elseif ($idEquipoUnirse) {
        try {
            $stmtCheck = $pdo->prepare("SELECT 1 FROM INTEGRANTES_EQUIPO WHERE id_equipo = :id_equipo AND id_participante = :id_participante");
            $stmtCheck->execute([
                ':id_equipo'       => $idEquipoUnirse,
                ':id_participante' => $idParticipanteActual
            ]);

            if ($stmtCheck->fetch()) {
                $mensajeError = "Ya eres integrante de este equipo.";
            } else {
                $stmtUnir = $pdo->prepare("INSERT INTO INTEGRANTES_EQUIPO (id_equipo, id_participante) VALUES (:id_equipo, :id_participante)");
                $stmtUnir->execute([
                    ':id_equipo'       => $idEquipoUnirse,
                    ':id_participante' => $idParticipanteActual
                ]);
                $mensajeExito = "¡Te has unido al equipo correctamente!";
            }
        } catch (PDOException $e) {
            $mensajeError = "Error al intentar unirse al equipo: " . $e->getMessage();
        }
    }
}

// --------------------------------------------------------------------------
// PROCESAR: Abandonar un Equipo
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_abandonar_equipo'])) {
    $idEquipoAbandonar = filter_input(INPUT_POST, 'id_equipo', FILTER_VALIDATE_INT);

    if (!$idParticipanteActual) {
        $mensajeError = "No se encontró un perfil de participante asociado a tu usuario.";
    } elseif ($idEquipoAbandonar) {
        try {
            $stmtSalida = $pdo->prepare("DELETE FROM INTEGRANTES_EQUIPO WHERE id_equipo = :id_equipo AND id_participante = :id_participante");
            $stmtSalida->execute([
                ':id_equipo'       => $idEquipoAbandonar,
                ':id_participante' => $idParticipanteActual
            ]);

            if ($stmtSalida->rowCount() > 0) {
                $mensajeExito = "Has abandonado el equipo correctamente.";
            } else {
                $mensajeError = "No perteneces a este equipo o ya te habías retirado.";
            }
        } catch (PDOException $e) {
            $mensajeError = "Error al intentar salir del equipo: " . $e->getMessage();
        }
    }
}

// --------------------------------------------------------------------------
// CONSULTA: Obtener equipos e integrantes
// --------------------------------------------------------------------------
$equipos = [];
try {
    $sqlEquipos = "SELECT e.id_equipo, e.nombre_equipo, u.username AS creador
                   FROM EQUIPOS e
                   INNER JOIN USUARIOS u ON e.id_creador = u.id_usuario
                   ORDER BY e.id_equipo DESC";
    $stmtEq = $pdo->query($sqlEquipos);
    $equipos = $stmtEq->fetchAll(PDO::FETCH_ASSOC);

    foreach ($equipos as &$eq) {
        $stmtInt = $pdo->prepare("SELECT p.id_participante, p.nombre, p.apellido, p.ci 
                                  FROM INTEGRANTES_EQUIPO ie
                                  INNER JOIN PARTICIPANTES p ON ie.id_participante = p.id_participante
                                  WHERE ie.id_equipo = :id_equipo");
        $stmtInt->execute([':id_equipo' => $eq['id_equipo']]);
        $eq['integrantes'] = $stmtInt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $mensajeError = "No se pudieron cargar los equipos: " . $e->getMessage();
}

// Participantes disponibles para el select de Admin
$participantesDisponibles = [];
if ($esAdmin) {
    try {
        $stmtPart = $pdo->query("SELECT id_participante, nombre, apellido, ci FROM PARTICIPANTES ORDER BY apellido ASC");
        $participantesDisponibles = $stmtPart->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $participantesDisponibles = [];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Gestión de Equipos</title>
    <link rel="icon" type="image/png" href="../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../css/inicio.css">
    <link rel="stylesheet" href="../css/equipo.css">
</head>
<body>

    <!-- Menú Lateral -->
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
            <?php if ($esAdmin): ?>
                <a href="formularioTorneo.php" class="sidebar-link">Crea tu torneo</a>
                <a href="dashboard.php" class="sidebar-link">Panel Administrador</a>
            <?php endif; ?>
            <a href="equipo.php" class="sidebar-link active">Equipos</a>
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

    <!-- Navbar Principal -->
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
                    <a href="logica/login.php" class="profile-menu-item">
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
                    <a href="logica/logout.php" class="profile-menu-item logout-item">
                        <svg class="avatar-svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                            <path d="M377.9 105.9L468.1 196c11.1 11.1 11.1 29.1 0 40.2l-90.1 90.1c-11.5 11.5-30.1 11.5-41.6 0s-11.5-30.1 0-41.6l39.3-39.3L160 245.4c-16.3 0-29.4-13.2-29.4-29.4s13.2-29.4 29.4-29.4l215.7 0-39.3-39.3c-11.5-11.5-11.5-30.１ 0-4１．６s30．１-１１．５ ４１．６ ０zM１２０ ９６c０-１３．３-１０．７-２４-２４-２４C４３ ７２ ０ １１５ ０ １６８L０ ３４４c０ ５３ ４３ ９６ ９６ ９６c１３．３ ０ ２４-１０．７ ２４-２４s-１０．７-２４-２４-２４c-２６．５ ０-４８-２１．５-４８-４８l０-１７６c０-２６．５ ２１．５-４８ ４８-４８c１３．３ ０ ２４-１０．７ ２４-２４z"/>
                        </svg> Cierre de sesión
                    </a>
                </nav>
            </div>
        </div>
    </nav>

    <!-- Contenido Principal -->
    <main class="main-container">

        <header class="cabecera-equipo">
            <h2>Gestión de Equipos</h2>
        </header>

        <?php if (!empty($mensajeExito)): ?>
            <div class="alerta-equipo alerta-exito"><?php echo $mensajeExito; ?></div>
        <?php endif; ?>
        <?php if (!empty($mensajeError)): ?>
            <div class="alerta-equipo alerta-error"><?php echo $mensajeError; ?></div>
        <?php endif; ?>

        <!-- Panel de Creación exclusivo para Administradores -->
        <?php if ($esAdmin): ?>
            <section class="tarjeta-equipo seccion-creacion">
                <div class="encabezado-tarjeta">
                    <h3>Registrar Nuevo Equipo</h3>
                    <span class="badge-admin">Acceso Administrador</span>
                </div>
                <form action="equipo.php" method="POST" class="form-equipo-admin">
                    <input type="hidden" name="accion_crear_equipo" value="1">
                    
                    <div class="grid-form-equipo">
                        <div class="grupo-campo">
                            <label for="nombre_equipo" class="label-gold">Nombre del Equipo *</label>
                            <input type="text" id="nombre_equipo" name="nombre_equipo" class="input-dark" placeholder="Ej. Real Montevideo" required>
                        </div>
                        <div class="grupo-campo">
                            <label for="id_participante_inicial" class="label-gold">Agregar Primer Integrante (Opcional)</label>
                            <select id="id_participante_inicial" name="id_participante_inicial" class="input-dark">
                                <option value="">-- Seleccionar Participante --</option>
                                <?php foreach ($participantesDisponibles as $p): ?>
                                    <option value="<?php echo $p['id_participante']; ?>">
                                        <?php echo htmlspecialchars($p['nombre'] . ' ' . $p['apellido'] . ' (CI: ' . $p['ci'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn-gold-action">Crear Equipo</button>
                </form>
            </section>
        <?php endif; ?>

        <!-- Listado de Equipos Registrados -->
        <section class="tarjeta-equipo">
            <div class="encabezado-tarjeta">
                <h3>Equipos Registrados</h3>
            </div>

            <?php if (empty($equipos)): ?>
                <p class="texto-vacio">No hay equipos registrados actualmente en el sistema.</p>
            <?php else: ?>
                <div class="grid-equipos">
                    <?php foreach ($equipos as $eq): 
                        $esMiembro = false;
                        if ($idParticipanteActual) {
                            foreach ($eq['integrantes'] as $integ) {
                                if ($integ['id_participante'] == $idParticipanteActual) {
                                    $esMiembro = true;
                                    break;
                                }
                            }
                        }
                    ?>
                        <div class="card-equipo-item">
                            <div>
                                <div class="card-equipo-header">
                                    <span class="card-equipo-titulo"><?php echo htmlspecialchars($eq['nombre_equipo']); ?></span>
                                    <span class="card-equipo-creador">Creado por: @<?php echo htmlspecialchars($eq['creador']); ?></span>
                                </div>
                                <div class="card-equipo-body">
                                    <h4>Integrantes (<?php echo count($eq['integrantes']); ?>):</h4>
                                    <?php if (!empty($eq['integrantes'])): ?>
                                        <ul class="lista-integrantes">
                                            <?php foreach ($eq['integrantes'] as $integ): ?>
                                                <li><?php echo htmlspecialchars($integ['nombre'] . ' ' . $integ['apellido']); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="sin-integrantes">Sin integrantes asignados aún.</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Pie de tarjeta: Alterna entre Unirse o Abandonar -->
                            <div class="card-equipo-footer">
                                <?php if ($esMiembro): ?>
                                    <form action="equipo.php" method="POST" style="margin: 0; width: 100%;" onsubmit="return confirm('¿Estás seguro de que deseas abandonar el equipo <?php echo htmlspecialchars($eq['nombre_equipo']); ?>?');">
                                        <input type="hidden" name="accion_abandonar_equipo" value="1">
                                        <input type="hidden" name="id_equipo" value="<?php echo $eq['id_equipo']; ?>">
                                        <button type="submit" class="btn-abandonar">Abandonar equipo</button>
                                    </form>
                                <?php else: ?>
                                    <form action="equipo.php" method="POST" style="margin: 0; width: 100%;">
                                        <input type="hidden" name="accion_unirse_equipo" value="1">
                                        <input type="hidden" name="id_equipo" value="<?php echo $eq['id_equipo']; ?>">
                                        <button type="submit" class="btn-unirse">Unirme al equipo</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
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