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
            header("Location: " . url('student/dashboard.php'));
            exit();
        case 'staff':
            if (file_exists(__DIR__ . '/staff/dashboard.php')) {
                header("Location: " . url('staff/dashboard.php'));
                exit();
            } else {
                setFlashMessage('info', 'Staff module is under development (Phase 4).');
                header("Location: " . url('logout.php'));
                exit();
            }
        case 'admin':
            if (file_exists(__DIR__ . '/admin/dashboard.php')) {
                header("Location: " . url('admin/dashboard.php'));
                exit();
            } else {
                setFlashMessage('info', 'Admin module is under development (Phase 5).');
                header("Location: " . url('logout.php'));
                exit();
            }
        default:
            header("Location: " . url('student/dashboard.php'));
            exit();
    }
}

$error = '';

// Handle form submission
$csrf_token = generateCSRFToken();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid submission token. Please reload and try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = 'Please enter both email and password.';
        } elseif (!isValidCtuEmail($email)) {
            $error = 'Access restricted: Please enter an official CTU email address (@ctu.edu.ph). Unofficial email domains (such as @gmail.com, @yahoo.com, or @outlook.com) are not permitted.';
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
                            header("Location: " . url('student/dashboard.php'));
                            exit();
                        case 'staff':
                            if (file_exists(__DIR__ . '/staff/dashboard.php')) {
                                header("Location: " . url('staff/dashboard.php'));
                            } else {
                                setFlashMessage('info', 'Staff module is under development (Phase 4).');
                                header("Location: " . url('logout.php'));
                            }
                            exit();
                        case 'admin':
                            if (file_exists(__DIR__ . '/admin/dashboard.php')) {
                                header("Location: " . url('admin/dashboard.php'));
                            } else {
                                setFlashMessage('info', 'Admin module is under development (Phase 5).');
                                header("Location: " . url('logout.php'));
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
}

$page_title = 'Sign In';
$hide_nav = true;
$hide_footer = true;
include 'includes/header.php';
?>

<div class="auth-split-wrapper">
    <!-- Floating Geometric Accents matching reference aesthetic -->
    <div class="auth-bg-decor">
        <div class="auth-polygon auth-polygon-1"></div>
        <div class="auth-polygon auth-polygon-2"></div>
        <div class="auth-polygon auth-polygon-3"></div>
        <div class="auth-polygon auth-polygon-4"></div>
        <div class="auth-polygon auth-polygon-5"></div>
    </div>

    <div class="container auth-split-container">
        <div class="row align-items-center justify-content-center g-4">

            <!-- Left Branding Column (Campus identity & Seal) -->
            <div class="col-lg-6 auth-brand-col">
                <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Official Seal" class="auth-brand-logo">
                <h1 class="auth-brand-title">Cebu Technological University</h1>
                <p class="auth-brand-subtitle">
                    Daanbantayan Campus &bull; Registrar's Office<br>
                    Agujo, Daanbantayan, Cebu, Philippines
                </p>
            </div>

            <!-- Right Column: Sign In Card (SWU / PHINMA Education style) -->
            <div class="col-lg-6">
                <div class="auth-form-card">

                    <!-- Top Form Header Logo & Campus Name -->
                    <div class="auth-card-top-brand">
                        <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" class="auth-card-top-logo">
                        <div class="auth-card-top-text">
                            <h5 class="mb-0">CTU REGISTRAR</h5>
                            <small>OFFICE OF THE CAMPUS REGISTRAR</small>
                        </div>
                    </div>

                    <!-- Underlined Section Title -->
                    <div class="auth-form-title-wrap">
                        <div class="auth-form-title">Sign In</div>
                        <div class="auth-form-title-bar"></div>
                    </div>

                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger py-2 small d-flex align-items-center mb-3">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <div><?php echo sanitize($error); ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                        <!-- Official CTU Email field with underline aesthetic -->
                        <div class="auth-input-group">
                            <label for="email" class="auth-input-label">
                                <span class="req">*</span> Official CTU Email Address
                            </label>
                            <input type="email" class="auth-input-control" id="email" name="email"
                                placeholder="username@ctu.edu.ph"
                                value="<?php echo sanitize($email ?? ''); ?>" required autofocus>
                            <i class="bi bi-envelope auth-input-icon"></i>
                        </div>

                        <!-- Password field with toggle visibility icon -->
                        <div class="auth-input-group">
                            <label for="password" class="auth-input-label">
                                <span class="req">*</span> Password
                            </label>
                            <input type="password" class="auth-input-control" id="password" name="password"
                                placeholder="Enter Password" required>
                            <button type="button" class="auth-pwd-toggle" id="togglePasswordBtn" aria-label="Toggle password view">
                                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                            </button>
                        </div>

                        <!-- Sign In Button -->
                        <div class="mb-3">
                            <button type="submit" class="btn btn-auth-primary">
                                <span>Sign In</span>
                                <i class="bi bi-box-arrow-in-right"></i>
                            </button>
                        </div>

                        <!-- Secondary Links -->
                        <div class="auth-sub-links">
                            <div class="mb-2">
                                <a href="javascript:void(0);" onclick="alert('For password resets, please contact the campus MIS office.');" class="small">
                                    Forgot Password?
                                </a>
                            </div>
                            <div class="text-muted small pt-2 border-top">
                                Don't have an account?
                                <a href="<?php echo url('register.php'); ?>" class="fw-semibold ms-1">Create Student Account</a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Password show/hide toggle
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('togglePasswordBtn');
        const pwdInput = document.getElementById('password');
        const icon = document.getElementById('togglePasswordIcon');

        if (toggleBtn && pwdInput && icon) {
            toggleBtn.addEventListener('click', function() {
                const isPassword = pwdInput.getAttribute('type') === 'password';
                pwdInput.setAttribute('type', isPassword ? 'text' : 'password');
                icon.className = isPassword ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }
    });
</script>

<?php include 'includes/footer.php'; ?>