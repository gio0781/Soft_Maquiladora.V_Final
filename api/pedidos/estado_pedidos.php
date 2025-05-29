<?php
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Incluir archivo de conexiÃ³n
include __DIR__ . '/../conexion.php';

$response = ['success' => false, 'data' => [], 'error' => ''];

if (!isset($conn) || !$conn) {
    $response['error'] = 'Error de conexiÃ³n a la base de datos.';
    echo json_encode($response);
    exit;
}

$sql = "SELECT estado, COUNT(*) as total FROM Pedidos GROUP BY estado";
$result = $conn->query($sql);

if (!$result) {
    $response['error'] = 'Error en la consulta: ' . $conn->error;
    echo json_encode($response);
    exit;
}

$datos = [];
while ($row = $result->fetch_assoc()) {
    $datos[] = $row;
}

$response['success'] = true;
$response['data'] = $datos;
echo json_encode($response);
?>