<?php
// php/logica/avanzarGanador.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/generarEliminacionDirecta.php';

/**
 * Verifica si la ronda actual terminó y hace avanzar a los ganadores a la siguiente ronda.
 */
function procesarAvanceEliminacionDirecta(PDO $pdo, int $idTorneo, int $idRondaActual): bool {
    try {
        // 1. Verificar si quedan enfrentamientos pendientes en la ronda actual
        $stmtPendientes = $pdo->prepare("
            SELECT COUNT(*) 
            FROM enfrentamientos 
            WHERE id_ronda = ? AND estado_enfrentamiento != 'finalizado'
        ");
        $stmtPendientes->execute([$idRondaActual]);
        if ($stmtPendientes->fetchColumn() > 0) {
            return true; // Aún hay partidos pendientes en esta ronda
        }

        $pdo->beginTransaction();

        // 2. Marcar la ronda actual como 'finalizada'
        $stmtCerrarRonda = $pdo->prepare("UPDATE rondas SET estado_ronda = 'finalizada' WHERE id_ronda = ?");
        $stmtCerrarRonda->execute([$idRondaActual]);

        // 3. Obtener los ganadores de la ronda actual
        $stmtGanadores = $pdo->prepare("
            SELECT r.id_ganador 
            FROM resultados r
            JOIN enfrentamientos e ON r.id_enfrentamiento = e.id_enfrentamiento
            WHERE e.id_ronda = ? AND r.id_ganador IS NOT NULL
        ");
        $stmtGanadores->execute([$idRondaActual]);
        $ganadores = $stmtGanadores->fetchAll(PDO::FETCH_COLUMN);

        // Si solo queda 1 ganador y no hay más rondas pendientes, el torneo finalizó
        if (count($ganadores) === 1) {
            $stmtFin = $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?");
            $stmtFin->execute([$idTorneo]);
            $pdo->commit();
            return true;
        }

        // 4. Obtener el número de la siguiente ronda
        $stmtNumRonda = $pdo->prepare("SELECT numero_ronda FROM rondas WHERE id_ronda = ?");
        $stmtNumRonda->execute([$idRondaActual]);
        $numeroRondaActual = $stmtNumRonda->fetchColumn();
        $siguienteNumRonda = $numeroRondaActual + 1;

        // 5. Verificar si existe la siguiente ronda o crearla
        $stmtRondaSig = $pdo->prepare("SELECT id_ronda FROM rondas WHERE id_torneo = ? AND numero_ronda = ?");
        $stmtRondaSig->execute([$idTorneo, $siguienteNumRonda]);
        $idRondaSiguiente = $stmtRondaSig->fetchColumn();

        if (!$idRondaSiguiente) {
            $nombreSig = obtenerNombreRonda(count($ganadores));
            $stmtNuevaRonda = $pdo->prepare("
                INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
                VALUES (?, ?, ?, 'en_curso')
            ");
            $stmtNuevaRonda->execute([$idTorneo, $siguienteNumRonda, $nombreSig]);
            $idRondaSiguiente = $pdo->lastInsertId();
        } else {
            $stmtAct = $pdo->prepare("UPDATE rondas SET estado_ronda = 'en_curso' WHERE id_ronda = ?");
            $stmtAct->execute([$idRondaSiguiente]);
        }

        // 6. Generar emparejamientos de la siguiente ronda con los ganadores
        $stmtEnfrentamiento = $pdo->prepare("
            INSERT INTO enfrentamientos (id_ronda, id_local, id_visitante, estado_enfrentamiento) 
            VALUES (?, ?, ?, 'pendiente')
        ");

        for ($i = 0; $i < count($ganadores); $i += 2) {
            if (isset($ganadores[$i + 1])) {
                $stmtEnfrentamiento->execute([$idRondaSiguiente, $ganadores[$i], $ganadores[$i + 1]]);
            }
        }

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error al avanzar ronda en eliminación directa: " . $e->getMessage());
        return false;
    }
}