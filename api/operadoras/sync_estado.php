<?php
// api/operadoras/sync_estado.php - Sincronización de estados entre admin y operadora
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
    // OBTENER ESTADO ACTUAL DE OPERADORA
    $nombre = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
    
    if (empty($nombre)) {
        echo json_encode([
            'success' => false,
            'error' => 'Nombre de operadora requerido'
        ]);
        exit;
    }
    
    try {
        $sql = "SELECT 
                    o.id,
                    o.nombre,
                    o.especialidad,
                    o.estado,
                    o.tarea_asignada,
                    at.id as asignacion_id,
                    at.tarea_asignada as tarea_activa,
                    at.descripcion,
                    at.linea_produccion,
                    at.pedido,
                    at.estado as estado_tarea,
                    at.prioridad,
                    at.meta_diaria,
                    at.progreso_actual,
                    at.fecha_asignacion
                FROM operador o
                LEFT JOIN Asig_Tareas at ON o.id = at.operador_id 
                    AND at.estado IN ('asignada', 'en_proceso')
                WHERE o.nombre = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'success' => false,
                'error' => 'Operadora no encontrada'
            ]);
            exit;
        }
        
        $data = $result->fetch_assoc();
        
        // Determinar estado real
        $estadoReal = 'disponible';
        if ($data['tarea_asignada'] && $data['asignacion_id']) {
            $estadoReal = 'ocupado';
        } else if ($data['estado']) {
            $estadoReal = $data['estado'];
        }
        
        echo json_encode([
            'success' => true,
            'data' => [
                'id' => (int)$data['id'],
                'nombre' => $data['nombre'],
                'especialidad' => $data['especialidad'],
                'estado' => $estadoReal,
                'tarea_asignada' => $data['tarea_asignada'],
                'tiene_tarea_activa' => !is_null($data['asignacion_id']),
                'tarea_activa' => $data['tarea_activa'],
                'descripcion' => $data['descripcion'],
                'linea_produccion' => $data['linea_produccion'],
                'pedido' => $data['pedido'],
                'estado_tarea' => $data['estado_tarea'],
                'prioridad' => $data['prioridad'],
                'meta_diaria' => (int)$data['meta_diaria'],
                'progreso_actual' => (int)$data['progreso_actual'],
                'fecha_asignacion' => $data['fecha_asignacion'],
                'asignacion_id' => $data['asignacion_id'] ? (int)$data['asignacion_id'] : null
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Error del servidor: ' . $e->getMessage()
        ]);
    }

} elseif ($method === 'POST') {
    // ACTUALIZAR ESTADO DE OPERADORA
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode([
            'success' => false,
            'error' => 'Datos JSON inválidos'
        ]);
        exit;
    }
    
    $accion = $input['accion'] ?? null;
    
    if (!$accion) {
        echo json_encode([
            'success' => false,
            'error' => 'Acción no especificada'
        ]);
        exit;
    }
    
    try {
        switch ($accion) {
            case 'sync_estado':
                syncEstadoOperadora($conn, $input);
                break;
                
            case 'notify_change':
                notificarCambio($conn, $input);
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

function syncEstadoOperadora($conn, $input) {
    $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
    
    if (empty($nombre)) {
        throw new Exception('Nombre de operadora requerido');
    }
    
    // Obtener ID de la operadora
    $sql = "SELECT id, estado, tarea_asignada FROM operador WHERE nombre = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Operadora no encontrada');
    }
    
    $operadora = $result->fetch_assoc();
    $operador_id = $operadora['id'];
    
    // Verificar si hay tarea activa
    $sqlTarea = "SELECT COUNT(*) as tiene_tarea FROM Asig_Tareas 
                 WHERE operador_id = ? AND estado IN ('asignada', 'en_proceso')";
    $stmtTarea = $conn->prepare($sqlTarea);
    $stmtTarea->bind_param("i", $operador_id);
    $stmtTarea->execute();
    $resultTarea = $stmtTarea->get_result();
    $tieneTarea = $resultTarea->fetch_assoc()['tiene_tarea'] > 0;
    
    // Determinar estado correcto
    $estadoCorrect = $tieneTarea ? 'ocupado' : 'disponible';
    
    // Actualizar si es necesario
    if ($operadora['estado'] !== $estadoCorrect) {
        $sqlUpdate = "UPDATE operador SET estado = ?, updated_at = NOW() WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("si", $estadoCorrect, $operador_id);
        $stmtUpdate->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Estado sincronizado correctamente',
            'data' => [
                'estado_anterior' => $operadora['estado'],
                'estado_nuevo' => $estadoCorrect,
                'tiene_tarea' => $tieneTarea
            ]
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'message' => 'Estado ya sincronizado',
            'data' => [
                'estado_actual' => $estadoCorrect,
                'tiene_tarea' => $tieneTarea
            ]
        ]);
    }
}

function notificarCambio($conn, $input) {
    $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
    $tipo_cambio = isset($input['tipo_cambio']) ? $input['tipo_cambio'] : '';
    $mensaje = isset($input['mensaje']) ? $input['mensaje'] : '';
    
    if (empty($nombre) || empty($tipo_cambio)) {
        throw new Exception('Datos incompletos para notificación');
    }
    
    // Obtener ID de la operadora
    $sql = "SELECT id FROM operador WHERE nombre = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $nombre);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Operadora no encontrada');
    }
    
    $operador_id = $result->fetch_assoc()['id'];
    
    // Crear títulos según el tipo de cambio
    $titulos = [
        'tarea_asignada' => '🎯 Nueva Tarea Asignada',
        'tarea_removida' => '🔄 Tarea Removida',
        'estado_cambiado' => '📋 Estado Actualizado',
        'meta_actualizada' => '📈 Meta Actualizada'
    ];
    
    $titulo = $titulos[$tipo_cambio] ?? '📢 Actualización del Sistema';
    
    // Insertar notificación
    $sqlNotif = "INSERT INTO notificaciones_operadora (operador_id, titulo, mensaje, tipo) 
                 VALUES (?, ?, ?, 'info')";
    $stmtNotif = $conn->prepare($sqlNotif);
    $stmtNotif->bind_param("iss", $operador_id, $titulo, $mensaje);
    
    if ($stmtNotif->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Notificación enviada correctamente'
        ]);
    } else {
        throw new Exception('Error al enviar notificación');
    }
}
?>