<?php
session_start();
require_once '../db.php';

// Validar que exista la sesión de usuario y se envíe por POST
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
    // 1. Obtener el id_participante correspondiente al id_usuario actual
    $stmtPart = $pdo->prepare("SELECT id_participante FROM PARTICIPANTES WHERE id_usuario = ?");
    $stmtPart->execute([$id_usuario]);
    $participante = $stmtPart->fetch(PDO::FETCH_ASSOC);

    // 2. Si el usuario no existe aún en PARTICIPANTES, se crea la fila automáticamente
    if (!$participante) {
        $stmtInsPart = $pdo->prepare("INSERT INTO PARTICIPANTES (id_usuario) VALUES (?)");
        $stmtInsPart->execute([$id_usuario]);
        $id_participante = $pdo->lastInsertId();
    } else {
        $id_participante = $participante['id_participante'];
    }

    // 3. Evitar registros duplicados en INSCRIPCIONES_TORNEO
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM INSCRIPCIONES_TORNEO WHERE id_torneo = ? AND id_participante = ?");
    $stmtCheck->execute([$id_torneo, $id_participante]);

    if ($stmtCheck->fetchColumn() > 0) {
        header('Location: ../detalleTorneo.php?id=' . $id_torneo . '&estado=error');
        exit;
    }

    // 4. Insertar la inscripción en la tabla intermedia
    $stmtInscripcion = $pdo->prepare("INSERT INTO INSCRIPCIONES_TORNEO (id_torneo, id_participante, estado_inscripcion) VALUES (?, ?, 'Confirmado')");
    $stmtInscripcion->execute([$id_torneo, $id_participante]);

    header('Location: ../detalleTorneo.php?id=' . $id_torneo . '&estado=inscrito');
    exit;

} catch (PDOException $e) {
    // Redireccionar con error ante fallos de BD
    header('Location: ../detalleTorneo.php?id=' . $id_torneo . '&estado=error');
    exit;
}