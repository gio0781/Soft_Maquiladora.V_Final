<?php
header("Content-Type: application/json");
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start(); // Inicia el buffer de salida
require_once("../conexion.php");

$id = $_POST['id'] ?? null;
$nombre = $_POST['nombre'] ?? null;
$descripcion = $_POST['descripcion'] ?? null;
$boton = $_POST['boton'] ?? null;
$cierre = $_POST['cierre'] ?? null;
$hilo = $_POST['hilo'] ?? null;
$sku = $_POST['sku'] ?? null;

if (!$id || !$nombre || !$descripcion || !$boton || !$cierre || !$hilo || !$sku) {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "error" => "Todos los campos son obligatorios."
    ]);
    exit;
}

// Verificar si la ficha existe
$sqlCheck = "SELECT sku FROM FichaTec WHERE idfic = ?";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("i", $id);
$stmtCheck->execute();
$result = $stmtCheck->get_result();

if ($result->num_rows === 0) {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "error" => "Ficha no encontrada."
    ]);
    exit;
}

// Actualizar los datos
$sqlUpdate = "UPDATE FichaTec SET nombre = ?, descripcion = ?, boton = ?, cierre = ?, hilo = ?, sku = ? WHERE idfic = ?";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("ssiiisi", $nombre, $descripcion, $boton, $cierre, $hilo, $sku, $id);

if ($stmtUpdate->execute()) {
    // Manejar la nueva foto si se subió
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        // Guardar la imagen directamente en la carpeta 'img' como JPG
        $uploadDir = '../../img/';
        $skuLimpiado = preg_replace('/[^A-Za-z0-9_\-]/', '', $sku);
        $nombreArchivo = $skuLimpiado . '.jpg';
        $rutaCompleta = $uploadDir . $nombreArchivo;

        // Forzar conversión a JPG
        $imgInfo = getimagesize($_FILES['foto']['tmp_name']);
        $origen = null;
        switch ($imgInfo['mime']) {
            case 'image/jpeg':
                $origen = imagecreatefromjpeg($_FILES['foto']['tmp_name']);
                break;
            case 'image/png':
                $origen = imagecreatefrompng($_FILES['foto']['tmp_name']);
                break;
            case 'image/gif':
                $origen = imagecreatefromgif($_FILES['foto']['tmp_name']);
                break;
            default:
                ob_end_clean();
                echo json_encode([
                    "success" => false,
                    "error" => "El archivo debe ser una imagen válida (jpg, png, gif)."
                ]);
                exit;
        }

        if ($origen && imagejpeg($origen, $rutaCompleta, 90)) {
            imagedestroy($origen);
        } else {
            ob_end_clean();
            echo json_encode([
                "success" => false,
                "error" => "No se pudo convertir y guardar la nueva foto como JPG."
            ]);
            exit;
        }
    }

    ob_end_clean();
    echo json_encode([
        "success" => true,
        "message" => "Ficha actualizada correctamente."
    ]);
    exit;
} else {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "error" => "Error al actualizar la ficha: " . $stmtUpdate->error
    ]);
    exit;
}
?>