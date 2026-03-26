<?php
// ===== ARCHIVO: auth_check.php =====

declare(strict_types=1);

// --- Config ---
$WEB = "ControlHorarioCMW-Test-2"; // <-- actualizado al nombre de la carpeta

$ALLOWED_GROUPS = ['ControlHorario']; // ajusta según tu caso
$SESSION_TIMEOUT = 7200; // 2 horas

// --- Seguridad de cookies de sesión (antes de session_start) ---
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => $cookieParams['path'] ?? '/',
    'domain'   => $cookieParams['domain'] ?? '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);

// --- No cache en contenido protegido ---
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
    // Si quieres redirigir luego a la página original:
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

    // ¿logueado?
    if (empty($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        redirect_and_exit('login.html');
    }

    // ¿usuario válido?
    if (empty($_SESSION['user'])) {
        destroy_session();
        redirect_and_exit('login.html');
    }

    // Timeout de sesión
    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int)$_SESSION['last_activity'] > $SESSION_TIMEOUT)) {
        destroy_session();
        header("Location: /" . $GLOBALS['WEB'] . "/login.html?timeout=1");
        exit;
    }
    $_SESSION['last_activity'] = $now;

    // Comprobación de grupo
    if (empty($_SESSION['user_group']) || !in_array($_SESSION['user_group'], $ALLOWED_GROUPS, true)) {
        redirect_and_exit('sin_permisos.html');
    }

    return true;
}

// Ejecutar check
verificar_autenticacion();
