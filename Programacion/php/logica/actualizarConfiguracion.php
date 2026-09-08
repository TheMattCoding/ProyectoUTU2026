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
                
                // --- PROCESAMIENTO Y GUARDADO DE LA FOTO DE PERFIL ---
                $rutaFotoDB = null;
                if (isset($_FILES['foto_perfil']) && $_FILES['foto_perfil']['error'] === UPLOAD_ERR_OK) {
                    $fileTmpPath   = $_FILES['foto_perfil']['tmp_name'];
                    $fileName      = $_FILES['foto_perfil']['name'];
                    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                    $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];

                    if (in_array($fileExtension, $extensionesPermitidas)) {
                        // Ubicación física de la carpeta destino (2 niveles arriba desde php/logica)
                        $dirDestino = '../../img/perfiles/';
                        if (!file_exists($dirDestino)) {
                            mkdir($dirDestino, 0777, true);
                        }

                        // Generar nombre único para evitar sobreescritura
                        $nuevoNombre = 'perfil_' . $idUsuario . '_' . time() . '.' . $fileExtension;
                        $destPath = $dirDestino . $nuevoNombre;

                        if (move_uploaded_file($fileTmpPath, $destPath)) {
                            // Ruta relativa almacenada en la BD para usarse desde php/
                            $rutaFotoDB = 'img/perfiles/' . $nuevoNombre;
                        }
                    }
                }

                $pdo->beginTransaction();

                // Actualizar tabla usuarios (incluyendo la foto si se subió una nueva)
                if ($rutaFotoDB) {
                    $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, email = ?, foto_perfil = ? WHERE id_usuario = ?");
                    $stmtUser->execute([$username, $correo, $rutaFotoDB, $idUsuario]);
                    $_SESSION['foto_perfil'] = $rutaFotoDB;
                } else {
                    $stmtUser = $pdo->prepare("UPDATE usuarios SET username = ?, email = ? WHERE id_usuario = ?");
                    $stmtUser->execute([$username, $correo, $idUsuario]);
                }

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