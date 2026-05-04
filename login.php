<?php
require_once __DIR__ . '/auth_check.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username !== '' && $password !== '') {
        require_once __DIR__ . '/ConexionSQL/config.php';
        $conn = DatabaseConfig::connect();
        $stmt = $conn->prepare("SELECT id, password_hash, role, nombre_completo FROM usuarios WHERE username = ? AND activo = 1");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();

        if ($row && password_verify($password, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['logged_in']        = true;
            $_SESSION['user']             = $username;
            $_SESSION['usuario_id']       = $row['id'];
            $_SESSION['role']             = $row['role'];
            $_SESSION['user_group']       = 'ControlHorario';
            $_SESSION['user_display_name'] = $row['nombre_completo'];
            $_SESSION['login_time']       = time();
            $_SESSION['last_activity']    = time();

            if ($row['role'] === 'admin') {
                header("Location: /ControlHorarioCMW-Test-2/CMW/index.php");
            } else {
                header("Location: /ControlHorarioCMW-Test-2/empleado/index.php");
            }
            exit;
        } else {
            $error = "Usuario o contraseña incorrectos.";
        }
    } else {
        $error = "Por favor, completa todos los campos.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control Horario CMW — Iniciar Sesión</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #587587 0%, #3d5a6b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            padding: 48px 40px;
            width: 360px;
            max-width: calc(100vw - 32px);
        }
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        .logo h1 {
            color: #587587;
            font-size: 1.6rem;
            font-weight: 600;
        }
        .logo p {
            color: #888;
            font-size: 0.85rem;
            margin-top: 4px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #555;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s;
            outline: none;
        }
        input:focus { border-color: #587587; }
        .btn {
            width: 100%;
            padding: 13px;
            background: #587587;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
        }
        .btn:hover { background: #3d5a6b; }
        .error {
            background: #fde8e8;
            color: #c0392b;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.88rem;
            margin-bottom: 18px;
            border-left: 3px solid #c0392b;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="logo">
            <h1>Control Horario</h1>
            <p>CMW — Acceso al sistema</p>
        </div>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="form-group">
                <label>Usuario</label>
                <input type="text" name="username" placeholder="Tu usuario" required autofocus
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" placeholder="••••••••" required>
            </div>
            <button type="submit" name="login" class="btn">Entrar</button>
        </form>
    </div>
</body>
</html>
