<?php
/**
 * Footer Include
 * 
 * Included at the bottom of every page:
 * - Closes main content container and dashboard wrapper
 * - Footer HTML with copyright notice
 * - Bootstrap 5 JavaScript (from CDN)
 * - Custom JavaScript (assets/js/script.js)
 */
?>

</main> <!-- End of main content container -->

<?php if (empty($hide_nav) && isLoggedIn()): ?>
    <!-- Dashboard Footer for Authenticated Layout -->
    <?php if (empty($hide_footer)): ?>
        <footer class="dashboard-footer">
            <div class="container-fluid">
                <small>
                    &copy; <?php echo date('Y'); ?> CTU Registrar System &mdash;
                    Cebu Technological University &ndash; Daanbantayan Campus
                </small>
            </div>
        </footer>
    <?php endif; ?>
    </div> <!-- End of dashboard-main-area -->
    </div> <!-- End of dashboard-wrapper -->
<?php else: ?>
    <!-- Public/Guest Footer -->
    <?php if (empty($hide_footer)): ?>
        <footer class="bg-light text-center text-muted py-3 mt-auto border-top">
            <div class="container">
                <small>
                    &copy; <?php echo date('Y'); ?> CTU Registrar System &mdash;
                    Cebu Technological University &ndash; Daanbantayan Campus
                </small>
            </div>
        </footer>
    <?php endif; ?>
<?php endif; ?>

<!-- Bootstrap 5 JS Bundle (includes Popper.js for dropdowns) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="<?php echo url('assets/js/script.js'); ?>"></script>

</body>
</html>
