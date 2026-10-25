<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('availability.manage');
$tutorId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_only();
    $action = post('action');

    /* ---- Add a slot ---- */
    if ($action === 'add') {
        $day   = post('day_of_week');
        $start = parse_time(post('start_time'));
        $end   = parse_time(post('end_time'));

        if (!in_array($day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'], true)) {
            message_page('Invalid slot', 'Availability can only be set for Monday to Friday.');
        }
        if (!$start || !$end || $end <= $start || $start < '08:00:00' || $end > '18:00:00') {
            message_page('Invalid slot', 'Enter a valid time range between 08:00 and 18:00.');
        }

        // Reject overlap with an existing slot on the same day
        $st = $pdo->prepare(
            'SELECT 1 FROM tutor_availability WHERE tutor_id = ? AND day_of_week = ? AND start_time < ? AND end_time > ? LIMIT 1'
        );
        $st->execute([$tutorId, $day, $end, $start]);
        if ($st->fetchColumn()) {
            message_page('Invalid slot', 'This slot overlaps with one you already have on that day.');
        }

        $pdo->prepare(
            'INSERT INTO tutor_availability (tutor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)'
        )->execute([$tutorId, $day, $start, $end]);
        message_page('Slot saved', "Availability added for $day.", true, 'availability.php');
    }

    /* ---- Delete a slot (only your own, and only if no upcoming booking depends on it) ---- */
    if ($action === 'delete') {
        $slotId = filter_var(post('availability_id'), FILTER_VALIDATE_INT);

        $st = $pdo->prepare('SELECT day_of_week, start_time, end_time FROM tutor_availability WHERE availability_id = ? AND tutor_id = ?');
        $st->execute([$slotId, $tutorId]);
        $slot = $st->fetch();
        if (!$slot) {
            message_page('Cannot remove', 'That slot does not exist in your schedule.', false, 'availability.php');
        }

        $st = $pdo->prepare(
            "SELECT 1 FROM bookings
             WHERE tutor_id = ? AND status IN ('requested', 'confirmed') AND session_date >= CURDATE()
               AND DAYNAME(session_date) = ? AND start_time >= ? AND end_time <= ? LIMIT 1"
        );
        $st->execute([$tutorId, $slot['day_of_week'], $slot['start_time'], $slot['end_time']]);
        if ($st->fetchColumn()) {
            message_page('Cannot remove', 'You have upcoming sessions in this slot. Ask an administrator to reassign them first.', false, 'availability.php');
        }

        $pdo->prepare('DELETE FROM tutor_availability WHERE availability_id = ? AND tutor_id = ?')->execute([$slotId, $tutorId]);
        message_page('Slot removed', 'The availability slot has been removed.', true, 'availability.php');
    }

    message_page('Invalid request', 'Unknown action.');
}

/* ---- List this tutor's slots ---- */
$st = $pdo->prepare(
    "SELECT availability_id AS slot_number, day_of_week, TIME_FORMAT(start_time, '%H:%i') AS from_time, TIME_FORMAT(end_time, '%H:%i') AS to_time
     FROM tutor_availability WHERE tutor_id = ?
     ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'), start_time"
);
$st->execute([$tutorId]);

page_start('My Availability', true);
echo '<h1>My Availability</h1>';
render_table($st->fetchAll());
echo '<p><a href="availability.html">Add or remove a slot</a> | <a href="tutor_sessions.php">View my sessions</a></p>';
page_end();