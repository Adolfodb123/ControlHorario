<?php
// Genera filas vacías en empleados_anual para todos los días laborables
// (lunes-viernes) desde el primer registro de cada empleado hasta hoy.
// Usa INSERT IGNORE para no tocar filas que ya existen.

require_once __DIR__ . '/../../auth_check.php';
requerir_rol_admin();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../ConexionSQL/config.php';
$conn = DatabaseConfig::connect();

$diasES  = ['','lunes','martes','miércoles','jueves','viernes'];   // 1=lun … 5=vie (ISO)
$mesesES = ['','enero','febrero','marzo','abril','mayo','junio',
             'julio','agosto','septiembre','octubre','noviembre','diciembre'];

$hoy = new DateTime();
$hoy->setTime(0,0,0);

// Empleados activos con su fecha de alta y equipo
$empleados = $conn->query("
    SELECT u.nombre_completo AS full_name,
           COALESCE(u.equipo,'')  AS Equipo,
           DATE(u.created_at)     AS fecha_alta
    FROM   usuarios u
    WHERE  u.role = 'empleado' AND u.activo = 1
")->fetch_all(MYSQLI_ASSOC);

$insertados = 0;

$stmt = $conn->prepare("
    INSERT IGNORE INTO empleados_anual
        (full_name, Equipo, date, dia_semana, nombre_mes, horas)
    VALUES (?, ?, ?, ?, ?, 0)
");

foreach ($empleados as $emp) {
    $inicio = new DateTime($emp['fecha_alta']);
    $inicio->setTime(0,0,0);

    $cursor = clone $inicio;
    while ($cursor <= $hoy) {
        $dow = (int)$cursor->format('N'); // 1=lun … 7=dom
        if ($dow <= 5) {                  // solo laborables
            $fechaStr  = $cursor->format('Y-m-d');
            $diaSemana = $diasES[$dow];
            $nombreMes = $mesesES[(int)$cursor->format('n')];

            $stmt->bind_param('sssss',
                $emp['full_name'], $emp['Equipo'],
                $fechaStr, $diaSemana, $nombreMes
            );
            $stmt->execute();
            $insertados += $stmt->affected_rows;
        }
        $cursor->modify('+1 day');
    }
}

$stmt->close();
$conn->close();

echo json_encode(['ok' => true, 'insertados' => $insertados]);
