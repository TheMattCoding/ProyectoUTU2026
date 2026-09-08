<?php
// php/logica/generarEliminacionDirecta.php

require_once __DIR__ . '/../db.php';

/**
 * Retorna el nombre de la ronda según la cantidad de participantes en ella.
 */
function obtenerNombreRonda(int $cantidadEquipos): string {
    return match ($cantidadEquipos) {
        2       => 'Final',
        4       => 'Semifinales',
        8       => 'Cuartos de Final',
        16      => 'Octavos de Final',
        32      => 'Dieciseisavos de Final',
        default => 'Ronda de ' . $cantidadEquipos,
    };
}

/**
 * Genera el cuadro inicial para Eliminación Directa.
 */
function generarFixtureEliminacionDirecta(PDO $pdo, int $idTorneo): bool {
    try {
        $pdo->beginTransaction();

        // 1. Obtener equipos confirmados
        $stmtInscriptos = $pdo->prepare("
            SELECT id_equipo 
            FROM inscripciones_torneo 
            WHERE id_torneo = ? AND id_equipo IS NOT NULL
        ");
        $stmtInscriptos->execute([$idTorneo]);
        $equipos = $stmtInscriptos->fetchAll(PDO::FETCH_COLUMN);

        $numEquipos = count($equipos);
        if ($numEquipos < 2) {
            throw new Exception("Se necesitan al menos 2 equipos para eliminación directa.");
        }

        // Sorteo de llaves
        shuffle($equipos);

        // 2. Calcular la potencia de 2 igual o superior más cercana
        $potencia = 1;
        while ($potencia < $numEquipos) {
            $potencia *= 2;
        }

        // BYEs = equipos que avanzan directo a la Ronda 2
        $byes = $potencia - $numEquipos;
        $equiposRonda1 = $numEquipos - $byes; // Equipos que juegan la Ronda 1

        // 3. Crear la primera ronda en la BD
        $nombreRonda1 = obtenerNombreRonda($potencia);
        $stmtRonda = $pdo->prepare("
            INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
            VALUES (?, 1, ?, 'en_curso')
        ");
        $stmtRonda->execute([$idTorneo, $nombreRonda1]);
        $idRonda1 = $pdo->lastInsertId();

        // 4. Crear partidos de Ronda 1
        $stmtEnfrentamiento = $pdo->prepare("
            INSERT INTO enfrentamientos (id_ronda, id_local, id_visitante, estado_enfrentamiento) 
            VALUES (?, ?, ?, 'pendiente')
        ");

        for ($i = 0; $i < $equiposRonda1; $i += 2) {
            $stmtEnfrentamiento->execute([$idRonda1, $equipos[$i], $equipos[$i + 1]]);
        }

        // 5. Si hay BYEs, crear Ronda 2 con esos equipos esperando a los ganadores de Ronda 1
        if ($byes > 0) {
            $nombreRonda2 = obtenerNombreRonda($potencia / 2);
            $stmtRonda2 = $pdo->prepare("
                INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
                VALUES (?, 2, ?, 'pendiente')
            ");
            $stmtRonda2->execute([$idTorneo, $nombreRonda2]);
        }

        // Actualizar estado del torneo
        $stmtTorneo = $pdo->prepare("UPDATE torneos SET estado = 'en_curso' WHERE id_torneo = ?");
        $stmtTorneo->execute([$idTorneo]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error en eliminación directa: " . $e->getMessage());
        return false;
    }
}