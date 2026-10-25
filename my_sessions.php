<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('booking.view_own');
$studentId = (int)$_SESSION['user_id'];

/* ---- Cancel a booking (CONFIRMED / REQUESTED -> CANCELLED) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_only();
    csrf_check();
    $bookingId = filter_var(post('booking_id'), FILTER_VALIDATE_INT);

    $st = $pdo->prepare(
        "SELECT b.booking_id FROM bookings b
         JOIN enrolments e ON e.enrolment_id = b.enrolment_id
         WHERE b.booking_id = ? AND e.student_id = ?
           AND b.status IN ('requested', 'confirmed')
           AND TIMESTAMP(b.session_date, b.start_time) > NOW()"
    );
    $st->execute([$bookingId, $studentId]);
    if (!$st->fetchColumn()) {
        message_page('Cannot cancel', 'This booking does not exist, is already closed, or has already started.', false, 'my_sessions.php');
    }

    $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ?")->execute([$bookingId]);
    notify_booking($pdo, (int)$bookingId, 'cancelled');
    message_page('Booking cancelled', 'Your session has been cancelled and the tutor has been notified.', true, 'my_sessions.php');
}

/* ---- List this student's sessions ---- */
$st = $pdo->prepare(
    "SELECT b.booking_id, p.programme_name, t.full_name AS tutor,
            b.session_date, b.start_time, b.end_time, b.status
     FROM bookings b
     JOIN enrolments e ON e.enrolment_id = b.enrolment_id
     JOIN programmes p ON p.programme_id = e.programme_id
     JOIN tutors t     ON t.tutor_id     = b.tutor_id
     WHERE e.student_id = ?
     ORDER BY b.session_date DESC, b.start_time DESC"
);
$st->execute([$studentId]);
$rows = $st->fetchAll();

page_start('My Sessions', true);
echo '<h1>My Tutoring Sessions</h1>';
if (!$rows) {
    echo '<p>You have no sessions yet. <a href="booking.php">Book one now</a>.</p>';
} else {
    echo '<table><thead><tr><th>ID</th><th>Programme</th><th>Tutor</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . e($r['booking_id']) . '</td><td>' . e($r['programme_name']) . '</td><td>' . e($r['tutor'])
           . '</td><td>' . e($r['session_date']) . '</td><td>' . e(substr($r['start_time'], 0, 5)) . ' - ' . e(substr($r['end_time'], 0, 5))
           . '</td><td>' . e(ucfirst($r['status'])) . '</td><td>';
        if (in_array($r['status'], ['requested', 'confirmed'], true)) {
            echo '<form method="post" onsubmit="return confirm(\'Cancel this session?\');">'
               . csrf_field() . '<input type="hidden" name="booking_id" value="' . e($r['booking_id']) . '">'
               . '<button type="submit" class="danger">Cancel</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}
page_end();