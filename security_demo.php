<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('security.demo');

$hashResult   = null;
$injectResult = null;
$error        = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_only();
    csrf_check();
    $demo = post('demo');

    /* ---------- Demo 1: password hashing ---------- */
    if ($demo === 'hash') {
        $pw = post('password', false);
        if ($pw === '' || strlen($pw) > 72) {
            $error = 'Enter a password of 1 to 72 characters.';
        } else {
            $h1   = password_hash($pw, PASSWORD_DEFAULT);
            $h2   = password_hash($pw, PASSWORD_DEFAULT);
            $info = password_get_info($h1);
            $hashResult = [
                'Algorithm'                  => $info['algoName'],
                'Cost factor'                => $info['options']['cost'] ?? 'n/a',
                'Hash length'                => strlen($h1),
                'Hash 1 (same password)'     => $h1,
                'Hash 2 (same password)'     => $h2,
                'Hashes identical?'          => $h1 === $h2 ? 'Yes' : 'No (a new random salt is used every time)',
                'Correct password verifies?' => password_verify($pw, $h1) ? 'Yes' : 'No',
                'Wrong password verifies?'   => password_verify($pw . 'x', $h1) ? 'Yes' : 'No',
            ];
        }

    /* ---------- Demo 2: SQL injection and XSS ---------- */
    } elseif ($demo === 'inject') {
        $input = mb_substr(post('payload'), 0, 200);

        // (a) What unsafe string concatenation WOULD build. Displayed only, never executed.
        $unsafeSql = "SELECT student_id, email FROM students WHERE email = '" . $input . "'";

        // (b) The safe version: the value is bound as data and cannot change the query
        $st = $pdo->prepare('SELECT student_id, email FROM students WHERE email = ?');
        $st->execute([$input]);
        $injectResult = [
            'input'    => $input,
            'unsafe'   => $unsafeSql,
            'rowCount' => count($st->fetchAll()),
        ];
    } else {
        $error = 'Unknown demonstration.';
    }
}

/* ---------- Stored hashes for ALL users (only metadata is shown) ---------- */
$tables = [
    'Student'       => ['students', 'student_id'],
    'Tutor'         => ['tutors', 'tutor_id'],
    'Administrator' => ['administrators', 'admin_id'],
];
$userRows  = [];
$allHashes = [];
foreach ($tables as $label => [$table, $pk]) {
    foreach ($pdo->query("SELECT $pk AS id, email, password_hash FROM $table ORDER BY $pk") as $r) {
        $info        = password_get_info($r['password_hash']);
        $allHashes[] = $r['password_hash'];
        $userRows[]  = [
            'role'             => $label,
            'id'               => $r['id'],
            'email'            => $r['email'],
            'algorithm'        => $info['algoName'],
            'cost'             => $info['options']['cost'] ?? 'n/a',
            'hash_length'      => strlen($r['password_hash']),
            'hash_starts_with' => substr($r['password_hash'], 0, 7),
            'needs_rehash'     => password_needs_rehash($r['password_hash'], PASSWORD_DEFAULT) ? 'yes' : 'no',
        ];
    }
}
$allUnique = count(array_unique($allHashes)) === count($allHashes);

/* ---------- Page ---------- */
page_start('Security demonstration', true);
echo '<h1>Security Demonstration</h1>';
if ($error) echo '<p class="error">' . e($error) . '</p>';

echo '<h2>8.1 Password hashing demo</h2>';
echo '<form method="post">' . csrf_field()
   . '<input type="hidden" name="demo" value="hash">'
   . '<label for="password">Type any password to hash (it is not stored)</label>'
   . '<input type="text" id="password" name="password" maxlength="72" required autocomplete="off">'
   . '<button type="submit">Hash it twice</button></form>';

if ($hashResult) {
    echo '<table><tbody>';
    foreach ($hashResult as $label => $value) {
        echo '<tr><th>' . e($label) . '</th><td style="word-break:break-all">' . e($value) . '</td></tr>';
    }
    echo '</tbody></table>';
}

echo '<h2>8.1 Stored hashes: students, tutors and administrators</h2>';
echo '<p>Only the algorithm, cost, length and a 7-character prefix are shown. Full hashes are never displayed.</p>';
render_table($userRows);
echo '<p class="' . ($allUnique ? 'ok' : 'error') . '">'
   . ($allUnique
        ? 'All ' . count($allHashes) . ' stored hashes are different, because every hash has its own random salt, even if two users chose the same password.'
        : 'Note: some stored hashes are identical. This is expected for the sample data (all sample users share one hash). Run tools/set_passwords.php to give every user a unique salted hash.')
   . '</p>';

echo '<h2>8.3 SQL injection and XSS demo</h2>';
echo '<p>Try <code>\' OR \'1\'=\'1</code> or <code>&lt;script&gt;alert(\'XSS\')&lt;/script&gt;</code>.</p>';
echo '<form method="post">' . csrf_field()
   . '<input type="hidden" name="demo" value="inject">'
   . '<label for="payload">Enter a test string</label>'
   . '<input type="text" id="payload" name="payload" maxlength="200" required autocomplete="off">'
   . '<button type="submit">Run demo</button></form>';

if ($injectResult) {
    echo '<h3>1. Unsafe code (concatenation) would build this query. It was NOT executed.</h3>';
    echo '<pre style="white-space:pre-wrap">' . e($injectResult['unsafe']) . '</pre>';
    echo '<p>For the input <code>\' OR \'1\'=\'1</code> the condition becomes always true and every student would be returned.</p>';

    echo '<h3>2. Safe code (prepared statement) was executed</h3>';
    echo '<pre>SELECT student_id, email FROM students WHERE email = ?</pre>';
    echo '<p>Bound value (treated purely as data): <code>' . e($injectResult['input']) . '</code></p>';
    echo '<p class="ok">Rows returned: ' . e($injectResult['rowCount']) . '. The input cannot change the structure of the query.</p>';

    echo '<h3>3. Cross-site scripting protection</h3>';
    echo '<p>Displayed safely with <code>e()</code>: <strong>' . e($injectResult['input']) . '</strong></p>';
    echo '<p>The HTML the browser actually receives (special characters converted to entities, so no script can run):</p>';
    echo '<pre style="white-space:pre-wrap">' . e(e($injectResult['input'])) . '</pre>';
}

page_end();