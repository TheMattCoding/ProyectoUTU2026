<?php
session_start();
require_once 'auth.php';
require_once '../db.php';

$conexion = $pdo;

requerirLogin();

$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;
if (!$idUsuario) {
    header("Location: ../login.php");
    exit();
}

$accion = $_POST['accion'] ?? '';

switch ($accion) {

    case 'actualizar_perfil':
        $nuevoUsername = trim($_POST['nombre_usuario'] ?? '');
        $nuevoEmail    = trim($_POST['correo'] ?? '');
        $nuevoNombre   = trim($_POST['nombre'] ?? '');
        $nuevoApellido = trim($_POST['apellido'] ?? '');
        $nuevoTelefono = trim($_POST['telefono'] ?? '');

        if (empty($nuevoUsername) || empty($nuevoEmail) || empty($nuevoNombre) || empty($nuevoApellido) || empty($nuevoTelefono)) {
            $_SESSION['mensaje_error'] = "Todos los campos son obligatorios.";
            break;
        }

        try {
            // 1. Verificar duplicados de username o email
            $stmtCheck = $conexion->prepare("
                SELECT id_usuario FROM usuarios 
                WHERE (username = :user OR email = :email) AND id_usuario != :id
            ");
            $stmtCheck->execute([
                ':user'  => $nuevoUsername,
                ':email' => $nuevoEmail,
                ':id'    => $idUsuario
            ]);

            if ($stmtCheck->rowCount() > 0) {
                $_SESSION['mensaje_error'] = "El nombre de usuario o correo ya está en uso.";
            } else {
                // 2. Actualizar tabla usuarios
                $stmtUpdateUser = $conexion->prepare("
                    UPDATE usuarios 
                    SET username = :user, email = :email 
                    WHERE id_usuario = :id
                ");
                $stmtUpdateUser->execute([
                    ':user'  => $nuevoUsername,
                    ':email' => $nuevoEmail,
                    ':id'    => $idUsuario
                ]);

                // 3. Insertar o actualizar tabla participantes
                $stmtCheckPart = $conexion->prepare("SELECT id_usuario FROM participantes WHERE id_usuario = :id");
                $stmtCheckPart->execute([':id' => $idUsuario]);

                if ($stmtCheckPart->rowCount() > 0) {
                    $stmtUpdatePart = $conexion->prepare("
                        UPDATE participantes 
                        SET nombre = :nombre, apellido = :apellido, telefono = :telefono 
                        WHERE id_usuario = :id
                    ");
                    $stmtUpdatePart->execute([
                        ':nombre'   => $nuevoNombre,
                        ':apellido' => $nuevoApellido,
                        ':telefono' => $nuevoTelefono,
                        ':id'       => $idUsuario
                    ]);
                } else {
                    $stmtInsertPart = $conexion->prepare("
                        INSERT INTO participantes (id_usuario, nombre, apellido, telefono) 
                        VALUES (:id, :nombre, :apellido, :telefono)
                    ");
                    $stmtInsertPart->execute([
                        ':id'       => $idUsuario,
                        ':nombre'   => $nuevoNombre,
                        ':apellido' => $nuevoApellido,
                        ':telefono' => $nuevoTelefono
                    ]);
                }

                $_SESSION['usuario'] = $nuevoUsername;
                $_SESSION['correo']  = $nuevoEmail;
                $_SESSION['nombre']  = $nuevoNombre;
                $_SESSION['apellido'] = $nuevoApellido;
                $_SESSION['telefono'] = $nuevoTelefono;
                $_SESSION['mensaje_exito'] = "Perfil actualizado correctamente.";
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_error'] = "Error al actualizar la base de datos.";
        }
        break;

    case 'cambiar_password':
        $passActual    = $_POST['contrasena_actual'] ?? '';
        $passNueva     = $_POST['nueva_contrasena'] ?? '';
        $passConfirmar = $_POST['confirmar_contrasena'] ?? '';

        if (empty($passActual) || empty($passNueva) || empty($passConfirmar)) {
            $_SESSION['mensaje_error'] = "Completa todos los campos de contraseña.";
            break;
        }

        if ($passNueva !== $passConfirmar) {
            $_SESSION['mensaje_error'] = "Las nuevas contraseñas no coinciden.";
            break;
        }

        try {
            $stmt = $conexion->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = :id");
            $stmt->execute([':id' => $idUsuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $passwordValida = password_verify($passActual, $user['password_hash']) || ($passActual === $user['password_hash']);

            if (!$passwordValida) {
                $_SESSION['mensaje_error'] = "La contraseña actual es incorrecta.";
            } else {
                $nuevoHash = password_hash($passNueva, PASSWORD_BCRYPT);
                $stmtPass = $conexion->prepare("UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id");
                $stmtPass->execute([':hash' => $nuevoHash, ':id' => $idUsuario]);

                $_SESSION['mensaje_exito'] = "Contraseña actualizada exitosamente.";
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_error'] = "Error de servidor al cambiar la contraseña.";
        }
        break;

    case 'borrar_cuenta':
        $passConfirmar = $_POST['contrasena_borrado'] ?? '';

        try {
            $stmt = $conexion->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = :id");
            $stmt->execute([':id' => $idUsuario]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $passwordValida = password_verify($passConfirmar, $user['password_hash']) || ($passConfirmar === $user['password_hash']);

            if (!$passwordValida) {
                $_SESSION['mensaje_error'] = "La contraseña es incorrecta. No se eliminó la cuenta.";
            } else {
                $stmtDelete = $conexion->prepare("DELETE FROM usuarios WHERE id_usuario = :id");
                $stmtDelete->execute([':id' => $idUsuario]);

                session_destroy();
                header("Location: ../login.php?msg=cuenta_eliminada");
                exit();
            }
        } catch (PDOException $e) {
            $_SESSION['mensaje_error'] = "No se pudo eliminar la cuenta debido a restricciones del sistema.";
        }
        break;

    default:
        $_SESSION['mensaje_exito'] = "Preferencias guardadas.";
        break;
}

header("Location: ../configuracion.php");
exit();