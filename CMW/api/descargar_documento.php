<?php
require_once __DIR__ . '/../../auth_check.php';
requerir_rol_admin();

$nombre = basename($_GET['f'] ?? '');

if (!$nombre || !preg_match('/^doc_\d+_\d{8}_\d{6}_[0-9a-f]{8}\.(pdf|jpg|png|gif|webp)$/', $nombre)) {
    http_response_code(400);
    exit('Nombre de archivo no válido');
}

$ruta = __DIR__ . '/../../empleado/uploads/' . $nombre;

if (!file_exists($ruta)) {
    http_response_code(404);
    exit('Archivo no encontrado');
}

$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mimeReal = $finfo->file($ruta);
$permitidos = ['application/pdf','image/jpeg','image/png','image/gif','image/webp'];

if (!in_array($mimeReal, $permitidos)) {
    http_response_code(403);
    exit('Tipo de archivo no permitido');
}

header('Content-Type: ' . $mimeReal);
header('Content-Disposition: attachment; filename="' . $nombre . '"');
header('Content-Length: ' . filesize($ruta));
header('Cache-Control: private, no-cache');
readfile($ruta);
