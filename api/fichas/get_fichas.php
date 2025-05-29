<?php
header('Content-Type: application/json');

require_once '../conexion.php'; // Adjust path if necessary

try {
    $sql = "SELECT idfic, nombre, descripcion, boton, cierre, hilo, sku FROM FichaTec";
    $result = $conn->query($sql);

    $fichas = [];
    while ($row = $result->fetch_assoc()) {
        $fichas[] = [
            'idfic' => $row['idfic'],
            'nombre' => $row['nombre'],
            'descripcion' => $row['descripcion'],
            'boton' => $row['boton'],
            'cierre' => $row['cierre'],
            'hilo' => $row['hilo'],
            'sku' => $row['sku'], // explicitly include SKU
            'foto' => 'https://sistema-taller.site/img/' . $row['sku'] . '.jpg'
        ];
    }

    echo json_encode($fichas);
} catch (Exception $e) {
    echo json_encode(['error' => 'Error fetching data: ' . $e->getMessage()]);
}
$conn->close();
?>