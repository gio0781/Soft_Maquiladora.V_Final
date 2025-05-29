<?php
// api/empresa/logo.php
header('Content-Type: application/json');

$logoPath = '../../img/logo_empresa.jpg';
if (file_exists($logoPath)) {
  echo json_encode(['logo' => 'img/logo_empresa.jpg']);
} else {
  echo json_encode(['logo' => 'img/Logo_Maquiladora.jpg']); // fallback
}
?>
