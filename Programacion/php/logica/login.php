<?php
session_start();
require_once '../db.php';

if (isset($_SESSION['usuario'])) {
    header('Location: ../inicio.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = trim($_POST['contrasena'] ?? '');

    if (!empty($correo) && !empty($contrasena)) {
        try {
            // Consulta relacionando la tabla USUARIOS con ROLES
            $sql = "SELECT u.*, r.nombre_rol AS nombre_rol 
                    FROM usuarios u
                    LEFT JOIN roles r ON u.id_rol = r.id_rol
                    WHERE u.email = :correo LIMIT 1";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([':correo' => $correo]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            // Verifica la contraseña (soporta texto plano o hash seguro)
            if ($usuario && ($contrasena === $usuario['password_hash'] || password_verify($contrasena, $usuario['password_hash']))) {
                
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['usuario']    = $usuario['username'];
                $_SESSION['correo']     = $usuario['email'];
                // Guarda el nombre del rol (o 'usuario' si no se encuentra relación)
                $_SESSION['rol']        = $usuario['nombre_rol'] ?? 'usuario';

                header('Location: ../inicio.php'); 
                exit();
            } else {
                $error = 'Correo o contraseña incorrectos.';
            }
        } catch (PDOException $e) {
            $error = 'Error al verificar credenciales en la base de datos.';
        }
    } else {
        $error = 'Por favor completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - SGDM</title>
    <link rel="icon" type="image/png" href="../../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../../css/login.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="contenedor-login">
        <div class="contenedor-logo">
            <img src="../../img/logoapp2.jpeg" alt="Logo SGDM" class="imagen-logo">
        </div>

        <h1 class="saludo">¡Hola! Que gusto verte de nuevo</h1>

        <?php if (!empty($error)): ?>
            <p style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>

        <form class="formulario-login" action="" method="POST">
            <div class="grupo-entrada">
                <input type="email" id="correo" name="correo" required placeholder="Correo Electrónico">
            </div>
            
            <div class="grupo-entrada">
                <input type="password" id="contrasena" name="contrasena" required placeholder="Contraseña">
            </div>

            <div class="contrasena-olvidada">
                <a href="#">Olvidaste tu contraseña</a>
            </div>

            <button type="submit" class="btn-principal">INICIAR SESIÓN</button>
        </form>

        <div class="divisor">
            <span>o</span>
        </div>

        <p class="texto-social">Continuar con</p>

        <div class="botones-sociales">
            <button type="button" class="btn-red-social apple"><i class="fab fa-apple"></i> Apple</button>
            <button type="button" class="btn-red-social google"><i class="fab fa-google"></i> Google</button>
            <button type="button" class="btn-red-social facebook"><i class="fab fa-facebook-f"></i> Facebook</button>
        </div>

        <div class="enlace-registro">
            ¿No tienes una cuenta? 
            <a href="registro.php">Registrar</a>
        </div>
    </div>
</body>
</html>