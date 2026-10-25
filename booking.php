<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('booking.create');

// Only this student's active, paid enrolments are offered
$st = $pdo->prepare(
    "SELECT e.enrolment_id, p.programme_name
     FROM enrolments e
     JOIN payments py  ON py.payment_id  = e.payment_id
     JOIN programmes p ON p.programme_id = e.programme_id
     WHERE e.student_id = ? AND e.status = 'active' AND py.payment_status = 'completed'
     ORDER BY p.programme_name"
);
$st->execute([(int)$_SESSION['user_id']]);
$enrolments = $st->fetchAll();

$minDate = date('Y-m-d');
$maxDate = date('Y-m-d', strtotime('+90 days'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book a Session | Tutoring System</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="payment.html">Programme &amp; Payment</a>
      <a href="booking.php">Book a Session</a>
      <a href="my_sessions.php">My Sessions</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <h1>Book a Tutoring Session</h1>

    <?php if (!$enrolments): ?>
      <p class="error">You are not enrolled in any programme yet.</p>
      <p><a href="payment.html">Select a programme and pay first</a>.</p>
    <?php else: ?>
    <form id="bookingForm" action="book_session.php" method="post">
      <?= csrf_field() ?>

      <label for="enrolment_id">Enrolled programme</label>
      <select id="enrolment_id" name="enrolment_id" required>
        <option value="" disabled selected>Select your programme</option>
        <?php foreach ($enrolments as $en): ?>
          <option value="<?= e($en['enrolment_id']) ?>"><?= e($en['programme_name']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="session_date">Session date (Monday to Friday)</label>
      <input type="date" id="session_date" name="session_date"
             required min="<?= e($minDate) ?>" max="<?= e($maxDate) ?>">

      <div class="row">
        <div>
          <label for="start_time">Start time</label>
          <input type="time" id="start_time" name="start_time"
                 required min="08:00" max="17:00" step="3600">
        </div>
        <div>
          <label for="end_time">End time</label>
          <input type="time" id="end_time" name="end_time"
                 required min="09:00" max="18:00" step="3600">
        </div>
      </div>
      <small>Sessions run between 08:00 and 18:00. A tutor is assigned automatically.</small>

      <button type="submit">Request Session</button>
    </form>
    <?php endif; ?>
  </main>

  <script src="validation.js"></script>
</body>
</html>