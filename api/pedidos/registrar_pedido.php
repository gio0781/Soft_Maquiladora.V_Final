<?php
// api/pedidos/registrar_pedido.php - VERSIÓN SIMPLIFICADA QUE FUNCIONA
header('Content-Type: application/json');
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../conexion.php';

$input = json_decode(file_get_contents("php://input"), true);

// Validar campos requeridos
$campos_faltantes = [];
if (!isset($input['cliente']) || trim($input['cliente']) === "") $campos_faltantes[] = 'cliente';
if (!isset($input['cantidad']) || trim($input['cantidad']) === "") $campos_faltantes[] = 'cantidad';
if (!isset($input['fecha_registro']) || trim($input['fecha_registro']) === "") $campos_faltantes[] = 'fecha_registro';
if (!isset($input['fecha_entrega']) || trim($input['fecha_entrega']) === "") $campos_faltantes[] = 'fecha_entrega';
if (!isset($input['producto']) || trim($input['producto']) === "") $campos_faltantes[] = 'producto';

if (!empty($campos_faltantes)) {
    echo json_encode(['success' => false, 'error' => 'Campos faltantes o vacíos: ' . implode(", ", $campos_faltantes)]);
    exit;
}

$cliente = trim($input['cliente']);
$cantidad = (int)$input['cantidad'];
$fechaRegistro = $input['fecha_registro'];
$fechaEntrega = $input['fecha_entrega'];
$idfic = (int)$input['producto'];

// Validaciones básicas
if ($cantidad <= 0) {
    echo json_encode(["success" => false, "error" => "La cantidad debe ser mayor a 0"]);
    exit;
}

try {
    // Obtener nombre del producto
    $stmt_ficha = $conn->prepare("SELECT nombre FROM FichaTec WHERE idfic = ?");
    if (!$stmt_ficha) {
        throw new Exception("Error preparando consulta a FichaTec: " . $conn->error);
    }

    $stmt_ficha->bind_param("i", $idfic);
    $stmt_ficha->execute();
    $result_ficha = $stmt_ficha->get_result();
    
    if ($result_ficha->num_rows === 0) {
        throw new Exception("Producto no encontrado con ID: $idfic");
    }
    
    $ficha = $result_ficha->fetch_assoc();
    $pro_nombre = $ficha['nombre'];
    $stmt_ficha->close();

    // Insertar pedido
    $estado = "Confirmado";
    $stmt = $conn->prepare("INSERT INTO Pedidos (nombrecliente, cantidad, estado, fecha_registro, fecha_entrega, pro_nombre) VALUES (?, ?, ?, ?, ?, ?)");
    
    if (!$stmt) {
        throw new Exception("Error preparando INSERT: " . $conn->error);
    }

    $stmt->bind_param("sissss", $cliente, $cantidad, $estado, $fechaRegistro, $fechaEntrega, $pro_nombre);

    if ($stmt->execute()) {
        $pedido_id = $conn->insert_id;
        
        echo json_encode([
            "success" => true,
            "message" => "Pedido registrado exitosamente",
            "pedido_id" => $pedido_id,
            "cliente" => $cliente,
            "producto" => $pro_nombre,
            "cantidad" => $cantidad
        ]);
    } else {
        throw new Exception("Error ejecutando INSERT: " . $stmt->error);
    }

    $stmt->close();

} catch (Exception $e) {
    error_log("Error en registrar_pedido.php: " . $e->getMessage());
    echo json_encode([
        "success" => false, 
        "error" => $e->getMessage()
    ]);
}

$conn->close();
?>