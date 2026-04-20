<?php
require_once __DIR__ . '/../../auth_check.php';
requerir_rol_admin();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../ConexionSQL/config.php';
$conn   = DatabaseConfig::connect();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        $estado = $_GET['estado'] ?? 'pendiente';
        $estados = ['pendiente','aprobada','rechazada','todas'];
        if (!in_array($estado, $estados)) $estado = 'pendiente';

        $where = $estado !== 'todas' ? "WHERE s.estado = '$estado'" : '';
        $sql = "SELECT s.id, s.tipo, s.fecha_inicio, s.fecha_fin, s.motivo, s.documento, s.estado, s.admin_nota, s.created_at,
                       u.nombre_completo, u.equipo
                FROM solicitudes s
                JOIN usuarios u ON u.id = s.usuario_id
                $where
                ORDER BY s.created_at DESC
                LIMIT 200";
        $r = $conn->query($sql);
        echo json_encode(['ok' => true, 'solicitudes' => $r->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'resolver':
        $id      = (int)($_POST['id']       ?? 0);
        $accion2 = $_POST['decision']       ?? '';
        $nota    = trim($_POST['nota']      ?? '');

        if ($id <= 0 || !in_array($accion2, ['aprobada','rechazada'])) {
            echo json_encode(['ok' => false, 'error' => 'Datos incorrectos']); break;
        }

        // Obtener datos de la solicitud y el empleado
        $stmt = $conn->prepare("
            SELECT s.tipo, s.fecha_inicio, s.fecha_fin, u.nombre_completo, u.equipo
            FROM solicitudes s
            JOIN usuarios u ON u.id = s.usuario_id
            WHERE s.id = ?
        ");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $sol = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Actualizar estado de la solicitud
        $stmt = $conn->prepare("UPDATE solicitudes SET estado=?, admin_nota=?, updated_at=NOW() WHERE id=?");
        $stmt->bind_param('ssi', $accion2, $nota, $id);
        $stmt->execute();
        $stmt->close();

        // Si se aprueba o rechaza → actualizar empleados_anual en el rango de fechas
        if ($sol) {
            $diasES  = ['','lunes','martes','miércoles','jueves','viernes'];
            $mesesES = ['','enero','febrero','marzo','abril','mayo','junio',
                        'julio','agosto','septiembre','octubre','noviembre','diciembre'];

            $fechaFin = $sol['fecha_fin'] ?: $sol['fecha_inicio'];
            $cursor   = new DateTime($sol['fecha_inicio']);
            $fin      = new DateTime($fechaFin);
            $fin->setTime(23,59,59);

            $justVal = $accion2 === 'aprobada' ? 'Sí' : null;

            $stmtIns = $conn->prepare("
                INSERT IGNORE INTO empleados_anual
                    (full_name, Equipo, date, dia_semana, nombre_mes, horas)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmtUpd = $conn->prepare("
                UPDATE empleados_anual SET Justificado = ? WHERE full_name = ? AND date = ?
            ");

            while ($cursor <= $fin) {
                $dow = (int)$cursor->format('N'); // 1=lun … 7=dom
                if ($dow <= 5) {                  // solo laborables
                    $fechaStr  = $cursor->format('Y-m-d');
                    $diaSemana = $diasES[$dow];
                    $nombreMes = $mesesES[(int)$cursor->format('n')];
                    $nombre    = $sol['nombre_completo'];
                    $equipo    = $sol['equipo'] ?? '';

                    // Crear fila si no existe
                    $stmtIns->bind_param('sssss', $nombre, $equipo, $fechaStr, $diaSemana, $nombreMes);
                    $stmtIns->execute();

                    // Marcar como justificado (o quitar si se rechaza)
                    $stmtUpd->bind_param('sss', $justVal, $nombre, $fechaStr);
                    $stmtUpd->execute();
                }
                $cursor->modify('+1 day');
            }
            $stmtIns->close();
            $stmtUpd->close();
        }

        echo json_encode(['ok' => true]);
        break;

    case 'pendientes_count':
        $r = $conn->query("SELECT COUNT(*) AS n FROM solicitudes WHERE estado='pendiente'");
        echo json_encode(['ok' => true, 'count' => (int)$r->fetch_assoc()['n']]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
}
$conn->close();
