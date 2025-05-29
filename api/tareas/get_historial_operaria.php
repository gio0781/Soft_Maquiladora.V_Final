<?php
// api/tareas/get_historial_operaria.php
header('Content-Type: application/json');
require_once '../../conexion.php';

$nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
if ($nombre === '') {
  echo json_encode(['error' => 'Nombre no especificado']);
  exit;
}

$sql = "SELECT historial FROM Asig_Tareas WHERE nombre_operadora = ? ORDER BY fecha_asignacion DESC LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $nombre);
$stmt->execute();
$result = $stmt->get_result();

$historial = [];
if ($row = $result->fetch_assoc()) {
  $texto = $row['historial'];
  $lineas = explode("\n", $texto);
  foreach ($lineas as $linea) {
    $partes = explode('|', $linea);
    if (count($partes) === 4) {
      $historial[] = [
        'tarea' => trim($partes[0]),
        'linea_produccion' => trim($partes[1]),
        'pedido' => trim($partes[2]),
        'fecha' => trim($partes[3])
      ];
    }
  }
}

echo json_encode(['historial' => $historial]);
$stmt->close();
$conn->close();
?>
