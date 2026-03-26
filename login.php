<?php
session_start();

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

// Usuarios de prueba locales
$users = [
    'admin' => 'admin',
    'user1' => 'pass1',
    'user2' => 'pass2'
];

if (isset($_POST['login'])) {
    if (isset($users[$username]) && $users[$username] === $password) {
        // Usuario autorizado - CONFIGURAR SESIÓN SEGURA
        session_regenerate_id(true); // Regenerar ID de sesión por seguridad
        
        $_SESSION['logged_in'] = true;
        $_SESSION['user'] = $username;
        $_SESSION['user_group'] = 'ControlHorario';
        $_SESSION['user_display_name'] = ucfirst($username);
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Redirigir a la página principal
        header("Location: /ControlHorarioCMW-Test-2/CMW/index.php");
        exit;
    } else {
        $error = "Usuario o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Control Horario CMW</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .login-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            width: 300px;
        }
        h2 {
            text-align: center;
            margin-bottom: 20px;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .error {
            color: red;
            text-align: center;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="text" name="username" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit" name="login">Iniciar Sesión</button>
        </form>
        <p style="text-align: center; margin-top: 10px; font-size: 12px;">
            Usuarios de prueba:<br>
            admin / admin<br>
            user1 / pass1<br>
            user2 / pass2
        </p>
    </div>
</body>
</html>