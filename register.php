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
    header("Location: " . url('student/dashboard.php'));
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
        $student_id     = trim($_POST['student_id'] ?? '');
        $full_name      = trim($_POST['full_name'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $personal_gmail = trim($_POST['personal_gmail'] ?? '');
        $password       = $_POST['password'] ?? '';
        $confirm        = $_POST['confirm_password'] ?? '';
        
        if (empty($student_id) || empty($full_name) || empty($email) || empty($personal_gmail) || empty($password) || empty($confirm)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid student email address.';
        } elseif (!filter_var($personal_gmail, FILTER_VALIDATE_EMAIL) || !str_ends_with(strtolower($personal_gmail), '@gmail.com')) {
            $error = 'Please enter a valid personal Gmail address ending in @gmail.com (for status notifications).';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters long.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Check if login email exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            if ($stmt->get_result()->num_rows > 0) {
                $error = 'This CTU email is already registered.';
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
                    
                    $stmt = $conn->prepare("INSERT INTO users (student_id, full_name, email, personal_gmail, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssss", $student_id, $full_name, $email, $personal_gmail, $hashed_password, $role);
                    
                    if ($stmt->execute()) {
                        $success = 'Registration successful! You can now log in using your student email.';
                        $student_id = $full_name = $email = $personal_gmail = '';
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
            
            <!-- Left Branding Column -->
            <div class="col-lg-6 auth-brand-col">
                <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Official Seal" class="auth-brand-logo">
                <h1 class="auth-brand-title">Cebu Technological University</h1>
                <p class="auth-brand-subtitle">
                    Daanbantayan Campus &bull; Registrar's Office<br>
                    Agujo, Daanbantayan, 6013 Cebu, Philippines
                </p>
            </div>

            <!-- Right Column: Registration Card (SWU / PHINMA Education style) -->
            <div class="col-lg-6">
                <div class="auth-form-card" style="max-width: 500px;">
                    
                    <!-- Top Form Header -->
                    <div class="auth-card-top-brand">
                        <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" class="auth-card-top-logo">
                        <div class="auth-card-top-text">
                            <h5 class="mb-0">CTU REGISTRAR</h5>
                            <small>OFFICE OF THE CAMPUS REGISTRAR</small>
                        </div>
                    </div>

                    <!-- Underlined Section Title -->
                    <div class="auth-form-title-wrap">
                        <div class="auth-form-title">Create Account</div>
                        <div class="auth-form-title-bar"></div>
                    </div>

                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success py-2 small d-flex align-items-center mb-3">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <div>
                                <?php echo sanitize($success); ?> 
                                <a href="<?php echo url('login.php'); ?>" class="fw-semibold alert-link ms-1">Go to Sign In</a>
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

                        <div class="row g-3">
                            <!-- Student ID -->
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="student_id" class="auth-input-label">
                                        <span class="req">*</span> Student ID
                                    </label>
                                    <input type="text" class="auth-input-control" id="student_id" name="student_id" 
                                           placeholder="e.g. 2024-0001"
                                           value="<?php echo sanitize($student_id ?? ''); ?>" required>
                                    <i class="bi bi-card-text auth-input-icon"></i>
                                </div>
                            </div>

                            <!-- Full Name -->
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="full_name" class="auth-input-label">
                                        <span class="req">*</span> Full Name
                                    </label>
                                    <input type="text" class="auth-input-control" id="full_name" name="full_name" 
                                           placeholder="First and Last name"
                                           value="<?php echo sanitize($full_name ?? ''); ?>" required>
                                    <i class="bi bi-person auth-input-icon"></i>
                                </div>
                            </div>
                        </div>

                        <!-- Login Student Email Address -->
                        <div class="auth-input-group">
                            <label for="email" class="auth-input-label">
                                <span class="req">*</span> CTU Student Email <small class="text-muted fw-normal">(used for Sign In)</small>
                            </label>
                            <input type="email" class="auth-input-control" id="email" name="email" 
                                   placeholder="username@student.ctu.edu.ph"
                                   value="<?php echo sanitize($email ?? ''); ?>" required>
                            <i class="bi bi-envelope auth-input-icon"></i>
                        </div>

                        <!-- Personal Gmail for Notifications -->
                        <div class="auth-input-group">
                            <label for="personal_gmail" class="auth-input-label">
                                <span class="req">*</span> Personal Gmail Address <small class="text-primary fw-normal">(for status email updates only)</small>
                            </label>
                            <input type="email" class="auth-input-control" id="personal_gmail" name="personal_gmail" 
                                   placeholder="yourname@gmail.com"
                                   pattern=".+@gmail\.com"
                                   title="Please enter a valid Gmail address ending with @gmail.com"
                                   value="<?php echo sanitize($personal_gmail ?? ''); ?>" required>
                            <i class="bi bi-google auth-input-icon text-danger"></i>
                        </div>

                        <div class="row g-3">
                            <!-- Password -->
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="password" class="auth-input-label">
                                        <span class="req">*</span> Password
                                    </label>
                                    <input type="password" class="auth-input-control" id="password" name="password" 
                                           placeholder="Min. 6 chars" required minlength="6">
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="confirm_password" class="auth-input-label">
                                        <span class="req">*</span> Confirm Password
                                    </label>
                                    <input type="password" class="auth-input-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Re-type password" required minlength="6">
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="mb-3">
                            <button type="submit" class="btn btn-auth-primary">
                                <span>Create Student Account</span>
                                <i class="bi bi-arrow-right-circle"></i>
                            </button>
                        </div>

                        <!-- Secondary Links -->
                        <div class="auth-sub-links">
                            <div class="text-muted small pt-2 border-top">
                                Already registered? 
                                <a href="<?php echo url('login.php'); ?>" class="fw-semibold ms-1">Sign in here</a>
                            </div>
                        </div>

                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
