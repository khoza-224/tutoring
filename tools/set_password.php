<?php
// Run from the command line:   php tools/set_passwords.php "Sample@2026"
// XAMPP on Windows example:    C:\xampp\php\php.exe tools\set_passwords.php "Sample@2026"

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This script can only be run from the command line.');
}
require_once __DIR__ . '/../includes/db.php';

$password = $argv[1] ?? '';
if (strlen($password) < 8) {
    exit("Usage: php tools/set_passwords.php \"YourPassword1\"\n(minimum 8 characters)\n");
}

// Table and column names come from this fixed list, never from user input
$targets = [
    'students'       => 'student_id',
    'tutors'         => 'tutor_id',
    'administrators' => 'admin_id',
];

foreach ($targets as $table => $pk) {
    $ids = $pdo->query("SELECT $pk FROM $table")->fetchAll(PDO::FETCH_COLUMN);
    $update = $pdo->prepare("UPDATE $table SET password_hash = ? WHERE $pk = ?");
    foreach ($ids as $id) {
        // Same password, but a new random salt each time = a different hash for every user
        $update->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
    }
    echo count($ids) . " accounts updated in $table\n";
}
echo "Done. All sample accounts now use the password you supplied.\n";