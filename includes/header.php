<?php

/**
 * Header Include
 * 
 * Included at the top of every page:
 * - HTML <head> with Bootstrap 5 CDN + Google Fonts (Inter) + Bootstrap Icons
 * - Branded CTU Lightweight Preloader
 * - Desktop Left Sidebar + Sticky Topbar + Mobile Offcanvas Drawer (for authenticated users)
 * - Clean Top Navbar (for unauthenticated guest pages if any)
 * - Flash message display
 */

// Include auth helper and database connection
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/check_migration.php';
startSession();

// Get notification count for logged-in users
$notif_count = 0;
if (isLoggedIn()) {
    $notif_count = getUnreadNotificationCount($conn);
}

// Set page title (can be overridden before including header)
if (!isset($page_title)) {
    $page_title = 'CTU Registrar System';
}

// User data for header/sidebar
$user_name = $_SESSION['full_name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'student';
$user_email = $_SESSION['email'] ?? '';
$user_sid = $_SESSION['student_id'] ?? '';

// Generate user initials (e.g., "Juan Dela Cruz" -> "JC", "Maria" -> "M")
$name_parts = preg_split('/\s+/', trim($user_name));
$initials = strtoupper(substr($name_parts[0] ?? 'U', 0, 1) . (count($name_parts) > 1 ? substr($name_parts[count($name_parts) - 1], 0, 1) : ''));

// Current script detection for active link
$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
if (!function_exists('isNavActive')) {
    function isNavActive($page)
    {
        global $current_script;
        return ($current_script === $page) ? 'active' : '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($page_title); ?> | CTU Registrar</title>

    <!-- Favicon (Transparent High-Res) -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?php echo url('assets/images/favicon.png?v=3'); ?>">
    <link rel="apple-touch-icon" sizes="192x192" href="<?php echo url('assets/images/apple-touch-icon.png?v=3'); ?>">
    <link rel="shortcut icon" type="image/png" href="<?php echo url('assets/images/favicon.png?v=3'); ?>">

    <!-- Google Font: Inter for modern typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom Modern Minimalist CSS -->
    <link href="<?php echo url('assets/css/style.css'); ?>" rel="stylesheet">
</head>

<body class="<?php echo !empty($hide_nav) ? 'auth-clean-body' : ''; ?>">

    <!-- CTU Branded Lightweight Preloader -->
    <!--<div id="ctuPreloader" class="ctu-preloader" aria-hidden="true">
    <div class="ctu-preloader-inner">
        <div class="ctu-preloader-logo-wrap">
            <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU" class="ctu-preloader-logo">
            <div class="ctu-preloader-spinner"></div>
        </div>
        <div class="ctu-preloader-text">CTU Registrar</div>
        <div class="ctu-preloader-bar"><div class="ctu-preloader-bar-fill"></div></div>
    </div>
</div>-->

    <?php if (empty($hide_nav)): ?>
        <?php if (isLoggedIn()): ?>
            <!-- ============================================================
             AUTHENTICATED DASHBOARD LAYOUT: SIDEBAR + TOPBAR
             ============================================================ -->
            <div class="dashboard-wrapper">

                <!-- Left Sidebar Navigation (Desktop Fixed & Mobile Drawer) -->
                <aside class="dashboard-sidebar" id="dashboardSidebar" aria-label="Dashboard Sidebar Navigation">

                    <!-- Desktop Sidebar Brand (Hidden on Mobile) -->
                    <div class="sidebar-brand-header d-none d-lg-flex">
                        <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" class="sidebar-brand-logo">
                        <div class="sidebar-brand-text">
                            <span class="sidebar-brand-title">CTU Registrar</span>
                            <span class="sidebar-brand-subtitle">Daanbantayan Campus</span>
                        </div>
                    </div>

                    <!-- Mobile Drawer Header with Close Button (Visible only on Mobile) -->
                    <div class="drawer-header d-flex align-items-center justify-content-between p-3 d-lg-none">
                        <div class="d-flex align-items-center gap-2">
                            <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" style="width: 36px; height: 36px; object-fit: contain;">
                            <div>
                                <span class="fw-bold text-primary d-block leading-tight" style="font-size: 0.95rem;">CTU Registrar</span>
                                <small class="text-muted" style="font-size: 0.7rem;">Campus Portal</small>
                            </div>
                        </div>
                        <button type="button" class="drawer-close-btn" id="sidebarCloseBtn" aria-label="Close navigation menu">
                            <i class="bi bi-x-lg fs-5"></i>
                        </button>
                    </div>

                    <!-- Mobile Drawer Controls: Profile & Notifications near top (d-lg-none) -->
                    <div class="drawer-account-section p-3 d-lg-none">
                        <div class="d-flex align-items-center justify-content-between gap-2">
                            <!-- Profile Dropdown for Mobile Drawer -->
                            <div class="dropdown flex-grow-1">
                                <button class="btn btn-sm btn-white border d-flex align-items-center gap-2 w-100 text-start p-1 pe-2 dropdown-toggle bg-white"
                                    type="button" id="mobileDrawerUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar-circle" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                        <?php echo sanitize($initials); ?>
                                    </div>
                                    <div class="leading-tight text-truncate" style="max-width: 140px;">
                                        <strong class="d-block text-truncate text-dark" style="font-size: 0.8rem;"><?php echo sanitize($user_name); ?></strong>
                                        <small class="text-muted text-capitalize" style="font-size: 0.7rem;"><?php echo sanitize($user_role); ?></small>
                                    </div>
                                </button>
                                <ul class="dropdown-menu shadow-sm border py-2 w-100" aria-labelledby="mobileDrawerUserDropdown">
                                    <li class="px-3 py-1">
                                        <small class="text-muted d-block" style="font-size: 0.7rem;">Signed in as</small>
                                        <strong class="d-block text-dark small"><?php echo sanitize($user_name); ?></strong>
                                        <span class="badge bg-primary-subtle text-primary text-capitalize mt-1"><?php echo sanitize($user_role); ?></span>
                                        <?php if (!empty($user_sid)): ?>
                                            <small class="d-block text-primary font-monospace mt-1">ID: <?php echo sanitize($user_sid); ?></small>
                                        <?php endif; ?>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider my-1">
                                    </li>
                                    <li>
                                        <a class="dropdown-item text-danger small py-2 d-flex align-items-center gap-2" href="<?php echo url('logout.php'); ?>">
                                            <i class="bi bi-box-arrow-right"></i> Sign Out
                                        </a>
                                    </li>
                                </ul>
                            </div>

                            <!-- Notification Button for Mobile Drawer -->
                            <a href="<?php echo url($user_role === 'student' ? 'student/notifications.php' : '#'); ?>"
                                class="btn btn-sm btn-white border position-relative d-flex align-items-center justify-content-center bg-white flex-shrink-0"
                                style="width: 38px; height: 38px; border-radius: 8px;"
                                title="Notifications">
                                <i class="bi bi-bell text-secondary fs-6"></i>
                                <?php if ($notif_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.62rem;">
                                        <?php echo $notif_count; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    </div>

                    <!-- Sidebar Role Pill -->
                    <div class="sidebar-role-banner">
                        <span class="sidebar-role-pill">
                            <i class="bi bi-shield-check"></i>
                            <?php echo match ($user_role) {
                                'admin' => 'Administrator',
                                'staff' => 'Registrar Staff',
                                default => 'Student Portal'
                            }; ?>
                        </span>
                    </div>

                    <!-- Role-Specific Sidebar Navigation Links -->
                    <nav class="sidebar-nav-container">
                        <ul class="sidebar-nav-list">

                            <?php if ($user_role === 'student'): ?>
                                <!-- Student Navigation Links -->
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('dashboard.php'); ?>" href="<?php echo url('student/dashboard.php'); ?>">
                                        <i class="bi bi-grid-1x2"></i>
                                        <span>Dashboard</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('request_document.php'); ?>" href="<?php echo url('student/request_document.php'); ?>">
                                        <i class="bi bi-file-earmark-plus"></i>
                                        <span>Request Document</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('my_requests.php'); ?>" href="<?php echo url('student/my_requests.php'); ?>">
                                        <i class="bi bi-folder2-open"></i>
                                        <span>My Requests</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('my_queue.php'); ?>" href="<?php echo url('student/my_queue.php'); ?>">
                                        <i class="bi bi-clock-history"></i>
                                        <span>My Queue</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('get_queue.php'); ?>" href="<?php echo url('student/get_queue.php'); ?>">
                                        <i class="bi bi-ticket-perforated"></i>
                                        <span>Get Queue</span>
                                    </a>
                                </li>

                            <?php elseif ($user_role === 'staff'): ?>
                                <!-- Staff Navigation Links -->
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('dashboard.php'); ?>" href="<?php echo url('staff/dashboard.php'); ?>">
                                        <i class="bi bi-grid-1x2"></i>
                                        <span>Dashboard</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('manage_queue.php'); ?>" href="<?php echo url('staff/manage_queue.php'); ?>">
                                        <i class="bi bi-people"></i>
                                        <span>Manage Queue</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('manage_requests.php'); ?>" href="<?php echo url('staff/manage_requests.php'); ?>">
                                        <i class="bi bi-file-earmark-check"></i>
                                        <span>Manage Requests</span>
                                    </a>
                                </li>

                            <?php elseif ($user_role === 'admin'): ?>
                                <!-- Admin Navigation Links -->
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('dashboard.php'); ?>" href="<?php echo url('admin/dashboard.php'); ?>">
                                        <i class="bi bi-grid-1x2"></i>
                                        <span>Dashboard</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('manage_users.php'); ?>" href="<?php echo url('admin/manage_users.php'); ?>">
                                        <i class="bi bi-people-fill"></i>
                                        <span>Manage Users</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('manage_documents.php'); ?>" href="<?php echo url('admin/manage_documents.php'); ?>">
                                        <i class="bi bi-files"></i>
                                        <span>Manage Documents</span>
                                    </a>
                                </li>
                                <li class="sidebar-nav-item">
                                    <a class="sidebar-nav-link <?php echo isNavActive('reports.php'); ?>" href="<?php echo url('admin/reports.php'); ?>">
                                        <i class="bi bi-bar-chart-line"></i>
                                        <span>Reports</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                        </ul>
                    </nav>

                    <!-- Sidebar Footer Note -->
                    <div class="sidebar-footer-info d-none d-lg-block">
                        <span class="d-block fw-semibold text-muted">CTU Daanbantayan</span>
                        <span style="font-size: 0.68rem;">Registrar Queue & Doc Processing</span>
                    </div>

                </aside>

                <!-- Backdrop Overlay for Mobile Drawer -->
                <div class="sidebar-backdrop" id="sidebarBackdrop" aria-hidden="true"></div>

                <!-- Main Dashboard Area (Topbar + Content) -->
                <div class="dashboard-main-area">

                    <!-- Topbar Header -->
                    <header class="dashboard-topbar">

                        <!-- Mobile View Header: CTU Logo + Title on Left, Hamburger on Right -->
                        <div class="topbar-mobile-brand d-flex align-items-center gap-2 d-lg-none">
                            <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" style="width: 36px; height: 36px; object-fit: contain;">
                            <span class="fw-bold text-dark fs-6 tracking-tight">CTU Registrar</span>
                        </div>

                        <button class="topbar-hamburger-btn d-lg-none border-0 bg-transparent" id="sidebarToggleBtn" aria-label="Open Navigation Menu" aria-expanded="false">
                            <i class="bi bi-list fs-2"></i>
                        </button>

                        <!-- Desktop Topbar Content: Page Title on Left, Actions on Right -->
                        <div class="topbar-desktop-title d-none d-lg-flex align-items-center gap-2">
                            <h1 class="topbar-page-heading"><?php echo sanitize($page_title); ?></h1>
                        </div>

                        <div class="topbar-desktop-actions d-none d-lg-flex ms-auto">
                            <!-- Notification Button -->
                            <a href="<?php echo url($user_role === 'student' ? 'student/notifications.php' : '#'); ?>"
                                class="topbar-icon-btn position-relative"
                                title="Notifications">
                                <i class="bi bi-bell fs-5"></i>
                                <?php if ($notif_count > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem;">
                                        <?php echo $notif_count; ?>
                                        <span class="visually-hidden">unread notifications</span>
                                    </span>
                                <?php endif; ?>
                            </a>

                            <div class="topbar-divider"></div>

                            <!-- Profile Dropdown Button -->
                            <div class="dropdown">
                                <button class="topbar-user-btn dropdown-toggle d-flex align-items-center gap-2 border-0 bg-transparent"
                                    type="button" id="desktopUserDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="user-avatar-circle"><?php echo sanitize($initials); ?></div>
                                    <div class="text-start leading-tight">
                                        <span class="d-block fw-semibold text-dark" style="font-size: 0.85rem; line-height: 1.2;"><?php echo sanitize($user_name); ?></span>
                                        <small class="text-muted text-capitalize" style="font-size: 0.72rem;"><?php echo sanitize($user_role); ?></small>
                                    </div>
                                    <i class="bi bi-chevron-down text-muted small ms-1"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border py-2" aria-labelledby="desktopUserDropdown" style="min-width: 220px;">
                                    <li class="px-3 py-2 border-bottom mb-1 bg-light rounded-top">
                                        <small class="text-muted d-block" style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px;">Signed in as</small>
                                        <strong class="d-block text-dark" style="font-size: 0.88rem;"><?php echo sanitize($user_name); ?></strong>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle text-capitalize mt-1" style="font-size: 0.7rem;">
                                            <?php echo sanitize($user_role); ?>
                                        </span>
                                        <?php if (!empty($user_sid)): ?>
                                            <span class="d-block text-muted font-monospace small mt-1">ID: <?php echo sanitize($user_sid); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <li>
                                        <a class="dropdown-item py-2 small text-danger d-flex align-items-center gap-2" href="<?php echo url('logout.php'); ?>">
                                            <i class="bi bi-box-arrow-right"></i> Sign Out
                                        </a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                    </header>

                    <!-- Main Content Container for Dashboard Pages -->
                    <main class="dashboard-content container-fluid px-3 px-md-4 py-4">

                    <?php else: ?>
                        <!-- ============================================================
             GUEST NAVBAR (Fallback if visited without login)
             ============================================================ -->
                        <nav class="navbar navbar-expand-lg">
                            <div class="container">
                                <a class="navbar-brand d-flex align-items-center" href="<?php echo url(); ?>">
                                    <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" class="brand-logo me-2" style="width: 44px; height: 44px; object-fit: contain;">
                                    <span style="font-size: 1.25rem; font-weight: 700;">CTU Registrar</span>
                                </a>
                                <div class="ms-auto d-flex gap-2">
                                    <a class="btn btn-outline-primary btn-sm" href="<?php echo url('login.php'); ?>">Sign In</a>
                                    <a class="btn btn-primary btn-sm" href="<?php echo url('register.php'); ?>">Register</a>
                                </div>
                            </div>
                        </nav>
                        <main class="container py-4">
                        <?php endif; ?>

                    <?php else: ?>
                        <!-- Auth/Split Layout Pages (login.php, register.php) -->
                        <main class="auth-clean-main">
                        <?php endif; ?>

                        <?php
                        // Display flash messages
                        $flash = getFlashMessage();
                        if ($flash): ?>
                            <div class="alert alert-<?php echo sanitize($flash['type']); ?> alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
                                <i class="bi bi-info-circle me-1"></i>
                                <?php echo sanitize($flash['message']); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>