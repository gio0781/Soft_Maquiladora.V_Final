

<?php
header("Content-Type: application/json");
require_once("../conexion.php");

try {
    $sql = "SELECT 
                op.idord AS id,
                f.nombre AS ficha_tecnica,
                u.usuario AS administrador,
                op.fecha_inicio,
                op.fecha_fin,
                op.estado
            FROM Orden_Produccion op
            JOIN fichas_tecnicas f ON op.pro_fic_idfic = f.id
            JOIN usuarios u ON op.admin_id = u.id";
    
    $result = $conn->query($sql);

    if (!$result) {
        echo json_encode([
            "success" => false,
            "error" => "Error en la consulta: " . $conn->error
        ]);
        exit;
    }

    $cortes = [];
    while ($row = $result->fetch_assoc()) {
        $cortes[] = $row;
    }

    echo json_encode([
        "success" => true,
        "data" => $cortes
    ]);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}
?>