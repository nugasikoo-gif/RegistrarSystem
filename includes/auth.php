<?php
/**
 * Auth Helper
 * 
 * This file provides helper functions for authentication and security.
 * Include this file at the top of any page that needs login protection.
 * 
 * Functions:
 * - startSession()       → Starts a PHP session if not already started
 * - isLoggedIn()         → Checks if a user is logged in
 * - requireLogin()       → Redirects to login page if not logged in
 * - requireRole($role)   → Checks if user has the correct role
 * - generateCSRFToken()  → Creates a CSRF token to protect forms
 * - validateCSRFToken()  → Validates a submitted CSRF token
 * - sanitize($data)      → Cleans user input for safe display
 */

// Start session if not already started
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Automatically detect dynamic base path based on directory location
if (!defined('BASE_URL')) {
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Determine the root directory relative to web server document root
    $docRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $projectDir = str_replace('\\', '/', realpath(__DIR__ . '/..'));
    
    if (!empty($docRoot) && !empty($projectDir) && str_starts_with($projectDir, $docRoot)) {
        $subPath = trim(substr($projectDir, strlen($docRoot)), '/');
        define('BASE_URL', empty($subPath) ? '' : '/' . $subPath);
    } else {
        // Fallback detection using SCRIPT_NAME parts
        $pos = strpos($script, '/includes');
        if ($pos !== false) {
            define('BASE_URL', substr($script, 0, $pos));
        } else {
            // Get first directory level from script
            $parts = explode('/', trim($script, '/'));
            define('BASE_URL', !empty($parts[0]) && !str_ends_with($parts[0], '.php') ? '/' . $parts[0] : '');
        }
    }
}

function url($path = '') {
    $trimmed = ltrim($path, '/');
    return empty($trimmed) ? BASE_URL : BASE_URL . '/' . $trimmed;
}

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

// Redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . url('login.php'));
        exit();
    }
}

/**
 * Require a specific role to access a page
 * If the user's role doesn't match, they are sent to their own dashboard
 */
function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        // Redirect user to their correct dashboard if it exists
        switch ($_SESSION['role']) {
            case 'student':
                header("Location: " . url('student/dashboard.php'));
                break;
            case 'staff':
                if (file_exists(__DIR__ . '/../staff/dashboard.php')) {
                    header("Location: " . url('staff/dashboard.php'));
                } else {
                    setFlashMessage('info', 'Staff module is currently under development (Phase 4).');
                    header("Location: " . url('logout.php'));
                }
                break;
            case 'admin':
                if (file_exists(__DIR__ . '/../admin/dashboard.php')) {
                    header("Location: " . url('admin/dashboard.php'));
                } else {
                    setFlashMessage('info', 'Admin module is currently under development (Phase 5).');
                    header("Location: " . url('logout.php'));
                }
                break;
            default:
                header("Location: " . url('login.php'));
                break;
        }
        exit();
    }
}

/**
 * Generate a CSRF token
 * 
 * CSRF (Cross-Site Request Forgery) protection prevents attackers
 * from tricking users into submitting forms on other websites.
 * We generate a random token, store it in the session, and include
 * it as a hidden field in forms. When the form is submitted, we
 * check that the token matches.
 */
