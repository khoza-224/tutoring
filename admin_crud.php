<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('admin.dashboard');

/* ---------- Whitelist of entities and their editable fields: [label, type, extra] ---------- */
$cfg = [
    'students' => [
        'table' => 'students', 'pk' => 'student_id', 'title' => 'Students', 'password' => true,
        'fields' => [
            'first_name' => ['First name', 'name'],
            'last_name'  => ['Last name', 'name'],
            'email'      => ['Email', 'email'],
            'phone'      => ['Phone', 'phone'],
        ],
    ],
    'tutors' => [
        'table' => 'tutors', 'pk' => 'tutor_id', 'title' => 'Tutors', 'password' => true,
        'fields' => [
            'full_name' => ['Full name', 'name'],
            'email'     => ['Email', 'email'],
            'phone'     => ['Phone', 'phone'],
        ],
    ],
    'programmes' => [
        'table' => 'programmes', 'pk' => 'programme_id', 'title' => 'Programmes', 'password' => false,
        'fields' => [
            'programme_name' => ['Programme name', 'name'],
            'description'    => ['Description', 'text'],
            'fee'            => ['Fee (R)', 'money'],
        ],
    ],
    'bookings' => [
        'table' => 'bookings', 'pk' => 'booking_id', 'title' => 'Bookings', 'password' => false,
        'fields' => [
            'enrolment_id' => ['Enrolment ID', 'int'],
            'tutor_id'     => ['Tutor ID', 'int'],
            'session_date' => ['Session date', 'date'],
            'start_time'   => ['Start time', 'time'],
            'end_time'     => ['End time', 'time'],
            'status'       => ['Status', 'enum', ['requested', 'confirmed', 'completed', 'cancelled']],
        ],
    ],
];

$entity = req('entity') ?: post('entity');
if (!isset($cfg[$entity])) $entity = 'students';
require_permission('crud.' . $entity);      // per-entity permission from the RBAC matrix

$c     = $cfg[$entity];
$table = $c['table'];
$pk    = $c['pk'];
$back  = 'admin_crud.php?entity=' . $entity;

/* ---------- Validation of a single field value ---------- */
function clean_value(string $type, $extra, string $v): ?string
{
    switch ($type) {
        case 'name':  return preg_match("/^[\p{L}0-9' .\-&]{2,100}$/u", $v) ? $v : null;
        case 'email': return (filter_var($v, FILTER_VALIDATE_EMAIL) && strlen($v) <= 150) ? strtolower($v) : null;
        case 'phone': return preg_match('/^0\d{9}$/', $v) ? $v : null;
        case 'text':  return strlen($v) <= 500 ? $v : null;
        case 'money': return preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v) ? number_format((float)$v, 2, '.', '') : null;
        case 'int':   return (ctype_digit($v) && (int)$v > 0) ? (string)(int)$v : null;
        case 'date':  $d = parse_date($v); return $d ? $d->format('Y-m-d') : null;
        case 'time':  return parse_time($v);
        case 'enum':  return in_array($v, $extra, true) ? $v : null;
    }
    return null;
}

/* ---------- Business rules for bookings (weekday, enrolment, availability, clashes) ---------- */
function check_booking_rules(PDO $pdo, array $v, int $excludeId): ?string
{
    $d = parse_date($v['session_date']);
    if (!is_weekday($d)) return 'Sessions can only be scheduled Monday to Friday.';
    if ($v['end_time'] <= $v['start_time']) return 'End time must be after start time.';

    $st = $pdo->prepare(
        "SELECT e.programme_id FROM enrolments e JOIN payments p ON p.payment_id = e.payment_id
         WHERE e.enrolment_id = ? AND e.status = 'active' AND p.payment_status = 'completed'"
    );
    $st->execute([$v['enrolment_id']]);
    $programmeId = $st->fetchColumn();
    if (!$programmeId) return 'The enrolment must exist, be active and be paid for.';

    if ($v['status'] !== 'cancelled') {
        if (!tutor_available($pdo, (int)$v['tutor_id'], (int)$programmeId, $d->format('l'), $v['start_time'], $v['end_time'])) {
            return 'That tutor does not teach this programme or is not available at that time.';
        }
        if (tutor_conflicts($pdo, (int)$v['tutor_id'], $v['session_date'], $v['start_time'], $v['end_time'], $excludeId)) {
            return 'That tutor already has a session overlapping this time.';
        }
    }
    return null;
}

