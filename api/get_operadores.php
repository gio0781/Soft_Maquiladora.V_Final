<?php
header("Content-Type: application/json");
$conn = new mysqli("localhost", "u984575157_admin", "Adminchido123@", "u984575157_sistema_taller");

// Verificar la conexión
if ($conn->connect_error) {
    die(json_encode(["error" => "Conexión fallida: " . $conn->connect_error]));
}

$query = "SELECT operador.id, operador.nombre, operador.especialidad, operador.tarea_asignada, operador.usuario_id, tareas.descripcion 
            FROM operador 
            LEFT JOIN tareas ON operador.tarea_asignada = tareas.nombre";
$result = $conn->query($query);

$operadores = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $operadores[] = $row;
    }
}

echo json_encode($operadores);
$conn->close();
?>
