<?php
// api/operadoras/get_historial.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion.php';

$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
$limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 10;
$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : null;

if (empty($nombre)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre de operadora requerido'
    ]);
    exit;
}

try {
    // Obtener ID de la operadora
    $sql_id = "SELECT id FROM operador WHERE nombre = ?";
    $stmt_id = $conn->prepare($sql_id);
    $stmt_id->bind_param("s", $nombre);
    $stmt_id->execute();
    $result_id = $stmt_id->get_result();
    
    if ($result_id->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Operadora no encontrada'
        ]);
        exit;
    }
    
    $operadora = $result_id->fetch_assoc();
    $operador_id = $operadora['id'];
    
    // Construir consulta de historial de tareas
    $sql = "SELECT 
                a.id,
                a.tarea_asignada,
                a.descripcion,
                a.linea_produccion,
                a.pedido,
                a.fecha_asignacion,
                a.fecha_inicio,
                a.fecha_fin,
                a.estado,
                a.prioridad,
                a.meta_diaria,
                a.progreso_actual,
                a.observaciones,
                CASE 
                    WHEN a.meta_diaria > 0 THEN ROUND((a.progreso_actual / a.meta_diaria) * 100, 1)
                    ELSE 0 
                END as porcentaje_completado,
                CASE 
                    WHEN a.fecha_inicio IS NOT NULL AND a.fecha_fin IS NOT NULL THEN
                        TIMESTAMPDIFF(HOUR, a.fecha_inicio, a.fecha_fin)
                    ELSE NULL 
                END as horas_trabajadas
            FROM Asig_Tareas a
            WHERE a.operador_id = ?";
    
    $params = [$operador_id];
    $param_types = "i";
    
    // Agregar filtros de fecha si se proporcionan
    if ($fecha_desde) {
        $sql .= " AND DATE(a.fecha_asignacion) >= ?";
        $params[] = $fecha_desde;
        $param_types .= "s";
    }
    
    if ($fecha_hasta) {
        $sql .= " AND DATE(a.fecha_asignacion) <= ?";
        $params[] = $fecha_hasta;
        $param_types .= "s";
    }
    
    $sql .= " ORDER BY a.fecha_asignacion DESC LIMIT ?";
    $params[] = $limite;
    $param_types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($param_types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $historial = [];
    while ($row = $result->fetch_assoc()) {
        $tarea = [
            'id' => $row['id'],
            'tarea' => $row['tarea_asignada'],
            'descripcion' => $row['descripcion'],
            'linea_produccion' => $row['linea_produccion'],
            'pedido' => $row['pedido'],
            'fecha_asignacion' => $row['fecha_asignacion'],
            'fecha_inicio' => $row['fecha_inicio'],
            'fecha_fin' => $row['fecha_fin'],
            'estado' => $row['estado'],
            'prioridad' => $row['prioridad'],
            'meta_diaria' => (int)$row['meta_diaria'],
            'progreso_actual' => (int)$row['progreso_actual'],
            'porcentaje_completado' => (float)$row['porcentaje_completado'],
            'horas_trabajadas' => $row['horas_trabajadas'],
            'observaciones' => $row['observaciones'],
            'meta_cumplida' => $row['progreso_actual'] >= $row['meta_diaria']
        ];
        
        // Calcular duración en formato legible
        if ($tarea['horas_trabajadas']) {
            $horas = floor($tarea['horas_trabajadas']);
            $minutos = round(($tarea['horas_trabajadas'] - $horas) * 60);
            $tarea['duracion_texto'] = $horas . 'h ' . $minutos . 'm';
        }
        
        $historial[] = $tarea;
    }
    
    // Obtener estadísticas del período
    $sql_stats = "SELECT 
                      COUNT(*) as total_tareas,
                      SUM(CASE WHEN estado = 'completada' THEN 1 ELSE 0 END) as tareas_completadas,
                      SUM(progreso_actual) as total_piezas,
                      AVG(CASE WHEN meta_diaria > 0 THEN (progreso_actual / meta_diaria) * 100 ELSE 0 END) as eficiencia_promedio,
                      SUM(CASE WHEN progreso_actual >= meta_diaria THEN 1 ELSE 0 END) as metas_cumplidas
                  FROM Asig_Tareas 
                  WHERE operador_id = ?";
    
    $params_stats = [$operador_id];
    $param_types_stats = "i";
    
    if ($fecha_desde) {
        $sql_stats .= " AND DATE(fecha_asignacion) >= ?";
        $params_stats[] = $fecha_desde;
        $param_types_stats .= "s";
    }
    
    if ($fecha_hasta) {
        $sql_stats .= " AND DATE(fecha_asignacion) <= ?";
        $params_stats[] = $fecha_hasta;
        $param_types_stats .= "s";
    }
    
    $stmt_stats = $conn->prepare($sql_stats);
    $stmt_stats->bind_param($param_types_stats, ...$params_stats);
    $stmt_stats->execute();
    $stats = $stmt_stats->get_result()->fetch_assoc();
    
    // Respuesta final
    echo json_encode([
        'success' => true,
        'data' => [
            'historial' => $historial,
            'estadisticas' => [
                'total_tareas' => (int)$stats['total_tareas'],
                'tareas_completadas' => (int)$stats['tareas_completadas'],
                'total_piezas' => (int)$stats['total_piezas'],
                'eficiencia_promedio' => round($stats['eficiencia_promedio'], 1),
                'metas_cumplidas' => (int)$stats['metas_cumplidas'],
                'tasa_completion' => $stats['total_tareas'] > 0 ? 
                    round(($stats['tareas_completadas'] / $stats['total_tareas']) * 100, 1) : 0
            ],
            'filtros' => [
                'fecha_desde' => $fecha_desde,
                'fecha_hasta' => $fecha_hasta,
                'limite' => $limite
            ]
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>