<?php
header("Content-Type: application/json");

// Conexión a la base de datos (ajusta los datos)
$conn = new mysqli("localhost", "u984575157_admin", "Adminchido123@", "u984575157_sistema_taller");

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión"]);
    exit();
}

// Obtener los datos del cuerpo del POST
$input = json_decode(file_get_contents("php://input"), true);
$username = $input["username"];
$password = $input["password"];

// Validación simple (ajusta a tu modelo real)
$query = "SELECT * FROM usuarios WHERE usuario = ? AND contraseña = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "jerarquia" => $row["jerarquia"],
        "nombre" => $row["usuario"] ?? null
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Credenciales inválidas"]);
}

$conn->close();
?>