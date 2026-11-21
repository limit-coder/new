<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_auth.php';
start_admin_session();

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
	http_response_code(404);
	exit;
}

$message = '';
$setupComplete = false;
$connectionError = false;
$connection = null;

try {
	$connection = get_database_connection();
	$setupComplete = (int) $connection->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;

	if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupComplete) {
		if (!verify_admin_csrf_token($_POST['csrf_token'] ?? null)) {
			$message = 'Your form session expired. Refresh the page and try again.';
		} else {
			$username = trim((string) ($_POST['username'] ?? ''));
			$password = (string) ($_POST['password'] ?? '');
			$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

			if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username)) {
				$message = 'Use 3 to 64 letters, numbers, dots, underscores, or hyphens for the username.';
			} elseif (strlen($password) < 12 || strlen($password) > 72) {
				$message = 'Use a password between 12 and 72 characters.';
			} elseif (!hash_equals($password, $passwordConfirmation)) {
				$message = 'The password confirmation does not match.';
			} else {
				$lockAcquired = (int) $connection->query("SELECT GET_LOCK('casa_adelaida_admin_setup', 5)")->fetchColumn() === 1;
				if (!$lockAcquired) {
					$message = 'Admin setup is busy. Please try again.';
				} else {
					try {
						$setupComplete = (int) $connection->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
						if ($setupComplete) {
							$message = 'An admin account already exists. Sign in instead.';
						} else {
						$statement = $connection->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
						$statement->execute([
							'username' => $username,
							'password_hash' => password_hash($password, PASSWORD_DEFAULT),
						]);

						session_regenerate_id(true);
						$_SESSION['admin_user_id'] = (int) $connection->lastInsertId();
						$_SESSION['admin_username'] = $username;
						$_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
						header('Location: admin.php');
						exit;
					}
					} finally {
						$connection->query("SELECT RELEASE_LOCK('casa_adelaida_admin_setup')");
					}
				}
			}
		}
	}
} catch (Throwable $exception) {
	error_log($exception->getMessage());
	$connectionError = true;
}

$csrfToken = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Set Up Admin | Casa Adelaida</title>
		<link rel="stylesheet" href="css/admin.css">
	</head>
	<body class="auth-body">
		<main class="auth-card">
			<a class="auth-brand" href="home.php">Casa Adelaida</a>
			<p class="eyebrow">ONE-TIME LOCAL SETUP</p>
			<h1>Create admin account</h1>
			<?php if ($connectionError): ?>
				<p class="auth-message is-error" role="alert">Could not connect to MySQL. Check the XAMPP service and database setup.</p>
			<?php elseif ($setupComplete): ?>
				<p class="auth-message">An admin account is already set up. Continue to sign in.</p>
				<a class="auth-button" href="admin_login.php">Go to admin sign in</a>
			<?php else: ?>
				<p class="auth-copy">Create the first administrator. This setup page only works from this computer and locks after the first account is created.</p>
				<?php if ($message !== ''): ?>
					<p class="auth-message is-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
				<?php endif; ?>
				<form method="post" class="auth-form">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
					<label for="username">Username</label>
					<input id="username" name="username" type="text" minlength="3" maxlength="64" pattern="[A-Za-z0-9._\-]{3,64}" autocomplete="username" required>
					<label for="password">Password</label>
					<input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
					<label for="password-confirmation">Confirm password</label>
					<input id="password-confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
					<button class="auth-button" type="submit">Create admin account</button>
				</form>
			<?php endif; ?>
			<a class="auth-back" href="home.php">Back to website</a>
		</main>
	</body>
</html>