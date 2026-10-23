<?php
/**
 * Registration Page (register.php)
 * Clean, modern student registration form
 */

require_once 'includes/auth.php';
require_once 'config/database.php';
startSession();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: /Registrar/student/dashboard.php");
    exit();
}

$error = '';
$success = '';

// Generate CSRF token for the form
$csrf_token = generateCSRFToken();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $student_id = trim($_POST['student_id'] ?? '');
        $full_name  = trim($_POST['full_name'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $password   = $_POST['password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        
        if (empty($student_id) || empty($full_name) || empty($email) || empty($password) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'This email is already registered.';
                $stmt->close();
            } else {
                $stmt->close();
                
                // Check if student ID exists
                $stmt = $conn->prepare("SELECT id FROM users WHERE student_id = ?");
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error = 'This Student ID is already registered.';
                    $stmt->close();
                } else {
                    $stmt->close();
                    
                    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                    $role = 'student';
                    
                    $stmt = $conn->prepare("INSERT INTO users (student_id, full_name, email, password, role) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssss", $student_id, $full_name, $email, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now log in with your credentials.';
                        $student_id = $full_name = $email = '';
                        unset($_SESSION['csrf_token']);
                        $csrf_token = generateCSRFToken();
                    } else {
                        $error = 'Registration failed. Please try again.';
                    }
                    $stmt->close();
                }
            }
        }
    }
}

$page_title = 'Student Registration';
include 'includes/header.php';
?>

<div class="auth-container" style="max-width: 480px;">
    <div class="card auth-card">
        <!-- Minimal Brand Header -->
        <div class="card-header text-center">
            <div class="mb-3">
                <img src="/Registrar/assets/images/ctu-logo.png?v=3" alt="CTU Logo" style="width: 76px; height: 76px; object-fit: contain; filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.08));">
            </div>
            <h4>Create Account</h4>
            <small>Register as a CTU student to request documents & queues</small>
        </div>
        
        <!-- Form Body -->
        <div class="card-body p-4">
            <?php if (!empty($success)): ?>
                <div class="alert alert-success py-2 small d-flex align-items-center mb-3">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <div>
                        <?php echo sanitize($success); ?> 
                        <a href="/Registrar/login.php" class="fw-semibold alert-link ms-1">Go to Login</a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small d-flex align-items-center mb-3">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div><?php echo sanitize($error); ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                
                <div class="row g-2 mb-3">
                    <div class="col-sm-6">
                        <label for="student_id" class="form-label">Student ID</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" 
                               placeholder="e.g. 2024-0001"
                               value="<?php echo sanitize($student_id ?? ''); ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="full_name" name="full_name" 
                               placeholder="First and Last name"
                               value="<?php echo sanitize($full_name ?? ''); ?>" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">CTU Student Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="username@student.ctu.edu.ph"
                           value="<?php echo sanitize($email ?? ''); ?>" required>
                </div>
                
                <div class="row g-2 mb-4">
                    <div class="col-sm-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Min. 6 chars" required minlength="6">
                    </div>
                    <div class="col-sm-6">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                               placeholder="Re-type password" required minlength="6">
                    </div>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Complete Registration
                    </button>
                </div>
            </form>
            
            <div class="text-center pt-2 border-top">
                <span class="text-muted small">Already have an account?</span>
                <a href="/Registrar/login.php" class="text-decoration-none fw-semibold small ms-1">Sign in here</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
