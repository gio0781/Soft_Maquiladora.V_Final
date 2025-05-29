<?php
// api/operadoras/marcar_leida.php
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
$notificacion_id = isset($input['notificacion_id']) ? (int)$input['notificacion_id'] : 0;
$marcar_todas = isset($input['marcar_todas']) ? (bool)$input['marcar_todas'] : false;

if (empty($nombre)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre de operadora requerido'
    ]);
    exit;
}

try {
    // Obtener ID del operador
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
    
    if ($marcar_todas) {
        // Marcar todas las notificaciones como leídas
        $sql_update = "UPDATE notificaciones_operadora 
                       SET leida = TRUE, fecha_lectura = NOW() 
                       WHERE operador_id = ? AND leida = FALSE";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("i", $operador_id);
        
        if ($stmt_update->execute()) {
            $notificaciones_marcadas = $stmt_update->affected_rows;
            
            echo json_encode([
                'success' => true,
                'message' => "Se marcaron $notificaciones_marcadas notificaciones como leídas",
                'notificaciones_marcadas' => $notificaciones_marcadas
            ]);
        } else {
            throw new Exception("Error al marcar notificaciones: " . $stmt_update->error);
        }
        
    } elseif ($notificacion_id > 0) {
        // Marcar notificación específica como leída
        $sql_update = "UPDATE notificaciones_operadora 
                       SET leida = TRUE, fecha_lectura = NOW() 
                       WHERE id = ? AND operador_id = ? AND leida = FALSE";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("ii", $notificacion_id, $operador_id);
        
        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Notificación marcada como leída',
                    'notificacion_id' => $notificacion_id
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Notificación no encontrada o ya estaba leída'
                ]);
            }
        } else {
            throw new Exception("Error al marcar notificación: " . $stmt_update->error);
        }
        
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Se requiere notificacion_id o marcar_todas = true'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>