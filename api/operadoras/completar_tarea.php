<?php
// api/operadoras/completar_tarea.php - VERSIÓN CORREGIDA
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
$tarea_id = isset($input['tarea_id']) ? (int)$input['tarea_id'] : 0;

if (empty($nombre)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre de operadora requerido'
    ]);
    exit;
}

try {
    $conn->begin_transaction();
    
    // DEBUG: Verificar qué nombre estamos buscando
    error_log("Buscando operadora: " . $nombre);
    
    // Primero, intentar encontrar por usuario
    $sql_usuario = "SELECT u.id as usuario_id, u.usuario, o.id as operador_id, o.nombre 
                    FROM usuarios u 
                    LEFT JOIN operador o ON u.id = o.usuario_id 
                    WHERE LOWER(u.usuario) = LOWER(?)";
    
    $stmt_usuario = $conn->prepare($sql_usuario);
    $stmt_usuario->bind_param("s", $nombre);
    $stmt_usuario->execute();
    $result_usuario = $stmt_usuario->get_result();
    
    $operador_id = null;
    $nombre_operadora = $nombre;
    
    if ($result_usuario->num_rows > 0) {
        $usuario_info = $result_usuario->fetch_assoc();
        $operador_id = $usuario_info['operador_id'];
        $nombre_operadora = $usuario_info['nombre'] ?: $nombre;
        error_log("Encontrado por usuario - Operador ID: " . $operador_id);
    }
    
    // Si no se encontró por usuario, buscar directamente en operador
    if (!$operador_id) {
        $sql_operador = "SELECT id, nombre FROM operador WHERE LOWER(nombre) = LOWER(?)";
        $stmt_operador = $conn->prepare($sql_operador);
        $stmt_operador->bind_param("s", $nombre);
        $stmt_operador->execute();
        $result_operador = $stmt_operador->get_result();
        
        if ($result_operador->num_rows > 0) {
            $operador_info = $result_operador->fetch_assoc();
            $operador_id = $operador_info['id'];
            $nombre_operadora = $operador_info['nombre'];
            error_log("Encontrado por nombre - Operador ID: " . $operador_id);
        }
    }
    
    if (!$operador_id) {
        // Listar todos los operadores para debug
        $sql_debug = "SELECT id, nombre, usuario_id FROM operador";
        $result_debug = $conn->query($sql_debug);
        $operadores_existentes = [];
        while ($row = $result_debug->fetch_assoc()) {
            $operadores_existentes[] = $row;
        }
        error_log("Operadores existentes: " . json_encode($operadores_existentes));
        
        throw new Exception("Operadora '$nombre' no encontrada en la base de datos");
    }
    
    // Obtener información de la tarea actual
    $sql_tarea = "SELECT 
                      id,
                      tarea_asignada,
                      pedido,
                      linea_produccion,
                      meta_diaria,
                      progreso_actual,
                      estado
                  FROM Asig_Tareas 
                  WHERE operador_id = ? 
                  AND estado IN ('asignada', 'en_proceso')
                  ORDER BY fecha_asignacion DESC 
                  LIMIT 1";
    
    $stmt_tarea = $conn->prepare($sql_tarea);
    $stmt_tarea->bind_param("i", $operador_id);
    $stmt_tarea->execute();
    $result_tarea = $stmt_tarea->get_result();
    
    if ($result_tarea->num_rows === 0) {
        // Verificar si hay tareas para este operador
        $sql_debug_tareas = "SELECT id, tarea_asignada, estado FROM Asig_Tareas WHERE operador_id = ?";
        $stmt_debug_tareas = $conn->prepare($sql_debug_tareas);
        $stmt_debug_tareas->bind_param("i", $operador_id);
        $stmt_debug_tareas->execute();
        $result_debug_tareas = $stmt_debug_tareas->get_result();
        
        $tareas_existentes = [];
        while ($row = $result_debug_tareas->fetch_assoc()) {
            $tareas_existentes[] = $row;
        }
        error_log("Tareas existentes para operador $operador_id: " . json_encode($tareas_existentes));
        
        throw new Exception('No hay tarea activa para completar');
    }
    
    $tarea = $result_tarea->fetch_assoc();
    error_log("Tarea encontrada: " . json_encode($tarea));
    
    // Marcar tarea como completada
    $sql_completar = "UPDATE Asig_Tareas 
                     SET estado = 'completada',
                         fecha_fin = NOW(),
                         observaciones = CONCAT(COALESCE(observaciones, ''), 'Tarea completada el ', NOW(), '\n')
                     WHERE id = ?";
    
    $stmt_completar = $conn->prepare($sql_completar);
    $stmt_completar->bind_param("i", $tarea['id']);
    
    if (!$stmt_completar->execute()) {
        throw new Exception("Error al completar tarea: " . $stmt_completar->error);
    }
    
    // Actualizar estado del operador a disponible y limpiar tarea asignada
    $sql_operador = "UPDATE operador 
                    SET estado = 'disponible',
                        tarea_asignada = NULL
                    WHERE id = ?";
    
    $stmt_operador = $conn->prepare($sql_operador);
    $stmt_operador->bind_param("i", $operador_id);
    
    if (!$stmt_operador->execute()) {
        throw new Exception("Error al actualizar operador: " . $stmt_operador->error);
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Tarea completada exitosamente',
        'data' => [
            'operador_id' => $operador_id,
            'tarea_completada' => $tarea['tarea_asignada'],
            'operadora' => $nombre_operadora,
            'pedido' => $tarea['pedido'],
            'fecha_completado' => date('Y-m-d H:i:s'),
            'nuevo_estado' => 'disponible'
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error en completar_tarea.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

$conn->close();
?>