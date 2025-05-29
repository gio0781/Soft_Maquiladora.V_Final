
<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

if (!file_exists(__DIR__ . '/conexion.php')) {
    echo "❌ conexion.php NO encontrado en: " . __DIR__ . "/conexion.php\n";
    exit;
}

include __DIR__ . '/conexion.php';

if (!isset($conexion)) {
    echo "❌ \$conexion no está definido después de incluir conexion.php\n";
    exit;
}

if ($conexion->connect_error) {
    echo "❌ Error de conexión: " . $conexion->connect_error . "\n";
} else {
    echo "✅ Conexión a la base de datos establecida correctamente.\n";
    echo "Base seleccionada: " . $conexion->query("SELECT DATABASE()")->fetch_row()[0] . "\n";

    // Probar acceso a la tabla Pedidos
    $result = $conexion->query("SHOW TABLES LIKE 'Pedidos'");
    if ($result && $result->num_rows > 0) {
        echo "✅ Tabla 'Pedidos' encontrada.\n";
    } else {
        echo "❌ Tabla 'Pedidos' NO encontrada.\n";
    }
}
?>
