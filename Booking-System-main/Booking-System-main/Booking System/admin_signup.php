<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_auth.php';
require_admin_login();

$csrfToken = admin_csrf_token();
$username = '';
$formError = '';
$successMessage = $_SESSION['admin_signup_notice'] ?? '';
unset($_SESSION['admin_signup_notice']);
$databaseError = false;

try {
	$connection = get_database_connection();

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$username = trim((string) ($_POST['username'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

		if (!verify_admin_csrf_token($_POST['csrf_token'] ?? null)) {
			$formError = 'Your form session expired. Refresh the page and try again.';
		} elseif (!preg_match('/^[A-Za-z0-9._-]{3,64}$/D', $username)) {
			$formError = 'Use 3 to 64 letters, numbers, dots, underscores, or hyphens for the username.';
		} elseif (strlen($password) < 12 || strlen($password) > 72) {
			$formError = 'Use a password between 12 and 72 characters.';
		} elseif (!hash_equals($password, $passwordConfirmation)) {
			$formError = 'The password confirmation does not match.';
		} else {
			try {
				$statement = $connection->prepare('INSERT INTO admin_users (username, password_hash) VALUES (:username, :password_hash)');
				$statement->execute([
					'username' => $username,
					'password_hash' => password_hash($password, PASSWORD_DEFAULT),
				]);
				$_SESSION['admin_signup_notice'] = 'Admin account created successfully.';
				header('Location: admin_signup.php');
				exit;
			} catch (PDOException $exception) {
				$isDuplicate = (string) $exception->getCode() === '23000'
					|| (int) ($exception->errorInfo[1] ?? 0) === 1062;
				if ($isDuplicate) {
					$formError = 'That username is already in use.';
				} else {
					throw $exception;
				}
			}
		}
	}
} catch (Throwable $exception) {
	error_log($exception->getMessage());
	$databaseError = true;
}

$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Add Admin | Casa Adelaida</title>
		<link rel="stylesheet" href="css/admin.css">
	</head>
	<body class="auth-body">
		<main class="auth-card">
			<a class="auth-brand" href="admin.php">Casa Adelaida</a>
			<p class="eyebrow">BOOKING DESK</p>
			<h1>Add admin account</h1>
			<p class="auth-copy">Create a separate sign-in for another trusted administrator.</p>
			<?php if ($databaseError): ?>
				<p class="auth-message is-error" role="alert">Could not connect to MySQL. Check the XAMPP service and database setup.</p>
			<?php else: ?>
				<?php if ($successMessage !== ''): ?>
					<p class="auth-message" role="status"><?= $escape($successMessage) ?></p>
				<?php endif; ?>
				<?php if ($formError !== ''): ?>
					<p class="auth-message is-error" role="alert"><?= $escape($formError) ?></p>
				<?php endif; ?>
				<form method="post" class="auth-form">
					<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
					<label for="username">Username</label>
					<input id="username" name="username" type="text" minlength="3" maxlength="64" pattern="[A-Za-z0-9._\-]{3,64}" autocomplete="username" value="<?= $escape($username) ?>" required>
					<label for="password">Password</label>
					<input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
					<label for="password-confirmation">Confirm password</label>
					<input id="password-confirmation" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required>
					<button class="auth-button" type="submit">Create admin account</button>
				</form>
			<?php endif; ?>
			<a class="auth-back" href="admin.php">Back to booking desk</a>
		</main>
	</body>
</html>