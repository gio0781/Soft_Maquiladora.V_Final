<?php
// Incluir el archivo de conexión
require_once '../conexion.php';

// Leer los datos recibidos en formato JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validar que se recibió el ID
if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'error' => 'ID no proporcionado']);
    exit;
}

$id = (int)$data['id'];

try {
    // Preparar la consulta SQL para eliminar el material
    $query = "DELETE FROM inventario WHERE id = :id";
    $stmt = $conn->prepare($query);
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Material eliminado correctamente']);
    } else {
        echo json_encode(['success' => false, 'error' => 'No se encontró el material con ese ID']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
