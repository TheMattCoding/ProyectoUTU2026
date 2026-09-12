<?php

function obtenerMisNotificaciones($pdo, $id_usuario) {
    $notificaciones = [];
    $descartadas = $_SESSION['notis_descartadas'] ?? [];

    // 1. Te has inscrito al torneo
    $sqlInscrito = "SELECT t.id_torneo, 
                           CONCAT('Te has inscrito al torneo \"', t.nombre_torneo, '\"') AS mensaje,
                           t.fecha_inicio AS fecha_orden
                    FROM inscripciones_torneo i
                    INNER JOIN participantes p ON i.id_participante = p.id_participante
                    INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                    WHERE p.id_usuario = :id1 AND i.estado_inscripcion = 'Confirmado'";

    // 2. Ya no perteneces al torneo (cancelado, rechazado o expulsado/baneado)
    $sqlNoPertenece = "SELECT t.id_torneo, 
                              CONCAT('Ya no perteneces al torneo \"', t.nombre_torneo, '\"') AS mensaje,
                              t.fecha_inicio AS fecha_orden
                       FROM inscripciones_torneo i
                       INNER JOIN participantes p ON i.id_participante = p.id_participante
                       INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                       WHERE p.id_usuario = :id2 AND i.estado_inscripcion IN ('baneado', 'cancelado', 'rechazada')";

    // 3. Comienza en menos de 1 hora
    $sqlPorComenzar = "SELECT t.id_torneo, 
                              CONCAT('El torneo \"', t.nombre_torneo, '\" comienza en menos de 1 hora') AS mensaje,
                              t.fecha_inicio AS fecha_orden
                       FROM inscripciones_torneo i
                       INNER JOIN participantes p ON i.id_participante = p.id_participante
                       INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                       WHERE p.id_usuario = :id3 
                         AND i.estado_inscripcion = 'Confirmado'
                         AND t.estado = 'pendiente'
                         AND TIMESTAMP(t.fecha_inicio, t.hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)";

    // 4. El torneo ya comenzó
    $sqlComenzo = "SELECT t.id_torneo, 
                          CONCAT('El torneo \"', t.nombre_torneo, '\" ya ha comenzado') AS mensaje,
                          t.fecha_inicio AS fecha_orden
                   FROM inscripciones_torneo i
                   INNER JOIN participantes p ON i.id_participante = p.id_participante
                   INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                   WHERE p.id_usuario = :id4 
                     AND i.estado_inscripcion = 'Confirmado'
                     AND t.estado = 'en_curso'";

    // 5. Avance de ronda
    $sqlSiguienteRonda = "SELECT t.id_torneo, 
                             CONCAT('El torneo \"', t.nombre_torneo, '\" avanzó a la ', r.nombre_ronda) AS mensaje,
                             t.fecha_inicio AS fecha_orden
                          FROM inscripciones_torneo i
                          INNER JOIN participantes p ON i.id_participante = p.id_participante
                          INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                          INNER JOIN rondas r ON r.id_torneo = t.id_torneo
                          WHERE p.id_usuario = :id5 
                            AND i.estado_inscripcion = 'Confirmado'
                            AND r.estado_ronda = 'en_curso'";

    $query = "($sqlInscrito) UNION ALL ($sqlNoPertenece) UNION ALL ($sqlPorComenzar) UNION ALL ($sqlComenzo) UNION ALL ($sqlSiguienteRonda)
              ORDER BY fecha_orden DESC";

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
            // Clave única basada en el ID del torneo y el texto del mensaje
            $idUnico = md5($f['id_torneo'] . '_' . $f['mensaje']);

            // Omitir si la notificación fue eliminada en esta sesión
            if (in_array($idUnico, $descartadas)) {
                continue;
            }

            $notificaciones[] = [
                'id'      => $idUnico,
                'mensaje' => $f['mensaje'],
                'enlace'  => 'detalleTorneo.php?id=' . $f['id_torneo']
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
    return true;
}