<?php
// Inicia la sesión si aún no se ha iniciado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no hay un rol definido en la sesión, se define como 'visitante' por defecto
if (!isset($_SESSION['rol'])) {
    $_SESSION['rol'] = 'visitante';
}

/**
 * Obliga a estar logueado (bloquea a visitantes)
 */
function requerirLogin() {
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Permite el acceso solo si el usuario tiene un rol específico
 */
function requerirRol($rolesPermitidos) {
    requerirLogin();
    $roles = (array) $rolesPermitidos;

    if (!in_array($_SESSION['rol'], $roles)) {
        header('Location: ../inicio.php?error=acceso_denegado');
        exit();
    }
}
?>