/* =========================================================
   CREATE / UPDATE / DELETE (POST)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    post_only();
    csrf_check();
    $action = post('action');
    $id     = filter_var(post('id'), FILTER_VALIDATE_INT);

    /* ----- DELETE ----- */
    if ($action === 'delete') {
        if (!$id) message_page('Invalid request', 'Missing record ID.', false, $back);
        try {
            $st = $pdo->prepare("DELETE FROM `$table` WHERE `$pk` = ?");
            $st->execute([$id]);
        } catch (PDOException $ex) {
            message_page('Cannot delete', 'This record is referenced by other records (payments, enrolments or bookings) and must be kept.', false, $back);
        }
        message_page('Deleted', 'The record has been deleted.', true, $back);
    }

    if (!in_array($action, ['create', 'update'], true)) {
        message_page('Invalid request', 'Unknown action.', false, $back);
    }
    if ($action === 'update' && !$id) {
        message_page('Invalid request', 'Missing record ID.', false, $back);
    }

    /* ----- Validate every field ----- */
    $vals   = [];
    $errors = [];
    foreach ($c['fields'] as $col => $def) {
        [$label, $type, $extra] = $def + [2 => null];
        $raw = post($col);
        if ($raw === '' && $type !== 'text') { $errors[] = "$label is required."; continue; }
        $clean = clean_value($type, $extra, $raw);
        if ($clean === null) { $errors[] = "$label is invalid."; continue; }
        $vals[$col] = $clean;
    }

    // Password (students and tutors): required on create, optional on update
    if ($c['password']) {
        $pw = post('password', false);
        if ($action === 'create' || $pw !== '') {
            if (!strong_password($pw)) {
                $errors[] = 'Password needs 8+ characters with uppercase, lowercase and a number.';
            } else {
                $vals['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
            }
        }
    }

    if (!$errors && $entity === 'bookings') {
        $rule = check_booking_rules($pdo, $vals, $action === 'update' ? (int)$id : 0);
        if ($rule) $errors[] = $rule;
    }
    if ($errors) {
        message_page('Please correct the form', implode(' ', $errors), false, $back);
    }

    /* ----- Write to the database (column names come from the whitelist only) ----- */
    try {
        $cols = array_keys($vals);
        if ($action === 'create') {
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $cols) . '`) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')';
            $pdo->prepare($sql)->execute(array_values($vals));
        } else {
            $sets = implode(', ', array_map(fn($col) => "`$col` = ?", $cols));
            $pdo->prepare("UPDATE `$table` SET $sets WHERE `$pk` = ?")->execute([...array_values($vals), $id]);
        }
    } catch (PDOException $ex) {
        if ($ex->getCode() === '23000') {
            message_page('Cannot save', 'A duplicate value (e.g. email, programme name or tutor time slot) or an invalid reference was entered.', false, $back);
        }
        error_log($ex->getMessage());
        message_page('Error', 'The record could not be saved.', false, $back);
    }
    message_page('Saved', 'The record has been saved.', true, $back);
}

/* =========================================================
   READ: list records + create / edit form (GET)
   ========================================================= */
