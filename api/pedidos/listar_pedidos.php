<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion.php';

try {
    $sql = "SELECT 
                idpedi, 
                nombrecliente, 
                cantidad, 
                fecha_registro, 
                fecha_entrega, 
                pro_nombre,
                estado
            FROM Pedidos 
            ORDER BY fecha_registro DESC";
    
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error en consulta: " . $conn->error);
    }

    $pedidos = [];
    while ($row = $result->fetch_assoc()) {
        $pedidos[] = [
            'idpedi' => (int)$row['idpedi'],
            'nombrecliente' => $row['nombrecliente'],
            'cantidad' => (int)$row['cantidad'],
            'fecha_registro' => $row['fecha_registro'],
            'fecha_entrega' => $row['fecha_entrega'],
            'pro_nombre' => $row['pro_nombre'],
            'estado' => $row['estado'] ?? 'Confirmado'
        ];
    }

    echo json_encode($pedidos);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>