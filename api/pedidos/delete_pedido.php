<?php
// api/pedidos/eliminar_pedido.php - NUEVO ARCHIVO
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

$pedido_id = isset($input['id']) ? (int)$input['id'] : 0;

if ($pedido_id <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'ID de pedido requerido'
    ]);
    exit;
}

try {
    $conn->begin_transaction();
    
    // Verificar que el pedido existe y obtener información
    $sql_check = "SELECT idpedi, nombrecliente, pro_nombre, cantidad, estado 
                  FROM Pedidos 
                  WHERE idpedi = ?";
    
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $pedido_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Pedido no encontrado');
    }
    
    $pedido_info = $result->fetch_assoc();
    
    // Verificar si el pedido está en producción
    if ($pedido_info['estado'] === 'En Proceso') {
        throw new Exception('No se puede eliminar un pedido que está en proceso de producción');
    }
    
    // Eliminar asignaciones de tareas relacionadas con este pedido
    $sql_tareas = "UPDATE Asig_Tareas 
                   SET estado = 'cancelada', 
                       observaciones = CONCAT(COALESCE(observaciones, ''), 'Pedido eliminado - ', NOW())
                   WHERE pedido LIKE ? OR pedido LIKE ?";
    
    $pedido_ref1 = "PED-" . str_pad($pedido_id, 6, '0', STR_PAD_LEFT);
    $pedido_ref2 = "%{$pedido_id}%";
    
    $stmt_tareas = $conn->prepare($sql_tareas);
    $stmt_tareas->bind_param("ss", $pedido_ref1, $pedido_ref2);
    $stmt_tareas->execute();
    
    // Liberar operadores que tenían asignado este pedido
    $sql_operadores = "UPDATE operador 
                       SET tarea_asignada = NULL, 
                           estado = 'disponible'
                       WHERE id IN (
                           SELECT DISTINCT operador_id 
                           FROM Asig_Tareas 
                           WHERE (pedido LIKE ? OR pedido LIKE ?) 
                           AND estado = 'cancelada'
                       )";
    
    $stmt_operadores = $conn->prepare($sql_operadores);
    $stmt_operadores->bind_param("ss", $pedido_ref1, $pedido_ref2);
    $stmt_operadores->execute();
    
    // Eliminar el pedido
    $sql_delete = "DELETE FROM Pedidos WHERE idpedi = ?";
    $stmt_delete = $conn->prepare($sql_delete);
    $stmt_delete->bind_param("i", $pedido_id);
    
    if (!$stmt_delete->execute()) {
        throw new Exception('Error al eliminar el pedido');
    }
    
    // Registrar en log de eliminaciones (crear tabla si no existe)
    $conn->query("CREATE TABLE IF NOT EXISTS pedidos_eliminados_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        pedido_id INT NOT NULL,
        cliente VARCHAR(255),
        producto VARCHAR(255),
        cantidad INT,
        estado_anterior VARCHAR(50),
        motivo TEXT,
        usuario_eliminacion VARCHAR(100),
        fecha_eliminacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $sql_log = "INSERT INTO pedidos_eliminados_log 
                (pedido_id, cliente, producto, cantidad, estado_anterior, motivo, usuario_eliminacion) 
                VALUES (?, ?, ?, ?, ?, 'Eliminación manual desde sistema', 'Administrador')";
    
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param("issis", 
        $pedido_id, 
        $pedido_info['nombrecliente'], 
        $pedido_info['pro_nombre'], 
        $pedido_info['cantidad'], 
        $pedido_info['estado']
    );
    $stmt_log->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Pedido eliminado correctamente",
        'data' => [
            'pedido_id' => $pedido_id,
            'cliente' => $pedido_info['nombrecliente'],
            'producto' => $pedido_info['pro_nombre'],
            'cantidad' => $pedido_info['cantidad'],
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        'success' => false,
        'error' => 'Error al eliminar pedido: ' . $e->getMessage()
    ]);
}

$conn->close();
?>