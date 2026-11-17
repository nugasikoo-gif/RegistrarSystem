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
// PRELOADER DISMISSAL
// ============================
(function() {
    function hidePreloader() {
        const preloader = document.getElementById('ctuPreloader');
        if (preloader && !preloader.classList.contains('fade-out')) {
            preloader.classList.add('fade-out');
            setTimeout(function() {
                if (preloader.parentNode) {
                    preloader.parentNode.removeChild(preloader);
                }
            }, 350);
        }
    }

    if (document.readyState === 'complete') {
        hidePreloader();
    } else {
        window.addEventListener('load', hidePreloader);
        // Fail-safe: dismiss preloader after 800ms max so user is never blocked
        setTimeout(hidePreloader, 800);
    }
})();

// ============================
// MOBILE DRAWER NAVIGATION
// ============================
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('dashboardSidebar');
    const toggleBtn = document.getElementById('sidebarToggleBtn');
    const closeBtn = document.getElementById('sidebarCloseBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    function openDrawer() {
        if (sidebar && backdrop) {
            sidebar.classList.add('drawer-open');
            backdrop.classList.add('active');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'true');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
    }

    function closeDrawer() {
        if (sidebar && backdrop) {
            sidebar.classList.remove('drawer-open');
            backdrop.classList.remove('active');
            if (toggleBtn) toggleBtn.setAttribute('aria-expanded', 'false');
            document.body.style.overflow = '';
        }
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (sidebar && sidebar.classList.contains('drawer-open')) {
                closeDrawer();
            } else {
                openDrawer();
            }
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            closeDrawer();
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', function() {
            closeDrawer();
        });
    }

    // Close drawer when clicking any navigation link inside sidebar on mobile
    if (sidebar) {
        const drawerLinks = sidebar.querySelectorAll('.sidebar-nav-link');
        drawerLinks.forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    closeDrawer();
                }
            });
        });
    }

    // Close on Escape key press
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar && sidebar.classList.contains('drawer-open')) {
            closeDrawer();
        }
    });

    // Handle viewport resize: if resized to desktop (>= 992px), reset mobile state
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992 && sidebar && sidebar.classList.contains('drawer-open')) {
            closeDrawer();
        }
    });
});

// ============================
// ACTIVE NAV LINK HIGHLIGHT
// ============================
// Highlight the current page's link in both sidebar and navbar
document.addEventListener('DOMContentLoaded', function() {
    const currentPath = window.location.pathname;
    const allNavLinks = document.querySelectorAll('.sidebar-nav-link, .navbar-nav .nav-link');
    
    allNavLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        if (href) {
            // Strip any query strings for clean matching
            const cleanHref = href.split('?')[0];
            if (cleanHref === currentPath || currentPath.endsWith(cleanHref)) {
                link.classList.add('active');
            }
        }
    });
});

