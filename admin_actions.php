<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('admin.dashboard');

$adminId = (int)$_SESSION['user_id'];
$action  = $_SERVER['REQUEST_METHOD'] === 'POST' ? post('action') : req('action');

/* =========================================================
   SEARCH / VIEW RECORDS (GET)
   ========================================================= */
if ($action === 'search') {
    $type = req('record_type');
    $q    = '%' . req('q') . '%';
    $from = parse_date(req('from_date')) ? req('from_date') : '1000-01-01';
    $to   = parse_date(req('to_date'))   ? req('to_date')   : '9999-12-31';

    // Fixed queries per record type (password hashes are never selected)
    $queries = [
        'students' => "SELECT student_id, first_name, last_name, email, phone, created_at FROM students
                       WHERE CONCAT_WS(' ', first_name, last_name, email) LIKE ? AND DATE(created_at) BETWEEN ? AND ?
                       ORDER BY student_id DESC LIMIT 200",
        'tutors'   => "SELECT tutor_id, full_name, email, phone, created_at FROM tutors
                       WHERE CONCAT_WS(' ', full_name, email) LIKE ? AND DATE(created_at) BETWEEN ? AND ?
                       ORDER BY tutor_id DESC LIMIT 200",
        'payments' => "SELECT py.payment_id, py.reference_no, CONCAT(s.first_name, ' ', s.last_name) AS student,
                              p.programme_name, py.amount, py.payment_method, py.payment_status, py.paid_at
                       FROM payments py
                       JOIN students s   ON s.student_id   = py.student_id
                       JOIN programmes p ON p.programme_id = py.programme_id
                       WHERE CONCAT_WS(' ', py.reference_no, s.first_name, s.last_name, s.email) LIKE ?
                         AND DATE(py.paid_at) BETWEEN ? AND ?
                       ORDER BY py.payment_id DESC LIMIT 200",
        'bookings' => "SELECT b.booking_id, CONCAT(s.first_name, ' ', s.last_name) AS student, t.full_name AS tutor,
                              p.programme_name, b.session_date, b.start_time, b.end_time, b.status
                       FROM bookings b
                       JOIN enrolments e ON e.enrolment_id = b.enrolment_id
                       JOIN students s   ON s.student_id   = e.student_id
                       JOIN programmes p ON p.programme_id = e.programme_id
                       JOIN tutors t     ON t.tutor_id     = b.tutor_id
                       WHERE CONCAT_WS(' ', s.first_name, s.last_name, t.full_name, p.programme_name) LIKE ?
                         AND b.session_date BETWEEN ? AND ?
                       ORDER BY b.session_date DESC, b.start_time LIMIT 200",
    ];
    if (!isset($queries[$type])) {
        message_page('Invalid request', 'Unknown record type.');
    }
    $st = $pdo->prepare($queries[$type]);
    $st->execute([$q, $from, $to]);

    page_start('Search results', true);
    echo '<h1>' . e(ucfirst($type)) . '</h1>';
    render_table($st->fetchAll());
    echo '<p><a href="admin_dashboard.php">Back to dashboard</a></p>';
    page_end();
    exit;
}

post_only();
csrf_check();

/* =========================================================
   VERIFY PAYMENT
   ========================================================= */
