<?php
// php/logica/gestorTorneos.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/generarEliminacionDirecta.php';
require_once __DIR__ . '/generarLiga.php';
require_once __DIR__ . '/generarSistemaSuizo.php';

/**
 * Inicia un torneo ejecutando el algoritmo del formato seleccionado.
 */
function iniciarTorneo(PDO $pdo, int $idTorneo, bool $forzarManual = false): array {
    // 1. Obtener datos del torneo y su configuración
    $stmt = $pdo->prepare("
        SELECT t.estado, c.formato, c.max_participantes 
        FROM torneos t
        JOIN configuracion_torneo c ON t.id_torneo = c.id_torneo
        WHERE t.id_torneo = ?
    ");
    $stmt->execute([$idTorneo]);
    $torneo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$torneo) {
        return ['exito' => false, 'mensaje' => 'Torneo no encontrado.'];
    }

    if ($torneo['estado'] === 'en_curso') {
        return ['exito' => false, 'mensaje' => 'El torneo ya se encuentra en curso.'];
    }

    // 2. Contar participantes confirmados
    $stmtInscritos = $pdo->prepare("
        SELECT COUNT(*) FROM inscripciones_torneo 
        WHERE id_torneo = ? AND (id_equipo IS NOT NULL OR id_participante IS NOT NULL)
    ");
    $stmtInscritos->execute([$idTorneo]);
    $cantidadInscritos = (int)$stmtInscritos->fetchColumn();

    if ($cantidadInscritos < 2) {
        return ['exito' => false, 'mensaje' => 'Se necesitan al menos 2 participantes inscritos para iniciar.'];
    }

    // 3. Validar cupos si no es un inicio forzado manualmente
    if (!$forzarManual && $torneo['max_participantes'] !== null) {
        if ($cantidadInscritos < $torneo['max_participantes']) {
            return [
                'exito' => false, 
                'mensaje' => 'No se alcanzó el cupo máximo de participantes para el inicio automático.'
            ];
        }
    }

    // 4. Ejecutar generador según el formato
    $formato = strtolower(trim($torneo['formato'] ?? ''));
    $resultado = false;

    switch ($formato) {
        case 'eliminatoria':
        case 'eliminacion_directa':
        case 'eliminacion directa':
            $resultado = generarFixtureEliminacionDirecta($pdo, $idTorneo);
            break;
        case 'liga':
            $resultado = generarFixtureLiga($pdo, $idTorneo);
            break;
        case 'suizo':
        case 'sistema_suizo':
        case 'sistema suizo':
            $resultado = generarRondaSuizo($pdo, $idTorneo);
            break;
        default:
            return ['exito' => false, 'mensaje' => "Formato de torneo no válido: '$formato'"];
    }

    if ($resultado) {
        return ['exito' => true, 'mensaje' => 'El torneo ha iniciado correctamente.'];
    }

    return ['exito' => false, 'mensaje' => 'Error al generar el fixture del torneo.'];
}

/**
 * Revisa torneos pendientes cuya fecha/hora ya llegó e intenta iniciarlos automáticamente.
 */
function verificarYAutoIniciarTorneos(PDO $pdo): void {
    try {
        $stmt = $pdo->prepare("
            SELECT id_torneo 
            FROM torneos 
            WHERE estado = 'pendiente' 
              AND CONCAT(fecha_inicio, ' ', COALESCE(hora_inicio, '00:00:00')) <= NOW()
        ");
        $stmt->execute();
        $torneosAIniciar = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($torneosAIniciar as $idTorneo) {
            iniciarTorneo($pdo, (int)$idTorneo, false); // false = sólo inicia si cumple el cupo completo
        }
    } catch (Exception $e) {
        error_log("Error al verificar auto-inicio de torneos: " . $e->getMessage());
    }
}