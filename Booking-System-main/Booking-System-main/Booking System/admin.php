<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/booking_data_structures.php';
require_admin_login();
$csrfToken = admin_csrf_token();

$adminNotice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$databaseError = false;
$bookings = [];
$stats = ['total' => 0, 'pending' => 0, 'confirmed' => 0, 'upcoming' => 0];
$pendingQueue = new BookingQueue();
$sortAlgorithm = (string) ($_GET['sort_algorithm'] ?? 'merge');
$binaryBookingIdInput = trim((string) ($_GET['booking_id_search'] ?? ''));
$binaryTargetId = filter_var(
	$binaryBookingIdInput,
	FILTER_VALIDATE_INT,
	['options' => ['min_range' => 1]]
);
$binaryIndex = -1;
$statusHistoryByBooking = [];
$recordBookingStatusChange = static function (
	PDO $connection,
	int $bookingId,
	int $adminId,
	string $newStatus,
	?string $requiredCurrentStatus = null
): bool {
	$connection->beginTransaction();

	try {
		$statement = $connection->prepare('SELECT status FROM bookings WHERE BookingID = :id FOR UPDATE');
		$statement->execute(['id' => $bookingId]);
		$currentStatus = $statement->fetchColumn();

		if (!is_string($currentStatus) || ($requiredCurrentStatus !== null && $currentStatus !== $requiredCurrentStatus)) {
			$connection->commit();
			return false;
		}

		if ($currentStatus !== $newStatus) {
			$update = $connection->prepare('UPDATE bookings SET status = :status WHERE BookingID = :id');
			$update->execute(['status' => $newStatus, 'id' => $bookingId]);

			$history = $connection->prepare(
				'INSERT INTO booking_status_history (BookingID, AdminID, previous_status, new_status)
				 VALUES (:booking_id, :admin_id, :previous_status, :new_status)'
			);
			$history->execute([
				'booking_id' => $bookingId,
				'admin_id' => $adminId,
				'previous_status' => $currentStatus,
				'new_status' => $newStatus,
			]);
		}

		$connection->commit();
		return true;
	} catch (Throwable $exception) {
		if ($connection->inTransaction()) {
			$connection->rollBack();
		}
		throw $exception;
	}
};

