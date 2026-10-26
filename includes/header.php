<?php
/**
 * Header Include
 * 
 * Included at the top of every page:
 * - HTML <head> with Bootstrap 5 CDN + Google Fonts (Inter)
 * - Modern clean navigation bar that changes based on user role
 * - Flash message display
 */

// Include auth helper and database connection
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/database.php';
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

<?php if (empty($hide_nav)): ?>
<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg">
    <div class="container">
        <!-- Brand / Logo -->
        <a class="navbar-brand d-flex align-items-center" href="<?php echo url(); ?>">
            <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" class="brand-logo me-2" style="width: 50px; height: 50px; object-fit: contain;">
            <span style="font-size: 1.35rem; font-weight: 700;">CTU Registrar</span>
        </a>
        
        <!-- Mobile toggle button -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Links -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto align-items-lg-center">
                
                <?php if (isLoggedIn()): ?>
                    
                    <?php if ($_SESSION['role'] === 'student'): ?>
                        <!-- Student Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('student/dashboard.php'); ?>">
                                <i class="bi bi-grid-1x2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('student/request_document.php'); ?>">
                                <i class="bi bi-file-earmark-plus me-1"></i>Request Document
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('student/my_requests.php'); ?>">
                                <i class="bi bi-folder2-open me-1"></i>My Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('student/get_queue.php'); ?>">
                                <i class="bi bi-ticket-perforated me-1"></i>Get Queue
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link position-relative" href="<?php echo url('student/notifications.php'); ?>">
                                <i class="bi bi-bell me-1"></i>Notifications
                                <?php if ($notif_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-1"><?php echo $notif_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        
                    <?php elseif ($_SESSION['role'] === 'staff'): ?>
                        <!-- Staff Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('staff/dashboard.php'); ?>">
                                <i class="bi bi-grid-1x2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('staff/manage_queue.php'); ?>">
                                <i class="bi bi-people me-1"></i>Manage Queue
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('staff/manage_requests.php'); ?>">
                                <i class="bi bi-file-earmark-check me-1"></i>Manage Requests
                            </a>
                        </li>
                        
                    <?php elseif ($_SESSION['role'] === 'admin'): ?>
                        <!-- Admin Navigation -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('admin/dashboard.php'); ?>">
                                <i class="bi bi-grid-1x2 me-1"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('admin/manage_users.php'); ?>">
                                <i class="bi bi-people-fill me-1"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('admin/manage_documents.php'); ?>">
                                <i class="bi bi-files me-1"></i>Document Types
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo url('admin/reports.php'); ?>">
                                <i class="bi bi-bar-chart-line me-1"></i>Reports
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- User dropdown -->
                    <li class="nav-item dropdown ms-lg-2">
                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i>
                            <span><?php echo sanitize($_SESSION['full_name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border">
                            <li>
                                <div class="px-3 py-1">
                                    <small class="text-muted d-block">Signed in as</small>
                                    <strong class="d-block text-capitalize"><?php echo sanitize($_SESSION['role']); ?></strong>
                                    <?php if (!empty($_SESSION['student_id'])): ?>
                                        <small class="text-primary font-monospace"><?php echo sanitize($_SESSION['student_id']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?php echo url('logout.php'); ?>">
                                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                <?php else: ?>
                    <!-- Guest links -->
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo url('login.php'); ?>">
                            <i class="bi bi-box-arrow-in-right me-1"></i>Login
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-primary btn-sm" href="<?php echo url('register.php'); ?>">
                            <i class="bi bi-person-plus me-1"></i>Register
                        </a>
                    </li>
                <?php endif; ?>
                
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<!-- Main Content Container -->
<main class="<?php echo !empty($hide_nav) ? 'auth-clean-main' : 'container py-4'; ?>">

<?php
// Display flash messages
$flash = getFlashMessage();
if ($flash): ?>
    <div class="alert alert-<?php echo sanitize($flash['type']); ?> alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-info-circle me-1"></i>
        <?php echo sanitize($flash['message']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
