<?php
// logout.php - Cerrar sesión de usuario

session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se usan cookies de sesión, eliminar la cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destruir la sesión
session_destroy();

// Limpiar cualquier cache del navegador
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Redirigir al login
header('Location: /ControlHorarioCMW-Test-2/login.php');
exit;
?>