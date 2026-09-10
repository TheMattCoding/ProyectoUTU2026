<?php

function obtenerMisNotificaciones($pdo, $id_usuario) {
    $notificaciones = [];

    // 1. Confirmación de inscripción
    $sqlInscrito = "SELECT t.id_torneo, CONCAT('Te has inscrito al torneo \"', t.nombre_torneo, '\"') AS mensaje
                    FROM inscripciones_torneo i
                    INNER JOIN participantes p ON i.id_participante = p.id_participante
                    INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                    WHERE p.id_usuario = :id1 AND i.estado_inscripcion = 'Confirmado'";

    // 2. Usuario baneado o expulsado
    $sqlBaneado = "SELECT t.id_torneo, CONCAT('Has sido expulsado/baneado del torneo \"', t.nombre_torneo, '\"') AS mensaje
                   FROM inscripciones_torneo i
                   INNER JOIN participantes p ON i.id_participante = p.id_participante
                   INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                   WHERE p.id_usuario = :id2 AND i.estado_inscripcion = 'baneado'";

    // 3. Torneo por comenzar (en 1 hora o menos)
    $sqlPorComenzar = "SELECT t.id_torneo, CONCAT('El torneo \"', t.nombre_torneo, '\" comienza en menos de 1 hora') AS mensaje
                       FROM inscripciones_torneo i
                       INNER JOIN participantes p ON i.id_participante = p.id_participante
                       INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                       WHERE p.id_usuario = :id3 
                         AND i.estado_inscripcion = 'Confirmado'
                         AND t.estado = 'pendiente'
                         AND TIMESTAMP(t.fecha_inicio, t.hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)";

    // 4. Torneo en curso / ya comenzó
    $sqlComenzo = "SELECT t.id_torneo, CONCAT('El torneo \"', t.nombre_torneo, '\" ya ha comenzado') AS mensaje
                   FROM inscripciones_torneo i
                   INNER JOIN participantes p ON i.id_participante = p.id_participante
                   INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                   WHERE p.id_usuario = :id4 
                     AND i.estado_inscripcion = 'Confirmado'
                     AND t.estado = 'en_curso'";

    // 5. Avance a siguiente ronda
    $sqlSiguienteRonda = "SELECT t.id_torneo, CONCAT('El torneo \"', t.nombre_torneo, '\" avanzó a la ', r.nombre_ronda) AS mensaje
                          FROM inscripciones_torneo i
                          INNER JOIN participantes p ON i.id_participante = p.id_participante
                          INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                          INNER JOIN rondas r ON r.id_torneo = t.id_torneo
                          WHERE p.id_usuario = :id5 
                            AND i.estado_inscripcion = 'Confirmado'
                            AND r.estado_ronda = 'en_curso'";

    $query = "($sqlInscrito) UNION ALL ($sqlBaneado) UNION ALL ($sqlPorComenzar) UNION ALL ($sqlComenzo) UNION ALL ($sqlSiguienteRonda)";

    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'id1' => $id_usuario,
            'id2' => $id_usuario,
            'id3' => $id_usuario,
            'id4' => $id_usuario,
            'id5' => $id_usuario
        ]);

        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $f) {
            $notificaciones[] = [
                'mensaje' => $f['mensaje'],
                'enlace'  => 'detalleTorneo.php?id=' . $f['id_torneo'],
                'leida'   => 0
            ];
        }
    } catch (PDOException $e) {
        return [];
    }

    return $notificaciones;
}

function contarNoLeidas($pdo, $id_usuario) {
    return count(obtenerMisNotificaciones($pdo, $id_usuario));
}

function mandarNotificacion($pdo, $id_usuario, $mensaje, $enlace = '#') {
    // Función vacía para mantener compatibilidad con scripts existentes
    return true;
}