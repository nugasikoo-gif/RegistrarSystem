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
        } elseif (!isValidCtuEmail($email)) {
            $error = 'Official CTU email address (@ctu.edu.ph) is required for student registration. Unofficial email domains (such as @gmail.com, @yahoo.com, or @outlook.com) are not allowed.';
        } elseif (!filter_var($personal_gmail, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid personal notification email address (e.g., Gmail, Yahoo, Outlook) for status updates.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            // Apply strict student-only strong password policy on backend
            $pwdCheck = validateStudentStrongPassword($password, $email, $full_name);
            if (!$pwdCheck['valid']) {
                $error = '<strong>Password does not meet student security requirements:</strong><ul class="mb-0 text-start ps-3 mt-1">' .
                    implode('', array_map(function ($e) {
                        return '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
                    }, $pwdCheck['errors'])) .
                    '</ul>';
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
                        // Role is strictly assigned on the server - users cannot select staff/admin
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
                    Agujo, Daanbantayan, Cebu, Philippines
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
                        <div class="alert alert-danger py-2 px-3 small d-flex align-items-start mb-3">
                            <i class="bi bi-exclamation-circle-fill me-2 mt-1 flex-shrink-0"></i>
                            <div><?php echo $error; ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" id="studentRegisterForm">
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
                                <span class="req">*</span> CTU Student Email <small class="text-primary fw-normal">(@ctu.edu.ph for Sign In)</small>
                            </label>
                            <input type="email" class="auth-input-control" id="email" name="email"
                                placeholder="username@ctu.edu.ph or username@student.ctu.edu.ph"
                                value="<?php echo sanitize($email ?? ''); ?>" required>
                            <i class="bi bi-envelope auth-input-icon"></i>
                        </div>

                        <!-- Personal Email for Notifications (Gmail, Yahoo, Outlook, etc.) -->
                        <div class="auth-input-group">
                            <label for="personal_gmail" class="auth-input-label">
                                <span class="req">*</span> Personal Notification Email <small class="text-muted fw-normal">(Gmail, Yahoo, Outlook, etc. for updates)</small>
                            </label>
                            <input type="email" class="auth-input-control" id="personal_gmail" name="personal_gmail"
                                placeholder="yourname@gmail.com, yahoo.com, etc."
                                value="<?php echo sanitize($personal_gmail ?? ''); ?>" required>
                            <i class="bi bi-bell-fill auth-input-icon text-primary"></i>
                        </div>

                        <div class="row g-3">
                            <!-- Password with Show/Hide toggle -->
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="password" class="auth-input-label">
                                        <span class="req">*</span> Password
                                    </label>
                                    <input type="password" class="auth-input-control" id="password" name="password"
                                        placeholder="Min. 12 chars" required minlength="12" autocomplete="new-password">
                                    <button type="button" class="auth-pwd-toggle" id="togglePasswordBtn" aria-label="Toggle password view">
                                        <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Confirm Password with Show/Hide toggle -->
                            <div class="col-sm-6">
                                <div class="auth-input-group">
                                    <label for="confirm_password" class="auth-input-label">
                                        <span class="req">*</span> Confirm Password
                                    </label>
                                    <input type="password" class="auth-input-control" id="confirm_password" name="confirm_password"
                                        placeholder="Re-type password" required minlength="12" autocomplete="new-password">
                                    <button type="button" class="auth-pwd-toggle" id="toggleConfirmBtn" aria-label="Toggle confirm password view">
                                        <i class="bi bi-eye-slash" id="toggleConfirmIcon"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm Password Live Match Status -->
                        <div id="confirmMatchMsg" class="small mt-1 mb-2 fw-medium" style="display: none;"></div>

                        <!-- Password Strength Meter -->
                        <div class="password-meter-wrap mt-2 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted fw-semibold" style="font-size: 0.76rem;">Password Strength:</small>
                                <small id="strengthText" class="fw-bold text-muted" style="font-size: 0.76rem;">Not entered</small>
                            </div>
                            <div class="progress" style="height: 6px; background-color: #e2e8f0; border-radius: 4px;">
                                <div id="strengthBar" class="progress-bar" role="progressbar" style="width: 0%; border-radius: 4px; transition: width 0.25s ease, background-color 0.25s ease;"></div>
                            </div>
                        </div>

                        <!-- Live Password Requirement Checklist -->
                        <div class="password-checklist-box mb-4 p-3 rounded" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="fw-bold text-dark" style="font-size: 0.8rem;">
                                    <i class="bi bi-shield-lock-fill text-primary me-1"></i> Student Password Policy
                                </span>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 0.68rem;">12+ Chars Required</span>
                            </div>
                            <ul class="list-unstyled mb-0" style="font-size: 0.77rem;" id="passwordChecklist">
                                <li id="req-length" class="text-muted mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>Minimum 12 characters long</span>
                                </li>
                                <li id="req-upper" class="text-muted mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>At least one uppercase letter (A-Z)</span>
                                </li>
                                <li id="req-lower" class="text-muted mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>At least one lowercase letter (a-z)</span>
                                </li>
                                <li id="req-number" class="text-muted mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>At least one number (0-9)</span>
                                </li>
                                <li id="req-special" class="text-muted mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>At least one special character (!@#$%^&*...)</span>
                                </li>
                                <li id="req-pattern" class="text-muted mb-1 d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>No repeated characters (aaa) or sequences (1234, abcd)</span>
                                </li>
                                <li id="req-username" class="text-muted d-flex align-items-center gap-2">
                                    <i class="bi bi-circle"></i> <span>Does not contain your email username or name</span>
                                </li>
                            </ul>
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

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const pwdInput = document.getElementById('password');
        const confirmInput = document.getElementById('confirm_password');
        const emailInput = document.getElementById('email');
        const nameInput = document.getElementById('full_name');

        const togglePwdBtn = document.getElementById('togglePasswordBtn');
        const togglePwdIcon = document.getElementById('togglePasswordIcon');
        const toggleConfirmBtn = document.getElementById('toggleConfirmBtn');
        const toggleConfirmIcon = document.getElementById('toggleConfirmIcon');

        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const confirmMsg = document.getElementById('confirmMatchMsg');

        // Rule DOM elements
        const reqLength = document.getElementById('req-length');
        const reqUpper = document.getElementById('req-upper');
        const reqLower = document.getElementById('req-lower');
        const reqNumber = document.getElementById('req-number');
        const reqSpecial = document.getElementById('req-special');
        const reqPattern = document.getElementById('req-pattern');
        const reqUsername = document.getElementById('req-username');

        // 1. Password show/hide toggles
        if (togglePwdBtn && pwdInput && togglePwdIcon) {
            togglePwdBtn.addEventListener('click', function() {
                const isPwd = pwdInput.getAttribute('type') === 'password';
                pwdInput.setAttribute('type', isPwd ? 'text' : 'password');
                togglePwdIcon.className = isPwd ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }

        if (toggleConfirmBtn && confirmInput && toggleConfirmIcon) {
            toggleConfirmBtn.addEventListener('click', function() {
                const isPwd = confirmInput.getAttribute('type') === 'password';
                confirmInput.setAttribute('type', isPwd ? 'text' : 'password');
                toggleConfirmIcon.className = isPwd ? 'bi bi-eye' : 'bi bi-eye-slash';
            });
        }

        // Helper: detect 4-char sequences (1234, abcd, qwerty, etc.)
        function hasSequentialPattern(val) {
            const lower = val.toLowerCase();
            const keyPatterns = ['qwerty', 'asdfgh', 'zxcvbn', 'ytrewq', 'hgfdsa', 'nbvcxz'];
            for (let i = 0; i < keyPatterns.length; i++) {
                if (lower.indexOf(keyPatterns[i]) !== -1) return true;
            }
            if (lower.length >= 4) {
                for (let i = 0; i <= lower.length - 4; i++) {
                    const c1 = lower.charCodeAt(i);
                    const c2 = lower.charCodeAt(i + 1);
                    const c3 = lower.charCodeAt(i + 2);
                    const c4 = lower.charCodeAt(i + 3);
                    // Ascending
                    if (c2 === c1 + 1 && c3 === c2 + 1 && c4 === c3 + 1) return true;
                    // Descending
                    if (c2 === c1 - 1 && c3 === c2 - 1 && c4 === c3 - 1) return true;
                }
            }
            return false;
        }

        // Update individual rule UI element
        function setRuleStatus(elem, isPassed) {
            if (!elem) return;
            const icon = elem.querySelector('i');
            if (isPassed) {
                elem.className = 'text-success mb-1 d-flex align-items-center gap-2 fw-medium';
                if (icon) icon.className = 'bi bi-check-circle-fill text-success';
            } else {
                elem.className = 'text-muted mb-1 d-flex align-items-center gap-2';
                if (icon) icon.className = 'bi bi-circle';
            }
        }

        // 2. Real-time checklist and strength validation
        function validatePassword() {
            const pwd = pwdInput ? pwdInput.value : '';
            const email = emailInput ? emailInput.value.toLowerCase().trim() : '';
            const name = nameInput ? nameInput.value.toLowerCase().trim() : '';
            const pwdLower = pwd.toLowerCase();

            if (!pwd) {
                strengthBar.style.width = '0%';
                strengthBar.className = 'progress-bar';
                strengthText.textContent = 'Not entered';
                strengthText.className = 'fw-bold text-muted';
                [reqLength, reqUpper, reqLower, reqNumber, reqSpecial, reqPattern, reqUsername].forEach(el => setRuleStatus(el, false));
                validateConfirm();
                return;
            }

            // Rule 1: Length >= 12
            const passLength = pwd.length >= 12;
            setRuleStatus(reqLength, passLength);

            // Rule 2: Uppercase
            const passUpper = /[A-Z]/.test(pwd);
            setRuleStatus(reqUpper, passUpper);

            // Rule 3: Lowercase
            const passLower = /[a-z]/.test(pwd);
            setRuleStatus(reqLower, passLower);

            // Rule 4: Number
            const passNumber = /[0-9]/.test(pwd);
            setRuleStatus(reqNumber, passNumber);

            // Rule 5: Special Character
            const passSpecial = /[^A-Za-z0-9]/.test(pwd);
            setRuleStatus(reqSpecial, passSpecial);

            // Rule 6: No 3+ repeats or sequences
            const hasRepeat = /(.)\1{2,}/.test(pwd);
            const hasSeq = hasSequentialPattern(pwd);
            const passPattern = !hasRepeat && !hasSeq;
            setRuleStatus(reqPattern, passPattern);

            // Rule 7: No username or name substrings (len >= 3)
            let passUsername = true;
            if (email.indexOf('@') !== -1) {
                const username = email.split('@')[0];
                const tokens = username.split(/[\._\-\d]+/);
                tokens.push(username.replace(/[^a-z0-9]/g, ''));
                for (let t of tokens) {
                    if (t && t.length >= 3 && pwdLower.indexOf(t) !== -1) {
                        passUsername = false;
                        break;
                    }
                }
            }
            if (passUsername && name) {
                const nameTokens = name.split(/[\s,\.\-_]+/);
                for (let nt of nameTokens) {
                    if (nt && nt.length >= 3 && pwdLower.indexOf(nt) !== -1) {
                        passUsername = false;
                        break;
                    }
                }
            }
            setRuleStatus(reqUsername, passUsername);

            // Calculate score
            const checks = [passLength, passUpper, passLower, passNumber, passSpecial, passPattern, passUsername];
            const passedCount = checks.filter(Boolean).length;

            if (passedCount <= 2) {
                strengthBar.style.width = '25%';
                strengthBar.className = 'progress-bar bg-danger';
                strengthText.textContent = 'Weak';
                strengthText.className = 'fw-bold text-danger';
            } else if (passedCount <= 4) {
                strengthBar.style.width = '50%';
                strengthBar.className = 'progress-bar bg-warning';
                strengthText.textContent = 'Fair';
                strengthText.className = 'fw-bold text-warning';
            } else if (passedCount < 7) {
                strengthBar.style.width = '75%';
                strengthBar.className = 'progress-bar bg-info';
                strengthText.textContent = 'Good';
                strengthText.className = 'fw-bold text-info';
            } else {
                strengthBar.style.width = '100%';
                strengthBar.className = 'progress-bar bg-success';
                strengthText.textContent = 'Strong';
                strengthText.className = 'fw-bold text-success';
            }

            validateConfirm();
        }

        // 3. Confirm Password Match Validation
        function validateConfirm() {
            if (!confirmInput || !pwdInput) return;
            const pwd = pwdInput.value;
            const confirm = confirmInput.value;

            if (!confirm) {
                confirmMsg.style.display = 'none';
                confirmMsg.textContent = '';
                return;
            }

            confirmMsg.style.display = 'block';
            if (pwd === confirm) {
                confirmMsg.className = 'small mt-1 mb-2 fw-medium text-success';
                confirmMsg.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> Passwords match.';
            } else {
                confirmMsg.className = 'small mt-1 mb-2 fw-medium text-danger';
                confirmMsg.innerHTML = '<i class="bi bi-x-circle-fill me-1"></i> Passwords do not match.';
            }
        }

        if (pwdInput) {
            pwdInput.addEventListener('input', validatePassword);
        }
        if (confirmInput) {
            confirmInput.addEventListener('input', validateConfirm);
        }
        if (emailInput) {
            emailInput.addEventListener('input', validatePassword);
        }
        if (nameInput) {
            nameInput.addEventListener('input', validatePassword);
        }
    });
</script>

<?php include 'includes/footer.php'; ?>