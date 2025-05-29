<?php
// api/usuarios/verificar_usuario.php - NUEVO ARCHIVO
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

if (!$input || !isset($input['usuario'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Usuario requerido'
    ]);
    exit;
}

$usuario = trim($input['usuario']);

if (strlen($usuario) < 3) {
    echo json_encode([
        'success' => false,
        'disponible' => false,
        'error' => 'Usuario debe tener al menos 3 caracteres'
    ]);
    exit;
}

try {
    $sql = "SELECT COUNT(*) as existe FROM usuarios WHERE LOWER(usuario) = LOWER(?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    $disponible = $row['existe'] == 0;
    
    echo json_encode([
        'success' => true,
        'disponible' => $disponible,
        'usuario' => $usuario
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error verificando usuario: ' . $e->getMessage()
    ]);
}

$conn->close();
?>