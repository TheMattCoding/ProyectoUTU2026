<?php
// php/logica/generarLiga.php

// Si db.php está en la raíz de 'php/', subimos un nivel con __DIR__
require_once __DIR__ . '/../db.php';

/**
 * Genera el fixture completo para un torneo en formato LIGA (Round-Robin).
 *
 * @param PDO $pdo Instancia de conexión a la base de datos.
 * @param int $idTorneo ID del torneo a procesar.
 * @return bool True si se generó correctamente, False si hubo error.
 */
function generarFixtureLiga(PDO $pdo, int $idTorneo): bool {
    try {
        $pdo->beginTransaction();

        // 1. Obtener equipos o participantes inscriptos y confirmados
        $stmtInscriptos = $pdo->prepare("
            SELECT COALESCE(id_equipo, id_participante) AS id_participante 
            FROM inscripciones_torneo 
            WHERE id_torneo = ? AND (id_equipo IS NOT NULL OR id_participante IS NOT NULL)
        ");
        $stmtInscriptos->execute([$idTorneo]);
        $equipos = $stmtInscriptos->fetchAll(PDO::FETCH_COLUMN);

        $numEquipos = count($equipos);
        if ($numEquipos < 2) {
            throw new Exception("Se necesitan al menos 2 equipos para generar la liga.");
        }

        // 2. Si la cantidad es impar, agregamos NULL para simular jornada libre
        if ($numEquipos % 2 !== 0) {
            $equipos[] = null;
            $numEquipos++;
        }

        $totalRondas = $numEquipos - 1;
        $partidosPorRonda = $numEquipos / 2;

        // Sentencias preparadas
        $stmtRonda = $pdo->prepare("
            INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
            VALUES (?, ?, ?, 'pendiente')
        ");

        $stmtEnfrentamiento = $pdo->prepare("
            INSERT INTO enfrentamientos (id_ronda, id_local, id_visitante, estado_enfrentamiento) 
            VALUES (?, ?, ?, 'pendiente')
        ");

        // 3. Generación de Rondas y Enfrentamientos (Algoritmo de Berger)
        for ($ronda = 1; $ronda <= $totalRondas; $ronda++) {
            $nombreRonda = "Fecha " . $ronda;
            $stmtRonda->execute([$idTorneo, $ronda, $nombreRonda]);
            $idRonda = $pdo->lastInsertId();

            for ($i = 0; $i < $partidosPorRonda; $i++) {
                $local = $equipos[$i];
                $visitante = $equipos[$numEquipos - 1 - $i];

                // Alternar localía en rondas pares
                if ($ronda % 2 === 0) {
                    $temp = $local;
                    $local = $visitante;
                    $visitante = $temp;
                }

                if ($local !== null && $visitante !== null) {
                    $stmtEnfrentamiento->execute([$idRonda, $local, $visitante]);
                }
            }

            // Rotación circular conservando la primera posición
            $ultimo = array_pop($equipos);
            array_splice($equipos, 1, 0, [$ultimo]);
        }

        // 4. Actualizar estado del torneo
        $stmtTorneo = $pdo->prepare("UPDATE torneos SET estado = 'en_curso' WHERE id_torneo = ?");
        $stmtTorneo->execute([$idTorneo]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error al generar liga: " . $e->getMessage());
        return false;
    }
}