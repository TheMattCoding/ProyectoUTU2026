<?php
session_start();

// Si ya tiene sesión iniciada (no es visitante), redirigir
if (isset($_SESSION['usuario'])) {
    header('Location: ../inicio.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'] ?? '';
    $contrasena = $_POST['contrasena'] ?? '';

    // --- BASE DE DATOS FICTICIA DE PRUEBA ---
    $usuarios_db = [
        'admin@gmail.com' => ['pass' => '123456', 'rol' => 'administrador'],
        'orga@gmail.com'  => ['pass' => '123456', 'rol' => 'organizador'],
        'user@gmail.com'  => ['pass' => '123456', 'rol' => 'usuario']
    ];

    if (isset($usuarios_db[$correo]) && $usuarios_db[$correo]['pass'] === $contrasena) {
        // Guardar correo y rol en la sesión
        $_SESSION['usuario'] = $correo;
        $_SESSION['rol']     = $usuarios_db[$correo]['rol'];

        header('Location: ../inicio.php'); 
        exit();
    } else {
        $error = 'Correo o contraseña incorrectos.';
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
            <p style="color: #ff4d4d; text-align: center; margin-bottom: 15px; font-weight: bold;"><?php echo $error; ?></p>
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