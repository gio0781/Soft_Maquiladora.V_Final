<?php
header('Content-Type: application/json');
require_once '../conexion.php';

$usuario = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';

if (empty($usuario)) {
    echo json_encode(['success' => false, 'error' => 'Usuario no especificado']);
    exit;
}

try {
    // Paso 1: obtener el ID del usuario
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE LOWER(usuario) = LOWER(?) AND jerarquia = 2");
    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$row = $result->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Usuario no encontrado o no es operadora']);
        exit;
    }

    $usuario_id = $row['id'];

    // Paso 2: obtener el ID del operador asociado
    $stmt = $conn->prepare("SELECT id FROM operador WHERE usuario_id = ?");
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if (!$row = $result->fetch_assoc()) {
        echo json_encode(['success' => false, 'error' => 'Operador no encontrado']);
        exit;
    }

    $operador_id = $row['id'];

    // Paso 3: buscar la tarea asignada
    $sql = "SELECT 
                a.id,
                a.tarea_asignada as tarea,
                a.descripcion,
                a.linea_produccion,
                a.pedido,
                a.fecha_asignacion,
                a.estado,
                a.prioridad,
                a.meta_diaria,
                a.progreso_actual,
                t.descripcion as tarea_descripcion_completa
            FROM Asig_Tareas a
            LEFT JOIN tareas t ON a.tarea_asignada = t.nombre
            WHERE a.operador_id = ?
            AND a.estado IN ('asignada', 'en_proceso')
            ORDER BY a.fecha_asignacion DESC 
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $operador_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'tarea' => $row['tarea'],
            'descripcion' => $row['tarea_descripcion_completa'] ?: $row['descripcion'],
            'linea_produccion' => $row['linea_produccion'] ?: 'Línea General',
            'pedido' => $row['pedido'],
            'estado' => $row['estado'] ?: 'asignada',
            'prioridad' => $row['prioridad'] ?: 'media',
            'meta_diaria' => (int)($row['meta_diaria'] ?: 100),
            'progreso_actual' => (int)($row['progreso_actual'] ?: 0),
            'id' => (int)($row['id'])
        ]);
    } else {
        echo json_encode(['success' => true, 'tarea' => null]);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>