<?php
// api/inventario/get_inventario.php - VERSIÓN CORREGIDA
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../conexion.php';

try {
    // Consulta SQL mejorada con mejor manejo de estados
    $sql = "SELECT 
                id, 
                Material, 
                cantidad_disp, 
                cantidad_min, 
                CASE 
                    WHEN estado IS NULL OR TRIM(estado) = '' THEN 
                        CASE 
                            WHEN cantidad_disp = 0 THEN 'Agotado'
                            WHEN cantidad_disp <= cantidad_min THEN 'Crítico'
                            WHEN cantidad_disp <= (cantidad_min * 1.5) THEN 'Bajo'
                            ELSE 'Disponible'
                        END
                    ELSE estado
                END as estado,
                ord_idord,
                -- Campos adicionales para debugging
                CASE 
                    WHEN cantidad_disp = 0 THEN 'AGOTADO'
                    WHEN cantidad_disp <= cantidad_min THEN 'CRÍTICO'
                    WHEN cantidad_disp <= (cantidad_min * 1.5) THEN 'BAJO'
                    ELSE 'NORMAL'
                END as nivel_calculado
            FROM Inventario 
            ORDER BY 
                -- Priorizar materiales críticos primero
                CASE 
                    WHEN cantidad_disp = 0 THEN 1
                    WHEN cantidad_disp <= cantidad_min THEN 2
                    WHEN cantidad_disp <= (cantidad_min * 1.5) THEN 3
                    ELSE 4
                END,
                Material ASC";
    
    $result = $conn->query($sql);

    if (!$result) {
        throw new Exception("Error en la consulta SQL: " . $conn->error);
    }

    $inventario = [];
    $estadisticas = [
        'total_materiales' => 0,
        'agotados' => 0,
        'criticos' => 0,
        'bajos' => 0,
        'disponibles' => 0
    ];

    while ($row = $result->fetch_assoc()) {
        // Asegurar que los valores numéricos sean enteros
        $cantidad_disp = (int)$row['cantidad_disp'];
        $cantidad_min = (int)$row['cantidad_min'];
        $estado = trim($row['estado']) ?: 'Disponible';
        
        // Determinar el estado real basado en cantidades y estado de BD
        $estado_final = determinarEstadoFinal($cantidad_disp, $cantidad_min, $estado);
        
        $item = [
            'id' => (int)$row['id'],
            'Material' => $row['Material'],
            'cantidad_disp' => $cantidad_disp,
            'cantidad_min' => $cantidad_min,
            'estado' => $estado_final,
            'ord_idord' => $row['ord_idord'] ? (int)$row['ord_idord'] : null,
            'nivel_calculado' => $row['nivel_calculado'], // Para debugging
            'es_critico' => $cantidad_disp <= $cantidad_min || $cantidad_disp == 0,
            'sugerencia_pedido' => calcularSugerenciaPedido($cantidad_disp, $cantidad_min)
        ];
        
        $inventario[] = $item;
        
        // Actualizar estadísticas
        $estadisticas['total_materiales']++;
        
        switch ($estado_final) {
            case 'Agotado':
                $estadisticas['agotados']++;
                break;
            case 'Crítico':
                $estadisticas['criticos']++;
                break;
            case 'Bajo':
                $estadisticas['bajos']++;
                break;
            default:
                $estadisticas['disponibles']++;
        }
    }

    // Respuesta exitosa con datos e información adicional
    echo json_encode([
        'success' => true,
        'data' => $inventario,
        'estadisticas' => $estadisticas,
        'timestamp' => date('Y-m-d H:i:s'),
        'total_registros' => count($inventario),
        'mensaje' => count($inventario) > 0 ? 'Inventario cargado correctamente' : 'No hay materiales en inventario'
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Log del error para debugging
    error_log("Error en get_inventario.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'debug_info' => [
            'file' => __FILE__,
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]
    ], JSON_PRETTY_PRINT);
}

$conn->close();

// === FUNCIONES AUXILIARES ===

/**
 * Determina el estado final del material basado en cantidades y estado de BD
 */
function determinarEstadoFinal($cantidad_disp, $cantidad_min, $estado_bd) {
    // Si la cantidad es 0, siempre es agotado
    if ($cantidad_disp == 0) {
        return 'Agotado';
    }
    
    // Si el estado de BD es específico, respetarlo
    $estado_bd_lower = strtolower(trim($estado_bd));
    switch ($estado_bd_lower) {
        case 'agotado':
            return 'Agotado';
        case 'crítico':
        case 'critico':
            return 'Crítico';
        case 'disponible':
            // Solo si realmente tiene stock suficiente
            return $cantidad_disp > ($cantidad_min * 1.5) ? 'Disponible' : 'Bajo';
        case 'bajo':
            return 'Bajo';
    }
    
    // Calcular estado basado en cantidades
    if ($cantidad_disp <= $cantidad_min) {
        return 'Crítico';
    } elseif ($cantidad_disp <= ($cantidad_min * 1.5)) {
        return 'Bajo';
    } else {
        return 'Disponible';
    }
}

/**
 * Calcula la sugerencia de pedido para reabastecimiento
 */
function calcularSugerenciaPedido($cantidad_actual, $cantidad_minima) {
    if ($cantidad_actual == 0) {
        return $cantidad_minima * 3; // Si está agotado, 3x el mínimo
    } elseif ($cantidad_actual <= $cantidad_minima) {
        return max($cantidad_minima * 2 - $cantidad_actual, $cantidad_minima);
    } else {
        return $cantidad_minima; // Cantidad mínima básica
    }
}
?>