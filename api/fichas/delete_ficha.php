

<?php
header("Content-Type: application/json");
require_once("../conexion.php");

$data = json_decode(file_get_contents("php://input"), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode([
        "success" => false,
        "error" => "ID no proporcionado."
    ]);
    exit;
}

// Obtener el SKU para borrar la imagen relacionada
$sqlSelect = "SELECT sku FROM FichaTec WHERE idfic = ?";
$stmtSelect = $conn->prepare($sqlSelect);
$stmtSelect->bind_param("i", $id);
$stmtSelect->execute();
$result = $stmtSelect->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "success" => false,
        "error" => "Ficha no encontrada."
    ]);
    exit;
}

$ficha = $result->fetch_assoc();
$sku = $ficha['sku'];
$fotoPath = "../../img/" . $sku . ".jpg";

// Eliminar la ficha de la base de datos
$sqlDelete = "DELETE FROM FichaTec WHERE idfic = ?";
$stmtDelete = $conn->prepare($sqlDelete);
$stmtDelete->bind_param("i", $id);

if ($stmtDelete->execute()) {
    // Intentar borrar la foto si existe
    if (file_exists($fotoPath)) {
        unlink($fotoPath);
    }
    echo json_encode([
        "success" => true,
        "message" => "Ficha eliminada correctamente."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "error" => "No se pudo eliminar la ficha: " . $stmtDelete->error
    ]);
}
?>