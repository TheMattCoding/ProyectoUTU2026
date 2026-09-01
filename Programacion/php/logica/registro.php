<?php
session_start();
require_once '../db.php'; // Carga la conexión $pdo[cite: 11]

$error = '';

// 1. Manejo de mensajes de error desde URL[cite: 11]
if (isset($_GET['campos'])) {
    $error = 'Por favor, completá todos los campos obligatorios.';
} elseif (isset($_GET['password'])) {
    $error = 'Las contraseñas no coinciden.';
} elseif (isset($_GET['correo'])) {
    $error = 'El correo electrónico no es válido.';
} elseif (isset($_GET['existente'])) {
    $error = 'El correo electrónico, nombre de usuario o CI ya se encuentra registrado.';
} elseif (isset($_GET['segura'])) {
    $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.';
}

// 2. Procesamiento del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registrarse'])) {
    // Datos de la cuenta (USUARIOS)
    $username = trim($_POST['username'] ?? '');
    $correo = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $contrasena = $_POST['contrasena'] ?? '';
    $confirmar_contrasena = $_POST['confirmar_contrasena'] ?? '';

    // Datos del participante (PARTICIPANTES)
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $ci = trim($_POST['ci'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validar campos requeridos[cite: 11]
    if (empty($username) || empty($correo) || empty($contrasena) || empty($confirmar_contrasena) || empty($nombre) || empty($apellido) || empty($ci)) {
        header('Location: registro.php?campos=1');
        exit;
    }

    // Validar formato de correo[cite: 11]
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        header('Location: registro.php?correo=1');
        exit;
    }

    // Validar coincidencia de contraseñas[cite: 11]
    if ($contrasena !== $confirmar_contrasena) {
        header('Location: registro.php?password=1');
        exit;
    }

    // Validar complejidad de contraseña[cite: 11]
    $patron = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';
    if (!preg_match($patron, $contrasena)) {
        header('Location: registro.php?segura=1');
        exit;
    }

    try {
        // Verificar duplicados en USUARIOS (email/username) y PARTICIPANTES (ci)
        $stmt_check = $pdo->prepare("
            SELECT u.id_usuario 
            FROM USUARIOS u 
            LEFT JOIN PARTICIPANTES p ON u.id_usuario = p.id_usuario 
            WHERE u.email = ? OR u.username = ? OR p.ci = ?
        ");
        $stmt_check->execute([$correo, $username, $ci]);

        if ($stmt_check->fetch()) {
            header('Location: registro.php?existente=1');
            exit;
        }

        // Encriptar contraseña[cite: 11]
        $hash_contrasena = password_hash($contrasena, PASSWORD_BCRYPT);

        // INICIO DE LA TRANSACCIÓN
        $pdo->beginTransaction();

        // A) Insertar en USUARIOS (id_rol = 3 correspondiente a usuario estándar)[cite: 10, 11]
        $stmt_user = $pdo->prepare("INSERT INTO USUARIOS (username, email, password_hash, id_rol) VALUES (?, ?, ?, 3)");
        $stmt_user->execute([$username, $correo, $hash_contrasena]);
        
        $id_usuario = $pdo->lastInsertId();

        // B) Insertar en PARTICIPANTES[cite: 10]
        $stmt_part = $pdo->prepare("INSERT INTO PARTICIPANTES (nombre, apellido, ci, telefono, id_usuario) VALUES (?, ?, ?, ?, ?)");
        $stmt_part->execute([$nombre, $apellido, $ci, $telefono, $id_usuario]);

        // CONFIRMAR TRANSACCIÓN
        $pdo->commit();

        header('Location: login.php?registrado=1');
        exit;

    } catch (Exception $e) {
        // Si ocurre algún fallo, se revierten todos los cambios en la BD
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'Error al registrar la cuenta: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SGDM - Registro de Usuario</title>
    <link rel="icon" type="image/png" href="../../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../../css/registro.css">
</head>
<body>
    <main class="pagina-registro">
        <section class="tarjeta-registro">
            <div class="contenedor-logo-registro">
                <img src="../../img/logoapp2.jpeg" alt="Logo SGDM" class="logo-registro">
            </div>
            
            <div class="encabezado-registro">
                <h1>Crear una cuenta</h1>
                <p>Ingresá tus datos personales y de acceso</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="mensaje-registro error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="registro.php" method="POST" class="formulario-registro">
                
                <!-- Datos Personales (PARTICIPANTES) -->
                <div class="grupo-formulario">
                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" placeholder="Ej: Juan" required>
                </div>

                <div class="grupo-formulario">
                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" placeholder="Ej: Pérez" required>
                </div>

                <div class="grupo-formulario">
                    <label for="ci">Cédula de Identidad (CI)</label>
                    <input type="text" id="ci" name="ci" placeholder="Ej: 12345678" required>
                </div>

                <div class="grupo-formulario">
                    <label for="telefono">Teléfono / Celular</label>
                    <input type="tel" id="telefono" name="telefono" placeholder="Ej: 099123456">
                </div>

                <!-- Datos de Cuenta (USUARIOS) -->
                <div class="grupo-formulario">
                    <label for="username">Nombre de usuario</label>
                    <input type="text" id="username" name="username" placeholder="Ej: juanperez99" required>
                </div>

                <div class="grupo-formulario">
                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" placeholder="ejemplo@correo.com" required>
                </div>

                <div class="grupo-formulario">
                    <label for="contrasena">Contraseña</label>
                    <input 
                        type="password" 
                        id="contrasena" 
                        name="contrasena" 
                        placeholder="Mín. 8 caracteres, mayúscula, número y símbolo"
                        minlength="8"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                        required
                    >
                </div>

                <div class="grupo-formulario">
                    <label for="confirmar_contrasena">Confirmar contraseña</label>
                    <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Repetí tu contraseña" required>
                </div>

                <button type="submit" name="registrarse" class="boton-registro">
                    Registrarse
                </button>
            </form>

            <div class="enlace-inicio-sesion">
                <span>¿Ya tenés una cuenta?</span>
                <a href="login.php">Iniciar sesión</a>
            </div>

            <div class="contenedor-volver">
                <a href="../inicio.php">Volver al inicio</a>
            </div>
        </section>
    </main>

    <script>
    document.querySelector('.formulario-registro').addEventListener('submit', function(event) {
        const contrasena = document.getElementById('contrasena').value;
        const confirmar = document.getElementById('confirmar_contrasena').value;
        if (contrasena !== confirmar) {
            event.preventDefault();
            alert('Las contraseñas no coinciden.');
        }
    });
    </script>
</body>
</html>