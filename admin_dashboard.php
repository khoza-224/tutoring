<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('admin.dashboard');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard | Tutoring System</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <header>
    <nav>
      <a href="dashboard.php">Dashboard</a>
      <a href="admin_dashboard.php">Admin Controls</a>
      <a href="logout.php">Logout</a>
    </nav>
  </header>

  <main>
    <h1>Administrator Dashboard</h1>

    <!-- Verify payments -->
    <form action="admin_actions.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="verify_payment">
      <fieldset>
        <legend>Verify payment</legend>
        <label for="payment_id">Payment ID</label>
        <input type="number" id="payment_id" name="payment_id"
               required min="1" step="1">

        <label for="decision">Decision</label>
        <select id="decision" name="decision" required>
          <option value="" disabled selected>Select a decision</option>
          <option value="completed">Approve payment</option>
          <option value="failed">Reject payment</option>
          <option value="refunded">Mark as refunded</option>
        </select>
      </fieldset>
      <button type="submit">Update Payment</button>
    </form>

    <!-- Assign or reassign tutor -->
    <form action="admin_actions.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign_tutor">
      <fieldset>
        <legend>Assign / reassign tutor</legend>
        <div class="row">
          <div>
            <label for="booking_id">Booking ID</label>
            <input type="number" id="booking_id" name="booking_id"
                   required min="1" step="1">
          </div>
          <div>
            <label for="tutor_id">Tutor</label>
            <select id="tutor_id" name="tutor_id" required>
              <option value="" disabled selected>Select a tutor</option>
              <option value="1">Thabo Mokoena</option>
              <option value="2">Lerato Dlamini</option>
              <option value="3">Sipho Nkosi</option>
            </select>
          </div>
        </div>
      </fieldset>
      <button type="submit">Assign Tutor</button>
    </form>

    <!-- Add a programme -->
    <form action="admin_actions.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_programme">
      <fieldset>
        <legend>Add a programme</legend>
        <label for="programme_name">Programme name</label>
        <input type="text" id="programme_name" name="programme_name"
               required maxlength="100">

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3"
                  maxlength="500"></textarea>

        <label for="fee">Fee (R)</label>
        <input type="number" id="fee" name="fee"
               required min="0" step="0.01" placeholder="1500.00">
      </fieldset>
      <button type="submit">Add Programme</button>
    </form>

    <!-- Search and filter records -->
    <form action="admin_actions.php" method="get">
      <input type="hidden" name="action" value="search">
      <fieldset>
        <legend>View records</legend>
        <label for="record_type">Record type</label>
        <select id="record_type" name="record_type" required>
          <option value="students">Students</option>
          <option value="tutors">Tutors</option>
          <option value="payments">Payments</option>
          <option value="bookings">Bookings</option>
        </select>

        <label for="q">Search</label>
        <input type="search" id="q" name="q" maxlength="100"
               placeholder="Name, email or reference">

        <div class="row">
          <div>
            <label for="from_date">From</label>
            <input type="date" id="from_date" name="from_date">
          </div>
          <div>
            <label for="to_date">To</label>
            <input type="date" id="to_date" name="to_date">
          </div>
        </div>
      </fieldset>
      <button type="submit">Search</button>
    </form>

    <!-- Remove a user -->
    <form action="admin_actions.php" method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete_user">
      <fieldset>
        <legend>Remove a user account</legend>
        <div class="row">
          <div>
            <label for="user_role">Role</label>
            <select id="user_role" name="user_role" required>
              <option value="student">Student</option>
              <option value="tutor">Tutor</option>
            </select>
          </div>
          <div>
            <label for="user_id">User ID</label>
            <input type="number" id="user_id" name="user_id"
                   required min="1" step="1">
          </div>
        </div>
      </fieldset>
      <button type="submit" class="danger">Remove User</button>
    </form>

    <p><a href="admin_crud.php?entity=students">Manage students</a> |
       <a href="admin_crud.php?entity=tutors">Manage tutors</a> |
       <a href="admin_crud.php?entity=programmes">Manage programmes</a> |
       <a href="admin_crud.php?entity=bookings">Manage bookings</a></p>
  </main>
</body>
</html>