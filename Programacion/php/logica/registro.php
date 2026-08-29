<?php 
session_start(); 
 
// Errores recibidos por GET 
$error = ''; 
 
if (isset($_GET['campos'])) { 
    $error = 'Por favor, completá todos los campos.'; 
} elseif (isset($_GET['password'])) { 
    $error = 'Las contraseñas no coinciden.'; 
} elseif (isset($_GET['correo'])) { 
    $error = 'El correo electrónico no es válido.'; 
} elseif (isset($_GET['existente'])) { 
    $error = 'El correo electrónico ya está registrado.'; 
} elseif (isset($_GET['segura'])) { 
    $error = 'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.'; 
} 
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>SGDM - Registro</title>
    <link rel="icon" type="image/png" href="../../img/logoapp2.jpeg">
    <link rel="stylesheet" href="../../css/registro.css">
</head>
<body>
    <!--==============1. CONTENEDOR PRINCIPAL ======================-->
    <main class="pagina-registro">
        <!--==============2. TARJETA DE REGISTRO ======================-->
        <section class="tarjeta-registro">
            <!--==============3. CONTENEDOR Y PROPORCIONES DEL LOGO ======================-->
            <div class="contenedor-logo-registro">
                <img
                    src="../../img/logoapp2.jpeg"
                    alt="Logo SGDM"
                    class="logo-registro"
                >
            </div>
            <!--==============4. ENCABEZADO ======================-->
            <div class="encabezado-registro">
                <h1>Crear una cuenta</h1>
                <p>
                    Registrate para comenzar a utilizar SGDM
                </p>
            </div>
            <!--==============5. MENSAJE DE ERROR ======================-->
            <?php if ($error !== ''): ?>
                <div class="mensaje-registro error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            <!--==============6. FORMULARIO DE REGISTRO ======================-->
            <form
                action="registro.php"
                method="POST"
                class="formulario-registro"
            >
                <!--==============7. GRUPOS DEL FORMULARIO ======================-->
                <div class="grupo-formulario">
                    <label for="nombre">
                        Nombre de usuario
                    </label>
                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        placeholder="Ingresá tu nombre de usuario"
                        required
                    >
                </div>
                <div class="grupo-formulario">
                    <label for="correo">
                        Correo electrónico
                    </label>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="Ingresá tu correo electrónico"
                        required
                    >
                </div>
                <div class="grupo-formulario">
                    <label for="contrasena">
                        Contraseña
                    </label>
                    <input
                        type="password"
                        id="contrasena"
                        name="contrasena"
                        placeholder="Mín. 8 caracteres, mayúscula, número y símbolo"
                        minlength="8"
                        pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                        title="La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula, un número y un símbolo."
                        required
                    >
                </div>
                <!--==============10. INPUTS AL SELECCIONAR ======================-->
                <div class="grupo-formulario">
                    <label for="confirmar_contrasena">
                        Confirmar contraseña
                    </label>
                    <input
                        type="password"
                        id="confirmar_contrasena"
                        name="confirmar_contrasena"
                        placeholder="Repetí tu contraseña"
                        required
                    >
                </div>
                <!--==============11. ROL DEL USUARIO ======================-->
                <input
                    type="hidden"
                    name="rol"
                    value="usuario"
                >
                <!--==============12. BOTÓN PRINCIPAL DE REGISTRO ======================-->
                <button
                    type="submit"
                    name="registrarse"
                    class="boton-registro"
                >
                    Registrarse
                </button>
            </form>
            <!--==============13. ENLACE PARA INICIAR SESIÓN ======================-->
            <div class="enlace-inicio-sesion">
                <span>
                    ¿Ya tenés una cuenta?
                </span>
                <a href="login.php">
                    Iniciar sesión
                </a>
            </div>
            <!--==============14. VOLVER AL INICIO ======================-->
            <div class="contenedor-volver">
                <a href="../inicio.php">
                    Volver al inicio
                </a>
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