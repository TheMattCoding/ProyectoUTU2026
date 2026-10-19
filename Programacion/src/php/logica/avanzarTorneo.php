<?php
// php/logica/avanzarTorneo.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/generarEliminacionDirecta.php';
require_once __DIR__ . '/generarSistemaSuizo.php';

/**
 * Evalúa si la ronda actual del torneo finalizó y avanza automáticamente a la siguiente.
 */
function verificarYAvanzarTorneo(PDO $pdo, int $idTorneo): void {
    try {
        // 1. Obtener formato del torneo
        $stmt = $pdo->prepare("
            SELECT c.formato 
            FROM torneos t
            JOIN configuracion_torneo c ON t.id_torneo = c.id_torneo
            WHERE t.id_torneo = ? AND t.estado = 'en_curso'
        ");
        $stmt->execute([$idTorneo]);
        $formato = strtolower(trim($stmt->fetchColumn() ?: ''));

        if (empty($formato)) {
            return;
        }

        // 2. Obtener la ronda que actualmente está en curso
        $stmtRonda = $pdo->prepare("
            SELECT id_ronda, numero_ronda, nombre_ronda 
            FROM rondas 
            WHERE id_torneo = ? AND estado_ronda = 'en_curso' 
            ORDER BY numero_ronda DESC LIMIT 1
        ");
        $stmtRonda->execute([$idTorneo]);
        $rondaActual = $stmtRonda->fetch(PDO::FETCH_ASSOC);

        if (!$rondaActual) {
            verificarFinalizacionTorneo($pdo, $idTorneo);
            return;
        }

        $idRonda = (int)$rondaActual['id_ronda'];
        $numRonda = (int)$rondaActual['numero_ronda'];

        // 3. Verificar si existen partidos pendientes en la ronda actual
        $stmtPendientes = $pdo->prepare("
            SELECT COUNT(*) 
            FROM enfrentamientos 
            WHERE id_ronda = ? AND estado_enfrentamiento != 'finalizado'
        ");
        $stmtPendientes->execute([$idRonda]);
        if ((int)$stmtPendientes->fetchColumn() > 0) {
            return; // Aún hay partidos en juego
        }

        // 4. Marcar la ronda actual como finalizada
        $stmtCerrarRonda = $pdo->prepare("UPDATE rondas SET estado_ronda = 'finalizada' WHERE id_ronda = ?");
        $stmtCerrarRonda->execute([$idRonda]);

        // 5. Determinar el flujo de avance según el formato
        switch ($formato) {
            case 'eliminatoria':
            case 'eliminatoria directa':
            case 'eliminacion_directa':
            case 'eliminacion directa':
                avanzarEliminacionDirecta($pdo, $idTorneo, $idRonda, $numRonda, $rondaActual['nombre_ronda']);
                break;

            case 'suizo':
            case 'sistema_suizo':
            case 'sistema suizo':
                avanzarSistemaSuizo($pdo, $idTorneo, $numRonda);
                break;

            case 'liga':
            case 'todos contra todos':
                avanzarLiga($pdo, $idTorneo, $numRonda);
                break;
        }

    } catch (Exception $e) {
        error_log("Error al avanzar ronda en torneo ID $idTorneo: " . $e->getMessage());
    }
}

function avanzarEliminacionDirecta(PDO $pdo, int $idTorneo, int $idRondaActual, int $numRondaActual, string $nombreRondaActual): void {
    
    // Si la ronda que acaba de finalizar son las "Finales", calcular posiciones finales y concluir torneo
    if ($nombreRondaActual === 'Finales' || $nombreRondaActual === 'Final') {
        actualizarPosicionesFinalesEliminatoria($pdo, $idTorneo, $idRondaActual);
        $stmtFin = $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?");
        $stmtFin->execute([$idTorneo]);
        return;
    }

    // Obtener resultados de la ronda que recién terminó
    $stmtPartidos = $pdo->prepare("
        SELECT e.id_local, e.id_visitante, r.id_ganador 
        FROM resultados r
        JOIN enfrentamientos e ON r.id_enfrentamiento = e.id_enfrentamiento
        WHERE e.id_ronda = ? AND r.id_ganador IS NOT NULL
    ");
    $stmtPartidos->execute([$idRondaActual]);
    $enfrentamientos = $stmtPartidos->fetchAll(PDO::FETCH_ASSOC);

    $ganadores = [];
    $perdedores = [];

    foreach ($enfrentamientos as $enf) {
        $ganadores[] = $enf['id_ganador'];
        $perdedores[] = ($enf['id_ganador'] == $enf['id_local']) ? $enf['id_visitante'] : $enf['id_local'];
    }

    $numGanadores = count($ganadores);

    if ($numGanadores <= 1) {
        $stmtFin = $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?");
        $stmtFin->execute([$idTorneo]);
        return;
    }

    $siguienteNumRonda = $numRondaActual + 1;

    // CASO SEMIFINALES (2 Ganadores): Generar la ronda "Finales" con 3°/4° puesto + Gran Final
    if ($numGanadores === 2) {
        $stmtInsRonda = $pdo->prepare("
            INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
            VALUES (?, ?, 'Finales', 'en_curso')
        ");
        $stmtInsRonda->execute([$idTorneo, $siguienteNumRonda]);
        $idRondaSiguiente = $pdo->lastInsertId();

        $stmtEnfrentamiento = $pdo->prepare("
            INSERT INTO enfrentamientos (id_ronda, id_local, id_visitante, estado_enfrentamiento) 
            VALUES (?, ?, ?, 'pendiente')
        ");

        // 1. Partido por 3er y 4to Puesto (entre perdedores)
        $stmtEnfrentamiento->execute([$idRondaSiguiente, $perdedores[0], $perdedores[1]]);

        // 2. Partido de la Gran Final (entre ganadores)
        $stmtEnfrentamiento->execute([$idRondaSiguiente, $ganadores[0], $ganadores[1]]);

        return;
    }

    // Para rondas previas (Cuartos, Octavos)
    $nombreRonda = obtenerNombreRonda($numGanadores);
    $stmtInsRonda = $pdo->prepare("
        INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
        VALUES (?, ?, ?, 'en_curso')
    ");
    $stmtInsRonda->execute([$idTorneo, $siguienteNumRonda, $nombreRonda]);
    $idRondaSiguiente = $pdo->lastInsertId();

    $stmtEnfrentamiento = $pdo->prepare("
        INSERT INTO enfrentamientos (id_ronda, id_local, id_visitante, estado_enfrentamiento) 
        VALUES (?, ?, ?, 'pendiente')
    ");

    for ($i = 0; $i < $numGanadores; $i += 2) {
        if (isset($ganadores[$i + 1])) {
            $stmtEnfrentamiento->execute([$idRondaSiguiente, $ganadores[$i], $ganadores[$i + 1]]);
        }
    }
}

/**
 * Calcula y asigna los puestos 1°, 2°, 3° y 4° al finalizar la ronda "Finales".
 */
function actualizarPosicionesFinalesEliminatoria(PDO $pdo, int $idTorneo, int $idRondaFinal): void {
    $stmt = $pdo->prepare("
        SELECT e.id_local, e.id_visitante, r.id_ganador 
        FROM enfrentamientos e
        LEFT JOIN resultados r ON e.id_enfrentamiento = r.id_enfrentamiento
        WHERE e.id_ronda = ?
        ORDER BY e.id_enfrentamiento ASC
    ");
    $stmt->execute([$idRondaFinal]);
    $partidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($partidos) >= 2) {
        $p3erPartido = $partidos[0];
        $pFinal      = $partidos[1];

        // 3° y 4° Puesto
        $tercero = $p3erPartido['id_ganador'];
        $cuarto  = ($tercero == $p3erPartido['id_local']) ? $p3erPartido['id_visitante'] : $p3erPartido['id_local'];

        // 1° y 2° Puesto
        $primero = $pFinal['id_ganador'];
        $segundo = ($primero == $pFinal['id_local']) ? $pFinal['id_visitante'] : $pFinal['id_local'];

        $stmtPos = $pdo->prepare("
            UPDATE inscripciones_torneo 
            SET posicion_final = ? 
            WHERE id_torneo = ? AND (id_equipo = ? OR id_participante = ?)
        ");

        if ($primero) $stmtPos->execute([1, $idTorneo, $primero, $primero]);
        if ($segundo) $stmtPos->execute([2, $idTorneo, $segundo, $segundo]);
        if ($tercero) $stmtPos->execute([3, $idTorneo, $tercero, $tercero]);
        if ($cuarto)  $stmtPos->execute([4, $idTorneo, $cuarto, $cuarto]);
    }
}

function avanzarSistemaSuizo(PDO $pdo, int $idTorneo, int $numRondaActual): void {
    $stmtEquipos = $pdo->prepare("
        SELECT COUNT(*) FROM inscripciones_torneo 
        WHERE id_torneo = ? AND (id_equipo IS NOT NULL OR id_participante IS NOT NULL)
    ");
    $stmtEquipos->execute([$idTorneo]);
    $numEquipos = (int)$stmtEquipos->fetchColumn();

    $maxRondasSuizo = $numEquipos > 1 ? (int)ceil(log($numEquipos, 2)) : 1;

    if ($numRondaActual < $maxRondasSuizo) {
        generarRondaSuizo($pdo, $idTorneo);
    } else {
        $stmtFin = $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?");
        $stmtFin->execute([$idTorneo]);
    }
}

function avanzarLiga(PDO $pdo, int $idTorneo, int $numRondaActual): void {
    $siguienteRonda = $numRondaActual + 1;

    $stmtSig = $pdo->prepare("SELECT id_ronda FROM rondas WHERE id_torneo = ? AND numero_ronda = ?");
    $stmtSig->execute([$idTorneo, $siguienteRonda]);
    $idSigRonda = $stmtSig->fetchColumn();

    if ($idSigRonda) {
        $stmtAct = $pdo->prepare("UPDATE rondas SET estado_ronda = 'en_curso' WHERE id_ronda = ?");
        $stmtAct->execute([$idSigRonda]);
    } else {
        $stmtFin = $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?");
        $stmtFin->execute([$idTorneo]);
    }
}

function verificarFinalizacionTorneo(PDO $pdo, int $idTorneo): void {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM enfrentamientos e
        JOIN rondas r ON e.id_ronda = r.id_ronda
        WHERE r.id_torneo = ? AND e.estado_enfrentamiento != 'finalizado'
    ");
    $stmt->execute([$idTorneo]);

    if ((int)$stmt->fetchColumn() === 0) {
        $stmtFin = $pdo->prepare("UPDATE torneos SET estado = 'finalizado' WHERE id_torneo = ?");
        $stmtFin->execute([$idTorneo]);
    }
}