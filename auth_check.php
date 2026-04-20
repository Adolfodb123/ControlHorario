<?php
// ===== auth_check.php =====

declare(strict_types=1);

$WEB = "ControlHorarioCMW-Test-2";
$ALLOWED_GROUPS  = ['ControlHorario'];
$SESSION_TIMEOUT = 7200; // 2 horas

$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $cookieParams['path'] ?? '/',
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function redirect_and_exit(string $path): void {
    global $WEB;
    if (!isset($_SESSION['login_redirect'])) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? '/';
    }
    header("Location: /{$WEB}/{$path}");
    exit;
}

function destroy_session(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'] ?? '/',
            $params['domain'] ?? '',
            $params['secure'] ?? false,
            $params['httponly'] ?? true
        );
    }
    session_destroy();
}

function verificar_autenticacion(): bool {
    global $ALLOWED_GROUPS, $SESSION_TIMEOUT;

    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        redirect_and_exit('login.html');
    }

    if (empty($_SESSION['user'])) {
        destroy_session();
        redirect_and_exit('login.html');
    }

    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > $SESSION_TIMEOUT)) {
        destroy_session();
        header("Location: /" . $GLOBALS['WEB'] . "/login.html?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = $now;

    if (empty($_SESSION['user_group']) || !in_array($_SESSION['user_group'], $ALLOWED_GROUPS, true)) {
        redirect_and_exit('sin_permisos.html');
    }

    return true;
}

function requerir_rol_admin(): void {
    verificar_autenticacion();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        redirect_and_exit('sin_permisos.html');
    }
}

function requerir_rol_empleado(): void {
    verificar_autenticacion();
    if (($_SESSION['role'] ?? '') !== 'empleado') {
        // Si es admin, redirigir a su interfaz
        if (($_SESSION['role'] ?? '') === 'admin') {
            global $WEB;
            header("Location: /{$WEB}/CMW/index.php");
            exit;
        }
        redirect_and_exit('sin_permisos.html');
    }
}
