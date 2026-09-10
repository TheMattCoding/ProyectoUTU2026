<?php
// php/logica/generarSistemaSuizo.php

require_once __DIR__ . '/../db.php';

/**
 * Obtiene la tabla de posiciones actual del torneo calculando los puntos acumulados.
 */
function obtenerPuntajesSuizo(PDO $pdo, int $idTorneo): array {
    // 1. Obtener puntos configurados para el torneo
    $stmtConfig = $pdo->prepare("
        SELECT puntos_victoria, puntos_empate, puntos_derrota 
        FROM configuracion_torneo 
        WHERE id_torneo = ?
    ");
    $stmtConfig->execute([$idTorneo]);
    $config = $stmtConfig->fetch(PDO::FETCH_ASSOC) ?: [
        'puntos_victoria' => 3, 
        'puntos_empate' => 1, 
        'puntos_derrota' => 0
    ];

    // 2. Obtener todos los equipos inscritos
    $stmtEquipos = $pdo->prepare("
        SELECT COALESCE(id_equipo, id_participante) AS id_participante 
        FROM inscripciones_torneo 
        WHERE id_torneo = ? AND (id_equipo IS NOT NULL OR id_participante IS NOT NULL)
        ");
    $stmtEquipos->execute([$idTorneo]);
    $equipos = $stmtEquipos->fetchAll(PDO::FETCH_COLUMN);

    $puntajes = array_fill_keys($equipos, 0);

    // 3. Sumar puntos según resultados finalizados
    $stmtResultados = $pdo->prepare("
        SELECT e.id_local, e.id_visitante, r.puntuacion_local, r.puntuacion_visitante, r.id_ganador
        FROM resultados r
        JOIN enfrentamientos e ON r.id_enfrentamiento = e.id_enfrentamiento
        JOIN rondas ro ON e.id_ronda = ro.id_ronda
        WHERE ro.id_torneo = ? AND e.estado_enfrentamiento = 'finalizado'
    ");
    $stmtResultados->execute([$idTorneo]);
    $resultados = $stmtResultados->fetchAll(PDO::FETCH_ASSOC);

    foreach ($resultados as $res) {
        if ($res['puntuacion_local'] === $res['puntuacion_visitante']) {
            $puntajes[$res['id_local']] += $config['puntos_empate'];
            $puntajes[$res['id_visitante']] += $config['puntos_empate'];
        } elseif ($res['id_ganador'] == $res['id_local']) {
            $puntajes[$res['id_local']] += $config['puntos_victoria'];
            $puntajes[$res['id_visitante']] += $config['puntos_derrota'];
        } else {
            $puntajes[$res['id_visitante']] += $config['puntos_victoria'];
            $puntajes[$res['id_local']] += $config['puntos_derrota'];
        }
    }

    // Ordenar de mayor a menor puntaje
    arsort($puntajes);
    return $puntajes;
}

/**
 * Obtiene el historial de enfrentamientos previos para evitar cruces repetidos.
 */
function obtenerHistorialEnfrentamientos(PDO $pdo, int $idTorneo): array {
    $stmt = $pdo->prepare("
        SELECT e.id_local, e.id_visitante 
        FROM enfrentamientos e
        JOIN rondas r ON e.id_ronda = r.id_ronda
        WHERE r.id_torneo = ?
    ");
    $stmt->execute([$idTorneo]);
    $cruces = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $historial = [];
    foreach ($cruces as $cruce) {
        $key1 = $cruce['id_local'] . '-' . $cruce['id_visitante'];
        $key2 = $cruce['id_visitante'] . '-' . $cruce['id_local'];
        $historial[$key1] = true;
        $historial[$key2] = true;
    }
    return $historial;
}

/**
 * Genera la siguiente ronda del Sistema Suizo.
 */
function generarRondaSuizo(PDO $pdo, int $idTorneo): bool {
    try {
        $pdo->beginTransaction();

        // 1. Verificar número de ronda a crear
        $stmtUltimaRonda = $pdo->prepare("
            SELECT MAX(numero_ronda) 
            FROM rondas 
            WHERE id_torneo = ?
        ");
        $stmtUltimaRonda->execute([$idTorneo]);
        $ultimaRonda = (int)$stmtUltimaRonda->fetchColumn();
        $siguienteRondaNum = $ultimaRonda + 1;

        // 2. Obtener puntajes actuales y construir la lista de ordenamiento
        $tablaPuntajes = obtenerPuntajesSuizo($pdo, $idTorneo);
        $equiposOrdenados = array_keys($tablaPuntajes);

        if (count($equiposOrdenados) < 2) {
            throw new Exception("Se necesitan al menos 2 equipos para el sistema suizo.");
        }

        // Si es la primera ronda, mezclamos aleatoriamente
        if ($siguienteRondaNum === 1) {
            shuffle($equiposOrdenados);
        }

        // Si la cantidad es impar, el de menor puntaje sin BYE recibe fecha libre
        if (count($equiposOrdenados) % 2 !== 0) {
            array_pop($equiposOrdenados); // Manejo simplificado de BYE
        }

        // 3. Obtener historial para evitar repetidos
        $historial = obtenerHistorialEnfrentamientos($pdo, $idTorneo);

        // 4. Algoritmo de emparejamiento (Greedy Pairing)
        $parejas = [];
        $disponibles = $equiposOrdenados;

        while (count($disponibles) > 1) {
            $local = array_shift($disponibles);
            $rivalEncontrado = null;
            $indexRival = -1;

            foreach ($disponibles as $index => $candidato) {
                $key = $local . '-' . $candidato;
                if (!isset($historial[$key])) {
                    $rivalEncontrado = $candidato;
                    $indexRival = $index;
                    break;
                }
            }

            // Si ya jugó contra todos los disponibles restantes, se fuerza el emparejamiento
            if ($rivalEncontrado === null) {
                $indexRival = 0;
                $rivalEncontrado = $disponibles[0];
            }

            unset($disponibles[$indexRival]);
            $disponibles = array_values($disponibles); // Reindexar

            $parejas[] = ['local' => $local, 'visitante' => $rivalEncontrado];
        }

        // 5. Insertar ronda y enfrentamientos en la base de datos
        $nombreRonda = "Ronda " . $siguienteRondaNum;
        $stmtRonda = $pdo->prepare("
            INSERT INTO rondas (id_torneo, numero_ronda, nombre_ronda, estado_ronda) 
            VALUES (?, ?, ?, 'en_curso')
        ");
        $stmtRonda->execute([$idTorneo, $siguienteRondaNum, $nombreRonda]);
        $idRonda = $pdo->lastInsertId();

        $stmtEnfrentamiento = $pdo->prepare("
            INSERT INTO enfrentamientos (id_ronda, id_local, id_visitante, estado_enfrentamiento) 
            VALUES (?, ?, ?, 'pendiente')
        ");

        foreach ($parejas as $pareja) {
            $stmtEnfrentamiento->execute([$idRonda, $pareja['local'], $pareja['visitante']]);
        }

        // Actualizar torneo a 'en_curso'
        $stmtTorneo = $pdo->prepare("UPDATE torneos SET estado = 'en_curso' WHERE id_torneo = ?");
        $stmtTorneo->execute([$idTorneo]);

        $pdo->commit();
        return true;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error en sistema suizo: " . $e->getMessage());
        return false;
    }
}