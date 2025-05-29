<?php
// api/operadoras/registrar_progreso.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos JSON inválidos'
    ]);
    exit;
}

$nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
$cantidad = isset($input['cantidad']) ? (int)$input['cantidad'] : 0;
$calidad = isset($input['calidad']) ? trim($input['calidad']) : 'buena';
$observaciones = isset($input['observaciones']) ? trim($input['observaciones']) : '';
$fecha = isset($input['fecha']) ? $input['fecha'] : date('Y-m-d H:i:s');

// Validar datos requeridos
if (empty($nombre) || $cantidad <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre y cantidad válida son requeridos'
    ]);
    exit;
}

// Validar calidad
$calidades_validas = ['excelente', 'buena', 'regular', 'mala'];
if (!in_array($calidad, $calidades_validas)) {
    $calidad = 'buena';
}

try {
    $conn->begin_transaction();
    
    // Obtener ID de la operadora
    $sql_operadora = "SELECT id FROM operador WHERE nombre = ?";
    $stmt_operadora = $conn->prepare($sql_operadora);
    $stmt_operadora->bind_param("s", $nombre);
    $stmt_operadora->execute();
    $result_operadora = $stmt_operadora->get_result();
    
    if ($result_operadora->num_rows === 0) {
        throw new Exception('Operadora no encontrada');
    }
    
    $operadora = $result_operadora->fetch_assoc();
    $operador_id = $operadora['id'];
    
    // Obtener tarea actual
    $sql_tarea = "SELECT 
                      id,
                      tarea_asignada,
                      meta_diaria,
                      progreso_actual,
                      linea_produccion,
                      pedido
                  FROM Asig_Tareas 
                  WHERE nombre_operadora = ? 
                  AND estado IN ('asignada', 'en_proceso')
                  ORDER BY fecha_asignacion DESC 
                  LIMIT 1";
    
    $stmt_tarea = $conn->prepare($sql_tarea);
    $stmt_tarea->bind_param("s", $nombre);
    $stmt_tarea->execute();
    $result_tarea = $stmt_tarea->get_result();
    
    if ($result_tarea->num_rows === 0) {
        throw new Exception('No hay tarea activa para registrar progreso');
    }
    
    $tarea = $result_tarea->fetch_assoc();
    $nuevo_progreso = $tarea['progreso_actual'] + $cantidad;
    
    // Actualizar progreso en la tarea
    $sql_update_tarea = "UPDATE Asig_Tareas 
                         SET progreso_actual = ?,
                             estado = 'en_proceso',
                             observaciones = CONCAT(COALESCE(observaciones, ''), ?, '\n')
                         WHERE id = ?";
    
    $observacion_completa = date('Y-m-d H:i:s') . " - Progreso: +$cantidad piezas ($calidad)";
    if (!empty($observaciones)) {
        $observacion_completa .= " - $observaciones";
    }
    
    $stmt_update = $conn->prepare($sql_update_tarea);
    $stmt_update->bind_param("isi", $nuevo_progreso, $observacion_completa, $tarea['id']);
    $stmt_update->execute();
    
    // Calcular puntos de calidad
    $puntos_calidad = 0;
    switch ($calidad) {
        case 'excelente': $puntos_calidad = $cantidad * 4; break;
        case 'buena': $puntos_calidad = $cantidad * 3; break;
        case 'regular': $puntos_calidad = $cantidad * 2; break;
        case 'mala': $puntos_calidad = $cantidad * 1; break;
    }
    
    // Actualizar o insertar en productividad_operadora
    $fecha_hoy = date('Y-m-d');
    
    $sql_check_prod = "SELECT id, piezas_completadas, puntos_calidad 
                       FROM productividad_operadora 
                       WHERE operador_id = ? AND fecha = ?";
    
    $stmt_check = $conn->prepare($sql_check_prod);
    $stmt_check->bind_param("is", $operador_id, $fecha_hoy);
    $stmt_check->execute();
    $result_prod = $stmt_check->get_result();
    
    if ($result_prod->num_rows > 0) {
        // Actualizar registro existente
        $prod_existente = $result_prod->fetch_assoc();
        $nuevas_piezas = $prod_existente['piezas_completadas'] + $cantidad;
        $nuevos_puntos = $prod_existente['puntos_calidad'] + $puntos_calidad;
        
        $sql_update_prod = "UPDATE productividad_operadora 
                           SET piezas_completadas = ?,
                               puntos_calidad = ?,
                               meta_cumplida = (piezas_completadas >= ?),
                               eficiencia_porcentaje = CASE 
                                   WHEN ? > 0 THEN (piezas_completadas / ?) * 100 
                                   ELSE 0 
                               END
                           WHERE id = ?";
        
        $meta = $tarea['meta_diaria'] ?: 100;
        $stmt_update_prod = $conn->prepare($sql_update_prod);
        $stmt_update_prod->bind_param("iiiiiii", 
            $nuevas_piezas, 
            $nuevos_puntos, 
            $meta,
            $meta,
            $meta,
            $prod_existente['id']
        );
        $stmt_update_prod->execute();
        
    } else {
        // Insertar nuevo registro
        $sql_insert_prod = "INSERT INTO productividad_operadora 
                           (operador_id, fecha, piezas_completadas, puntos_calidad, meta_cumplida, eficiencia_porcentaje) 
                           VALUES (?, ?, ?, ?, ?, ?)";
        
        $meta = $tarea['meta_diaria'] ?: 100;
        $meta_cumplida = $cantidad >= $meta ? 1 : 0;
        $eficiencia = $meta > 0 ? ($cantidad / $meta) * 100 : 0;
        
        $stmt_insert = $conn->prepare($sql_insert_prod);
        $stmt_insert->bind_param("isiiid", 
            $operador_id, 
            $fecha_hoy, 
            $cantidad, 
            $puntos_calidad, 
            $meta_cumplida, 
            $eficiencia
        );
        $stmt_insert->execute();
    }
    
    // Registrar en historial de la tarea
    $historial_entry = date('Y-m-d H:i:s') . "|Progreso|+$cantidad piezas|$calidad|" . ($observaciones ?: 'Sin observaciones');
    
    $sql_historial = "UPDATE Asig_Tareas 
                     SET historial = CONCAT(COALESCE(historial, ''), ?, '\n')
                     WHERE id = ?";
    
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("si", $historial_entry, $tarea['id']);
    $stmt_historial->execute();
    
    // Verificar si se completó la meta
    $tarea_completada = false;
    if ($nuevo_progreso >= $tarea['meta_diaria']) {
        $sql_completar = "UPDATE Asig_Tareas 
                         SET estado = 'completada',
                             fecha_fin = NOW()
                         WHERE id = ?";
        
        $stmt_completar = $conn->prepare($sql_completar);
        $stmt_completar->bind_param("i", $tarea['id']);
        $stmt_completar->execute();
        
        $tarea_completada = true;
    }
    
    $conn->commit();
    
    // Respuesta exitosa
    $response = [
        'success' => true,
        'message' => "Progreso registrado: $cantidad piezas ($calidad)",
        'data' => [
            'cantidad_registrada' => $cantidad,
            'calidad' => $calidad,
            'progreso_anterior' => $tarea['progreso_actual'],
            'progreso_nuevo' => $nuevo_progreso,
            'meta_diaria' => $tarea['meta_diaria'],
            'porcentaje_completado' => round(($nuevo_progreso / $tarea['meta_diaria']) * 100, 1),
            'puntos_calidad_ganados' => $puntos_calidad,
            'tarea_completada' => $tarea_completada,
            'observaciones' => $observaciones
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>