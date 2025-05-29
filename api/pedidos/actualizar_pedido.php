<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit();
}

require_once '../conexion.php';

// Obtener datos JSON
$input = json_decode(file_get_contents("php://input"), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'JSON inválido']);
    exit();
}

// Validar campos necesarios (sin producto para no usar id_ficha)
$required = ['id', 'cliente', 'cantidad', 'fecha_registro', 'fecha_entrega', 'estado'];
foreach ($required as $campo) {
    if (!isset($input[$campo])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
        exit();
    }
}

try {
    $id = (int)$input['id'];
    $cliente = $conn->real_escape_string($input['cliente']);
    $cantidad = (int)$input['cantidad'];
    $fecha_registro = $conn->real_escape_string($input['fecha_registro']);
    $fecha_entrega = $conn->real_escape_string($input['fecha_entrega']);
    $estado = $conn->real_escape_string($input['estado']);

    $sql = "UPDATE Pedidos SET 
                nombrecliente = ?, 
                cantidad = ?, 
                fecha_registro = ?, 
                fecha_entrega = ?, 
                estado = ?
            WHERE idpedi = ?";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error en prepare: " . $conn->error);
    }

    // s: string, i: integer
    $stmt->bind_param("sisssi", $cliente, $cantidad, $fecha_registro, $fecha_entrega, $estado, $id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Pedido actualizado correctamente']);
    } else {
        throw new Exception("Error al ejecutar: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}

$conn->close();
?>