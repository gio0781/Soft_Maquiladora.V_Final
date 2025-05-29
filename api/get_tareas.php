<?php
header("Content-Type: application/json");

// Conexión a la base de datos (misma que en login.php)
$conn = new mysqli("localhost", "u984575157_admin", "Adminchido123@", "u984575157_sistema_taller");

// Verificar la conexión
if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión"]);
    exit();
}

// Consulta de tareas
$query = "SELECT nombre FROM tareas ORDER BY nombre ASC";
$result = $conn->query($query);

$tareas = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tareas[] = $row["nombre"];
    }
}

// Devolver como JSON
echo json_encode($tareas);
$conn->close();
