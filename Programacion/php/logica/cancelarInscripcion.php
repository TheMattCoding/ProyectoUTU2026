<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['id_usuario']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../../login.php');
    exit;
}

$id_torneo = filter_input(INPUT_POST, 'id_torneo', FILTER_VALIDATE_INT);
$id_usuario = $_SESSION['id_usuario'];

if (!$id_torneo) {
    header('Location: ../busquedaTorneo.php');
    exit;
}

try {
    // 1. Obtener el id_participante
    $stmtPart = $pdo->prepare("SELECT id_participante FROM participantes WHERE id_usuario = ?");
    $stmtPart->execute([$id_usuario]);
    $participante = $stmtPart->fetch(PDO::FETCH_ASSOC);

    if ($participante) {
        $id_participante = $participante['id_participante'];

        // 2. Eliminar la inscripción
        $stmtDel = $pdo->prepare("DELETE FROM inscripciones_torneo WHERE id_torneo = ? AND id_participante = ?");
        $stmtDel->execute([$id_torneo, $id_participante]);
    }

    header('Location: ../detalleTorneo.php?id=' . $id_torneo . '&estado=cancelado');
    exit;

} catch (PDOException $e) {
    header('Location: ../detalleTorneo.php?id=' . $id_torneo . '&estado=error');
    exit;
}