try {
	$connection = get_database_connection();

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		$submittedToken = (string) ($_POST['csrf_token'] ?? '');
		if (!verify_admin_csrf_token($submittedToken)) {
			$_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Your session expired. Refresh the page and try again.'];
		} elseif (($_POST['action'] ?? '') === 'update_status') {
			$bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
			$status = (string) ($_POST['status'] ?? '');
			if (!$bookingId || !in_array($status, ['Pending', 'Confirmed', 'Declined', 'Checked Out'], true)) {
				$_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Invalid booking status update.'];
			} else {
				$updated = $recordBookingStatusChange(
					$connection,
					$bookingId,
					(int) $_SESSION['admin_user_id'],
					$status
				);
				$_SESSION['admin_notice'] = $updated
					? ['type' => 'success', 'text' => 'Booking status updated.']
					: ['type' => 'error', 'text' => 'Booking request could not be found.'];
			}
		} elseif (($_POST['action'] ?? '') === 'delete_booking') {
			$bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
			if (!$bookingId) {
				$_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Invalid booking request.'];
			} else {
				$statement = $connection->prepare('DELETE FROM bookings WHERE BookingID = :id');
				$statement->execute(['id' => $bookingId]);
				$_SESSION['admin_notice'] = ['type' => 'success', 'text' => 'Booking request deleted.'];
			}
		} elseif (($_POST['action'] ?? '') === 'check_out') {
			$bookingId = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
			if (!$bookingId) {
				$_SESSION['admin_notice'] = ['type' => 'error', 'text' => 'Invalid booking request.'];
			} else {
				$checkedOut = $recordBookingStatusChange(
					$connection,
					$bookingId,
					(int) $_SESSION['admin_user_id'],
					'Checked Out',
					'Confirmed'
				);
				$_SESSION['admin_notice'] = $checkedOut
					? ['type' => 'success', 'text' => 'Guest checked out. The booking date is available again.']
					: ['type' => 'error', 'text' => 'Only confirmed bookings can be checked out.'];
			}
		}

		header('Location: admin.php#bookings');
		exit;
	}

	$stats = $connection->query(
		"SELECT COUNT(*) AS total,
			COALESCE(SUM(status = 'Pending'), 0) AS pending,
			COALESCE(SUM(status = 'Confirmed'), 0) AS confirmed,
			COALESCE(SUM(status <> 'Declined' AND check_in >= CURDATE()), 0) AS upcoming
		FROM bookings"
	)->fetch() ?: $stats;
	$bookings = $connection->query(
		'SELECT BookingID, full_name, email, phone, pool_size, tour_type, check_in, check_out, check_in_time, check_out_time, status, created_at
		 FROM bookings ORDER BY created_at DESC'
	)->fetchAll();
	$historyRows = $connection->query(
		'SELECT history.BookingID, history.AdminID, history.previous_status, history.new_status, history.changed_at, admin_user.username
		 FROM booking_status_history AS history
		 LEFT JOIN admin_users AS admin_user ON admin_user.AdminID = history.AdminID
		 ORDER BY history.changed_at DESC, history.StatusHistoryID DESC'
	)->fetchAll();
	foreach ($historyRows as $historyRow) {
		$statusHistoryByBooking[(int) $historyRow['BookingID']][] = $historyRow;
	}

	foreach ($bookings as $booking) {
		if ($booking['status'] === 'Pending') {
			$pendingQueue->enqueue($booking);
		}
	}

	$allowedAlgorithms = ['bubble', 'insertion', 'merge', 'quick'];
	if (!in_array($sortAlgorithm, $allowedAlgorithms, true)) {
		$sortAlgorithm = 'merge';
	}

	$bookings = match ($sortAlgorithm) {
		'bubble' => BookingAlgorithms::bubbleSort($bookings, 'tour_type'),
		'insertion' => BookingAlgorithms::insertionSort($bookings, 'pool_size'),
		'quick' => BookingAlgorithms::quickSort($bookings, 'check_in'),
		default => BookingAlgorithms::mergeSort($bookings),
	};

	if ($binaryBookingIdInput !== '' && $binaryTargetId !== false) {
		$idSortedBookings = BookingAlgorithms::mergeSort($bookings, 'BookingID');
		$searchIndex = BookingAlgorithms::binarySearch($idSortedBookings, (string) $binaryTargetId, 'BookingID');
		if ($searchIndex >= 0) {
			$matchedBookingId = (int) $idSortedBookings[$searchIndex]['BookingID'];
			foreach ($bookings as $index => $booking) {
				if ((int) $booking['BookingID'] === $matchedBookingId) {
					$binaryIndex = $index;
					break;
				}
			}
		}
	}
} catch (Throwable $exception) {
	error_log($exception->getMessage());
	$databaseError = true;
}

