<?php
require_once __DIR__ . '/../../auth_check.php';
requerir_rol_empleado();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../ConexionSQL/config.php';
$conn = DatabaseConfig::connect();
$uid  = (int)$_SESSION['usuario_id'];
$hoy  = date('Y-m-d');

// Fichajes de hoy
$stmt = $conn->prepare("SELECT tipo, fecha_hora FROM fichajes_empleado WHERE usuario_id = ? AND DATE(fecha_hora) = ? ORDER BY fecha_hora ASC");
$stmt->bind_param('is', $uid, $hoy);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);   // MySQLi: fetch_all
$stmt->close();

// Mis solicitudes recientes
$stmt = $conn->prepare("SELECT id, tipo, fecha_inicio, fecha_fin, motivo, documento, estado, admin_nota, created_at FROM solicitudes WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 20");
$stmt->bind_param('i', $uid);
$stmt->execute();
$solicitudes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);   // MySQLi: fetch_all
$stmt->close();

// Días usados por tipo este año
$anio = date('Y');
$stmt = $conn->prepare("
    SELECT tipo,
           SUM(DATEDIFF(COALESCE(fecha_fin, fecha_inicio), fecha_inicio) + 1) AS dias
    FROM solicitudes
    WHERE usuario_id = ? AND estado = 'aprobada' AND YEAR(fecha_inicio) = ?
      AND tipo IN ('vacaciones','libre_disposicion')
    GROUP BY tipo
");
$stmt->bind_param('ii', $uid, $anio);
$stmt->execute();
$diasUsados = ['vacaciones' => 0, 'libre_disposicion' => 0];
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $diasUsados[$row['tipo']] = (int)$row['dias'];
}
$stmt->close();
$conn->close();

$entrada = null;
$salida  = null;
foreach ($rows as $r) {
    if ($r['tipo'] === 'entrada') $entrada = substr($r['fecha_hora'], 11, 5);
    if ($r['tipo'] === 'salida')  $salida  = substr($r['fecha_hora'], 11, 5);
}

$ultimo = !empty($rows) ? end($rows) : null;
$estado = $ultimo ? $ultimo['tipo'] : null;

echo json_encode([
    'ok'          => true,
    'estado'      => $estado,
    'entrada'     => $entrada,
    'salida'      => $salida,
    'solicitudes' => $solicitudes,
    'balance' => [
        'vacaciones_total'    => 22,
        'vacaciones_usados'   => $diasUsados['vacaciones'],
        'vacaciones_restantes'=> 22 - $diasUsados['vacaciones'],
        'ld_total'            => 6,
        'ld_usados'           => $diasUsados['libre_disposicion'],
        'ld_restantes'        => 6 - $diasUsados['libre_disposicion'],
    ],
]);
