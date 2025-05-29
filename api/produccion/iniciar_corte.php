<?php
header("Content-Type: application/json");
require_once("../conexion.php");

// Obtener los datos del POST
$data = json_decode(file_get_contents("php://input"), true);

$ficha_id = $data['ficha_id'] ?? null;
$cantidad_piezas = $data['cantidad_piezas'] ?? null;
$tamaño = $data['tamaño'] ?? null;
$admin_id = $data['admin_id'] ?? null;
$fecha_inicio = $data['fecha_inicio'] ?? null;
$fecha_fin = $data['fecha_fin'] ?? null;

if (!$ficha_id || !$cantidad_piezas || !$tamaño || !$admin_id || !$fecha_inicio || !$fecha_fin) {
    echo json_encode([
        "success" => false,
        "error" => "Datos incompletos. Se requieren todos los campos."
    ]);
    exit;
}

try {
    // Obtener los materiales necesarios para la ficha técnica seleccionada
    $sqlFicha = "SELECT material_id, cantidad_requerida FROM ficha_materiales WHERE ficha_id = ?";
    $stmtFicha = $conn->prepare($sqlFicha);
    $stmtFicha->bind_param("i", $ficha_id);
    $stmtFicha->execute();
    $resultFicha = $stmtFicha->get_result();

    if ($resultFicha->num_rows === 0) {
        echo json_encode([
            "success" => false,
            "error" => "No se encontraron materiales asociados a la ficha técnica seleccionada."
        ]);
        exit;
    }

    // Verificar inventario suficiente para cada material
    while ($material = $resultFicha->fetch_assoc()) {
        $material_id = $material['material_id'];
        $cantidad_necesaria = $material['cantidad_requerida'] * $cantidad_piezas;

        $sqlInventario = "SELECT cantidad_disp FROM inventario WHERE id = ?";
        $stmtInv = $conn->prepare($sqlInventario);
        $stmtInv->bind_param("i", $material_id);
        $stmtInv->execute();
        $resultInv = $stmtInv->get_result();
        $inv = $resultInv->fetch_assoc();

        if (!$inv || $inv['cantidad_disp'] < $cantidad_necesaria) {
            echo json_encode([
                "success" => false,
                "error" => "Inventario insuficiente para el material ID: $material_id"
            ]);
            exit;
        }
    }

    // Registrar la orden de producción
    $sqlInsert = "INSERT INTO Orden_Produccion (pro_fic_idfic, admin_id, fecha_inicio, fecha_fin, estado)
                  VALUES (?, ?, ?, ?, 'En proceso')";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("iiss", $ficha_id, $admin_id, $fecha_inicio, $fecha_fin);
    $stmtInsert->execute();

    // Obtener el ID de la orden recién creada
    $idord = $conn->insert_id;

    // Insertar en Produccion_Piezas
    $sqlPieza = "INSERT INTO Produccion_Piezas (idord, talla, cantidad)
                 VALUES (?, ?, ?)";
    $stmtPieza = $conn->prepare($sqlPieza);
    $stmtPieza->bind_param("isi", $idord, $tamaño, $cantidad_piezas);
    $stmtPieza->execute();

    echo json_encode([
        "success" => true,
        "message" => "Corte de tela iniciado correctamente.",
        "orden_id" => $idord
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>
