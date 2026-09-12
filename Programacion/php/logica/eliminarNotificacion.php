<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id_notificacion'])) {
    $idNoti = filter_input(INPUT_POST, 'id_notificacion', FILTER_DEFAULT);

    if (!isset($_SESSION['notis_descartadas'])) {
        $_SESSION['notis_descartadas'] = [];
    }

    if (!in_array($idNoti, $_SESSION['notis_descartadas'])) {
        $_SESSION['notis_descartadas'][] = $idNoti;
    }
}

$referer = $_SERVER['HTTP_REFERER'] ?? '../inicio.php';
header("Location: " . $referer);
exit;