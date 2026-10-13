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
    $error = 'El correo electrónico debe ser una cuenta válida finalizada en @gmail.com.';
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

    // --- VALIDACIONES DE SERVIDOR ---
    if (empty($username) || empty($correo) || empty($contrasena) || empty($confirmar_contrasena) || empty($nombre) || empty($apellido) || empty($ci) || empty($telefono)) {
        $error = 'Por favor, completá todos los campos obligatorios.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{1,15}$/u', $nombre)) {
        $error = 'El nombre no puede tener números, caracteres especiales ni superar los 15 caracteres.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{1,15}$/u', $apellido)) {
        $error = 'El apellido no puede tener números, caracteres especiales ni superar los 15 caracteres.';
    } elseif (!preg_match('/^\d{8}$/', $ci)) {
        $error = 'La cédula de identidad debe tener exactamente 8 números.';
    } elseif (!preg_match('/^\d{9}$/', $telefono)) {
        $error = 'El número de celular debe tener exactamente 9 dígitos.';
    } elseif (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $username)) {
        $error = 'El nombre de usuario no permite caracteres especiales y debe tener máximo 20 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/i', $correo)) {
        $error = 'El correo electrónico debe finalizar obligatoriamente en @gmail.com.';
    } elseif ($contrasena !== $confirmar_contrasena) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $contrasena)) {
        $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.';
    }

    if (empty($error)) {
        try {
            // Verificar duplicados en USUARIOS (email/username) y PARTICIPANTES (ci)
            $stmt_check = $pdo->prepare("
                SELECT u.id_usuario 
                FROM usuarios u 
                LEFT JOIN participantes p ON u.id_usuario = p.id_usuario 
                WHERE u.email = ? OR u.username = ? OR p.ci = ?
            ");
            $stmt_check->execute([$correo, $username, $ci]);

            if ($stmt_check->fetch()) {
                $error = 'El correo electrónico, nombre de usuario o CI ya se encuentra registrado.';
            } else {
                // Encriptar contraseña[cite: 11]
                $hash_contrasena = password_hash($contrasena, PASSWORD_BCRYPT);

                // INICIO DE LA TRANSACCIÓN
                $pdo->beginTransaction();

                // A) Insertar en USUARIOS[cite: 10, 11]
                $stmt_user = $pdo->prepare("INSERT INTO usuarios (username, email, password_hash, id_rol) VALUES (?, ?, ?, 3)");
                $stmt_user->execute([$username, $correo, $hash_contrasena]);
                
                $id_usuario = $pdo->lastInsertId();

                // B) Insertar en PARTICIPANTES[cite: 10]
                $stmt_part = $pdo->prepare("INSERT INTO participantes (nombre, apellido, ci, telefono, id_usuario) VALUES (?, ?, ?, ?, ?)");
                $stmt_part->execute([$nombre, $apellido, $ci, $telefono, $id_usuario]);

                // CONFIRMAR TRANSACCIÓN
                $pdo->commit();

                header('Location: login.php?registrado=1');
                exit;
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'Error al registrar la cuenta: ' . $e->getMessage();
        }
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
                    <input type="text" id="nombre" name="nombre" placeholder="Ej: Juan" maxlength="15" required>
                </div>

                <div class="grupo-formulario">
                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" placeholder="Ej: Pérez" maxlength="15" required>
                </div>

                <div class="grupo-formulario">
                    <label for="ci">Cédula de Identidad (8 dígitos)</label>
                    <input type="text" id="ci" name="ci" placeholder="Ej: 12345678" maxlength="8" minlength="8" required>
                </div>

                <div class="grupo-formulario">
                    <label for="telefono">Teléfono / Celular (9 dígitos)</label>
                    <input type="tel" id="telefono" name="telefono" placeholder="Ej: 099123456" maxlength="9" minlength="9" required>
                </div>

                <!-- Datos de Cuenta (USUARIOS) -->
                <div class="grupo-formulario">
                    <label for="username">Nombre de usuario</label>
                    <input type="text" id="username" name="username" placeholder="Ej: juanperez99" maxlength="20" required>
                </div>

                <div class="grupo-formulario">
                    <label for="correo">Correo electrónico (@gmail.com)</label>
                    <input type="email" id="correo" name="correo" placeholder="ejemplo@gmail.com" pattern="[a-zA-Z0-9._%+-]+@gmail\.com$" required>
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
    document.addEventListener('DOMContentLoaded', () => {
        const inputNombre = document.getElementById('nombre');
        const inputApellido = document.getElementById('apellido');
        const inputCi = document.getElementById('ci');
        const inputTelefono = document.getElementById('telefono');
        const inputUsername = document.getElementById('username');
        const inputCorreo = document.getElementById('correo');

        // 1. Nombre y Apellido: solo letras y espacios (máximo 15 caracteres)
        [inputNombre, inputApellido].forEach(input => {
            if (input) {
                input.addEventListener('input', (e) => {
                    e.target.value = e.target.value.replace(/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]/g, '');
                });
            }
        });

        // 2. CI: solo números y exactamente 8 dígitos
        if (inputCi) {
            inputCi.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }

        // 3. Celular: solo números y exactamente 9 dígitos
        if (inputTelefono) {
            inputTelefono.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/\D/g, '');
            });
        }

        // 4. Username: solo letras y números, sin caracteres especiales (máximo 20 caracteres)
        if (inputUsername) {
            inputUsername.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^a-zA-Z0-9]/g, '');
            });
        }

        // 5. Validaciones previas al envío
        document.querySelector('.formulario-registro').addEventListener('submit', function(event) {
            const contrasena = document.getElementById('contrasena').value;
            const confirmar = document.getElementById('confirmar_contrasena').value;

            if (inputCi.value.length !== 8) {
                event.preventDefault();
                alert('La Cédula de Identidad debe tener exactamente 8 números.');
                return;
            }

            if (inputTelefono.value.length !== 9) {
                event.preventDefault();
                alert('El número de celular debe tener exactamente 9 dígitos.');
                return;
            }

            if (!inputCorreo.value.toLowerCase().endsWith('@gmail.com')) {
                event.preventDefault();
                alert('El correo electrónico debe ser obligatoriamente una dirección @gmail.com');
                return;
            }

            if (contrasena !== confirmar) {
                event.preventDefault();
                alert('Las contraseñas no coinciden.');
            }
        });
    });
    </script>
</body>
</html>