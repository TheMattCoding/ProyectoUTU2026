<?php
// php/logica/procesarTorneosAuto.php

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/generarLiga.php';
require_once __DIR__ . '/generarEliminacionDirecta.php';
require_once __DIR__ . '/generarSistemaSuizo.php';

/**
 * Evalúa e inicia automáticamente los torneos pendientes cuya fecha/hora ya transcurrió.
 */
function procesarTorneosProgramados(PDO $pdo): void {
    try {
        // 1. Buscar torneos 'pendiente' cuyo horario de inicio sea menor o igual al momento actual
        $sql = "
            SELECT t.id_torneo, t.nombre_torneo, c.formato
            FROM torneos t
            JOIN configuracion_torneo c ON t.id_torneo = c.id_torneo
            WHERE t.estado = 'pendiente' 
              AND CONCAT(t.fecha_inicio, ' ', t.hora_inicio) <= NOW()
        ";
        
        $stmt = $pdo->query($sql);
        $torneosAProcesar = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($torneosAProcesar as $torneo) {
            $idTorneo = (int)$torneo['id_torneo'];
            $formato = strtolower(trim($torneo['formato']));

            // 2. Contar inscriptos confirmados (equipos o participantes)
            $stmtCount = $pdo->prepare("
                SELECT COUNT(*) 
                FROM inscripciones_torneo 
                WHERE id_torneo = ? AND (id_equipo IS NOT NULL OR id_participante IS NOT NULL)
            ");
            $stmtCount->execute([$idTorneo]);
            $cantidadInscriptos = (int)$stmtCount->fetchColumn();

            // 3. Si no hay suficiente gente (mínimo 2), se cancela el torneo
            if ($cantidadInscriptos < 2) {
                $stmtCancelar = $pdo->prepare("UPDATE torneos SET estado = 'cancelado' WHERE id_torneo = ?");
                $stmtCancelar->execute([$idTorneo]);
                error_log("Torneo ID $idTorneo ('{$torneo['nombre_torneo']}') cancelado: Inscriptos insuficientes ($cantidadInscriptos).");
                continue;
            }

            // 4. Generar el fixture según el formato registrado
            switch ($formato) {
                case 'liga':
                    generarFixtureLiga($pdo, $idTorneo);
                    break;

                case 'eliminatoria':
                case 'eliminacion_directa':
                case 'eliminacion directa':
                    generarFixtureEliminacionDirecta($pdo, $idTorneo);
                    break;

                case 'suizo':
                case 'sistema_suizo':
                case 'sistema suizo':
                    generarRondaSuizo($pdo, $idTorneo);
                    break;

                default:
                    error_log("Torneo ID $idTorneo tiene un formato no soportado: '$formato'");
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Error en procesarTorneosProgramados: " . $e->getMessage());
    }
}

// Ejecución automática de la verificación
procesarTorneosProgramados($pdo);