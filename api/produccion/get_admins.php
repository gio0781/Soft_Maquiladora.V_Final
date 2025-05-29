
<?php
header("Content-Type: application/json");
require_once("../conexion.php");

try {
    $sql = "SELECT id, usuario FROM usuarios WHERE jerarquia = 1";
    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode([
            "success" => false,
            "error" => "Error en la consulta: " . $conn->error
        ]);
        exit();
    }

    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $admins
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>