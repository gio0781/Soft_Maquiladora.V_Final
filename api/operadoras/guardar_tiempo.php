<?php
// api/operadoras/guardar_tiempo.php
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
$tiempo_ms = isset($input['tiempo_ms']) ? (int)$input['tiempo_ms'] : 0;
$fecha = isset($input['fecha']) ? $input['fecha'] : date('Y-m-d');

// Validar datos
if (empty($nombre) || $tiempo_ms <= 0) {
    echo json_encode([
        'success' => false,
        'error' => 'Nombre y tiempo válido son requeridos'
    ]);
    exit;
}

// Convertir milisegundos a horas decimales
$horas_trabajadas = $tiempo_ms / (1000 * 60 * 60);

try {
    // Obtener ID de la operadora
    $sql_operadora = "SELECT id FROM operador WHERE nombre = ?";
    $stmt_operadora = $conn->prepare($sql_operadora);
    $stmt_operadora->bind_param("s", $nombre);
    $stmt_operadora->execute();
    $result = $stmt_operadora->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode([
            'success' => false,
            'error' => 'Operadora no encontrada'
        ]);
        exit;
    }
    
    $operadora = $result->fetch_assoc();
    $operador_id = $operadora['id'];
    
    // Verificar si ya existe un registro para esta fecha
    $sql_check = "SELECT id, horas_trabajadas FROM productividad_operadora 
                  WHERE operador_id = ? AND fecha = ?";
    
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->bind_param("is", $operador_id, $fecha);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows > 0) {
        // Actualizar registro existente (agregar tiempo)
        $registro_existente = $result_check->fetch_assoc();
        $nuevas_horas = $registro_existente['horas_trabajadas'] + $horas_trabajadas;
        
        $sql_update = "UPDATE productividad_operadora 
                       SET horas_trabajadas = ?
                       WHERE id = ?";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param("di", $nuevas_horas, $registro_existente['id']);
        
        if ($stmt_update->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Tiempo de trabajo actualizado',
                'data' => [
                    'tiempo_agregado_ms' => $tiempo_ms,
                    'tiempo_agregado_horas' => round($horas_trabajadas, 2),
                    'tiempo_total_horas' => round($nuevas_horas, 2),
                    'fecha' => $fecha
                ]
            ]);
        } else {
            throw new Exception("Error al actualizar tiempo: " . $stmt_update->error);
        }
        
    } else {
        // Crear nuevo registro
        $sql_insert = "INSERT INTO productividad_operadora 
                       (operador_id, fecha, horas_trabajadas, piezas_completadas, puntos_calidad, eficiencia_porcentaje) 
                       VALUES (?, ?, ?, 0, 0, 0)";
        
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param("isd", $operador_id, $fecha, $horas_trabajadas);
        
        if ($stmt_insert->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Tiempo de trabajo registrado',
                'data' => [
                    'tiempo_registrado_ms' => $tiempo_ms,
                    'tiempo_registrado_horas' => round($horas_trabajadas, 2),
                    'fecha' => $fecha,
                    'nuevo_registro' => true
                ]
            ]);
        } else {
            throw new Exception("Error al insertar tiempo: " . $stmt_insert->error);
        }
    }
    
    // Registrar en log de tiempo (opcional - para auditoría)
    $sql_log = "INSERT INTO tiempo_log (operador_id, fecha, tiempo_ms, tiempo_horas, timestamp) 
                VALUES (?, ?, ?, ?, NOW())";
    
    // Crear tabla de log si no existe
    $conn->query("CREATE TABLE IF NOT EXISTS tiempo_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        operador_id INT NOT NULL,
        fecha DATE NOT NULL,
        tiempo_ms INT NOT NULL,
        tiempo_horas DECIMAL(5,2) NOT NULL,
        timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (operador_id) REFERENCES operador(id)
    )");
    
    $stmt_log = $conn->prepare($sql_log);
    $stmt_log->bind_param("isid", $operador_id, $fecha, $tiempo_ms, $horas_trabajadas);
    $stmt_log->execute();
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}

$conn->close();
?>