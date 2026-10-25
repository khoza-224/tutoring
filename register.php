<?php
require_once __DIR__ . '/includes/helpers.php';
post_only();

$first    = post('first_name');
$last     = post('last_name');
$email    = strtolower(post('email'));
$phone    = post('phone');
$password = post('password', false);
$confirm  = post('confirm_password', false);

$errors = [];
if (!preg_match("/^[\p{L}' \-]{2,60}$/u", $first))  $errors[] = 'Enter a valid first name.';
if (!preg_match("/^[\p{L}' \-]{2,60}$/u", $last))   $errors[] = 'Enter a valid last name.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Enter a valid email address.';
if (!preg_match('/^0\d{9}$/', $phone))              $errors[] = 'Phone must be 10 digits starting with 0.';
if (!strong_password($password))                    $errors[] = 'Password needs 8+ characters with uppercase, lowercase and a number.';
if ($password !== $confirm)                         $errors[] = 'Passwords do not match.';
if (post('terms') !== '1')                          $errors[] = 'You must accept the terms and conditions.';

if ($errors) {
    message_page('Registration failed', implode(' ', $errors));
}

$hash = password_hash($password, PASSWORD_DEFAULT);

try {
    $st = $pdo->prepare(
        'INSERT INTO students (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
    );
    $st->execute([$first, $last, $email, $phone, $hash]);
} catch (PDOException $ex) {
    if ($ex->getCode() === '23000') {
        message_page('Registration failed', 'That email address is already registered.');
    }
    error_log($ex->getMessage());
    message_page('Registration failed', 'Something went wrong. Please try again later.');
}

message_page('Registration successful', 'Your account has been created. You can now log in.', true, 'login.html');