<?php
require_once __DIR__ . '/includes/helpers.php';
post_only();

// Whitelist of roles -> table details (the role from the form is never put into SQL directly)
$roles = [
    'student' => ['table' => 'students',       'id' => 'student_id', 'name' => "CONCAT(first_name, ' ', last_name)"],
    'tutor'   => ['table' => 'tutors',         'id' => 'tutor_id',   'name' => 'full_name'],
    'admin'   => ['table' => 'administrators', 'id' => 'admin_id',   'name' => 'full_name'],
];

// Brute-force protection: lock for 5 minutes after 5 failed attempts
if (($_SESSION['login_locked_until'] ?? 0) > time()) {
    message_page('Login locked', 'Too many failed attempts. Please wait a few minutes and try again.');
}

$role     = post('role');
$email    = strtolower(post('email'));
$password = post('password', false);

if (!isset($roles[$role]) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
    message_page('Login failed', 'Invalid email or password.');
}

$r  = $roles[$role];
$st = $pdo->prepare("SELECT {$r['id']} AS id, {$r['name']} AS name, email, password_hash FROM {$r['table']} WHERE email = ?");
$st->execute([$email]);
$user = $st->fetch();

// Always run one bcrypt check, so an unknown email takes as long as a wrong password
$hashToCheck   = $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$validPassword = password_verify($password, $hashToCheck);


if (!$user || !$validPassword) {
    $_SESSION['login_fails'] = ($_SESSION['login_fails'] ?? 0) + 1;
    if ($_SESSION['login_fails'] >= 5) {
        $_SESSION['login_locked_until'] = time() + 300;
        $_SESSION['login_fails'] = 0;
    }
    message_page('Login failed', 'Invalid email or password.');
}

// Upgrade the hash if PHP's default algorithm has changed
if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
    $up = $pdo->prepare("UPDATE {$r['table']} SET password_hash = ? WHERE {$r['id']} = ?");
    $up->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
}

session_regenerate_id(true);          // prevents session fixation
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role']    = $role;
$_SESSION['name']    = $user['name'];
$_SESSION['email']   = $user['email'];
$_SESSION['login_fails'] = 0;

header('Location: dashboard.php');
exit;