function generateCSRFToken() {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token from a form submission
 * Returns true if valid, false if invalid
 */
function validateCSRFToken($token) {
    startSession();
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output to prevent XSS attacks
 * 
 * XSS (Cross-Site Scripting) happens when an attacker injects
 * JavaScript through user input. htmlspecialchars() converts
 * special characters like < > " to safe HTML entities.
 */
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Get the count of unread notifications for the logged-in user
 */
function getUnreadNotificationCount($conn) {
    if (!isLoggedIn()) return 0;
    
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return $row['count'];
}

/**
 * Set a flash message to display on the next page load
 * Flash messages are shown once and then cleared
 */
function setFlashMessage($type, $message) {
    startSession();
    $_SESSION['flash'] = [
        'type' => $type,     // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

/**
 * Get and clear the flash message
 */
function getFlashMessage() {
    startSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Validate that an email belongs to the official CTU domain (@ctu.edu.ph or *.ctu.edu.ph)
 * 
 * Rejects unofficial email domains (e.g., gmail.com, yahoo.com, outlook.com, hotmail.com).
 * 
 * @param string $email
 * @return bool
 */
function isValidCtuEmail($email) {
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $parts = explode('@', strtolower(trim($email)));
    if (count($parts) !== 2) {
        return false;
    }
    $domain = $parts[1];
    return ($domain === 'ctu.edu.ph' || str_ends_with($domain, '.ctu.edu.ph'));
}

/**
 * Check if a string contains predictable sequences of 4 or more consecutive characters
 * e.g., 1234, 4321, abcd, dcba, qwerty
 * 
 * @param string $str
 * @return bool
 */
function hasPredictableSequence($str) {
    $lower = strtolower($str);
    
    // Common keyboard sequences
    $keyboard_patterns = ['qwerty', 'asdfgh', 'zxcvbn', 'ytrewq', 'hgfdsa', 'nbvcxz'];
    foreach ($keyboard_patterns as $kp) {
        if (strpos($lower, $kp) !== false) {
            return true;
        }
    }
    
    // Check 4-character sequential runs (numeric or alphabetical)
    $len = strlen($lower);
    if ($len >= 4) {
        for ($i = 0; $i <= $len - 4; $i++) {
            $c1 = ord($lower[$i]);
            $c2 = ord($lower[$i+1]);
            $c3 = ord($lower[$i+2]);
            $c4 = ord($lower[$i+3]);
            
            // Ascending sequence (e.g. 1-2-3-4 or a-b-c-d)
            if ($c2 === $c1 + 1 && $c3 === $c2 + 1 && $c4 === $c3 + 1) {
                if ((ctype_digit($lower[$i]) && ctype_digit($lower[$i+3])) || 
                    (ctype_lower($lower[$i]) && ctype_lower($lower[$i+3]))) {
                    return true;
                }
            }
            // Descending sequence (e.g. 4-3-2-1 or d-c-b-a)
            if ($c2 === $c1 - 1 && $c3 === $c2 - 1 && $c4 === $c3 - 1) {
                if ((ctype_digit($lower[$i]) && ctype_digit($lower[$i+3])) || 
                    (ctype_lower($lower[$i]) && ctype_lower($lower[$i+3]))) {
                    return true;
                }
            }
        }
    }
    return false;
}

/**
 * Strict Student-Only Strong Password Policy Validator
 * 
 * Enforces:
 * 1. Minimum 12 characters.
 * 2. At least one uppercase letter (A-Z).
 * 3. At least one lowercase letter (a-z).
 * 4. At least one number (0-9).
 * 5. At least one special character.
 * 6. Block common and easily guessed passwords.
 * 7. Reject predictable sequences (1234, abcd, qwerty) and repeated characters (aaa, 111).
 * 8. Reject passwords containing obvious variations of the student's email username or full name.
 * 
 * NOTE: This policy is strictly for students and is NEVER applied to Staff or Admin.
 * 
 * @param string $password The raw password to validate
 * @param string $email The student's login email
 * @param string $fullName The student's full name
 * @return array ['valid' => bool, 'errors' => string[]]
 */
function validateStudentStrongPassword($password, $email = '', $fullName = '') {
    $errors = [];
    
    if (strlen($password) < 12) {
        $errors[] = 'Password must be at least 12 characters long.';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must include at least one uppercase letter (A-Z).';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must include at least one lowercase letter (a-z).';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must include at least one number (0-9).';
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
        $errors[] = 'Password must include at least one special character (e.g. !@#$%^&*()_+-=[]{};:|,.<>?).';
    }
    
    // Repeated character check (3 or more consecutive identical characters)
    if (preg_match('/(.)\1{2,}/', $password)) {
        $errors[] = 'Password must not contain repeated characters (e.g. "aaa" or "111").';
    }
    
    // Predictable sequence check (1234, abcd, qwerty, etc.)
    if (hasPredictableSequence($password)) {
        $errors[] = 'Password must not contain predictable sequences (e.g. "1234", "abcd", or "qwerty").';
    }
    
    // Common easily guessed passwords blacklist
    $common_passwords = [
        'password', 'password123', 'password1234', 'password12345',
        'pass12345678', 'admin1234567', 'qwerty123456', 'qwertyuiop12',
        'welcome12345', 'ctu_registrar', 'ctudaanbantayan', 'student12345',
        'changeme1234', '123456789012', '1234567890123', 'iloveyou1234',
        'letmein12345', 'secret123456', 'administrator', 'default12345',
        'p@ssword1234', 'passw0rd1234', 'ctuportal1234', 'registrarsystem'
    ];
    $pwd_lower = strtolower($password);
    foreach ($common_passwords as $common) {
        if ($pwd_lower === $common || strpos($pwd_lower, $common) !== false) {
            $errors[] = 'Password is too common or easily guessed. Please create a unique passphrase.';
            break;
        }
    }
    
    // Reject obvious variations of email username
    if (!empty($email)) {
        $email_parts = explode('@', strtolower($email));
        $username = $email_parts[0] ?? '';
        $tokens = preg_split('/[\._\-\d]+/', $username);
        $tokens[] = preg_replace('/[^a-z0-9]/', '', $username);
        foreach ($tokens as $token) {
            $token = trim($token);
            if (strlen($token) >= 3 && strpos($pwd_lower, $token) !== false) {
                $errors[] = 'Password must not contain parts of your email username ("' . $token . '").';
                break;
            }
        }
    }
    
    // Reject obvious variations of full name
    if (!empty($fullName)) {
        $name_tokens = preg_split('/[\s,\.\-_]+/', strtolower($fullName));
        foreach ($name_tokens as $ntoken) {
            $ntoken = trim($ntoken);
            if (strlen($ntoken) >= 3 && strpos($pwd_lower, $ntoken) !== false) {
                $errors[] = 'Password must not contain your name ("' . $ntoken . '").';
                break;
            }
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}


