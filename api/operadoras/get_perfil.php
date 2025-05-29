<?php
// api/operadoras/get_perfil.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';

if (empty($nombre)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre de operadora requerido'
    ]);
    exit;
}

try {
    // Obtener datos básicos de la operadora
    $sql = "SELECT 
                o.id,
                o.nombre,
                o.especialidad,
                o.estado,
                o.tarea_asignada,
                o.foto_perfil,
                o.fecha_ingreso,
                o.turno,
                u.usuario as usuario_sistema
            FROM operador o 
            LEFT JOIN usuarios u ON o.usuario_id = u.id 
            WHERE o.nombre = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Operadora no encontrada'
        ]);
        exit;
    }
    
    $operadora = $result->fetch_assoc();
    
    // Obtener estadísticas de productividad
    $fecha_hoy = date('Y-m-d');
    $fecha_semana = date('Y-m-d', strtotime('-7 days'));
    
    // Productividad de hoy
    $sql_hoy = "SELECT 
                    COALESCE(SUM(piezas_completadas), 0) as piezas_hoy,
                    COALESCE(AVG(eficiencia_porcentaje), 0) as eficiencia_hoy,
                    COALESCE(SUM(horas_trabajadas), 0) as horas_hoy,
                    COALESCE(SUM(puntos_calidad), 0) as puntos_hoy
                FROM productividad_operadora 
                WHERE operador_id = ? AND fecha = ?";
    
    $stmt_hoy = $conn->prepare($sql_hoy);
    $stmt_hoy->bind_param("is", $operadora['id'], $fecha_hoy);
    $stmt_hoy->execute();
    $stats_hoy = $stmt_hoy->get_result()->fetch_assoc();
    
    // Estadísticas generales
    $sql_general = "SELECT 
                        COUNT(*) as total_dias,
                        COALESCE(SUM(piezas_completadas), 0) as total_piezas,
                        COALESCE(AVG(eficiencia_porcentaje), 0) as eficiencia_promedio,
                        COALESCE(SUM(puntos_calidad), 0) as puntos_total,
                        COALESCE(SUM(horas_trabajadas), 0) as horas_total
                    FROM productividad_operadora 
                    WHERE operador_id = ?";
    
    $stmt_general = $conn->prepare($sql_general);
    $stmt_general->bind_param("i", $operadora['id']);
    $stmt_general->execute();
    $stats_general = $stmt_general->get_result()->fetch_assoc();
    
    // Tarea actual si existe
    $tarea_actual = null;
    if ($operadora['tarea_asignada']) {
        $sql_tarea = "SELECT 
                          tarea_asignada,
                          descripcion,
                          linea_produccion,
                          pedido,
                          estado,
                          prioridad,
                          meta_diaria,
                          progreso_actual
                      FROM Asig_Tareas 
                      WHERE nombre_operadora = ? 
                      ORDER BY fecha_asignacion DESC 
                      LIMIT 1";
        
        $stmt_tarea = $conn->prepare($sql_tarea);
        $stmt_tarea->bind_param("s", $nombre);
        $stmt_tarea->execute();
        $result_tarea = $stmt_tarea->get_result();
        
        if ($result_tarea->num_rows > 0) {
            $tarea_actual = $result_tarea->fetch_assoc();
        }
    }
    
    // Construir respuesta
    $response = [
        'success' => true,
        'data' => [
            'id' => $operadora['id'],
            'nombre' => $operadora['nombre'],
            'especialidad' => $operadora['especialidad'],
            'estado' => $operadora['estado'] ?: 'disponible',
            'tarea_asignada' => $operadora['tarea_asignada'],
            'foto_perfil' => $operadora['foto_perfil'],
            'fecha_ingreso' => $operadora['fecha_ingreso'],
            'turno' => $operadora['turno'] ?: 'matutino',
            'usuario_sistema' => $operadora['usuario_sistema'],
            
            // Estadísticas de hoy
            'piezas_hoy' => (int)$stats_hoy['piezas_hoy'],
            'eficiencia_hoy' => round($stats_hoy['eficiencia_hoy'], 1),
            'horas_hoy' => round($stats_hoy['horas_hoy'], 1),
            'puntos_hoy' => (int)$stats_hoy['puntos_hoy'],
            
            // Estadísticas generales
            'total_tareas' => (int)$stats_general['total_dias'],
            'total_piezas' => (int)$stats_general['total_piezas'],
            'eficiencia_promedio' => round($stats_general['eficiencia_promedio'], 1),
            'puntos_calidad' => (int)$stats_general['puntos_total'],
            'dias_trabajados' => (int)$stats_general['total_dias'],
            'horas_total' => round($stats_general['horas_total'], 1),
            
            // Tarea actual
            'tarea_actual' => $tarea_actual
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>