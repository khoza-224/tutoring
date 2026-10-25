<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('sessions.view_own');
$tutorId = (int)$_SESSION['user_id'];

/* ---- Mark a session completed (CONFIRMED -> COMPLETED) ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_only();
    csrf_check();
    $bookingId = filter_var(post('booking_id'), FILTER_VALIDATE_INT);

    $st = $pdo->prepare(
        "SELECT booking_id FROM bookings
         WHERE booking_id = ? AND tutor_id = ? AND status = 'confirmed'
           AND TIMESTAMP(session_date, start_time) <= NOW()"
    );
    $st->execute([$bookingId, $tutorId]);
    if (!$st->fetchColumn()) {
        message_page('Cannot complete', 'Only your confirmed sessions that have already started can be completed.', false, 'tutor_sessions.php');
    }

    $pdo->prepare("UPDATE bookings SET status = 'completed' WHERE booking_id = ?")->execute([$bookingId]);
    notify_booking($pdo, (int)$bookingId, 'completed');
    message_page('Session completed', 'The session has been marked as completed.', true, 'tutor_sessions.php');
}

/* ---- List this tutor's scheduled sessions ---- */
$st = $pdo->prepare(
    "SELECT b.booking_id, CONCAT(s.first_name, ' ', s.last_name) AS student, p.programme_name,
            b.session_date, b.start_time, b.end_time, b.status
     FROM bookings b
     JOIN enrolments e ON e.enrolment_id = b.enrolment_id
     JOIN students s   ON s.student_id   = e.student_id
     JOIN programmes p ON p.programme_id = e.programme_id
     WHERE b.tutor_id = ?
     ORDER BY b.session_date, b.start_time"
);
$st->execute([$tutorId]);
$rows = $st->fetchAll();

page_start('My Sessions', true);
echo '<h1>My Scheduled Sessions</h1>';
if (!$rows) {
    echo '<p>You have no sessions assigned yet.</p>';
} else {
    echo '<table><thead><tr><th>ID</th><th>Student</th><th>Programme</th><th>Date</th><th>Time</th><th>Status</th><th></th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr><td>' . e($r['booking_id']) . '</td><td>' . e($r['student']) . '</td><td>' . e($r['programme_name'])
           . '</td><td>' . e($r['session_date']) . '</td><td>' . e(substr($r['start_time'], 0, 5)) . ' - ' . e(substr($r['end_time'], 0, 5))
           . '</td><td>' . e(ucfirst($r['status'])) . '</td><td>';
        if ($r['status'] === 'confirmed' && strtotime($r['session_date'] . ' ' . $r['start_time']) <= time()) {
            echo '<form method="post">' . csrf_field() . '<input type="hidden" name="booking_id" value="' . e($r['booking_id']) . '">'
               . '<button type="submit">Mark completed</button></form>';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
}
echo '<p><a href="availability.php">Manage availability</a></p>';
page_end();