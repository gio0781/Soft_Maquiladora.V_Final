<?php
// api/admin/get_notificaciones.php - Obtener notificaciones para administradores
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // OBTENER NOTIFICACIONES
    $solo_no_leidas = isset($_GET['solo_no_leidas']) ? $_GET['solo_no_leidas'] === 'true' : false;
    $limite = isset($_GET['limite']) ? (int)$_GET['limite'] : 20;
    
    try {
        // Crear tabla si no existe
        $conn->query("CREATE TABLE IF NOT EXISTS notificaciones_admin (
            id INT PRIMARY KEY AUTO_INCREMENT,
            titulo VARCHAR(100) NOT NULL,
            mensaje TEXT NOT NULL,
            tipo ENUM('info','success','warning','error') DEFAULT 'info',
            operadora_nombre VARCHAR(100),
            tarea_completada VARCHAR(100),
            leida TINYINT(1) DEFAULT 0,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Construir consulta
        $sql = "SELECT 
                    id,
                    titulo,
                    mensaje,
                    tipo,
                    operadora_nombre,
                    tarea_completada,
                    leida,
                    fecha_creacion
                FROM notificaciones_admin ";
        
        $params = [];
        $param_types = "";
        
        if ($solo_no_leidas) {
            $sql .= " WHERE leida = 0";
        }
        
        $sql .= " ORDER BY fecha_creacion DESC LIMIT ?";
        $params[] = $limite;
        $param_types .= "i";
        
        $stmt = $conn->prepare($sql);
        if (!empty($param_types)) {
            $stmt->bind_param($param_types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notificaciones = [];
        while ($row = $result->fetch_assoc()) {
            $notificaciones[] = [
                'id' => (int)$row['id'],
                'titulo' => $row['titulo'],
                'mensaje' => $row['mensaje'],
                'tipo' => $row['tipo'],
                'operadora_nombre' => $row['operadora_nombre'],
                'tarea_completada' => $row['tarea_completada'],
                'leida' => (bool)$row['leida'],
                'fecha_creacion' => $row['fecha_creacion'],
                'tiempo_relativo' => calcularTiempoRelativo($row['fecha_creacion'])
            ];
        }
        
        // Contar no leídas
        $sql_count = "SELECT COUNT(*) as no_leidas FROM notificaciones_admin WHERE leida = 0";
        $stmt_count = $conn->prepare($sql_count);
        $stmt_count->execute();
        $count_result = $stmt_count->get_result();
        $no_leidas = $count_result->fetch_assoc()['no_leidas'];
        
        echo json_encode([
            'success' => true,
            'notificaciones' => $notificaciones,
            'total_no_leidas' => (int)$no_leidas,
            'total_mostradas' => count($notificaciones)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }

} elseif ($method === 'POST') {
    // MARCAR COMO LEÍDA
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode([
            'success' => false,
            'error' => 'Datos JSON inválidos'
        ]);
        exit;
    }
    
    $accion = $input['accion'] ?? null;
    
    try {
        switch ($accion) {
            case 'marcar_leida':
                marcarNotificacionLeida($conn, $input);
                break;
                
            case 'marcar_todas_leidas':
                marcarTodasLeidas($conn);
                break;
                
            case 'eliminar':
                eliminarNotificacion($conn, $input);
                break;
                
            default:
                throw new Exception("Acción no válida: $accion");
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

$conn->close();

// === FUNCIONES AUXILIARES ===

function calcularTiempoRelativo($fecha) {
    $ahora = new DateTime();
    $fecha_notif = new DateTime($fecha);
    $diff = $ahora->diff($fecha_notif);
    
    if ($diff->days > 0) {
        return $diff->days == 1 ? 'Hace 1 día' : 'Hace ' . $diff->days . ' días';
    } elseif ($diff->h > 0) {
        return $diff->h == 1 ? 'Hace 1 hora' : 'Hace ' . $diff->h . ' horas';
    } elseif ($diff->i > 0) {
        return $diff->i == 1 ? 'Hace 1 minuto' : 'Hace ' . $diff->i . ' minutos';
    } else {
        return 'Hace un momento';
    }
}

function marcarNotificacionLeida($conn, $input) {
    $notificacion_id = isset($input['notificacion_id']) ? (int)$input['notificacion_id'] : 0;
    
    if ($notificacion_id <= 0) {
        throw new Exception('ID de notificación requerido');
    }
    
    $sql = "UPDATE notificaciones_admin SET leida = 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notificacion_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificación marcada como leída'
        ]);
    } else {
        throw new Exception('Error al marcar notificación como leída');
    }
}

function marcarTodasLeidas($conn) {
    $sql = "UPDATE notificaciones_admin SET leida = 1 WHERE leida = 0";
    $stmt = $conn->prepare($sql);
    
    if ($stmt->execute()) {
        $marcadas = $stmt->affected_rows;
        echo json_encode([
            'success' => true,
            'message' => "Se marcaron $marcadas notificaciones como leídas"
        ]);
    } else {
        throw new Exception('Error al marcar todas las notificaciones como leídas');
    }
}

function eliminarNotificacion($conn, $input) {
    $notificacion_id = isset($input['notificacion_id']) ? (int)$input['notificacion_id'] : 0;
    
    if ($notificacion_id <= 0) {
        throw new Exception('ID de notificación requerido');
    }
    
    $sql = "DELETE FROM notificaciones_admin WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $notificacion_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificación eliminada'
        ]);
    } else {
        throw new Exception('Error al eliminar notificación');
    }
}
?>