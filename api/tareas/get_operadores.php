<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

// Determinar si es GET (listar) o POST (CRUD)
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // LISTAR OPERADORES
    try {
        $sql = "SELECT 
                    o.id, 
                    o.nombre, 
                    o.especialidad, 
                    o.tarea_asignada, 
                    o.usuario_id,
                    t.descripcion as tarea_descripcion
                FROM operador o 
                LEFT JOIN tareas t ON o.tarea_asignada = t.nombre 
                ORDER BY o.id ASC";
        
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception("Error en consulta: " . $conn->error);
        }

        $operadores = [];
        while ($row = $result->fetch_assoc()) {
            $operadores[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'especialidad' => $row['especialidad'],
                'tarea_asignada' => $row['tarea_asignada'],
                'usuario_id' => $row['usuario_id'] ? (int)$row['usuario_id'] : null,
                'descripcion' => $row['tarea_descripcion']
            ];
        }

        echo json_encode($operadores);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }

} elseif ($method === 'POST') {
    // CRUD OPERADORES
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Datos JSON inválidos']);
        exit;
    }

    $accion = $input['accion'] ?? null;

    if (!$accion) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
        exit;
    }

    try {
        switch ($accion) {
            case 'actualizar':
                $required_fields = ['id', 'nombre', 'especialidad'];
                foreach ($required_fields as $field) {
                    if (!isset($input[$field]) || trim($input[$field]) === '') {
                        throw new Exception("Campo requerido faltante: $field");
                    }
                }

                $id = (int)$input['id'];
                $nombre = trim($input['nombre']);
                $especialidad = trim($input['especialidad']);

                $sql = "UPDATE operador SET nombre = ?, especialidad = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssi", $nombre, $especialidad, $id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Operador actualizado correctamente']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'No se encontró el operador o no hubo cambios']);
                    }
                } else {
                    throw new Exception("Error al actualizar: " . $stmt->error);
                }
                break;

            case 'eliminar':
                if (!isset($input['id'])) {
                    throw new Exception("ID requerido para eliminar");
                }

                $id = (int)$input['id'];

                $sql = "DELETE FROM operador WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    if ($stmt->affected_rows > 0) {
                        echo json_encode(['success' => true, 'message' => 'Operador eliminado correctamente']);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'No se encontró el operador']);
                    }
                } else {
                    throw new Exception("Error al eliminar: " . $stmt->error);
                }
                break;

            case 'asignar_tarea':
                $required_fields = ['id', 'tarea_asignada'];
                foreach ($required_fields as $field) {
                    if (!isset($input[$field])) {
                        throw new Exception("Campo requerido faltante: $field");
                    }
                }

                // Validar jerarquía (simulada desde frontend)
                $jerarquia = $_GET['jerarquia'] ?? null;
                if ((int)$jerarquia !== 1) {
                    throw new Exception("No autorizado para asignar tareas");
                }

                $id = (int)$input['id'];
                $tarea = trim($input['tarea_asignada']);

                $sql = "UPDATE operador SET tarea_asignada = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $tarea, $id);

                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Tarea asignada correctamente']);
                } else {
                    throw new Exception("Error al asignar tarea: " . $stmt->error);
                }
                break;

            default:
                throw new Exception("Acción no válida: $accion");
        }

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}

$conn->close();
?>