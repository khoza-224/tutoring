<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('payment.make');
post_only();

$studentId   = (int)$_SESSION['user_id'];
$programmeId = filter_var(post('programme_id'), FILTER_VALIDATE_INT);
$method      = post('payment_method');

function luhn_valid(string $number): bool
{
    $sum = 0;
    $alt = false;
    for ($i = strlen($number) - 1; $i >= 0; $i--) {
        $d = (int)$number[$i];
        if ($alt) { $d *= 2; if ($d > 9) $d -= 9; }
        $sum += $d;
        $alt = !$alt;
    }
    return $sum % 10 === 0;
}

$errors = [];
if (!$programmeId) $errors[] = 'Select a programme.';
if (!in_array($method, ['Card', 'EFT'], true)) $errors[] = 'Select a valid payment method.';

if ($method === 'Card') {
    $cardNumber = preg_replace('/\s+/', '', post('card_number'));
    if (!preg_match('/^\d{13,19}$/', $cardNumber) || !luhn_valid($cardNumber)) $errors[] = 'Card number is invalid.';
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', post('card_expiry')) || post('card_expiry') < date('Y-m')) $errors[] = 'Card has expired.';
    if (!preg_match('/^\d{3,4}$/', post('card_cvv'))) $errors[] = 'CVV is invalid.';
    if (!preg_match("/^[\p{L}' \-]{2,100}$/u", post('card_name'))) $errors[] = 'Name on card is invalid.';
}
if ($errors) {
    message_page('Payment failed', implode(' ', $errors));
}

// Fee is read from the database (never trust an amount sent by the browser)
$st = $pdo->prepare('SELECT programme_id, programme_name, fee FROM programmes WHERE programme_id = ?');
$st->execute([$programmeId]);
$programme = $st->fetch();
if (!$programme) {
    message_page('Payment failed', 'That programme does not exist.');
}

// Already enrolled?
$st = $pdo->prepare('SELECT 1 FROM enrolments WHERE student_id = ? AND programme_id = ?');
$st->execute([$studentId, $programmeId]);
if ($st->fetchColumn()) {
    message_page('Already enrolled', 'You are already enrolled in ' . $programme['programme_name'] . '.', false, 'dashboard.php');
}

// A real system would call a card gateway here. This project simulates an approved payment.
// Only the method, amount and reference are stored. Card number and CVV are discarded.
$reference = 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

try {
    $pdo->beginTransaction();

    $pay = $pdo->prepare(
        "INSERT INTO payments (student_id, programme_id, amount, payment_method, payment_status, reference_no)
         VALUES (?, ?, ?, ?, 'completed', ?)"
    );
    $pay->execute([$studentId, $programmeId, $programme['fee'], $method, $reference]);
    $paymentId = (int)$pdo->lastInsertId();

    // Payment confirmed -> automatic enrolment
    $enr = $pdo->prepare(
        "INSERT INTO enrolments (student_id, programme_id, payment_id, enrolment_date, status)
         VALUES (?, ?, ?, CURDATE(), 'active')"
    );
    $enr->execute([$studentId, $programmeId, $paymentId]);

    $pdo->commit();
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($ex->getMessage());
    message_page('Payment failed', 'We could not process your payment. You have not been charged. Please try again.');
}

message_page(
    'Payment successful',
    "Reference $reference. R" . number_format((float)$programme['fee'], 2) .
    " paid. You are now enrolled in {$programme['programme_name']} and can book sessions.",
    true,
    'booking.php'
);