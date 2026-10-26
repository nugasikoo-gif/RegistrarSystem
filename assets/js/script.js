/**
 * CTU Registrar System - Custom JavaScript
 * 
 * This file contains small interactive functions used throughout the system.
 */

// ============================
// AUTO-DISMISS ALERTS
// ============================
// Automatically hide Bootstrap alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    // Find all dismissible alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    
    alerts.forEach(function(alert) {
        setTimeout(function() {
            // Use Bootstrap's built-in alert close method
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000); // 5 seconds
    });
});

// ============================
// CONFIRM DELETE
// ============================
// Show a confirmation dialog before deleting something
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this? This action cannot be undone.');
}

// ============================
// CONFIRM ACTION
// ============================
// Show a confirmation dialog for any action
function confirmAction(message) {
    return confirm(message || 'Are you sure you want to proceed?');
}

// ============================
// ACTIVE NAV LINK HIGHLIGHT
// ============================
// Highlight the current page's link in the navbar
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
    
    navLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        if (href && (href === currentPath || currentPath.endsWith(href))) {
            link.classList.add('active');
        }
    });
});

