<?php
// api/fichas/get_fichas_select.php - VERSIÓN CORREGIDA COMPLETA
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
    // Consulta corregida para obtener fichas técnicas con los campos correctos
    $query = "SELECT idfic as id, nombre FROM FichaTec WHERE nombre IS NOT NULL AND nombre != '' ORDER BY nombre ASC";
    $result = mysqli_query($conn, $query);

    if (!$result) {
        throw new Exception("Error en la consulta: " . mysqli_error($conn));
    }

    $fichas = [];

    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $fichas[] = [
                'id' => (int)$row['id'],
                'nombre' => $row['nombre']
            ];
        }
    }

    // Log para debug
    error_log("Fichas técnicas encontradas: " . count($fichas));
    if (count($fichas) > 0) {
        error_log("Primera ficha: " . json_encode($fichas[0]));
    }

    // Respuesta JSON
    echo json_encode($fichas);

} catch (Exception $e) {
    error_log("Error en get_fichas_select.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Error al obtener fichas técnicas',
        'message' => $e->getMessage(),
        'debug' => [
            'file' => __FILE__,
            'line' => $e->getLine()
        ]
    ]);
}

// Cerrar conexión
if (isset($conn)) {
    mysqli_close($conn);
}
?>