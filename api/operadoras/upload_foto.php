<?php
// api/operaria/upload_foto.php
header('Content-Type: application/json');

$dir = '../../img/';
if (!file_exists($dir)) {
  mkdir($dir, 0777, true);
}

if (!isset($_FILES['foto']) || !isset($_POST['usuario'])) {
  echo json_encode(['success' => false, 'error' => 'Datos incompletos.']);
  exit;
}

$usuario = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['usuario']);
$archivo = $_FILES['foto'];

if ($archivo['error'] !== UPLOAD_ERR_OK) {
  echo json_encode(['success' => false, 'error' => 'Error al subir el archivo.']);
  exit;
}

$extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
$nombreArchivo = 'perfil_' . strtolower($usuario) . '.' . $extension;
$rutaDestino = $dir . $nombreArchivo;

if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
  echo json_encode([
    'success' => true,
    'url' => 'img/' . $nombreArchivo
  ]);
} else {
  echo json_encode(['success' => false, 'error' => 'No se pudo guardar la imagen.']);
}
?>
