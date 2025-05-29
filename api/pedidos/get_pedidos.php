<?php
header('Content-Type: application/json');
require_once '../conexion.php';

$query = "SELECT nombrecliente, pro_nombre, cantidad, fecha_registro, fecha_entrega FROM Pedidos ORDER BY idpedi DESC";
$result = mysqli_query($conn, $query);

$pedidos = [];

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pedidos[] = [
            'cliente' => $row['nombrecliente'],
            'producto' => $row['pro_nombre'],
            'cantidad' => $row['cantidad'],
            'fecha_registro' => $row['fecha_registro'],
            'fecha_entrega' => $row['fecha_entrega']
        ];
    }
    echo json_encode($pedidos);
} else {
    echo json_encode(["error" => "Error al obtener pedidos: " . mysqli_error($conn)]);
}
?>