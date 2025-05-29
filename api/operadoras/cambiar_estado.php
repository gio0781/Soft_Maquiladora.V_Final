<?php
// api/operadoras/cambiar_estado.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'error' => 'Método no permitido'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode([
        'success' => false,
        'error' => 'Datos JSON inválidos'
    ]);
    exit;
}

$nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
$estado = isset($input['estado']) ? trim($input['estado']) : '';

// Validar datos
if (empty($nombre) || empty($estado)) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre y estado son requeridos'
    ]);
    exit;
}

// Validar estados permitidos
$estados_validos = ['disponible', 'ocupado', 'descanso', 'ausente'];
if (!in_array($estado, $estados_validos)) {
    echo json_encode([
        'success' => false,
        'error' => 'Estado no válido'
    ]);
    exit;
}

try {
    // Verificar que la operadora existe
    $sql_check = "SELECT id, estado FROM operador WHERE nombre = ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("s", $nombre);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Operadora no encontrada'
        ]);
        exit;
    }
    
    $operadora = $result->fetch_assoc();
    $estado_anterior = $operadora['estado'];
    
    // Si el estado es el mismo, no hacer nada
    if ($estado_anterior === $estado) {
        echo json_encode([
            'success' => true,
            'message' => 'Estado sin cambios',
            'estado_actual' => $estado
        ]);
        exit;
    }
    
    // Actualizar estado
    $sql_update = "UPDATE operador SET estado = ?, updated_at = CURRENT_TIMESTAMP WHERE nombre = ?";
    $stmt_update = $conn->prepare($sql_update);
    $stmt_update->bind_param("ss", $estado, $nombre);
    
    if ($stmt_update->execute()) {
        // Registrar el cambio de estado en un log (opcional)
        $sql_log = "INSERT INTO estado_log (operador_id, estado_anterior, estado_nuevo, fecha_cambio) 
                    VALUES (?, ?, ?, NOW())";
        
        // Crear tabla de log si no existe
        $conn->query("CREATE TABLE IF NOT EXISTS estado_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            operador_id INT NOT NULL,
            estado_anterior VARCHAR(20),
            estado_nuevo VARCHAR(20) NOT NULL,
            fecha_cambio TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (operador_id) REFERENCES operador(id)
        )");
        
        $stmt_log = $conn->prepare($sql_log);
        $stmt_log->bind_param("iss", $operadora['id'], $estado_anterior, $estado);
        $stmt_log->execute();
        
        echo json_encode([
            'success' => true,
            'message' => "Estado cambiado de '$estado_anterior' a '$estado'",
            'estado_anterior' => $estado_anterior,
            'estado_nuevo' => $estado,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } else {
        throw new Exception("Error al actualizar estado: " . $stmt_update->error);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>