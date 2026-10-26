<?php
/**
 * Index Page (index.php)
 * 
 * Per System Development Plan (Section 7):
 * Redirects visitors to login.php, or if already authenticated,
 * to their respective role dashboard.
 */

require_once __DIR__ . '/includes/auth.php';
startSession();

if (isLoggedIn()) {
    $role = $_SESSION['role'] ?? 'student';
    switch ($role) {
        case 'student':
            header("Location: " . url('student/dashboard.php'));
            break;
        case 'staff':
            if (file_exists(__DIR__ . '/staff/dashboard.php')) {
                header("Location: " . url('staff/dashboard.php'));
            } else {
                header("Location: " . url('logout.php'));
            }
            break;
        case 'admin':
            if (file_exists(__DIR__ . '/admin/dashboard.php')) {
                header("Location: " . url('admin/dashboard.php'));
            } else {
                header("Location: " . url('logout.php'));
            }
            break;
        default:
            header("Location: " . url('student/dashboard.php'));
            break;
    }
    exit();
}

// Redirect guest visitors straight to login
header("Location: " . url('login.php'));
exit();
