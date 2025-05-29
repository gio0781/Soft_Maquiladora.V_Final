<?php
require_once '../../includes/conexion.php';
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$nombre = $input['nombre'] ?? '';
$tarea_id = (int)($input['tarea_id'] ?? 0);

if (!$nombre || !$tarea_id) {
  echo json_encode(['success' => false, 'error' => 'Faltan datos']);
  exit;
}

$stmt = $conn->prepare("UPDATE Asig_Tareas SET estado = 'pausada' WHERE id = ? AND nombre_operadora = ?");
$stmt->bind_param('is', $tarea_id, $nombre);
$success = $stmt->execute();

if ($success) {
  $stmt2 = $conn->prepare("UPDATE operador SET estado = 'descanso' WHERE nombre = ?");
  $stmt2->bind_param('s', $nombre);
  $stmt2->execute();
  echo json_encode(['success' => true]);
} else {
  echo json_encode(['success' => false, 'error' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
