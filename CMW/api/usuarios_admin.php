<?php
require_once __DIR__ . '/../../auth_check.php';
requerir_rol_admin();
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../ConexionSQL/config.php';
$conn   = DatabaseConfig::connect();
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

switch ($accion) {

    case 'listar':
        $r = $conn->query("SELECT id, username, nombre_completo, email, equipo, role, activo, created_at FROM usuarios ORDER BY role DESC, nombre_completo ASC");
        echo json_encode(['ok' => true, 'usuarios' => $r->fetch_all(MYSQLI_ASSOC)]);
        break;

    case 'crear':
        $username  = trim($_POST['username']  ?? '');
        $password  = $_POST['password']  ?? '';
        $nombre    = trim($_POST['nombre']    ?? '');
        $email     = trim($_POST['email']     ?? '');
        $equipo    = trim($_POST['equipo']    ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin','empleado']) ? $_POST['role'] : 'empleado';

        if ($username === '' || $password === '' || $nombre === '') {
            echo json_encode(['ok' => false, 'error' => 'Usuario, contraseña y nombre son obligatorios']); break;
        }
        if (strlen($password) < 6) {
            echo json_encode(['ok' => false, 'error' => 'La contraseña debe tener al menos 6 caracteres']); break;
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO usuarios (username, password_hash, nombre_completo, email, equipo, role) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('ssssss', $username, $hash, $nombre, $email, $equipo, $role);
        if ($stmt->execute()) {
            echo json_encode(['ok' => true, 'id' => $stmt->insert_id]);
        } else {
            $msg = str_contains($conn->error, 'Duplicate') ? 'El nombre de usuario ya existe' : $conn->error;
            echo json_encode(['ok' => false, 'error' => $msg]);
        }
        $stmt->close();
        break;

    case 'editar':
        $id     = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email']  ?? '');
        $equipo = trim($_POST['equipo'] ?? '');
        $role   = in_array($_POST['role'] ?? '', ['admin','empleado']) ? $_POST['role'] : 'empleado';
        $activo = (int)(bool)($_POST['activo'] ?? 1);

        if ($id <= 0 || $nombre === '') { echo json_encode(['ok' => false, 'error' => 'Datos incompletos']); break; }

        $stmt = $conn->prepare("UPDATE usuarios SET nombre_completo=?, email=?, equipo=?, role=?, activo=? WHERE id=?");
        $stmt->bind_param('ssssis', $nombre, $email, $equipo, $role, $activo, $id);
        $stmt->execute();
        echo json_encode(['ok' => true]);
        $stmt->close();
        break;

    case 'cambiar_password':
        $id       = (int)($_POST['id'] ?? 0);
        $password = $_POST['password'] ?? '';
        if ($id <= 0 || strlen($password) < 6) {
            echo json_encode(['ok' => false, 'error' => 'Contraseña mínimo 6 caracteres']); break;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE usuarios SET password_hash=? WHERE id=?");
        $stmt->bind_param('si', $hash, $id);
        $stmt->execute();
        echo json_encode(['ok' => true]);
        $stmt->close();
        break;

    case 'eliminar':
        $id = (int)($_POST['id'] ?? 0);
        // No eliminar admin principal
        $check = $conn->query("SELECT username FROM usuarios WHERE id=$id");
        $u = $check->fetch_assoc();
        if (!$u || $u['username'] === 'admin') {
            echo json_encode(['ok' => false, 'error' => 'No se puede eliminar este usuario']); break;
        }
        $conn->query("UPDATE usuarios SET activo=0 WHERE id=$id");
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
}
$conn->close();
