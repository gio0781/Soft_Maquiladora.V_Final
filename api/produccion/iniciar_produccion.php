<?php
require_once '../conexion.php';

header('Content-Type: application/json');

if (!isset($_POST['id_pedido'])) {
    echo json_encode(['status' => 'error', 'message' => 'ID de pedido no proporcionado']);
    exit;
}

$id_pedido = intval($_POST['id_pedido']);

// 1. Obtener datos del pedido: ficha técnica y cantidad
$query = "SELECT ficha_id, cantidad FROM Pedidos WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Pedido no encontrado']);
    exit;
}

$pedido = $result->fetch_assoc();
$ficha_id = $pedido['ficha_id'];
$cantidad = $pedido['cantidad'];

// 2. Obtener materiales de la ficha técnica
$query_materiales = "SELECT material_id, cantidad_requerida FROM ficha_materiales WHERE ficha_id = ?";
$stmt = $conn->prepare($query_materiales);
$stmt->bind_param("i", $ficha_id);
$stmt->execute();
$result_materiales = $stmt->get_result();

$materiales = [];
while ($row = $result_materiales->fetch_assoc()) {
    $materiales[] = $row;
}

// 3. Verificar si hay suficiente inventario
$errores = [];

foreach ($materiales as $mat) {
    $material_id = $mat['material_id'];
    $requerido_total = $mat['cantidad_requerida'] * $cantidad;

    $query_inv = "SELECT cantidad FROM Inventario WHERE id = ?";
    $stmt = $conn->prepare($query_inv);
    $stmt->bind_param("i", $material_id);
    $stmt->execute();
    $result_inv = $stmt->get_result();

    if ($result_inv->num_rows === 0) {
        $errores[] = "Material ID $material_id no encontrado";
        continue;
    }

    $inv = $result_inv->fetch_assoc();
    if ($inv['cantidad'] < $requerido_total) {
        $errores[] = "Material ID $material_id insuficiente (Requiere $requerido_total, hay {$inv['cantidad']})";
    }
}

if (!empty($errores)) {
    echo json_encode(['status' => 'faltante', 'faltantes' => $errores]);
    exit;
}

// 4. Descontar del inventario
foreach ($materiales as $mat) {
    $material_id = $mat['material_id'];
    $requerido_total = $mat['cantidad_requerida'] * $cantidad;

    $query_descuento = "UPDATE Inventario SET cantidad = cantidad - ? WHERE id = ?";
    $stmt = $conn->prepare($query_descuento);
    $stmt->bind_param("di", $requerido_total, $material_id);
    $stmt->execute();
}

// 5. Cambiar estado del pedido a "en_produccion"
$query_update = "UPDATE Pedidos SET estado = 'en_produccion' WHERE id = ?";
$stmt = $conn->prepare($query_update);
$stmt->bind_param("i", $id_pedido);
$stmt->execute();

echo json_encode(['status' => 'success', 'message' => 'Producción iniciada y materiales descontados']);
?>
