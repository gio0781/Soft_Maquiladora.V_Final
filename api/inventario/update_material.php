<?php
// api/inventario/update_material.php - VERSIÓN CORREGIDA
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

// Log para debugging
error_log("=== INICIO UPDATE_MATERIAL.PHP ===");
error_log("Método: " . $_SERVER['REQUEST_METHOD']);

// Obtener datos JSON del cuerpo de la solicitud
$input_raw = file_get_contents('php://input');
error_log("Input raw: " . $input_raw);

$input = json_decode($input_raw, true);
error_log("Input parseado: " . json_encode($input));

if (!$input) {
    error_log("ERROR: Datos JSON inválidos");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos o vacíos']);
    exit;
}

$accion = $input['accion'] ?? null;
error_log("Acción recibida: " . $accion);

if (!$accion) {
    error_log("ERROR: Acción no especificada");
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
    exit;
}

try {
    switch ($accion) {
        case 'agregar':
            error_log("Ejecutando AGREGAR material");
            agregarMaterial($conn, $input);
            break;

        case 'editar':
            error_log("Ejecutando EDITAR material");
            editarMaterial($conn, $input);
            break;

        case 'eliminar':
            error_log("Ejecutando ELIMINAR material");
            eliminarMaterial($conn, $input);
            break;

        default:
            throw new Exception("Acción no válida: $accion");
    }

} catch (Exception $e) {
    error_log("EXCEPCIÓN: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
error_log("=== FIN UPDATE_MATERIAL.PHP ===");

// === FUNCIONES ESPECÍFICAS ===

function agregarMaterial($conn, $input) {
    error_log("FUNCIÓN agregarMaterial - Input: " . json_encode($input));
    
    // Validar campos requeridos con logging detallado
    $required_fields = ['Material', 'cantidad_disp', 'cantidad_min', 'estado'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            error_log("Campo faltante: $field");
            throw new Exception("Campo requerido faltante: $field");
        }
        if (trim($input[$field]) === '') {
            error_log("Campo vacío: $field");
            throw new Exception("Campo requerido vacío: $field");
        }
    }

    $material = trim($input['Material']);
    $cantidad_disp = (int)$input['cantidad_disp'];
    $cantidad_min = (int)$input['cantidad_min'];
    $estado = trim($input['estado']);
    $ord_idord = isset($input['ord_idord']) ? (int)$input['ord_idord'] : null;

    error_log("Datos procesados: Material='$material', cantidad_disp=$cantidad_disp, cantidad_min=$cantidad_min, estado='$estado'");

    // Validaciones adicionales
    if ($cantidad_disp < 0 || $cantidad_min < 0) {
        throw new Exception("Las cantidades no pueden ser negativas");
    }
    
    if (strlen($material) < 2) {
        throw new Exception("El nombre del material debe tener al menos 2 caracteres");
    }

    // Verificar si el material ya existe
    $stmt_check = $conn->prepare("SELECT id FROM Inventario WHERE LOWER(Material) = LOWER(?)");
    if (!$stmt_check) {
        error_log("Error preparando consulta de verificación: " . $conn->error);
        throw new Exception("Error preparando consulta de verificación");
    }
    
    $stmt_check->bind_param("s", $material);
    $stmt_check->execute();
    
    if ($stmt_check->get_result()->num_rows > 0) {
        throw new Exception("Ya existe un material con ese nombre");
    }

    // Insertar nuevo material
    $sql = "INSERT INTO Inventario (Material, cantidad_disp, cantidad_min, estado, ord_idord) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        error_log("Error preparando INSERT: " . $conn->error);
        throw new Exception("Error preparando consulta de inserción");
    }
    
    $stmt->bind_param("siisi", $material, $cantidad_disp, $cantidad_min, $estado, $ord_idord);
    
    if ($stmt->execute()) {
        $nuevo_id = $conn->insert_id;
        error_log("Material agregado exitosamente con ID: $nuevo_id");
        
        echo json_encode([
            'success' => true, 
            'message' => "Material '$material' agregado correctamente",
            'id' => $nuevo_id,
            'data' => [
                'id' => $nuevo_id,
                'material' => $material,
                'cantidad_disp' => $cantidad_disp,
                'cantidad_min' => $cantidad_min,
                'estado' => $estado
            ]
        ]);
    } else {
        error_log("Error ejecutando INSERT: " . $stmt->error);
        throw new Exception("Error al insertar material: " . $stmt->error);
    }
}

function editarMaterial($conn, $input) {
    error_log("FUNCIÓN editarMaterial - Input: " . json_encode($input));
    
    // Validar campos requeridos
    $required_fields = ['id', 'Material', 'cantidad_disp', 'cantidad_min', 'estado'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    $id = (int)$input['id'];
    $material = trim($input['Material']);
    $cantidad_disp = (int)$input['cantidad_disp'];
    $cantidad_min = (int)$input['cantidad_min'];
    $estado = trim($input['estado']);
    $ord_idord = isset($input['ord_idord']) ? (int)$input['ord_idord'] : null;

    // Validaciones
    if ($cantidad_disp < 0 || $cantidad_min < 0) {
        throw new Exception("Las cantidades no pueden ser negativas");
    }

    // Verificar que el material existe
    $stmt_check = $conn->prepare("SELECT Material FROM Inventario WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Material no encontrado");
    }

    // Verificar nombre único (excluyendo el actual)
    $stmt_unique = $conn->prepare("SELECT id FROM Inventario WHERE LOWER(Material) = LOWER(?) AND id != ?");
    $stmt_unique->bind_param("si", $material, $id);
    $stmt_unique->execute();
    if ($stmt_unique->get_result()->num_rows > 0) {
        throw new Exception("Ya existe otro material con ese nombre");
    }

    // Actualizar material
    $sql = "UPDATE Inventario SET Material = ?, cantidad_disp = ?, cantidad_min = ?, estado = ?, ord_idord = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("siisii", $material, $cantidad_disp, $cantidad_min, $estado, $ord_idord, $id);
    
    if ($stmt->execute()) {
        error_log("Material editado exitosamente - ID: $id");
        
        echo json_encode([
            'success' => true, 
            'message' => "Material '$material' actualizado correctamente",
            'data' => [
                'id' => $id,
                'material' => $material,
                'cantidad_disp' => $cantidad_disp,
                'cantidad_min' => $cantidad_min,
                'estado' => $estado
            ]
        ]);
    } else {
        error_log("Error actualizando material: " . $stmt->error);
        throw new Exception("Error al actualizar material: " . $stmt->error);
    }
}

function eliminarMaterial($conn, $input) {
    error_log("FUNCIÓN eliminarMaterial - Input: " . json_encode($input));
    
    if (!isset($input['id'])) {
        throw new Exception("ID requerido para eliminar");
    }

    $id = (int)$input['id'];
    
    // Verificar que el material existe
    $stmt_check = $conn->prepare("SELECT Material FROM Inventario WHERE id = ?");
    $stmt_check->bind_param("i", $id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Material no encontrado");
    }
    
    $material_info = $result->fetch_assoc();
    $nombre_material = $material_info['Material'];
    
    // Eliminar material
    $sql = "DELETE FROM Inventario WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            error_log("Material eliminado exitosamente - ID: $id, Nombre: $nombre_material");
            
            echo json_encode([
                'success' => true, 
                'message' => "Material '$nombre_material' eliminado correctamente"
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'error' => "No se encontró el material para eliminar"
            ]);
        }
    } else {
        error_log("Error eliminando material: " . $stmt->error);
        throw new Exception("Error al eliminar material: " . $stmt->error);
    }
}
?>