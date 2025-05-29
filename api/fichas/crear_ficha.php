<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ob_start();

header("Content-Type: application/json");
require_once("../conexion.php");

$nombre = $_POST['nombre'] ?? null;
$descripcion = $_POST['descripcion'] ?? null;
$boton = $_POST['boton'] ?? null;
$cierre = $_POST['cierre'] ?? null;
$hilo = $_POST['hilo'] ?? null;
$fotoRuta = null;

if (!$nombre || !$descripcion || !$boton || !$cierre || !$hilo) {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "error" => "Todos los campos son obligatorios."
    ]);
    exit;
}

// Generar SKU automáticamente basado en el último ID existente
$sqlLast = "SELECT idfic FROM FichaTec ORDER BY idfic DESC LIMIT 1";
$result = $conn->query($sqlLast);
$nextId = 1;
if ($result && $row = $result->fetch_assoc()) {
    $nextId = (int)$row['idfic'] + 1;
}
$sku = 'SKU' . str_pad($nextId, 3, '0', STR_PAD_LEFT);

// Guardar la imagen directamente en la carpeta 'img' para unificar todas las fotos en un solo lugar
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = '../../img/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
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
        $fotoRuta = 'img/' . $nombreArchivo;
    } else {
        ob_end_clean();
        echo json_encode([
            "success" => false,
            "error" => "No se pudo convertir y guardar la foto como JPG."
        ]);
        exit;
    }
}

// Nota: Si ahora solo usamos la nomenclatura SKU para las fotos y estas se buscan dinámicamente (ej. img/SKU005.jpg),
// podrías considerar eliminar el campo 'foto' en la tabla si no es necesario almacenarlo.

// Insertar en la base de datos
$sql = "INSERT INTO FichaTec (nombre, descripcion, boton, cierre, hilo, sku, foto) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sssssss", $nombre, $descripcion, $boton, $cierre, $hilo, $sku, $fotoRuta);

if ($stmt->execute()) {
    ob_end_clean();
    echo json_encode([
        "success" => true,
        "message" => "Ficha técnica creada exitosamente."
    ]);
    exit;
} else {
    ob_end_clean();
    echo json_encode([
        "success" => false,
        "error" => "Error al crear la ficha técnica: " . $stmt->error
    ]);
    exit;
}
?>