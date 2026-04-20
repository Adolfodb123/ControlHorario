<?php
require_once __DIR__ . '/../../auth_check.php';
requerir_rol_empleado();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

$tipo        = $_POST['tipo']         ?? '';
$fechaInicio = $_POST['fecha_inicio'] ?? '';
$fechaFin    = $_POST['fecha_fin']    ?? null;
$motivo      = trim($_POST['motivo']  ?? '');

if (!in_array($tipo, ['vacaciones', 'justificacion', 'libre_disposicion'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Tipo de solicitud no válido']);
    exit;
}

if (!$fechaInicio || !strtotime($fechaInicio)) {
    echo json_encode(['ok' => false, 'error' => 'Fecha de inicio no válida']);
    exit;
}

if (in_array($tipo, ['vacaciones', 'libre_disposicion']) && (!$fechaFin || !strtotime($fechaFin))) {
    echo json_encode(['ok' => false, 'error' => 'Necesitas indicar la fecha de fin']);
    exit;
}

if ($tipo === 'justificacion' && $motivo === '') {
    echo json_encode(['ok' => false, 'error' => 'El motivo es obligatorio para una justificación']);
    exit;
}

// Gestión de archivo adjunto (solo para justificacion)
$documentoPath = null;
if ($tipo === 'justificacion' && isset($_FILES['documento']) && $_FILES['documento']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['documento'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Error al subir el archivo (código ' . $file['error'] . ')']);
        exit;
    }

    // Validar tamaño máximo: 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['ok' => false, 'error' => 'El archivo no puede superar 5 MB']);
        exit;
    }

    // Validar tipo MIME real
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mimeReal = $finfo->file($file['tmp_name']);
    $permitidos = [
        'application/pdf'  => 'pdf',
        'image/jpeg'       => 'jpg',
        'image/png'        => 'png',
        'image/gif'        => 'gif',
        'image/webp'       => 'webp',
    ];
    if (!array_key_exists($mimeReal, $permitidos)) {
        echo json_encode(['ok' => false, 'error' => 'Tipo de archivo no permitido. Usa PDF o imagen (JPG, PNG)']);
        exit;
    }

    $ext        = $permitidos[$mimeReal];
    $uid        = (int)$_SESSION['usuario_id'];
    $nombreFich = 'doc_' . $uid . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destino    = __DIR__ . '/../uploads/' . $nombreFich;

    if (!move_uploaded_file($file['tmp_name'], $destino)) {
        echo json_encode(['ok' => false, 'error' => 'No se pudo guardar el archivo']);
        exit;
    }

    $documentoPath = $nombreFich;
}

require_once __DIR__ . '/../../ConexionSQL/config.php';
$conn = DatabaseConfig::connect();
$uid  = (int)$_SESSION['usuario_id'];
$fin  = $tipo === 'vacaciones' ? $fechaFin : null;

$stmt = $conn->prepare("INSERT INTO solicitudes (usuario_id, tipo, fecha_inicio, fecha_fin, motivo, documento) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param('isssss', $uid, $tipo, $fechaInicio, $fin, $motivo, $documentoPath);
$stmt->execute();
$id = $stmt->insert_id;
$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'id' => $id]);
