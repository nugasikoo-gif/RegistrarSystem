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

// Check if user is logged in
function isLoggedIn() {
    startSession();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

// Redirect to login page if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: /Registrar/login.php");
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
                header("Location: /Registrar/student/dashboard.php");
                break;
            case 'staff':
                if (file_exists(__DIR__ . '/../staff/dashboard.php')) {
                    header("Location: /Registrar/staff/dashboard.php");
                } else {
                    setFlashMessage('info', 'Staff module is currently under development (Phase 4).');
                    header("Location: /Registrar/logout.php");
                }
                break;
            case 'admin':
                if (file_exists(__DIR__ . '/../admin/dashboard.php')) {
                    header("Location: /Registrar/admin/dashboard.php");
                } else {
                    setFlashMessage('info', 'Admin module is currently under development (Phase 5).');
                    header("Location: /Registrar/logout.php");
                }
                break;
            default:
                header("Location: /Registrar/login.php");
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

