<?php

function obtenerMisNotificaciones($pdo, $id_usuario) {
    $notificaciones = [];
    $descartadas = $_SESSION['notis_descartadas'] ?? [];

    // Cargar preferencias activas de la sesión (por defecto todas habilitadas)
    $prefs = $_SESSION['preferencias_notificacion'] ?? [
        'noti_fixtures'               => 1,
        'noti_resultados'             => 1,
        'noti_cancelaciones'          => 1,
        'noti_proximos'               => 1,
        'noti_inscripcion_confirmada' => 1,
        'noti_inscripcion_rechazada'  => 1
    ];

    $queries = [];
    $params = [];

    // 1. Inscripción confirmada / aprobada
    if (!empty($prefs['noti_inscripcion_confirmada'])) {
        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('Tu inscripción al torneo \"', t.nombre_torneo, '\" ha sido APROBADA.') AS mensaje,
                             t.fecha_inicio AS fecha_orden
                      FROM inscripciones_torneo i
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                      WHERE p.id_usuario = :id_u1 AND LOWER(i.estado_inscripcion) = 'confirmado'";
        $params['id_u1'] = $id_usuario;
    }

    // 2. Solicitud rechazada o cancelada
    if (!empty($prefs['noti_inscripcion_rechazada'])) {
        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('Tu solicitud de inscripción al torneo \"', t.nombre_torneo, '\" fue RECHAZADA.') AS mensaje,
                             t.fecha_inicio AS fecha_orden
                      FROM inscripciones_torneo i
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                      WHERE p.id_usuario = :id_u2 AND LOWER(i.estado_inscripcion) IN ('baneado', 'cancelado', 'rechazada')";
        $params['id_u2'] = $id_usuario;
    }

    // 3. Próximos torneos (por comenzar o en curso)
    if (!empty($prefs['noti_proximos'])) {
        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('El torneo \"', t.nombre_torneo, '\" comienza en menos de 1 hora.') AS mensaje,
                             t.fecha_inicio AS fecha_orden
                      FROM inscripciones_torneo i
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                      WHERE p.id_usuario = :id_u3 
                        AND LOWER(i.estado_inscripcion) = 'confirmado'
                        AND t.estado = 'pendiente'
                        AND TIMESTAMP(t.fecha_inicio, t.hora_inicio) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 1 HOUR)";
        $params['id_u3'] = $id_usuario;

        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('El torneo \"', t.nombre_torneo, '\" ya ha comenzado.') AS mensaje,
                             t.fecha_inicio AS fecha_orden
                      FROM inscripciones_torneo i
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                      WHERE p.id_usuario = :id_u4 
                        AND LOWER(i.estado_inscripcion) = 'confirmado'
                        AND t.estado = 'en_curso'";
        $params['id_u4'] = $id_usuario;
    }

    // 4. Publicación de Fixtures y Avance de Rondas
    if (!empty($prefs['noti_fixtures'])) {
        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('El torneo \"', t.nombre_torneo, '\" avanzó a la ', r.nombre_ronda) AS mensaje,
                             t.fecha_inicio AS fecha_orden
                      FROM inscripciones_torneo i
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                      INNER JOIN rondas r ON r.id_torneo = t.id_torneo
                      WHERE p.id_usuario = :id_u5 
                        AND LOWER(i.estado_inscripcion) = 'confirmado'
                        AND r.estado_ronda = 'en_curso'";
        $params['id_u5'] = $id_usuario;
    }

    // 5. Cancelaciones de Torneo
    if (!empty($prefs['noti_cancelaciones'])) {
        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('El torneo \"', t.nombre_torneo, '\" ha sido CANCELADO.') AS mensaje,
                             t.fecha_inicio AS fecha_orden
                      FROM inscripciones_torneo i
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      INNER JOIN torneos t ON i.id_torneo = t.id_torneo
                      WHERE p.id_usuario = :id_u6 
                        AND LOWER(t.estado) = 'cancelado'";
        $params['id_u6'] = $id_usuario;
    }

    // 6. Resultados de Torneos
    if (!empty($prefs['noti_resultados'])) {
        $queries[] = "SELECT t.id_torneo, 
                             CONCAT('Se cargaron nuevos resultados en el torneo \"', t.nombre_torneo, '\".') AS mensaje,
                             res.fecha_registro AS fecha_orden
                      FROM resultados res
                      INNER JOIN enfrentamientos e ON res.id_enfrentamiento = e.id_enfrentamiento
                      INNER JOIN rondas r ON e.id_ronda = r.id_ronda
                      INNER JOIN torneos t ON r.id_torneo = t.id_torneo
                      INNER JOIN inscripciones_torneo i ON t.id_torneo = i.id_torneo
                      INNER JOIN participantes p ON i.id_participante = p.id_participante
                      WHERE p.id_usuario = :id_u7
                        AND LOWER(i.estado_inscripcion) = 'confirmado'
                        AND res.fecha_registro >= NOW() - INTERVAL 1 DAY";
        $params['id_u7'] = $id_usuario;
    }

    if (empty($queries)) {
        return [];
    }

    $queryFinal = implode(" UNION ALL ", $queries) . " ORDER BY fecha_orden DESC";

    try {
        $stmt = $pdo->prepare($queryFinal);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $f) {
            $idUnico = md5($f['id_torneo'] . '_' . $f['mensaje']);

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