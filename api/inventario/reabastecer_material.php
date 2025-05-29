<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Si es una petición OPTIONS, terminar aquí
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

try {
    // Información de debug
    $debug_info = array(
        "success" => false,
        "method" => $_SERVER['REQUEST_METHOD'],
        "timestamp" => date('Y-m-d H:i:s')
    );

    // Si es GET, mostrar información de la API
    if ($_SERVER['REQUEST_METHOD'] == 'GET') {
        $debug_info["success"] = true;
        $debug_info["message"] = "API de reabastecimiento funcionando correctamente";
        $debug_info["note"] = "Para usar la API, envía una petición POST con los datos requeridos";
        $debug_info["required_fields"] = array(
            "id_material" => "ID del material a reabastecer",
            "cantidad" => "Cantidad a agregar"
        );
        $debug_info["optional_fields"] = array(
            "comentario" => "Comentario del reabastecimiento",
            "usuario" => "Usuario que realiza el reabastecimiento"
        );
        echo json_encode($debug_info, JSON_PRETTY_PRINT);
        exit;
    }

    // Verificar método POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Este endpoint requiere método POST");
    }

    // Obtener datos del POST
    $id_material = isset($_POST['id_material']) ? intval($_POST['id_material']) : null;
    $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : null;
    $comentario = isset($_POST['comentario']) ? $_POST['comentario'] : 'Reabastecimiento via sistema';
    $usuario = isset($_POST['usuario']) ? $_POST['usuario'] : 'Sistema';

    // Validar campos requeridos
    if (!$id_material || !$cantidad) {
        throw new Exception("Campos requeridos faltantes: id_material y cantidad son obligatorios");
    }

    // Validar datos
    if ($id_material <= 0) {
        throw new Exception("ID de material inválido");
    }
    if ($cantidad <= 0) {
        throw new Exception("La cantidad debe ser mayor a 0");
    }
    if ($cantidad > 50000) {
        throw new Exception("La cantidad máxima por reabastecimiento es 50,000");
    }

    // Verificar que el material existe y obtener datos actuales
    $sql_check = "SELECT id, Material, cantidad_disp, cantidad_min, estado FROM Inventario WHERE id = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("i", $id_material);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();

    if ($result_check->num_rows === 0) {
        throw new Exception("Material con ID $id_material no encontrado");
    }

    $material_actual = $result_check->fetch_assoc();
    $cantidad_anterior = (int)$material_actual['cantidad_disp'];
    $cantidad_nueva = $cantidad_anterior + $cantidad;
    $estado_anterior = $material_actual['estado'];

    // Determinar nuevo estado basado en la cantidad mínima
    $cantidad_min = (int)$material_actual['cantidad_min'];
    $estado_nuevo = $estado_anterior;

    if ($cantidad_nueva === 0) {
        $estado_nuevo = 'Agotado';
    } elseif ($cantidad_nueva <= $cantidad_min) {
        $estado_nuevo = 'Crítico';
    } else {
        $estado_nuevo = 'Disponible';
    }

    // Actualizar inventario
    $sql_update = "UPDATE Inventario SET cantidad_disp = ?, estado = ? WHERE id = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("isi", $cantidad_nueva, $estado_nuevo, $id_material);

    if (!$stmt_update->execute()) {
        throw new Exception("Error al actualizar inventario: " . $stmt_update->error);
    }

    // Crear tabla de historial si no existe
    $sql_create_historial = "CREATE TABLE IF NOT EXISTS historial_reabastecimiento (
        id INT AUTO_INCREMENT PRIMARY KEY,
        material_id INT NOT NULL,
        material_nombre VARCHAR(255) NOT NULL,
        cantidad_anterior INT NOT NULL,
        cantidad_agregada INT NOT NULL,
        cantidad_nueva INT NOT NULL,
        estado_anterior VARCHAR(50),
        estado_nuevo VARCHAR(50),
        comentario TEXT,
        usuario VARCHAR(100),
        fecha_reabastecimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (material_id) REFERENCES Inventario(id) ON DELETE CASCADE
    )";
    $conn->query($sql_create_historial);

    // Registrar en historial
    $sql_historial = "INSERT INTO historial_reabastecimiento 
                      (material_id, material_nombre, cantidad_anterior, cantidad_agregada, cantidad_nueva, 
                       estado_anterior, estado_nuevo, comentario, usuario) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_historial = $conn->prepare($sql_historial);
    $stmt_historial->bind_param("isiiiisss", 
        $id_material, 
        $material_actual['Material'], 
        $cantidad_anterior, 
        $cantidad, 
        $cantidad_nueva, 
        $estado_anterior, 
        $estado_nuevo, 
        $comentario, 
        $usuario
    );
    $stmt_historial->execute();

    // Respuesta exitosa
    $debug_info["success"] = true;
    $debug_info["message"] = "Reabastecimiento registrado correctamente";
    $debug_info["data"] = array(
        "material_id" => $id_material,
        "material" => $material_actual['Material'],
        "cantidad_anterior" => $cantidad_anterior,
        "cantidad_agregada" => $cantidad,
        "cantidad_nueva" => $cantidad_nueva,
        "estado_anterior" => $estado_anterior,
        "estado_nuevo" => $estado_nuevo,
        "comentario" => $comentario,
        "usuario" => $usuario,
        "fecha" => date('Y-m-d H:i:s')
    );

    echo json_encode($debug_info, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $debug_info["success"] = false;
    $debug_info["error"] = $e->getMessage();
    
    // Información adicional para debug
    if (isset($_POST)) {
        $debug_info["received_post_data"] = $_POST;
    }
    
    http_response_code(400);
    echo json_encode($debug_info, JSON_PRETTY_PRINT);
}

$conn->close();
?>