function field_input(string $col, array $def, string $value): string
{
    [$label, $type, $extra] = $def + [2 => null];
    $v    = e($value);
    $html = '<label for="' . $col . '">' . e($label) . '</label>';
    switch ($type) {
        case 'email': return $html . "<input type=\"email\" id=\"$col\" name=\"$col\" value=\"$v\" required maxlength=\"150\">";
        case 'phone': return $html . "<input type=\"tel\" id=\"$col\" name=\"$col\" value=\"$v\" required pattern=\"0[0-9]{9}\" maxlength=\"10\">";
        case 'money': return $html . "<input type=\"number\" id=\"$col\" name=\"$col\" value=\"$v\" required min=\"0\" step=\"0.01\">";
        case 'int':   return $html . "<input type=\"number\" id=\"$col\" name=\"$col\" value=\"$v\" required min=\"1\" step=\"1\">";
        case 'date':  return $html . "<input type=\"date\" id=\"$col\" name=\"$col\" value=\"$v\" required>";
        case 'time':  return $html . "<input type=\"time\" id=\"$col\" name=\"$col\" value=\"" . e(substr($value, 0, 5)) . "\" required>";
        case 'text':  return $html . "<textarea id=\"$col\" name=\"$col\" rows=\"3\" maxlength=\"500\">$v</textarea>";
        case 'enum':
            $o = '';
            foreach ($extra as $opt) {
                $o .= '<option value="' . e($opt) . '"' . ($opt === $value ? ' selected' : '') . '>' . e(ucfirst($opt)) . '</option>';
            }
            return $html . "<select id=\"$col\" name=\"$col\" required>$o</select>";
    }
    return $html . "<input type=\"text\" id=\"$col\" name=\"$col\" value=\"$v\" required maxlength=\"100\">";
}

// Edit mode?
$editId  = filter_var(req('edit'), FILTER_VALIDATE_INT);
$editRow = null;
if ($editId) {
    $st = $pdo->prepare("SELECT * FROM `$table` WHERE `$pk` = ?");
    $st->execute([$editId]);
    $editRow = $st->fetch() ?: null;
}

// List (password hashes are never selected)
$listCols = '`' . $pk . '`, `' . implode('`, `', array_keys($c['fields'])) . '`';
$rows = $pdo->query("SELECT $listCols FROM `$table` ORDER BY `$pk` DESC LIMIT 200")->fetchAll();

page_start($c['title'], true);
echo '<h1>Manage ' . e($c['title']) . '</h1><p>';
foreach ($cfg as $key => $conf) {
    echo '<a href="admin_crud.php?entity=' . e($key) . '">' . e($conf['title']) . '</a> | ';
}
echo '<a href="admin_dashboard.php">Admin controls</a></p>';

if (!$rows) {
    echo '<p>No records yet.</p>';
} else {
    echo '<table><thead><tr>';
    foreach (array_keys($rows[0]) as $col) echo '<th>' . e(str_replace('_', ' ', $col)) . '</th>';
    echo '<th>Actions</th></tr></thead><tbody>';
    foreach ($rows as $r) {
        echo '<tr>';
        foreach ($r as $cell) echo '<td>' . e($cell) . '</td>';
        echo '<td><a href="admin_crud.php?entity=' . e($entity) . '&amp;edit=' . e($r[$pk]) . '">Edit</a> '
           . '<form method="post" onsubmit="return confirm(\'Delete this record?\');">' . csrf_field()
           . '<input type="hidden" name="entity" value="' . e($entity) . '">'
           . '<input type="hidden" name="action" value="delete">'
           . '<input type="hidden" name="id" value="' . e($r[$pk]) . '">'
           . '<button type="submit" class="danger">Delete</button></form></td></tr>';
    }
    echo '</tbody></table>';
}

echo '<form method="post"><fieldset><legend>' . ($editRow ? 'Edit record #' . e($editId) : 'Add a new record') . '</legend>';
echo csrf_field();
echo '<input type="hidden" name="entity" value="' . e($entity) . '">';
echo '<input type="hidden" name="action" value="' . ($editRow ? 'update' : 'create') . '">';
if ($editRow) echo '<input type="hidden" name="id" value="' . e($editId) . '">';
foreach ($c['fields'] as $col => $def) {
    echo field_input($col, $def, $editRow ? (string)$editRow[$col] : '');
}
if ($c['password']) {
    echo '<label for="password">Password' . ($editRow ? ' (leave blank to keep the current one)' : '') . '</label>'
       . '<input type="password" id="password" name="password" autocomplete="new-password" maxlength="72"'
       . ($editRow ? '' : ' required') . '>';
}
echo '</fieldset><button type="submit">' . ($editRow ? 'Update' : 'Create') . '</button>';
if ($editRow) echo ' <a href="admin_crud.php?entity=' . e($entity) . '">Cancel</a>';
echo '</form>';
page_end();