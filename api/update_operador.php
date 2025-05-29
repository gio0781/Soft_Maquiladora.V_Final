<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header("Content-Type: application/json");
include __DIR__ . '/../conexion.php';

$data = json_decode(file_get_contents("php://input"), true);
file_put_contents(__DIR__ . "/log_update.txt", "Datos recibidos: " . print_r($data, true) . "\n", FILE_APPEND);

$accion = $data["accion"] ?? null;

if (!$accion) {
    file_put_contents(__DIR__ . "/log_update.txt", "Acción recibida: $accion\n", FILE_APPEND);
    echo json_encode(["success" => false, "error" => "Acción no especificada"]);
    exit;
}

if ($accion === "actualizar") {
    $id = $data["id"] ?? null;
    $nombre = $data["nombre"] ?? null;
    $especialidad = $data["especialidad"] ?? null;

    if (!$id || !$nombre || !$especialidad) {
        echo json_encode(["success" => false, "error" => "Faltan datos para actualizar"]);
        exit;
    }

    $stmt = $conn->prepare("UPDATE operador SET nombre = ?, especialidad = ? WHERE id = ?");
    $stmt->bind_param("ssi", $nombre, $especialidad, $id);
    file_put_contents(__DIR__ . "/log_update.txt", "Ejecutando UPDATE para ID: $id, Nombre: $nombre, Especialidad: $especialidad\n", FILE_APPEND);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(["success" => true]);
        } else {
            echo json_encode(["success" => false, "error" => "No se modificó ningún registro. Verifica si los datos enviados son distintos a los actuales."]);
        }
    } else {
        echo json_encode(["success" => false, "error" => $conn->error]);
    }

    $stmt->close();

} elseif ($accion === "eliminar") {
    $id = $data["id"] ?? null;

    if (!$id) {
        echo json_encode(["success" => false, "error" => "ID no proporcionado para eliminar"]);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM operador WHERE id = ?");
    $stmt->bind_param("i", $id);
    file_put_contents(__DIR__ . "/log_update.txt", "Ejecutando DELETE para ID: $id\n", FILE_APPEND);

    if ($stmt->execute()) {
        echo json_encode(["success" => true]);
    } else {
        echo json_encode(["success" => false, "error" => "Error al eliminar operador"]);
    }

    $stmt->close();

} else {
    echo json_encode(["success" => false, "error" => "Acción no reconocida"]);
}

$conn->close();
?>