$todayLabel = (new DateTimeImmutable('today'))->format('D, M j, Y');
$escape = static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<meta name="description" content="Manage Casa Adelaida booking requests.">
		<title>Booking Desk | Casa Adelaida</title>
		<link rel="stylesheet" href="css/admin.css">
	</head>
	<body>
		<aside class="sidebar">
			<a class="brand" href="home.php">
				<img src="img/big_pool.jpg" alt="">
				<span>Casa Adelaida<small>Booking desk</small></span>
			</a>
			<nav class="side-nav" aria-label="Admin navigation">
				<a class="side-link is-active" href="#overview" aria-current="page">Overview</a>
				<a class="side-link" href="#bookings">Bookings</a>
				<a class="side-link" href="admin_signup.php">Add admin</a>
			</nav>
			<p class="sidebar-note">MySQL booking records<br>Keep this admin page on a trusted server.</p>
		</aside>

		<div class="admin-shell">
			<header class="topbar">
				<p>Property management <span>/</span> <strong id="current-section">Overview</strong></p>
				<div class="topbar-actions">
					<span>Signed in as <?= $escape($_SESSION['admin_username'] ?? 'Admin') ?></span>
					<a href="home.php">View website</a>
					<form method="post" action="admin_logout.php">
						<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
						<button class="logout-button" type="submit">Sign out</button>
					</form>
				</div>
			</header>

			<main class="admin-main">
				<section id="overview" class="overview" aria-labelledby="page-title">
					<div class="page-heading">
						<div>
							<p class="eyebrow">CASA ADELAIDA</p>
							<h1 id="page-title" tabindex="-1">Booking overview</h1>
							<p class="page-summary">Review incoming requests and keep booking statuses up to date.</p>
						</div>
						<time><?= $escape($todayLabel) ?></time>
					</div>

					<p class="demo-notice"><strong>MySQL connected</strong> Booking requests and status changes are stored in the database.</p>
					<div class="metrics" aria-label="Booking summary">
						<div class="metric"><span>Total requests</span><strong><?= (int) $stats['total'] ?></strong></div>
						<div class="metric"><span>Pending review</span><strong><?= (int) $stats['pending'] ?></strong></div>
						<div class="metric"><span>Confirmed</span><strong><?= (int) $stats['confirmed'] ?></strong></div>
						<div class="metric"><span>Upcoming stays</span><strong><?= (int) $stats['upcoming'] ?></strong></div>
						<div class="metric queue-metric"><span>Pending Queue</span><strong><?= $pendingQueue->count() ?></strong><small><?= $pendingQueue->peek() ? 'Next: ' . $escape((string) $pendingQueue->peek()['full_name']) : 'Queue empty' ?></small></div>
					</div>
				</section>

				<section id="bookings" class="booking-section" aria-labelledby="bookings-title">
					<div class="section-heading">
						<div>
							<h2 id="bookings-title" tabindex="-1">Booking requests</h2>
							<p>Guest details and stay dates</p>
						</div>
						<div class="table-controls">
							<form class="algorithm-controls" method="get" action="admin.php#bookings">
								<label for="sort-algorithm">Sort by</label>
								<select id="sort-algorithm" name="sort_algorithm">
									<option value="bubble" <?= $sortAlgorithm === 'bubble' ? 'selected' : '' ?>>By Tour: Day / Overnight</option>
									<option value="insertion" <?= $sortAlgorithm === 'insertion' ? 'selected' : '' ?>>By Pool: Small / Big</option>
									<option value="merge" <?= $sortAlgorithm === 'merge' ? 'selected' : '' ?>>By Guest Name</option>
									<option value="quick" <?= $sortAlgorithm === 'quick' ? 'selected' : '' ?>>By Date</option>
								</select>
								<label for="booking-id-search">Search by Booking ID</label>
								<input id="booking-id-search" name="booking_id_search" type="number" min="1" step="1" value="<?= $escape($binaryBookingIdInput) ?>">
								<button class="algorithm-button" type="submit">Apply</button>
							</form>
							<?php if ($binaryBookingIdInput !== ''): ?>
								<p class="binary-result <?= $binaryIndex >= 0 ? 'found' : '' ?>">
									<?php if ($binaryTargetId === false): ?>
										Enter a valid Booking ID.
									<?php elseif ($binaryIndex >= 0): ?>
										Found
									<?php else: ?>
										Not found
									<?php endif; ?>
								</p>
							<?php endif; ?>
							<label class="sr-only" for="status-filter">Filter by status</label>
							<select id="status-filter">
								<option value="all">All statuses</option>
								<option value="Pending">Pending</option>
								<option value="Confirmed">Confirmed</option>
								<option value="Declined">Declined</option>
								<option value="Checked Out">Checked Out</option>
							</select>
						</div>
					</div>

					<?php if ($adminNotice !== null): ?>
						<p class="admin-notice <?= $escape($adminNotice['type'] ?? 'success') ?>" role="status"><?= $escape($adminNotice['text'] ?? '') ?></p>
					<?php endif; ?>
					<?php if ($databaseError): ?>
						<p class="admin-notice error" role="alert">Could not connect to the database. Check the XAMPP MySQL service and db.php settings.</p>
					<?php endif; ?>

					<div class="table-wrap" id="table-wrap" <?= $bookings === [] || $databaseError ? 'hidden' : '' ?>>
						<table>
							<caption class="sr-only">Booking requests submitted through the booking form</caption>
							<thead>
								<tr>
									<th scope="col">Booking ID</th>
									<th scope="col">Guest</th>
									<th scope="col">Stay</th>
									<th scope="col">Contact</th>
									<th scope="col">Status</th>
									<th scope="col"><span class="sr-only">Actions</span></th>
								</tr>
							</thead>
							<tbody id="booking-rows">
								<?php foreach ($bookings as $bookingIndex => $booking): ?>
									<tr class="<?= $binaryIndex === $bookingIndex ? 'binary-match' : '' ?>" data-status="<?= $escape($booking['status']) ?>">
										<td>#<?= (int) $booking['BookingID'] ?></td>
										<td><span class="guest-name"><?= $escape($booking['full_name']) ?></span><span class="guest-date">Requested <?= $escape((new DateTimeImmutable($booking['created_at']))->format('M j, Y')) ?></span></td>
										<td><div class="stay-dates"><span><?= $escape($booking['tour_type']) ?> - <?= $escape($booking['pool_size']) ?></span><span>Check-in: <?= $escape((new DateTimeImmutable($booking['check_in']))->format('M j, Y')) ?>, <?= $escape((new DateTimeImmutable('1970-01-01 ' . $booking['check_in_time']))->format('g:i A')) ?></span><span>Check-out: <?= $escape((new DateTimeImmutable($booking['check_out']))->format('M j, Y')) ?>, <?= $escape((new DateTimeImmutable('1970-01-01 ' . $booking['check_out_time']))->format('g:i A')) ?></span></div></td>
										<td><div class="contact-info"><a href="mailto:<?= $escape($booking['email']) ?>"><?= $escape($booking['email']) ?></a><a href="tel:<?= $escape($booking['phone']) ?>"><?= $escape($booking['phone']) ?></a></div></td>
										<td>
											<form method="post" class="status-form">
												<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
												<input type="hidden" name="action" value="update_status">
												<input type="hidden" name="booking_id" value="<?= (int) $booking['BookingID'] ?>">
												<label class="sr-only" for="status-<?= (int) $booking['BookingID'] ?>">Status for <?= $escape($booking['full_name']) ?></label>
												<select id="status-<?= (int) $booking['BookingID'] ?>" name="status" class="status-select" data-status="<?= $escape($booking['status']) ?>">
													<?php foreach (['Pending', 'Confirmed', 'Declined', 'Checked Out'] as $status): ?>
														<option value="<?= $status ?>" <?= $booking['status'] === $status ? 'selected' : '' ?>><?= $status ?></option>
													<?php endforeach; ?>
												</select>
												<button class="save-status-button" type="submit">Save</button>
											</form>
											<?php $bookingHistory = $statusHistoryByBooking[(int) $booking['BookingID']] ?? []; ?>
											<details class="status-history">
												<summary>History (<?= count($bookingHistory) ?>)</summary>
												<?php if ($bookingHistory === []): ?>
													<p>No status changes recorded.</p>
												<?php else: ?>
													<ul>
														<?php foreach ($bookingHistory as $historyEntry): ?>
															<li>
																<?= $escape($historyEntry['previous_status']) ?> &rarr; <?= $escape($historyEntry['new_status']) ?>
																<small><?= $escape((new DateTimeImmutable($historyEntry['changed_at']))->format('M j, Y g:i A')) ?></small>
																<small>
																	<?= $historyEntry['AdminID'] === null ? 'Unknown admin' : 'Admin #' . (int) $historyEntry['AdminID'] ?>
																	<?= $historyEntry['username'] !== null ? ' (' . $escape($historyEntry['username']) . ')' : '' ?>
																</small>
															</li>
														<?php endforeach; ?>
													</ul>
												<?php endif; ?>
											</details>
										</td>
										<td>
											<div class="row-actions">
												<form method="post" onsubmit="return confirm('Delete this booking request?');">
													<input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
													<input type="hidden" name="action" value="delete_booking">
													<input type="hidden" name="booking_id" value="<?= (int) $booking['BookingID'] ?>">
													<button class="delete-button" type="submit">Delete</button>
												</form>
											</div>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p id="empty-state" class="empty-state" role="status" <?= $bookings === [] || $databaseError ? '' : 'hidden' ?>><?= $databaseError ? 'Booking records are unavailable.' : 'No booking requests yet.' ?></p>
				</section>
			</main>
		</div>
		<script src="js/admin.js?v=3" defer></script>
	</body>
</html>
