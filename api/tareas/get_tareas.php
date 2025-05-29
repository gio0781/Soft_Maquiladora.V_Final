<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../conexion.php';

try {
    $sql = "SELECT id, nombre, descripcion FROM tareas ORDER BY nombre ASC";
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error en consulta: " . $conn->error);
    }

    $tareas = [];
    while ($row = $result->fetch_assoc()) {
        $tareas[] = [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion']
        ];
    }

    echo json_encode($tareas);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();
?>