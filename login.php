<?php
/**
 * Login Page (login.php)
 * Clean, modern authentication form
 */

require_once 'includes/auth.php';
require_once 'config/database.php';
startSession();

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    $user_role = $_SESSION['role'] ?? 'student';
    switch ($user_role) {
        case 'student':
            header("Location: /Registrar/student/dashboard.php");
            exit();
        case 'staff':
            if (file_exists(__DIR__ . '/staff/dashboard.php')) {
                header("Location: /Registrar/staff/dashboard.php");
                exit();
            } else {
                setFlashMessage('info', 'Staff module is under development (Phase 4).');
                header("Location: /Registrar/logout.php");
                exit();
            }
        case 'admin':
            if (file_exists(__DIR__ . '/admin/dashboard.php')) {
                header("Location: /Registrar/admin/dashboard.php");
                exit();
            } else {
                setFlashMessage('info', 'Admin module is under development (Phase 5).');
                header("Location: /Registrar/logout.php");
                exit();
            }
        default:
            header("Location: /Registrar/student/dashboard.php");
            exit();
    }
}

$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, full_name, email, password, role, student_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['student_id'] = $user['student_id'];
                
                session_regenerate_id(true);
                
                switch ($user['role']) {
                    case 'student':
                        header("Location: /Registrar/student/dashboard.php");
                        exit();
                    case 'staff':
                        if (file_exists(__DIR__ . '/staff/dashboard.php')) {
                            header("Location: /Registrar/staff/dashboard.php");
                        } else {
                            setFlashMessage('info', 'Staff module is under development (Phase 4).');
                            header("Location: /Registrar/logout.php");
                        }
                        exit();
                    case 'admin':
                        if (file_exists(__DIR__ . '/admin/dashboard.php')) {
                            header("Location: /Registrar/admin/dashboard.php");
                        } else {
                            setFlashMessage('info', 'Admin module is under development (Phase 5).');
                            header("Location: /Registrar/logout.php");
                        }
                        exit();
                }
            } else {
                $error = 'Invalid email or password.';
            }
        } else {
            $error = 'Invalid email or password.';
        }
        $stmt->close();
    }
}

$page_title = 'Sign In';
include 'includes/header.php';
?>

<div class="auth-container">
    <div class="card auth-card">
        <!-- Minimal Brand Header -->
        <div class="card-header text-center">
            <div class="mb-3">
                <img src="/Registrar/assets/images/ctu-logo.png?v=3" alt="CTU Logo" style="width: 76px; height: 76px; object-fit: contain; filter: drop-shadow(0 3px 6px rgba(0, 0, 0, 0.08));">
            </div>
            <h4>Welcome Back</h4>
            <small>Sign in to your CTU Registrar account</small>
        </div>
        
        <!-- Form Body -->
        <div class="card-body p-4">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger py-2 small d-flex align-items-center">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div><?php echo sanitize($error); ?></div>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" class="form-control" id="email" name="email" 
                               placeholder="user@ctu.edu.ph"
                               value="<?php echo sanitize($email ?? ''); ?>" required autofocus>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Enter your password" required>
                    </div>
                </div>
                
                <div class="d-grid mb-3">
                    <button type="submit" class="btn btn-primary btn-lg">
                        Sign In
                    </button>
                </div>
            </form>
            
            <div class="text-center pt-3 border-top">
                <span class="text-muted small">Don't have an account yet?</span>
                <a href="/Registrar/register.php" class="text-decoration-none fw-semibold small ms-1">Create an Account</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
