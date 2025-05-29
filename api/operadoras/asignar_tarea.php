<?php
include '../conexion.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$operador_id = intval($data['operador_id'] ?? 0);
$tarea_id = intval($data['tarea_id'] ?? 0);
$linea_produccion = $data['linea_produccion'] ?? 'Línea General';
$pedido = $data['pedido'] ?? null;

if ($operador_id <= 0 || $tarea_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Datos inválidos.']);
    exit;
}

// Obtener nombre del operador
$query_nombre = $conexion->prepare("SELECT nombre FROM operador WHERE id = ?");
$query_nombre->bind_param("i", $operador_id);
$query_nombre->execute();
$result = $query_nombre->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Operador no encontrado.']);
    exit;
}

$nombre_operador = $result->fetch_assoc()['nombre'];

// Obtener nombre de la tarea
$query_tarea = $conexion->prepare("SELECT nombre FROM tareas WHERE id = ?");
$query_tarea->bind_param("i", $tarea_id);
$query_tarea->execute();
$tarea_result = $query_tarea->get_result();

if ($tarea_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Tarea no encontrada.']);
    exit;
}

$nombre_tarea = $tarea_result->fetch_assoc()['nombre'];

// Insertar tarea asignada
$insert_stmt = $conexion->prepare("INSERT INTO Asig_Tareas (operador_id, nombre_operadora, tarea_asignada, linea_produccion, pedido) VALUES (?, ?, ?, ?, ?)");
$insert_stmt->bind_param("issss", $operador_id, $nombre_operador, $nombre_tarea, $linea_produccion, $pedido);

if ($insert_stmt->execute()) {
    // Actualizar estado del operador a ocupado
    $conexion->query("UPDATE operador SET estado = 'ocupado', tarea_asignada = '$nombre_tarea' WHERE id = $operador_id");

    echo json_encode(['success' => true, 'message' => 'Tarea asignada con éxito.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al asignar tarea.']);
}
?>