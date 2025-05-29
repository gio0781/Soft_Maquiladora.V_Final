<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Si es una petición OPTIONS, terminar aquí
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

echo "=== DIAGNÓSTICO COMPLETO ===\n\n";
echo "Método HTTP: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Fecha/Hora: " . date('Y-m-d H:i:s') . "\n\n";

// Verificar si el archivo API existe
$api_file = __DIR__ . '/api/inventario/reabastecer_material.php';
echo "Ruta del archivo API: " . $api_file . "\n";
echo "¿Archivo existe?: " . (file_exists($api_file) ? "SÍ" : "NO") . "\n\n";

// Si el archivo existe, incluirlo para probar
if (file_exists($api_file)) {
    echo "=== PROBANDO API CON GET ===\n";
    try {
        // Simular una petición GET al API
        $_SERVER['REQUEST_METHOD'] = 'GET';
        ob_start();
        include $api_file;
        $get_result = ob_get_clean();
        echo "Resultado GET: " . $get_result . "\n\n";
    } catch (Exception $e) {
        echo "Error con GET: " . $e->getMessage() . "\n\n";
    }
    
    echo "=== PROBANDO API CON POST ===\n";
    try {
        // Simular una petición POST con datos
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['id_material'] = 1;
        $_POST['cantidad'] = 10;
        $_POST['fecha_reabastecimiento'] = '2025-05-23';
        $_POST['proveedor'] = 'Proveedor Test';
        $_POST['costo_unitario'] = 15.50;
        
        ob_start();
        include $api_file;
        $post_result = ob_get_clean();
        echo "Resultado POST: " . $post_result . "\n\n";
    } catch (Exception $e) {
        echo "Error con POST: " . $e->getMessage() . "\n\n";
    }
} else {
    echo "❌ El archivo API no existe. Verifica la ruta.\n\n";
}

// Información adicional del servidor
echo "=== INFORMACIÓN DEL SERVIDOR ===\n";
echo "Directorio actual: " . __DIR__ . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Extensiones cargadas: " . implode(', ', get_loaded_extensions()) . "\n";
?>