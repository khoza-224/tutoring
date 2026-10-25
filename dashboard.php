<?php
require_once __DIR__ . '/includes/rbac.php';
require_permission('dashboard.view');

// [permission needed, link, label]
$menu = [
    ['payment.make',         'payment.html',                     'Select programme and pay'],
    ['booking.create',       'booking.php',                      'Book a tutoring session'],
    ['booking.view_own',     'my_sessions.php',                  'My sessions'],
    ['booking.view_own',     'schedule.html',                    'Session planner'],
    ['availability.manage',  'availability.php',                 'My availability'],
    ['availability.manage',  'availability.html',                'Add or remove availability'],
    ['sessions.view_own',    'tutor_sessions.php',               'My scheduled sessions'],
    ['admin.dashboard',      'admin_dashboard.php',              'Administrator controls'],
    ['crud.students',        'admin_crud.php?entity=students',   'Manage students'],
    ['crud.tutors',          'admin_crud.php?entity=tutors',     'Manage tutors'],
    ['crud.programmes',      'admin_crud.php?entity=programmes', 'Manage programmes'],
    ['crud.bookings',        'admin_crud.php?entity=bookings',   'Manage bookings'],
    ['security.demo',        'security_demo.php',                'Security demonstration'],
];

page_start('Dashboard');
echo '<h1>Welcome, ' . e($_SESSION['name']) . '</h1>';
echo '<p>Logged in as: <strong>' . e(ucfirst($_SESSION['role'])) . '</strong></p><ul>';
foreach ($menu as [$permission, $href, $label]) {
    if (can($permission)) {
        echo '<li><a href="' . e($href) . '">' . e($label) . '</a></li>';
    }
}
echo '</ul>';
page_end();