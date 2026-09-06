<?php
require_once 'auth.php';
require_once '../db.php';

requerirLogin();

$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;
$accion = $_POST['accion'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $accion === 'actualizar_perfil') {
    $username = trim($_POST['nombre_usuario'] ?? '');
    $correo   = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
    $nombre   = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');

    // Validaciones estrictas del servidor
    if (empty($username) || empty($correo) || empty($nombre) || empty($apellido) || empty($telefono)) {
        $_SESSION['mensaje_error'] = 'Por favor, completá todos los campos obligatorios.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{1,15}$/u', $nombre)) {
        $_SESSION['mensaje_error'] = 'El nombre no puede incluir números ni símbolos y debe tener como máximo 15 caracteres.';
    } elseif (!preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]{1,15}$/u', $apellido)) {
        $_SESSION['mensaje_error'] = 'El apellido no puede incluir números ni símbolos y debe tener como máximo 15 caracteres.';
    } elseif (!preg_match('/^\d{9}$/', $telefono)) {
        $_SESSION['mensaje_error'] = 'El celular debe tener exactamente 9 dígitos numéricos.';
    } elseif (!preg_match('/^[a-zA-Z0-9]{1,20}$/', $username)) {
        $_SESSION['mensaje_error'] = 'El nombre de usuario permite únicamente letras y números, con un máximo de 20 caracteres.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@gmail\.com$/i', $correo)) {
        $_SESSION['mensaje_error'] = 'El correo electrónico debe ser obligatoriamente una dirección @gmail.com.';
    } else {
        try {
            // Verificar si el username o correo ya pertenecen a otro usuario
            $stmtCheck = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE (username = ? OR email = ?) AND id_usuario != ?");
            $stmtCheck->execute([$username, $correo, $idUsuario]);

            if ($stmtCheck->fetch()) {
                $_SESSION['mensaje_error'] = 'El nombre de usuario o correo ya se encuentra registrado por otro usuario.';
            } else {
                $pdo->beginTransaction();

                // Actualizar tabla usuarios
                $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, email = ? WHERE id_usuario = ?");
                $stmtUser->execute([$username, $correo, $idUsuario]);

                // Actualizar tabla participantes
                $stmtPart = $pdo->prepare("UPDATE participantes SET nombre = ?, apellido = ?, telefono = ? WHERE id_usuario = ?");
                $stmtPart->execute([$nombre, $apellido, $telefono, $idUsuario]);

                $pdo->commit();

                // Actualizar datos de sesión
                $_SESSION['usuario'] = $username;
                $_SESSION['correo']  = $correo;
                $_SESSION['nombre']  = $nombre;

                $_SESSION['mensaje_exito'] = 'Perfil actualizado correctamente.';
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['mensaje_error'] = 'Error al actualizar el perfil: ' . $e->getMessage();
        }
    }

    header('Location: ../configuracion.php');
    exit;
}