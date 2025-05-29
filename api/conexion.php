<?php
$servername = "localhost";
$username = "u984575157_admin";
$password = "Adminchido123@";
$database = "u984575157_sistema_taller";

$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión"]);
    exit();
}
?>
