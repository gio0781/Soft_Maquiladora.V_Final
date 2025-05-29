// Este archivo contiene la lógica de autenticación para validar las credenciales del usuario.
<?php
header("Content-Type: application/json");

// Conexión a la base de datos (ajusta los datos)
include __DIR__ . '/../conexion.php';

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Error de conexión"]);
    exit();
}

// Obtener los datos del cuerpo del POST
$input = json_decode(file_get_contents("php://input"), true);
$username = $input["username"];
$password = $input["password"];

// Validación simple (ajusta a tu modelo real)
$query = "SELECT * FROM usuarios WHERE usuario = ? AND contraseña = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $username, $password);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        "success" => true,
        "jerarquia" => (int)$row["jerarquia"],
        "nombre" => $row["usuario"] ?? null
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Credenciales inválidas"]);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Login</title>
</head>
<body>
    <h1>Iniciar sesión</h1>
    <form id="loginForm">
        <label for="username">Usuario:</label>
        <input type="text" id="username" name="username" required />
        <br />
        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required />
        <br />
        <button type="submit">Entrar</button>
    </form>

    <div id="mensaje"></div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            fetch("api/usuarios/login.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ username, password })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('mensaje').textContent = "Bienvenido, " + data.nombre;
                    // Aquí podrías redirigir o hacer otra acción
                } else {
                    document.getElementById('mensaje').textContent = data.message;
                }
            })
            .catch(error => {
                document.getElementById('mensaje').textContent = "Error de conexión";
            });
        });
    </script>
</body>
</html>