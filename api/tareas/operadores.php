<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // LISTAR OPERADORES CON ESTADO CORRECTO
    try {
        $sql = "SELECT 
                    o.id, 
                    o.nombre, 
                    o.especialidad, 
                    o.tarea_asignada,
                    o.usuario_id,
                    o.estado,
                    o.turno,
                    o.fecha_ingreso,
                    at.id as asignacion_id,
                    at.linea_produccion,
                    at.pedido,
                    at.prioridad,
                    at.meta_diaria,
                    at.progreso_actual,
                    at.fecha_asignacion,
                    at.estado as estado_tarea
                FROM operador o 
                LEFT JOIN Asig_Tareas at ON o.id = at.operador_id 
                    AND at.estado IN ('asignada', 'en_proceso')
                ORDER BY o.id ASC";
        
        $result = $conn->query($sql);

        if (!$result) {
            throw new Exception("Error en consulta: " . $conn->error);
        }

        $operadores = [];
        while ($row = $result->fetch_assoc()) {
            // Determinar estado real basado en tarea asignada
            $estadoReal = 'disponible';
            if ($row['tarea_asignada'] && $row['asignacion_id']) {
                $estadoReal = 'ocupado';
            } else if ($row['estado']) {
                $estadoReal = $row['estado'];
            }

            $operadores[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre'],
                'especialidad' => $row['especialidad'],
                'tarea_asignada' => $row['tarea_asignada'],
                'usuario_id' => $row['usuario_id'] ? (int)$row['usuario_id'] : null,
                'estado' => $estadoReal,
                'turno' => $row['turno'] ?? 'matutino',
                'fecha_ingreso' => $row['fecha_ingreso'],
                'asignacion_id' => $row['asignacion_id'] ? (int)$row['asignacion_id'] : null,
                'linea_produccion' => $row['linea_produccion'],
                'pedido' => $row['pedido'],
                'prioridad' => $row['prioridad'],
                'meta_diaria' => $row['meta_diaria'] ? (int)$row['meta_diaria'] : null,
                'progreso_actual' => $row['progreso_actual'] ? (int)$row['progreso_actual'] : 0,
                'fecha_asignacion' => $row['fecha_asignacion'],
                'estado_tarea' => $row['estado_tarea']
            ];
        }

        echo json_encode([
            'success' => true,
            'data' => $operadores,
            'total' => count($operadores)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }

} elseif ($method === 'POST') {
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
            case 'crear':
                crearOperador($conn, $input);
                break;
                
            case 'actualizar':
                actualizarOperador($conn, $input);
                break;

            case 'eliminar':
                eliminarOperador($conn, $input);
                break;

            case 'asignar_tarea':
                asignarTarea($conn, $input);
                break;

            case 'remover_tarea':
                removerTarea($conn, $input);
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

// === FUNCIONES AUXILIARES ===

function crearOperador($conn, $input) {
    $required_fields = ['nombre', 'especialidad'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    $nombre = trim($input['nombre']);
    $especialidad = trim($input['especialidad']);
    $turno = isset($input['turno']) ? trim($input['turno']) : 'matutino';
    $fecha_ingreso = isset($input['fecha_ingreso']) ? $input['fecha_ingreso'] : date('Y-m-d');
    $estado = isset($input['estado']) ? trim($input['estado']) : 'disponible';
    
    // Datos del usuario si se proporcionan
    $crear_usuario = isset($input['crear_usuario']) && $input['crear_usuario'] === true;
    $usuario_data = isset($input['usuario_data']) ? $input['usuario_data'] : null;

    // Iniciar transacción
    $conn->begin_transaction();

    try {
        // Validar que el nombre no exista
        $sqlCheck = "SELECT id FROM operador WHERE nombre = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("s", $nombre);
        $stmtCheck->execute();
        
        if ($stmtCheck->get_result()->num_rows > 0) {
            throw new Exception("Ya existe un operador con ese nombre");
        }

        $usuario_id = null;

        // Crear usuario si se solicita
        if ($crear_usuario && $usuario_data) {
            $usuario = $usuario_data['usuario'];
            $password = $usuario_data['password'];
            
            // Validar que el usuario no exista
            $sqlCheckUser = "SELECT id FROM usuarios WHERE usuario = ?";
            $stmtCheckUser = $conn->prepare($sqlCheckUser);
            $stmtCheckUser->bind_param("s", $usuario);
            $stmtCheckUser->execute();
            
            if ($stmtCheckUser->get_result()->num_rows > 0) {
                throw new Exception("El nombre de usuario ya existe");
            }
            
            // Crear usuario con jerarquía 2 (operador)
            $sqlUser = "INSERT INTO usuarios (usuario, contraseña, jerarquia) VALUES (?, ?, 2)";
            $stmtUser = $conn->prepare($sqlUser);
            $stmtUser->bind_param("ss", $usuario, $password);
            
            if (!$stmtUser->execute()) {
                throw new Exception("Error al crear usuario: " . $stmtUser->error);
            }
            
            $usuario_id = $conn->insert_id;
        }

        // Crear operador
        $sql = "INSERT INTO operador (nombre, especialidad, usuario_id, estado, turno, fecha_ingreso) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisss", $nombre, $especialidad, $usuario_id, $estado, $turno, $fecha_ingreso);
        
        if (!$stmt->execute()) {
            throw new Exception("Error al crear operador: " . $stmt->error);
        }
        
        $newId = $conn->insert_id;
        
        // Crear notificación de bienvenida
        $sqlNotif = "INSERT INTO notificaciones_operadora (operador_id, titulo, mensaje, tipo) 
                     VALUES (?, 'Bienvenida al Sistema', ?, 'info')";
        $mensaje = "Hola $nombre! Bienvenida al nuevo sistema de operadoras. Aquí podrás ver tus tareas, registrar tu progreso y gestionar tu tiempo de trabajo.";
        $stmtNotif = $conn->prepare($sqlNotif);
        $stmtNotif->bind_param("is", $newId, $mensaje);
        $stmtNotif->execute();
        
        // Confirmar transacción
        $conn->commit();
        
        $response = [
            'success' => true, 
            'message' => "Operador '$nombre' creado correctamente",
            'data' => [
                'id' => $newId,
                'nombre' => $nombre,
                'especialidad' => $especialidad,
                'estado' => $estado
            ]
        ];
        
        // Si se creó usuario, agregar información
        if ($crear_usuario && $usuario_id) {
            $response['usuario_creado'] = true;
            $response['usuario_info'] = [
                'usuario' => $usuario,
                'password' => $password,
                'mensaje' => 'Usuario creado exitosamente. Guarde estas credenciales de forma segura.'
            ];
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function actualizarOperador($conn, $input) {
    $required_fields = ['id', 'nombre', 'especialidad'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || trim($input[$field]) === '') {
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    $id = (int)$input['id'];
    $nombre = trim($input['nombre']);
    $especialidad = trim($input['especialidad']);

    // Verificar que el operador existe
    $sqlCheck = "SELECT nombre FROM operador WHERE id = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    $result = $stmtCheck->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Operador no encontrado");
    }
    
    $operadorAnterior = $result->fetch_assoc();

    // Verificar que el nombre no esté duplicado
    $sqlDup = "SELECT id FROM operador WHERE nombre = ? AND id != ?";
    $stmtDup = $conn->prepare($sqlDup);
    $stmtDup->bind_param("si", $nombre, $id);
    $stmtDup->execute();
    
    if ($stmtDup->get_result()->num_rows > 0) {
        throw new Exception("Ya existe otro operador con ese nombre");
    }

    // Actualizar operador
    $sql = "UPDATE operador SET nombre = ?, especialidad = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssi", $nombre, $especialidad, $id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true, 
            'message' => "Operador actualizado correctamente",
            'data' => [
                'id' => $id,
                'nombre_anterior' => $operadorAnterior['nombre'],
                'nombre_nuevo' => $nombre,
                'especialidad' => $especialidad
            ]
        ]);
    } else {
        throw new Exception("Error al actualizar: " . $stmt->error);
    }
}

function eliminarOperador($conn, $input) {
    if (!isset($input['id'])) {
        throw new Exception("ID requerido para eliminar");
    }

    $id = (int)$input['id'];

    $conn->begin_transaction();
    
    try {
        // Verificar que el operador no tenga tareas asignadas
        $sqlCheck = "SELECT nombre, tarea_asignada FROM operador WHERE id = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Operador no encontrado");
        }

        $operador = $result->fetch_assoc();
        if ($operador['tarea_asignada']) {
            throw new Exception("No se puede eliminar: el operador tiene una tarea asignada. Primero remueva la tarea.");
        }

        // Eliminar notificaciones del operador
        $sqlDelNotif = "DELETE FROM notificaciones_operadora WHERE operador_id = ?";
        $stmtDelNotif = $conn->prepare($sqlDelNotif);
        $stmtDelNotif->bind_param("i", $id);
        $stmtDelNotif->execute();

        // Eliminar operador
        $sql = "DELETE FROM operador WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode([
                'success' => true, 
                'message' => "Operador '{$operador['nombre']}' eliminado correctamente"
            ]);
        } else {
            throw new Exception("Error al eliminar: " . $stmt->error);
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function asignarTarea($conn, $input) {
    $required_fields = ['operador_id', 'tarea_id'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            throw new Exception("Campo requerido faltante: $field");
        }
    }

    $operador_id = (int)$input['operador_id'];
    $tarea_id = (int)$input['tarea_id'];
    $linea_produccion = $input['linea_produccion'] ?? 'Línea General';
    $pedido = $input['pedido'] ?? '';
    $prioridad = $input['prioridad'] ?? 'media';
    $meta_diaria = $input['meta_diaria'] ?? 100;

    $conn->begin_transaction();

    try {
        // Obtener datos del operador
        $sqlOperador = "SELECT nombre, tarea_asignada, estado FROM operador WHERE id = ?";
        $stmtOperador = $conn->prepare($sqlOperador);
        $stmtOperador->bind_param("i", $operador_id);
        $stmtOperador->execute();
        $resultOperador = $stmtOperador->get_result();

        if ($resultOperador->num_rows === 0) {
            throw new Exception("Operador no encontrado");
        }

        $operador = $resultOperador->fetch_assoc();
        if ($operador['tarea_asignada']) {
            throw new Exception("El operador ya tiene una tarea asignada: " . $operador['tarea_asignada']);
        }

        // Obtener datos de la tarea
        $sqlTarea = "SELECT nombre, descripcion FROM tareas WHERE id = ?";
        $stmtTarea = $conn->prepare($sqlTarea);
        $stmtTarea->bind_param("i", $tarea_id);
        $stmtTarea->execute();
        $resultTarea = $stmtTarea->get_result();

        if ($resultTarea->num_rows === 0) {
            throw new Exception("Tarea no encontrada");
        }

        $tarea = $resultTarea->fetch_assoc();

        // Insertar en Asig_Tareas
        $sqlAsignacion = "INSERT INTO Asig_Tareas (
            operador_id, 
            nombre_operadora, 
            tarea_asignada, 
            descripcion, 
            linea_produccion, 
            pedido, 
            estado, 
            prioridad, 
            meta_diaria, 
            progreso_actual, 
            fecha_asignacion
        ) VALUES (?, ?, ?, ?, ?, ?, 'asignada', ?, ?, 0, NOW())";
        
        $stmtAsignacion = $conn->prepare($sqlAsignacion);
        $stmtAsignacion->bind_param("issssssi", 
            $operador_id, 
            $operador['nombre'], 
            $tarea['nombre'], 
            $tarea['descripcion'], 
            $linea_produccion, 
            $pedido, 
            $prioridad, 
            $meta_diaria
        );
        
        if (!$stmtAsignacion->execute()) {
            throw new Exception("Error al crear asignación: " . $stmtAsignacion->error);
        }

        $asignacion_id = $conn->insert_id;

        // CRUCIAL: Actualizar operador con tarea asignada Y estado ocupado
        $sqlUpdate = "UPDATE operador 
                      SET tarea_asignada = ?, 
                          estado = 'ocupado',
                          updated_at = NOW() 
                      WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("si", $tarea['nombre'], $operador_id);
        
        if (!$stmtUpdate->execute()) {
            throw new Exception("Error al actualizar operador: " . $stmtUpdate->error);
        }

        // Crear notificación para la operadora
        $sqlNotif = "INSERT INTO notificaciones_operadora (operador_id, titulo, mensaje, tipo) 
                     VALUES (?, 'Nueva Tarea Asignada', ?, 'info')";
        $mensajeNotif = "Se te ha asignado una nueva tarea: {$tarea['nombre']}. Línea: $linea_produccion. Meta diaria: $meta_diaria piezas.";
        $stmtNotif = $conn->prepare($sqlNotif);
        $stmtNotif->bind_param("is", $operador_id, $mensajeNotif);
        $stmtNotif->execute();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Tarea '{$tarea['nombre']}' asignada correctamente a {$operador['nombre']}",
            'data' => [
                'asignacion_id' => $asignacion_id,
                'operador_id' => $operador_id,
                'operador_nombre' => $operador['nombre'],
                'tarea_nombre' => $tarea['nombre'],
                'linea_produccion' => $linea_produccion,
                'pedido' => $pedido,
                'prioridad' => $prioridad,
                'meta_diaria' => $meta_diaria,
                'estado_nuevo' => 'ocupado'
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function removerTarea($conn, $input) {
    if (!isset($input['operador_id'])) {
        throw new Exception("ID del operador requerido");
    }

    $operador_id = (int)$input['operador_id'];

    $conn->begin_transaction();

    try {
        // Obtener datos del operador
        $sqlCheck = "SELECT nombre, tarea_asignada FROM operador WHERE id = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("i", $operador_id);
        $stmtCheck->execute();
        $result = $stmtCheck->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Operador no encontrado");
        }

        $operador = $result->fetch_assoc();
        if (!$operador['tarea_asignada']) {
            throw new Exception("El operador no tiene tarea asignada");
        }

        // Finalizar asignaciones activas
        $sqlFinalizarAsignacion = "UPDATE Asig_Tareas 
                                   SET estado = 'cancelada', 
                                       fecha_fin = NOW(),
                                       observaciones = CONCAT(COALESCE(observaciones, ''), 'Tarea removida por administrador el ', NOW())
                                   WHERE operador_id = ? 
                                   AND estado IN ('asignada', 'en_proceso')";
        
        $stmtFinalizar = $conn->prepare($sqlFinalizarAsignacion);
        $stmtFinalizar->bind_param("i", $operador_id);
        $stmtFinalizar->execute();

        // CRUCIAL: Limpiar tarea del operador Y actualizar estado a disponible
        $sqlLimpiar = "UPDATE operador 
                       SET tarea_asignada = NULL, 
                           estado = 'disponible',
                           updated_at = NOW() 
                       WHERE id = ?";
        $stmtLimpiar = $conn->prepare($sqlLimpiar);
        $stmtLimpiar->bind_param("i", $operador_id);
        
        if (!$stmtLimpiar->execute()) {
            throw new Exception("Error al remover tarea: " . $stmtLimpiar->error);
        }

        // Crear notificación
        $sqlNotif = "INSERT INTO notificaciones_operadora (operador_id, titulo, mensaje, tipo) 
                     VALUES (?, 'Tarea Removida', ?, 'warning')";
        $mensajeNotif = "Tu tarea '{$operador['tarea_asignada']}' ha sido removida por el administrador. Ahora estás disponible para nuevas asignaciones.";
        $stmtNotif = $conn->prepare($sqlNotif);
        $stmtNotif->bind_param("is", $operador_id, $mensajeNotif);
        $stmtNotif->execute();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => "Tarea removida correctamente de {$operador['nombre']}",
            'data' => [
                'operador_id' => $operador_id,
                'operador_nombre' => $operador['nombre'],
                'tarea_anterior' => $operador['tarea_asignada'],
                'estado_nuevo' => 'disponible'
            ]
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}
?>