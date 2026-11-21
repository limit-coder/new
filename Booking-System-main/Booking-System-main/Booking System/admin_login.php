<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_auth.php';
start_admin_session();

if (is_admin_authenticated()) {
	header('Location: admin.php');
	exit;
}

$message = '';
$databaseError = false;
$hasAdminAccount = false;

try {
	$connection = get_database_connection();
	$hasAdminAccount = (int) $connection->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$username = trim((string) ($_POST['username'] ?? ''));
		$password = (string) ($_POST['password'] ?? '');
		if (!verify_admin_csrf_token($_POST['csrf_token'] ?? null)) {
			$message = 'Your sign-in session expired. Refresh the page and try again.';
		} else {
			$statement = $connection->prepare('SELECT AdminID, username, password_hash FROM admin_users WHERE username = :username LIMIT 1');
			$statement->execute(['username' => $username]);
			$admin = $statement->fetch();

			if ($admin && password_verify($password, $admin['password_hash'])) {
				session_regenerate_id(true);
				$_SESSION['admin_user_id'] = (int) $admin['AdminID'];
				$_SESSION['admin_username'] = $admin['username'];
				$_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));

				if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
					$updateHash = $connection->prepare('UPDATE admin_users SET password_hash = :password_hash WHERE AdminID = :id');
					$updateHash->execute([
						'password_hash' => password_hash($password, PASSWORD_DEFAULT),
						'id' => $admin['AdminID'],
					]);
				}

				header('Location: admin.php');
				exit;
			}

			$message = 'Username or password is incorrect.';
		}
	}
} catch (Throwable $exception) {
	error_log($exception->getMessage());
	$databaseError = true;
}

$csrfToken = admin_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title>Admin Sign In | Casa Adelaida</title>
		<link rel="stylesheet" href="css/admin.css">
	</head>
	<body class="auth-body">
		<main class="auth-card">
			<a class="auth-brand" href="home.php">Casa Adelaida</a>
			<p class="eyebrow">BOOKING DESK</p>
			<h1>Admin sign in</h1>
			<p class="auth-copy">Sign in to review and manage booking requests.</p>
			<?php if ($databaseError): ?>
				<p class="auth-message is-error" role="alert">Could not connect to MySQL. Check the XAMPP service and database setup.</p>
			<?php elseif ($message !== ''): ?>
				<p class="auth-message is-error" role="alert"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
			<?php endif; ?>
			<?php if (!$databaseError && $hasAdminAccount): ?>
				<form method="post" class="auth-form">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
					<label for="username">Username</label>
					<input id="username" name="username" type="text" autocomplete="username" required autofocus>
					<label for="password">Password</label>
					<input id="password" name="password" type="password" autocomplete="current-password" required>
					<button class="auth-button" type="submit">Sign in</button>
				</form>
			<?php elseif (!$databaseError): ?>
				<p class="auth-message">No admin account has been created yet. Open setup from the XAMPP computer.</p>
				<?php if (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)): ?>
					<a class="auth-button" href="admin_setup.php">Create first admin</a>
				<?php endif; ?>
			<?php endif; ?>
			<a class="auth-back" href="home.php">Back to website</a>
		</main>
	</body>
</html>