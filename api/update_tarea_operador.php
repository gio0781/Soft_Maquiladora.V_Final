<?php
header("Content-Type: application/json");

// Conexión directa
include __DIR__ . '/../conexion.php';
if ($conn->connect_error) {
    echo json_encode(["success" => false, "error" => "Conexión fallida"]);
    exit;
}

// Obtener datos JSON del cuerpo de la solicitud
$data = json_decode(file_get_contents("php://input"), true);

$id = $data["id"] ?? null;
$tarea = $data["tarea_asignada"] ?? null;

// Validar jerarquía simulada desde frontend (ideal: usar sesiones en producción)
$jerarquia = $_GET["jerarquia"] ?? null;

if ((int)$jerarquia !== 1) {
    echo json_encode(["success" => false, "error" => "No autorizado"]);
    exit;
}

if ($id && $tarea) {
    $stmt = $conn->prepare("UPDATE operador SET tarea_asignada = ? WHERE id = ?");
    $stmt->bind_param("si", $tarea, $id);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al actualizar"]);
    }

    $stmt->close();
} else {
    echo json_encode(["success" => false, "error" => "Datos incompletos"]);
}

$conn->close();
