

<?php
// Incluir el archivo de conexión
require_once '../conexion.php';

// Leer los datos recibidos en formato JSON
$data = json_decode(file_get_contents('php://input'), true);

// Validar que se recibieron los campos necesarios
if (!isset($data['Material'], $data['cantidad_disp'], $data['cantidad_min'], $data['estado'])) {
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

$material = $data['Material'];
$cantidad_disp = (int)$data['cantidad_disp'];
$cantidad_min = (int)$data['cantidad_min'];
$estado = $data['estado'];
$ord_idord = isset($data['ord_idord']) ? (int)$data['ord_idord'] : null;

try {
    // Preparar la consulta SQL para insertar el nuevo material
    $query = "INSERT INTO inventario (Material, cantidad_disp, cantidad_min, estado, ord_idord) VALUES (:material, :cantidad_disp, :cantidad_min, :estado, :ord_idord)";
    $stmt = $conn->prepare($query);

    // Ejecutar la consulta
    $stmt->execute([
        ':material' => $material,
        ':cantidad_disp' => $cantidad_disp,
        ':cantidad_min' => $cantidad_min,
        ':estado' => $estado,
        ':ord_idord' => $ord_idord
    ]);

    echo json_encode(['success' => true, 'message' => 'Material agregado correctamente']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>