<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('booking.create');
post_only();
csrf_check();

$studentId   = (int)$_SESSION['user_id'];
$enrolmentId = filter_var(post('enrolment_id'), FILTER_VALIDATE_INT);
$date        = post('session_date');
$start       = parse_time(post('start_time'));
$end         = parse_time(post('end_time'));
$d           = parse_date($date);

/* ---- 1. Input validation and business rules ---- */
if (!$enrolmentId || !$d || !$start || !$end) {
    message_page('Booking failed', 'Please complete all fields with valid values.');
}
if (!is_weekday($d)) {
    message_page('Booking failed', 'Sessions can only be booked from Monday to Friday.');
}
if ($end <= $start) {
    message_page('Booking failed', 'The end time must be after the start time.');
}
if ($start < '08:00:00' || $end > '18:00:00') {
    message_page('Booking failed', 'Sessions run between 08:00 and 18:00.');
}
if (new DateTime("$date $start") <= new DateTime('now')) {
    message_page('Booking failed', 'Please choose a date and time in the future.');
}

/* ---- 2. The enrolment must belong to this student, be active and be paid for ---- */
$st = $pdo->prepare(
    "SELECT e.programme_id, p.programme_name
     FROM enrolments e
     JOIN payments py  ON py.payment_id   = e.payment_id
     JOIN programmes p ON p.programme_id  = e.programme_id
     WHERE e.enrolment_id = ? AND e.student_id = ?
       AND e.status = 'active' AND py.payment_status = 'completed'"
);
$st->execute([$enrolmentId, $studentId]);
$enrolment = $st->fetch();
if (!$enrolment) {
    message_page('Booking failed', 'You must have a paid, active enrolment before booking sessions.');
}

/* ---- 3. The student cannot be in two sessions at once ---- */
$st = $pdo->prepare(
    "SELECT 1 FROM bookings b
     JOIN enrolments e ON e.enrolment_id = b.enrolment_id
     WHERE e.student_id = ? AND b.session_date = ? AND b.status <> 'cancelled'
       AND b.start_time < ? AND b.end_time > ? LIMIT 1"
);
$st->execute([$studentId, $date, $end, $start]);
if ($st->fetchColumn()) {
    message_page('Booking failed', 'You already have a session that overlaps with this time.');
}

/* ---- 4. Find tutors: teach the subject + availability covers the time (least busy first) ---- */
$dayName = $d->format('l');
$st = $pdo->prepare(
    "SELECT t.tutor_id FROM tutors t
     WHERE EXISTS (SELECT 1 FROM tutor_programmes tp
                   WHERE tp.tutor_id = t.tutor_id AND tp.programme_id = ?)
       AND EXISTS (SELECT 1 FROM tutor_availability ta
                   WHERE ta.tutor_id = t.tutor_id AND ta.day_of_week = ?
                     AND ta.start_time <= ? AND ta.end_time >= ?)
     ORDER BY (SELECT COUNT(*) FROM bookings x
               WHERE x.tutor_id = t.tutor_id AND x.status IN ('requested', 'confirmed')) ASC,
              t.tutor_id ASC"
);
$st->execute([$enrolment['programme_id'], $dayName, $start, $end]);
$candidates = $st->fetchAll(PDO::FETCH_COLUMN);

if (!$candidates) {
    message_page('No tutor available', 'No tutor for ' . $enrolment['programme_name'] . ' is available at that time. Please try another slot.');
}

/* ---- 5. Assign the first free tutor inside a transaction (prevents double-booking) ---- */
$assigned  = 0;
$bookingId = 0;
$failed    = false;

try {
    $pdo->beginTransaction();

    foreach ($candidates as $tid) {
        $tid = (int)$tid;

        // Lock the tutor row so two students cannot grab the same slot at the same time
        $pdo->prepare('SELECT tutor_id FROM tutors WHERE tutor_id = ? FOR UPDATE')->execute([$tid]);

        if (tutor_conflicts($pdo, $tid, $date, $start, $end)) {
            continue;
        }

        // A cancelled booking would block the unique key (tutor, date, start), so clear it
        $pdo->prepare(
            "DELETE FROM bookings WHERE tutor_id = ? AND session_date = ? AND start_time = ? AND status = 'cancelled'"
        )->execute([$tid, $date, $start]);

        // Lifecycle: REQUESTED -> CONFIRMED (as in the sequence diagram)
        $pdo->prepare(
            "INSERT INTO bookings (enrolment_id, tutor_id, session_date, start_time, end_time, status)
             VALUES (?, ?, ?, ?, ?, 'requested')"
        )->execute([$enrolmentId, $tid, $date, $start, $end]);
        $bookingId = (int)$pdo->lastInsertId();

        $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE booking_id = ?")->execute([$bookingId]);
        $assigned = $tid;
        break;
    }

    if ($assigned) {
        $pdo->commit();
    } else {
        $pdo->rollBack();
    }
} catch (Throwable $ex) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($ex->getMessage());
    $failed = true;
}

if ($failed) {
    message_page('Booking failed', 'Something went wrong. Please try again.');
}
if (!$assigned) {
    message_page('No tutor available', 'All suitable tutors are already booked at that time. Please try another slot.');
}

/* ---- 6. Notify student, tutor and administrator(s) ---- */
notify_booking($pdo, $bookingId, 'confirmed');

$st = $pdo->prepare('SELECT full_name FROM tutors WHERE tutor_id = ?');
$st->execute([$assigned]);
$tutorName = $st->fetchColumn();

message_page(
    'Session confirmed',
    "Your {$enrolment['programme_name']} session on $date at " . substr($start, 0, 5) .
    " is confirmed with $tutorName. Confirmation notifications have been sent.",
    true,
    'my_sessions.php'
);