if ($action === 'verify_payment') {
    $paymentId = filter_var(post('payment_id'), FILTER_VALIDATE_INT);
    $decision  = post('decision');
    if (!$paymentId || !in_array($decision, ['completed', 'failed', 'refunded'], true)) {
        message_page('Invalid request', 'Enter a valid payment ID and decision.');
    }

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare('SELECT student_id, programme_id FROM payments WHERE payment_id = ? FOR UPDATE');
        $st->execute([$paymentId]);
        $pay = $st->fetch();
        if (!$pay) {
            $pdo->rollBack();
            message_page('Not found', 'No payment with that ID exists.');
        }

        $pdo->prepare('UPDATE payments SET payment_status = ?, verified_by = ? WHERE payment_id = ?')
            ->execute([$decision, $adminId, $paymentId]);

        if ($decision === 'completed') {
            // Approved: make sure the student is enrolled
            $st = $pdo->prepare('SELECT 1 FROM enrolments WHERE payment_id = ?');
            $st->execute([$paymentId]);
            if ($st->fetchColumn()) {
                $pdo->prepare("UPDATE enrolments SET status = 'active' WHERE payment_id = ?")->execute([$paymentId]);
            } else {
                $st = $pdo->prepare('SELECT 1 FROM enrolments WHERE student_id = ? AND programme_id = ?');
                $st->execute([$pay['student_id'], $pay['programme_id']]);
                if (!$st->fetchColumn()) {
                    $pdo->prepare("INSERT INTO enrolments (student_id, programme_id, payment_id, enrolment_date, status)
                                   VALUES (?, ?, ?, CURDATE(), 'active')")
                        ->execute([$pay['student_id'], $pay['programme_id'], $paymentId]);
                }
            }
        } else {
            // Rejected or refunded: cancel the enrolment and its upcoming bookings
            $pdo->prepare("UPDATE enrolments SET status = 'cancelled' WHERE payment_id = ?")->execute([$paymentId]);
            $pdo->prepare(
                "UPDATE bookings b JOIN enrolments e ON e.enrolment_id = b.enrolment_id
                 SET b.status = 'cancelled'
                 WHERE e.payment_id = ? AND b.status IN ('requested', 'confirmed')"
            )->execute([$paymentId]);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($ex->getMessage());
        message_page('Error', 'The payment could not be updated.');
    }
    message_page('Payment updated', "Payment #$paymentId is now marked as $decision.", true, 'admin_dashboard.php');
}

/* =========================================================
   ASSIGN / REASSIGN TUTOR
   ========================================================= */
if ($action === 'assign_tutor') {
    $bookingId = filter_var(post('booking_id'), FILTER_VALIDATE_INT);
    $tutorId   = filter_var(post('tutor_id'), FILTER_VALIDATE_INT);
    if (!$bookingId || !$tutorId) {
        message_page('Invalid request', 'Enter a valid booking ID and select a tutor.');
    }

    $st = $pdo->prepare(
        "SELECT b.session_date, b.start_time, b.end_time, b.status, e.programme_id
         FROM bookings b JOIN enrolments e ON e.enrolment_id = b.enrolment_id
         WHERE b.booking_id = ?"
    );
    $st->execute([$bookingId]);
    $b = $st->fetch();
    if (!$b || !in_array($b['status'], ['requested', 'confirmed'], true)) {
        message_page('Cannot assign', 'The booking does not exist or is already completed/cancelled.');
    }

    // Same rules as a student booking: subject, availability, weekday and no clash
    $dayName = (new DateTime($b['session_date']))->format('l');
    if (!tutor_available($pdo, $tutorId, (int)$b['programme_id'], $dayName, $b['start_time'], $b['end_time'])) {
        message_page('Cannot assign', 'That tutor does not teach this programme or is not available at that time.');
    }
    if (tutor_conflicts($pdo, $tutorId, $b['session_date'], $b['start_time'], $b['end_time'], $bookingId)) {
        message_page('Cannot assign', 'That tutor already has a session at that time.');
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM bookings WHERE tutor_id = ? AND session_date = ? AND start_time = ?
                       AND status = 'cancelled' AND booking_id <> ?")
            ->execute([$tutorId, $b['session_date'], $b['start_time'], $bookingId]);
        $pdo->prepare('UPDATE bookings SET tutor_id = ? WHERE booking_id = ?')->execute([$tutorId, $bookingId]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log($ex->getMessage());
        message_page('Error', 'The tutor could not be assigned.');
    }
    notify_booking($pdo, (int)$bookingId, 'reassigned');
    message_page('Tutor assigned', "Booking #$bookingId has been assigned and everyone has been notified.", true, 'admin_dashboard.php');
}

/* =========================================================
   ADD PROGRAMME
   ========================================================= */
if ($action === 'add_programme') {
    $name = post('programme_name');
    $desc = post('description');
    $fee  = post('fee');

    if (!preg_match("/^[\p{L}0-9' .\-&]{2,100}$/u", $name) || strlen($desc) > 500
        || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $fee)) {
        message_page('Invalid input', 'Check the programme name, description and fee.');
    }
    try {
        $pdo->prepare('INSERT INTO programmes (programme_name, description, fee) VALUES (?, ?, ?)')
            ->execute([$name, $desc, number_format((float)$fee, 2, '.', '')]);
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            message_page('Duplicate', 'A programme with that name already exists.');
        }
        error_log($ex->getMessage());
        message_page('Error', 'The programme could not be added.');
    }
    message_page('Programme added', "$name has been added.", true, 'admin_dashboard.php');
}

/* =========================================================
   DELETE USER (users with payments/enrolments are protected by foreign keys)
   ========================================================= */
if ($action === 'delete_user') {
    $role = post('user_role');
    $id   = filter_var(post('user_id'), FILTER_VALIDATE_INT);
    $map  = ['student' => ['students', 'student_id'], 'tutor' => ['tutors', 'tutor_id']];
    if (!isset($map[$role]) || !$id) {
        message_page('Invalid request', 'Select a role and enter a valid user ID.');
    }
    [$table, $pk] = $map[$role];
    try {
        $st = $pdo->prepare("DELETE FROM $table WHERE $pk = ?");
        $st->execute([$id]);
        if ($st->rowCount() === 0) {
            message_page('Not found', 'No such user exists.');
        }
    } catch (PDOException $ex) {
        message_page('Cannot delete', 'This user has payment, enrolment or booking records that must be kept.');
    }
    message_page('User removed', 'The account has been deleted.', true, 'admin_dashboard.php');
}

message_page('Invalid request', 'Unknown action.');