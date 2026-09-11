<?php
require_once 'auth.php';
require_once '../db.php';

requerirLogin();

$idUsuario = $_SESSION['id_usuario'] ?? $_SESSION['id'] ?? null;
$accion = $_POST['accion'] ?? '';

if (isset($_POST['pestana_activa'])) {
    $_SESSION['pestana_activa'] = $_POST['pestana_activa'];
}
if (isset($_POST['ultimo_input_id'])) {
    $_SESSION['ultimo_input_id'] = $_POST['ultimo_input_id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ---------------------------------------------------------
    // 1. ACTUALIZAR PERFIL
    // ---------------------------------------------------------
    if ($accion === 'actualizar_perfil') {
        $username = trim($_POST['nombre_usuario'] ?? '');
        $correo   = filter_var(trim($_POST['correo'] ?? ''), FILTER_SANITIZE_EMAIL);
        $nombre   = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');

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
                $stmtCheck = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE (username = ? OR email = ?) AND id_usuario != ?");
                $stmtCheck->execute([$username, $correo, $idUsuario]);

                if ($stmtCheck->fetch()) {
                    $_SESSION['mensaje_error'] = 'El nombre de usuario o correo ya se encuentra registrado por otro usuario.';
                } else {
                    $rutaFotoDB = null;
                    if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                        $fileTmpPath   = $_FILES['foto_perfil']['tmp_name'];
                        $fileName      = $_FILES['foto_perfil']['name'];
                        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

                        if (in_array($fileExtension, $extensionesPermitidas)) {
                            $dirDestino = '../../img/perfiles/';
                            if (!file_exists($dirDestino)) {
                                mkdir($dirDestino, 0777, true);
                            }

                            $nuevoNombre = 'perfil_' . $idUsuario . '_' . time() . '.' . $fileExtension;
                            $destPath = $dirDestino . $nuevoNombre;

                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                $rutaFotoDB = 'img/perfiles/' . $nuevoNombre;
                            }
                        }
                    }

                    $pdo->beginTransaction();

                    if ($rutaFotoDB) {
                        $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, email = ?, foto_perfil = ? WHERE id_usuario = ?");
                        $stmtUser->execute([$username, $correo, $rutaFotoDB, $idUsuario]);
                        $_SESSION['foto_perfil'] = $rutaFotoDB;
                    } else {
                        $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, email = ? WHERE id_usuario = ?");
                        $stmtUser->execute([$username, $correo, $idUsuario]);
                    }

                    $stmtPart = $pdo->prepare("UPDATE participantes SET nombre = ?, apellido = ?, telefono = ? WHERE id_usuario = ?");
                    $stmtPart->execute([$nombre, $apellido, $telefono, $idUsuario]);

                    $pdo->commit();

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
    }

    // ---------------------------------------------------------
    // 2. CAMBIAR CONTRASEÑA
    // ---------------------------------------------------------
    elseif ($accion === 'cambiar_password') {
        $contrasenaActual    = $_POST['contrasena_actual'] ?? '';
        $nuevaContrasena     = $_POST['nueva_contrasena'] ?? '';
        $confirmarContrasena = $_POST['confirmar_contrasena'] ?? '';

        if (empty($contrasenaActual) || empty($nuevaContrasena) || empty($confirmarContrasena)) {
            $_SESSION['mensaje_error'] = 'Por favor, completá todos los campos de contraseña.';
        } elseif (strlen($nuevaContrasena) < 6) {
            $_SESSION['mensaje_error'] = 'La nueva contraseña debe tener al menos 6 caracteres.';
        } elseif ($nuevaContrasena !== $confirmarContrasena) {
            $_SESSION['mensaje_error'] = 'Las nuevas contraseñas no coinciden.';
        } else {
            try {
                // Obtener contraseña guardada en la base de datos
                $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$idUsuario]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($usuario) {
                    $hashGuardado = $usuario['password_hash'];

                    // Verificar contraseña actual (Soporta hash con password_verify o texto plano)
                    if (password_verify($contrasenaActual, $hashGuardado) || $contrasenaActual === $hashGuardado) {
                        
                        $nuevoHash = password_hash($nuevaContrasena, PASSWORD_DEFAULT);

                        $update = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id_usuario = ?");
                        $update->execute([$nuevoHash, $idUsuario]);

                        $_SESSION['mensaje_exito'] = 'Contraseña actualizada correctamente.';
                    } else {
                        $_SESSION['mensaje_error'] = 'La contraseña actual es incorrecta.';
                    }
                } else {
                    $_SESSION['mensaje_error'] = 'Usuario no encontrado.';
                }
            } catch (Exception $e) {
                $_SESSION['mensaje_error'] = 'Error al actualizar la contraseña: ' . $e->getMessage();
            }
        }
    }

    // ---------------------------------------------------------
    // 3. ELIMINAR CUENTA
    // ---------------------------------------------------------
    elseif ($accion === 'borrar_cuenta') {
        $contrasenaBorrado = $_POST['contrasena_borrado'] ?? '';

        if (empty($contrasenaBorrado)) {
            $_SESSION['mensaje_error'] = 'Ingresá tu contraseña para confirmar la eliminación.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT password_hash FROM usuarios WHERE id_usuario = ?");
                $stmt->execute([$idUsuario]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($usuario && (password_verify($contrasenaBorrado, $usuario['password_hash']) || $contrasenaBorrado === $usuario['password_hash'])) {
                    $pdo->beginTransaction();
                    $pdo->prepare("DELETE FROM participantes WHERE id_usuario = ?")->execute([$idUsuario]);
                    $pdo->prepare("DELETE FROM usuarios WHERE id_usuario = ?")->execute([$idUsuario]);
                    $pdo->commit();

                    session_destroy();
                    header('Location: login.php');
                    exit;
                } else {
                    $_SESSION['mensaje_error'] = 'La contraseña es incorrecta.';
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $_SESSION['mensaje_error'] = 'Error al eliminar la cuenta: ' . $e->getMessage();
            }
        }
    }
}

// Redirección por defecto a la vista de configuración
header('Location: ../configuracion.php');
exit;