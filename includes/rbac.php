<?php
require_once __DIR__ . '/helpers.php';

// Role -> permissions matrix (single source of truth for access control)
const ROLE_PERMISSIONS = [
    'student' => [
        'dashboard.view',
        'payment.make',
        'booking.create',
        'booking.view_own',
    ],
    'tutor' => [
        'dashboard.view',
        'availability.manage',
        'sessions.view_own',
    ],
    'admin' => [
        'dashboard.view',
        'admin.dashboard',
        'payments.verify',
        'tutors.assign',
        'crud.students',
        'crud.tutors',
        'crud.programmes',
        'crud.bookings',
        'users.delete',
        'records.search',
        'security.demo',
    ],
];

function can(string $permission): bool
{
    $role = $_SESSION['role'] ?? '';
    return $role !== '' && in_array($permission, ROLE_PERMISSIONS[$role] ?? [], true);
}

function require_permission(string $permission): void
{
    if (empty($_SESSION['user_id']) || empty($_SESSION['role'])) {
        header('Location: login.html');
        exit;
    }
    if (!can($permission)) {
        error_log(sprintf('RBAC denied: %s #%d tried "%s" on %s',
            $_SESSION['role'], $_SESSION['user_id'], $permission, $_SERVER['REQUEST_URI'] ?? ''));
        http_response_code(403);
        message_page('Access denied', 'Your role does not allow this action.', false, 'dashboard.php');
    }
}