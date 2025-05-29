<?php
// api/produccion/get_seguimiento.php - VERSIÓN CORREGIDA
require_once '../conexion.php';
header('Content-Type: application/json');

// Consulta corregida que incluye operadoras asignadas
$query = "
  SELECT 
    p.idpedi,
    p.pro_nombre,
    p.cantidad,
    p.estado,
    GROUP_CONCAT(DISTINCT o.nombre SEPARATOR ', ') as operadoras_asignadas
  FROM Pedidos p
  LEFT JOIN Asig_Tareas at ON p.pro_nombre = at.pedido OR at.pedido LIKE CONCAT('%', p.idpedi, '%')
  LEFT JOIN operador o ON at.operador_id = o.id
  WHERE at.estado IN ('asignada', 'en_proceso') OR at.estado IS NULL
  GROUP BY p.idpedi, p.pro_nombre, p.cantidad, p.estado
  ORDER BY p.idpedi DESC
";

$result = $conn->query($query);
$data = [];

while ($row = $result->fetch_assoc()) {
    // Determinar área basada en las operadoras asignadas
    $area = 'General';
    if ($row['operadoras_asignadas']) {
        // Obtener especialidad predominante
        $query_area = "SELECT o.especialidad, COUNT(*) as count 
                       FROM operador o 
                       INNER JOIN Asig_Tareas at ON o.id = at.operador_id 
                       WHERE o.nombre IN ('" . str_replace(', ', "','", $row['operadoras_asignadas']) . "')
                       GROUP BY o.especialidad 
                       ORDER BY count DESC 
                       LIMIT 1";
        $area_result = $conn->query($query_area);
        if ($area_result && $area_row = $area_result->fetch_assoc()) {
            $area = $area_row['especialidad'];
        }
    }
    
    $data[] = [
        'id_orden' => 'PED-' . str_pad($row['idpedi'], 5, '0', STR_PAD_LEFT),
        'producto' => $row['pro_nombre'],
        'cantidad' => (int)$row['cantidad'],
        'area' => $area,
        'estado' => $row['estado'] ?? 'Pendiente',
        'operadoras' => $row['operadoras_asignadas'] ? explode(', ', $row['operadoras_asignadas']) : []
    ];
}

echo json_encode($data);
$conn->close();
?>