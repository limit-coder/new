<?php
declare(strict_types=1);

function start_admin_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function admin_csrf_token(): string
{
    if (!isset($_SESSION['admin_csrf_token'])) {
        $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['admin_csrf_token'];
}

function is_admin_authenticated(): bool
{
    return isset($_SESSION['admin_user_id']) && is_int($_SESSION['admin_user_id']);
}

function require_admin_login(): void
{
    start_admin_session();

    if (!is_admin_authenticated()) {
        header('Location: admin_login.php');
        exit;
    }
}

function verify_admin_csrf_token(mixed $submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION['admin_csrf_token'])
        && hash_equals($_SESSION['admin_csrf_token'], $submittedToken);
}