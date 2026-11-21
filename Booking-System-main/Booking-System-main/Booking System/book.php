<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/booking_oop.php';

if (!isset($_SESSION['booking_csrf_token'])) {
    $_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));
}

$formValues = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'pool' => '',
    'tour_type' => '',
    'check-in' => '',
    'check-out' => '',
    'check-in-time' => '',
    'check-out-time' => '',
];
$bookingFeedback = '';
$bookingFeedbackType = 'success';
$confirmedBookings = [];
$bookingRepository = null;

try {
    $bookingRepository = new BookingRepository(get_database_connection());
    $confirmedBookings = $bookingRepository->getConfirmedBookings();
} catch (Throwable $exception) {
    error_log($exception->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($formValues as $field => $value) {
        $formValues[$field] = trim((string) ($_POST[$field] ?? ''));
    }

    $submittedToken = (string) ($_POST['csrf_token'] ?? '');
    if (!hash_equals($_SESSION['booking_csrf_token'], $submittedToken)) {
        http_response_code(400);
        $bookingFeedback = 'Your form session expired. Refresh the page and try again.';
        $bookingFeedbackType = 'error';
    } else {
        $name = $formValues['name'];
        $email = $formValues['email'];
        $phone = $formValues['phone'];
        $poolChoice = $formValues['pool'];
        $tourType = $formValues['tour_type'];
        $checkIn = $formValues['check-in'];
        $checkOut = $formValues['check-out'];
        $checkInTime = $formValues['check-in-time'];
        $checkOutTime = $formValues['check-out-time'];
        $bookingPlan = null;
        if (in_array($tourType, ['Day Tour', 'Overnight Tour'], true)) {
            $bookingPlan = BookingPlan::create($tourType);
            [$checkIn, $checkOut] = $bookingPlan->normalizeStayDates($checkIn, $checkOut);
            $formValues['check-in'] = $checkIn;
            $formValues['check-out'] = $checkOut;
        }
        $checkInDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkIn);
        $checkOutDate = DateTimeImmutable::createFromFormat('!Y-m-d', $checkOut);
        $checkInTimeValue = DateTimeImmutable::createFromFormat('!H:i', $checkInTime);
        $checkOutTimeValue = DateTimeImmutable::createFromFormat('!H:i', $checkOutTime);
        $today = new DateTimeImmutable('today');
        $validationErrors = [];

        if ($name === '' || strlen($name) > 120) {
            $validationErrors[] = 'Enter a name of 1 to 120 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
            $validationErrors[] = 'Enter a valid email address.';
        }
        if ($phone === '' || strlen($phone) > 40) {
            $validationErrors[] = 'Enter a phone number of 1 to 40 characters.';
        }
        if (!in_array($poolChoice, ['Big Pool', 'Small Pool'], true)) {
            $validationErrors[] = 'Choose either the big pool or the small pool.';
        }
        if (!in_array($tourType, ['Day Tour', 'Overnight Tour'], true)) {
            $validationErrors[] = 'Choose a day tour or overnight tour.';
        }
        if (
            !$checkInDate || $checkInDate->format('Y-m-d') !== $checkIn ||
            !$checkOutDate || $checkOutDate->format('Y-m-d') !== $checkOut ||
            $checkInDate < $today || $checkOutDate < $checkInDate
        ) {
            $validationErrors[] = 'Choose a valid check-in date and a check-out date on or after check-in.';
        }
        if (
            !$checkInTimeValue || $checkInTimeValue->format('H:i') !== $checkInTime ||
            !$checkOutTimeValue || $checkOutTimeValue->format('H:i') !== $checkOutTime
        ) {
            $validationErrors[] = 'Choose valid check-in and check-out times.';
        }
        if (
            $checkInDate && $checkOutDate &&
            $checkInDate->format('Y-m-d') === $checkOutDate->format('Y-m-d') &&
            $checkInTimeValue && $checkOutTimeValue &&
            $checkOutTimeValue <= $checkInTimeValue
        ) {
            $validationErrors[] = 'Check-out time must be later than check-in time.';
        }
        if ($bookingPlan !== null) {
            foreach ($bookingPlan->validateStayDates($checkInDate, $checkOutDate) as $stayError) {
                $validationErrors[] = $stayError;
            }
        }
        if ($checkInDate && $checkOutDate) {
            foreach ($confirmedBookings as $confirmedBooking) {
                if ($confirmedBooking['pool_size'] !== $poolChoice) {
                    continue;
                }
                $confirmedCheckIn = $confirmedBooking['check_in'];
                $confirmedCheckOut = $confirmedBooking['check_out'];
                $hasOverlap = $checkIn <= $confirmedCheckOut && $checkOut >= $confirmedCheckIn;

                if ($hasOverlap) {
                    $validationErrors[] = 'This date is unavailable because there is a confirmed booking.';
                    break;
                }
            }
        }

        if ($validationErrors !== []) {
            $bookingFeedback = implode(' ', $validationErrors);
            $bookingFeedbackType = 'error';
        } else {
            try {
                $bookingRequest = new BookingRequest(
                    $name,
                    $email,
                    $phone,
                    $poolChoice,
                    $tourType,
                    $checkIn,
                    $checkOut,
                    $checkInTime,
                    $checkOutTime
                );
                if ($bookingRepository === null) {
                    $bookingRepository = new BookingRepository(get_database_connection());
                }
                $bookingRepository->save($bookingRequest);
                $bookingFeedback = 'Your booking request has been received. We will contact you to confirm availability.';
                $formValues = array_fill_keys(array_keys($formValues), '');
                $_SESSION['booking_csrf_token'] = bin2hex(random_bytes(32));
            } catch (Throwable $exception) {
                error_log($exception->getMessage());
                http_response_code(503);
                $bookingFeedback = 'We could not save your request right now. Please try again later.';
                $bookingFeedbackType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Book a Stay | Casa Adelaida</title>
        <link rel="stylesheet" href="css/book.css?v=7">
    </head>

    <body>
        <header>
            <nav class="navbar">
                <a href="home.php" class="nav-logo"><h2 class="logo-text">Casa Adelaida</h2></a>

                <ul class="nav-menu">
                    <li class="nav-item">
                        <a href="home.php" class="nav-link">Home</a> </li>
                    
                    <li class="nav-item">
                        <a href="about.php" class="nav-link">About</a> </li>

                    <li class="nav-item">
                        <a href="book.php" class="nav-link" aria-current="page">Book Now</a> </li>
                </ul>
            </nav>
        </header>
        <main class="booking-layout">
            <section class="booking-intro" aria-labelledby="page-title">
                <p class="eyebrow">CASA ADELAIDA</p>
                <h1 id="page-title">Plan your stay.</h1>
                <p class="intro-copy">Tell us when you would like to visit and how to reach you. We'll follow up to discuss your booking.</p>
            </section>

            <section class="booking-panel" aria-labelledby="form-title">
                <h2 id="form-title">Booking request</h2>
                <p class="form-intro">Your details help us prepare your request.</p>

                <form id="booking-form" method="post" action="book.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['booking_csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <div class="field-grid">
                        <div class="field">
                            <label for="guest-name">Full name</label>
                            <input id="guest-name" name="name" type="text" maxlength="120" autocomplete="name" value="<?= htmlspecialchars($formValues['name'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="field">
                            <label for="guest-email">Email address</label>
                            <input id="guest-email" name="email" type="email" maxlength="254" autocomplete="email" value="<?= htmlspecialchars($formValues['email'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="field">
                            <label for="guest-phone">Phone number</label>
                            <input id="guest-phone" name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?= htmlspecialchars($formValues['phone'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="field">
                            <label for="pool-choice">Pool choice</label>
                            <select id="pool-choice" name="pool" required>
                                <option value="" <?= $formValues['pool'] === '' ? 'selected' : '' ?>>Select a pool</option>
                                <option value="Big Pool" <?= $formValues['pool'] === 'Big Pool' ? 'selected' : '' ?>>Big Pool</option>
                                <option value="Small Pool" <?= $formValues['pool'] === 'Small Pool' ? 'selected' : '' ?>>Small Pool</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="tour-type">Tour type</label>
                            <select id="tour-type" name="tour_type" required>
                                <option value="" <?= $formValues['tour_type'] === '' ? 'selected' : '' ?>>Select a tour type</option>
                                <option value="Day Tour" <?= $formValues['tour_type'] === 'Day Tour' ? 'selected' : '' ?>>Day Tour</option>
                                <option value="Overnight Tour" <?= $formValues['tour_type'] === 'Overnight Tour' ? 'selected' : '' ?>>Overnight Tour</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="check-in">Check-in</label>
                            <input id="check-in" name="check-in" type="date" value="<?= htmlspecialchars($formValues['check-in'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="field">
                            <label for="check-out" id="check-out-label">Check-out</label>
                            <input id="check-out" name="check-out" type="date" value="<?= htmlspecialchars($formValues['check-out'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="field">
                            <label for="check-in-time">Check-in time</label>
                            <input id="check-in-time" name="check-in-time" type="time" value="<?= htmlspecialchars($formValues['check-in-time'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                        <div class="field">
                            <label for="check-out-time">Check-out time</label>
                            <input id="check-out-time" name="check-out-time" type="time" value="<?= htmlspecialchars($formValues['check-out-time'], ENT_QUOTES, 'UTF-8') ?>" required>
                        </div>
                    </div>

                    <button class="submit-button" type="submit">Send booking request</button>
                    <p class="form-note">Your request is saved for review. Dates are not confirmed until we contact you.</p>
                    <?php if ($bookingFeedback !== ''): ?>
                        <p class="booking-feedback <?= $bookingFeedbackType === 'error' ? 'is-error' : '' ?>" role="status" aria-live="polite"><?= htmlspecialchars($bookingFeedback, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </form>
            </section>
        </main>

        <script>
            window.confirmedBookings = <?= json_encode($confirmedBookings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        </script>
        <script src="js/page-transition.js?v=1" defer></script>
        <script src="book.js" defer></script>

    </body>