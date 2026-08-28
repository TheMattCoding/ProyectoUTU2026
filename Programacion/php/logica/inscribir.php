<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['id_usuario'])) {
    die("Error: No hay sesión activa de usuario.");
}

$idTorneo  = intval($_POST['id_torneo'] ?? 0);
$idUsuario = intval($_SESSION['id_usuario']);
$idEquipo  = !empty($_POST['id_equipo']) ? intval($_POST['id_equipo']) : null;

if ($idTorneo > 0 && $idUsuario > 0) {
    try {
        // Step 1: Verificar si el id_usuario existe en la tabla participantes
        $stmtCheckPart = $pdo->prepare("SELECT COUNT(*) FROM participantes WHERE id_participante = :id_usuario");
        $stmtCheckPart->execute([':id_usuario' => $idUsuario]);

        // Si no existe, intentar crearlo
        if ($stmtCheckPart->fetchColumn() == 0) {
            try {
                $stmtInsPart = $pdo->prepare("INSERT INTO participantes (id_participante) VALUES (:id_usuario)");
                $stmtInsPart->execute([':id_usuario' => $idUsuario]);
            } catch (PDOException $ePart) {
                die("Error al crear el registro en la tabla 'participantes': " . $ePart->getMessage() . 
                    "<br>Asegúrate de que el ID $idUsuario exista previamente en la tabla de usuarios.");
            }
        }

        // Step 2: Verificar si ya está inscrito en el torneo
        $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM INSCRIPCIONES_TORNEO WHERE id_torneo = :id_torneo AND id_participante = :id_usuario");
        $stmtCheck->execute([
            ':id_torneo'  => $idTorneo,
            ':id_usuario' => $idUsuario
        ]);

        if ($stmtCheck->fetchColumn() == 0) {
            // Step 3: Insertar la inscripción
            $sql = "INSERT INTO INSCRIPCIONES_TORNEO (id_torneo, id_participante, id_equipo, estado_inscripcion) 
                    VALUES (:id_torneo, :id_usuario, :id_equipo, 'Confirmado')";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id_torneo'  => $idTorneo,
                ':id_usuario' => $idUsuario,
                ':id_equipo'  => $idEquipo
            ]);
        }

        header("Location: ../detalleTorneo.php?id=" . $idTorneo . "&estado=inscrito");
        exit();

    } catch (PDOException $e) {
        die("Error en INSCRIPCIONES_TORNEO: " . $e->getMessage());
    }
} else {
    die("Error: Parámetros inválidos.");
}