<?php
// api/pedidos/actualizar_estado_pedido.php - VERSIÓN FINAL SOLO POR ID
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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

if (!isset($input['pedido']) || !isset($input['estado'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos incompletos'
    ]);
    exit;
}

$pedido_id = (int)$input['pedido'];
$estado = trim($input['estado']);

$estados_validos = ['Confirmado', 'En Proceso', 'En Producción', 'Completado', 'Entregado', 'Cancelado'];
if (!in_array($estado, $estados_validos)) {
    echo json_encode([
        'success' => false,
        'error' => 'Estado no válido'
    ]);
    exit;
}

try {
    $stmt_check = $conn->prepare("SELECT nombrecliente, pro_nombre, estado as estado_actual FROM Pedidos WHERE idpedi = ?");
    $stmt_check->bind_param("i", $pedido_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Pedido no encontrado'
        ]);
        exit;
    }

    $pedido_info = $result->fetch_assoc();
    $estado_anterior = $pedido_info['estado_actual'];

    $stmt_update = $conn->prepare("UPDATE Pedidos SET estado = ?, fecha_actualizacion = NOW() WHERE idpedi = ?");
    $stmt_update->bind_param("si", $estado, $pedido_id);

    if ($stmt_update->execute()) {
        // Crear tabla de log si no existe
        $conn->query("CREATE TABLE IF NOT EXISTS pedidos_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            estado_anterior VARCHAR(50),
            estado_nuevo VARCHAR(50),
            fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            usuario VARCHAR(100),
            FOREIGN KEY (pedido_id) REFERENCES Pedidos(idpedi)
        )");

        // Insertar log
        $stmt_log = $conn->prepare("INSERT INTO pedidos_log (pedido_id, estado_anterior, estado_nuevo, usuario) VALUES (?, ?, ?, 'Sistema')");
        $stmt_log->bind_param("iss", $pedido_id, $estado_anterior, $estado);
        $stmt_log->execute();

        // Si se completó, marcar fecha de finalización
        if ($estado === 'Completado') {
            $stmt_completar = $conn->prepare("UPDATE Pedidos SET fecha_completado = NOW() WHERE idpedi = ?");
            $stmt_completar->bind_param("i", $pedido_id);
            $stmt_completar->execute();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Estado actualizado correctamente',
            'data' => [
                'pedido_id' => $pedido_id,
                'cliente' => $pedido_info['nombrecliente'],
                'producto' => $pedido_info['pro_nombre'],
                'estado_anterior' => $estado_anterior,
                'estado_nuevo' => $estado,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception("Error al actualizar el estado");
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>