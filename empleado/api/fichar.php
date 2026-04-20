<?php
require_once __DIR__ . '/../../auth_check.php';
requerir_rol_empleado();
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../../ConexionSQL/config.php';
$conn = DatabaseConfig::connect();
$uid  = (int)$_SESSION['usuario_id'];
$now  = new DateTime();
$hoy  = $now->format('Y-m-d');

// Último fichaje de hoy
$stmt = $conn->prepare("SELECT tipo FROM fichajes_empleado WHERE usuario_id = ? AND DATE(fecha_hora) = ? ORDER BY fecha_hora DESC LIMIT 1");
$stmt->bind_param('is', $uid, $hoy);
$stmt->execute();
$ultimo = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Alternar entrada/salida
$tipo = (!$ultimo || $ultimo['tipo'] === 'salida') ? 'entrada' : 'salida';

$fechaHora   = $now->format('Y-m-d H:i:s');
$horaFichaje = $now->format('H:i:s');
$ip          = $_SERVER['REMOTE_ADDR'] ?? null;

// Guardar en fichajes_empleado
$stmt = $conn->prepare("INSERT INTO fichajes_empleado (usuario_id, tipo, fecha_hora, ip_origen) VALUES (?, ?, ?, ?)");
$stmt->bind_param('isss', $uid, $tipo, $fechaHora, $ip);
$stmt->execute();
$stmt->close();

// Datos del usuario
$stmt = $conn->prepare("SELECT nombre_completo, equipo FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$usuario  = $stmt->get_result()->fetch_assoc();
$stmt->close();

$fullName  = $usuario['nombre_completo'];
$equipo    = $usuario['equipo'] ?? '';
$diasES    = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
$mesesES   = ['','enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$diaSemana = $diasES[(int)$now->format('w')];
$nombreMes = $mesesES[(int)$now->format('n')];

if ($tipo === 'entrada') {
    // Upsert: si ya existe la fila para hoy, actualiza clock_in; si no, la crea
    $stmt = $conn->prepare("
        INSERT INTO empleados_anual (full_name, Equipo, date, dia_semana, nombre_mes, clock_in, horas)
        VALUES (?, ?, ?, ?, ?, ?, 0)
        ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in)
    ");
    $stmt->bind_param('ssssss', $fullName, $equipo, $hoy, $diaSemana, $nombreMes, $horaFichaje);
    $stmt->execute();
    $stmt->close();

} else { // salida
    // Obtener clock_in para calcular horas
    $stmt = $conn->prepare("SELECT clock_in FROM empleados_anual WHERE full_name = ? AND date = ?");
    $stmt->bind_param('ss', $fullName, $hoy);
    $stmt->execute();
    $fila = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $horas = 0;
    if ($fila && $fila['clock_in']) {
        $dtIn  = new DateTime($hoy . ' ' . $fila['clock_in']);
        $diff  = $dtIn->diff($now);
        $horas = round(($diff->days * 1440 + $diff->h * 60 + $diff->i) / 60, 2);
    }

    $stmt = $conn->prepare("
        INSERT INTO empleados_anual (full_name, Equipo, date, dia_semana, nombre_mes, clock_out, horas)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE clock_out = VALUES(clock_out), horas = VALUES(horas)
    ");
    $stmt->bind_param('ssssssd', $fullName, $equipo, $hoy, $diaSemana, $nombreMes, $horaFichaje, $horas);
    $stmt->execute();
    $stmt->close();
}

$conn->close();
echo json_encode(['ok' => true, 'tipo' => $tipo, 'hora' => $now->format('H:i')]);
