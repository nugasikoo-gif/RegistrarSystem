<?php
/**
 * Landing Page (index.php)
 * Clean, modern university landing hero
 */

require_once 'includes/auth.php';
startSession();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $user_role = $_SESSION['role'] ?? 'student';
    switch ($user_role) {
        case 'student':
            header("Location: /Registrar/student/dashboard.php");
            break;
        case 'staff':
            header("Location: /Registrar/staff/dashboard.php");
            break;
        case 'admin':
            header("Location: /Registrar/admin/dashboard.php");
            break;
        default:
            header("Location: /Registrar/student/dashboard.php");
            break;
    }
    exit();
}

$page_title = 'Welcome';
include 'includes/header.php';
?>

<!-- Clean Minimal Hero Section -->
<div class="hero-card text-center my-4">
    <div class="mb-3">
        <img src="/Registrar/assets/images/ctu-logo.png?v=3" alt="CTU Official Logo" class="img-fluid" style="width: 120px; height: 120px; object-fit: contain; filter: drop-shadow(0 4px 10px rgba(0, 0, 0, 0.08));">
    </div>
    
    <div class="badge bg-light text-primary border px-3 py-2 mb-3">CTU &ndash; Daanbantayan Campus</div>
    
    <h1 class="fw-bold mb-3" style="color: #0f172a; font-size: 2.25rem;">
        Digital Queue & Document Request System
    </h1>
    
    <p class="text-muted mx-auto mb-4" style="max-width: 620px; font-size: 1.05rem; line-height: 1.6;">
        Manage your academic document applications and secure daily queue tickets online to eliminate waiting in line at the Registrar's Office.
    </p>
    
    <div class="d-flex justify-content-center gap-2 flex-wrap">
        <a href="/Registrar/login.php" class="btn btn-primary btn-lg">
            <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
        </a>
        <a href="/Registrar/register.php" class="btn btn-outline-primary btn-lg">
            <i class="bi bi-person-plus me-1"></i>Create Student Account
        </a>
    </div>
</div>

<!-- Features Overview Cards -->
<div class="row g-3 my-2">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-inline-flex p-2 bg-light text-primary rounded-3 mb-3">
                    <i class="bi bi-ticket-perforated fs-4"></i>
                </div>
                <h5 class="fw-semibold mb-2">Digital Queuing</h5>
                <p class="text-muted small mb-0">
                    Generate your queue number online before stepping into the office. Monitor the live serving counter from your phone.
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-inline-flex p-2 bg-light text-primary rounded-3 mb-3">
                    <i class="bi bi-file-earmark-text fs-4"></i>
                </div>
                <h5 class="fw-semibold mb-2">Online Document Requests</h5>
                <p class="text-muted small mb-0">
                    Submit requests for TOR, Certificate of Grades, and Good Moral Certificate with real-time status tracking.
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body p-4">
                <div class="d-inline-flex p-2 bg-light text-primary rounded-3 mb-3">
                    <i class="bi bi-bell fs-4"></i>
                </div>
                <h5 class="fw-semibold mb-2">Instant In-App Updates</h5>
                <p class="text-muted small mb-0">
                    Get clear updates right in your student dashboard once your document is ready for pickup or your ticket is called.
                </p>
            </div>
        </div>
    </div>
</div>

<div class="text-center py-4">
    <small class="text-muted">
        <i class="bi bi-shield-check me-1"></i>
        School Prototype Project &bull; BSIT 3rd Year &bull; Not an official CTU deployment
    </small>
</div>

<?php include 'includes/footer.php'; ?>
