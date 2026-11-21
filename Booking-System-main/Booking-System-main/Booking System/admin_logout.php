<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_auth.php';
require_admin_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

if (!verify_admin_csrf_token($_POST['csrf_token'] ?? null)) {
	http_response_code(400);
	exit('Invalid sign-out request.');
}

$_SESSION = [];
$cookieParameters = session_get_cookie_params();
setcookie(session_name(), '', [
	'expires' => time() - 42000,
	'path' => $cookieParameters['path'],
	'domain' => $cookieParameters['domain'],
	'secure' => $cookieParameters['secure'],
	'httponly' => $cookieParameters['httponly'],
	'samesite' => $cookieParameters['samesite'],
]);
session_destroy();

header('Location: admin_login.php');
exit;