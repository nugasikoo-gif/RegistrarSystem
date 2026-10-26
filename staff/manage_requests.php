<?php
/**
 * Staff - Manage Document Requests
 * Allows registrar staff to review, update status, and add remarks to document requests.
 * Triggers automatic PHPMailer email notifications on status change (Approved, Ready for Pickup, Rejected).
 */

require_once '../includes/auth.php';
require_once '../config/database.php';
require_once '../includes/request_status.php';

startSession();
requireRole('staff');

$flash = getFlashMessage();
$update_message = '';
$update_type = '';

// Handle status update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $update_message = 'Invalid form submission.';
        $update_type = 'danger';
    } else {
        $request_id = (int)($_POST['request_id'] ?? 0);
        $new_status = trim($_POST['status'] ?? '');
        $remarks    = trim($_POST['remarks'] ?? '');

        $allowed_statuses = ['Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected'];

        if (!in_array($new_status, $allowed_statuses)) {
            $update_message = 'Invalid status selected.';
            $update_type = 'danger';
        } else {
            $result = updateDocumentRequestStatus($request_id, $new_status, $remarks);
            if ($result['success']) {
                $update_message = "Request updated to <strong>$new_status</strong>.";
                if ($result['email_sent']) {
                    $update_message .= " An email notification was automatically sent to the student's personal Gmail!";
                } elseif (!empty($result['email_error'])) {
                    $update_message .= " <span class='text-muted'>(Note: Email notification skipped or pending SMTP config: " . htmlspecialchars($result['email_error']) . ")</span>";
                }
                $update_type = 'success';
            } else {
                $update_message = $result['message'];
                $update_type = 'danger';
            }
        }
    }
}

// Fetch all requests
$requests_query = "
    SELECT dr.*, u.full_name, u.student_id, u.email as ctu_email, u.personal_gmail, dt.document_name
    FROM document_requests dr
    JOIN users u ON dr.user_id = u.id
    JOIN document_types dt ON dr.document_type_id = dt.id
    ORDER BY dr.requested_at DESC
";
$requests_res = $conn->query($requests_query);

$page_title = 'Manage Document Requests';
include '../includes/header.php';
$csrf_token = generateCSRFToken();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Document Request Management</h3>
            <p class="text-muted small mb-0">Review student requests and update processing statuses with automatic Gmail notifications.</p>
        </div>
        <div>
            <span class="badge bg-primary px-3 py-2">
                <i class="bi bi-person-badge me-1"></i> Staff: <?php echo sanitize($_SESSION['full_name']); ?>
            </span>
        </div>
    </div>

    <?php if (!empty($update_message)): ?>
        <div class="alert alert-<?php echo $update_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div><?php echo $update_message; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-folder2-open me-2 text-primary"></i>All Submitted Requests
            </h6>
            <small class="text-muted"><?php echo $requests_res->num_rows; ?> total request(s)</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small">
                            <th>Tracking #</th>
                            <th>Student</th>
                            <th>Notification Gmail</th>
                            <th>Document</th>
                            <th>Purpose</th>
                            <th>Current Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_res->num_rows === 0): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No document requests submitted yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $requests_res->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="fw-bold text-primary font-monospace"><?php echo sanitize($row['tracking_number']); ?></span>
                                        <div class="text-muted" style="font-size: 11px;"><?php echo date('M d, Y h:i A', strtotime($row['requested_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($row['full_name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($row['student_id'] ?: 'N/A'); ?> &bull; <?php echo sanitize($row['ctu_email']); ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['personal_gmail'])): ?>
                                            <span class="text-success small fw-semibold">
                                                <i class="bi bi-google me-1 text-danger"></i><?php echo sanitize($row['personal_gmail']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted small"><em>None (uses CTU email)</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?php echo sanitize($row['document_name']); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted" style="max-width: 180px; display: inline-block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?php echo sanitize($row['purpose']); ?>">
                                            <?php echo sanitize($row['purpose']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = match($row['status']) {
                                            'Pending' => 'bg-warning text-dark',
                                            'Processing' => 'bg-info text-dark',
                                            'Approved' => 'bg-primary',
                                            'Ready for Pickup' => 'bg-success',
                                            'Completed' => 'bg-secondary',
                                            'Rejected' => 'bg-danger',
                                            default => 'bg-light text-dark'
                                        };
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?> px-2 py-1"><?php echo sanitize($row['status']); ?></span>
                                        <?php if (!empty($row['last_notified_status'])): ?>
                                            <div style="font-size: 10px; color: #198754;" title="Last status emailed to student">
                                                <i class="bi bi-envelope-check"></i> Emailed: <?php echo sanitize($row['last_notified_status']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Status Update Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $row['id']; ?>">
                                            <i class="bi bi-pencil-square me-1"></i> Update
                                        </button>

                                        <!-- Update Modal -->
                                        <div class="modal fade" id="updateModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold">Update Request #<?php echo sanitize($row['tracking_number']); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                            <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold text-muted">Student Name</label>
                                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo sanitize($row['full_name']); ?>" readonly>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold text-muted">Notification Recipient (Personal Gmail)</label>
                                                                <input type="text" class="form-control form-control-sm bg-light" value="<?php echo sanitize($row['personal_gmail'] ?: $row['ctu_email']); ?>" readonly>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="status<?php echo $row['id']; ?>" class="form-label small fw-semibold">New Status</label>
                                                                <select name="status" id="status<?php echo $row['id']; ?>" class="form-select form-select-sm" required>
                                                                    <option value="Pending" <?php echo ($row['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                                                                    <option value="Processing" <?php echo ($row['status'] === 'Processing') ? 'selected' : ''; ?>>Processing</option>
                                                                    <option value="Approved" <?php echo ($row['status'] === 'Approved') ? 'selected' : ''; ?>>Approved (Emails Student)</option>
                                                                    <option value="Ready for Pickup" <?php echo ($row['status'] === 'Ready for Pickup') ? 'selected' : ''; ?>>Ready for Pickup (Emails Student)</option>
                                                                    <option value="Completed" <?php echo ($row['status'] === 'Completed') ? 'selected' : ''; ?>>Completed</option>
                                                                    <option value="Rejected" <?php echo ($row['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected (Emails Student)</option>
                                                                </select>
                                                                <div class="form-text" style="font-size: 11px;">
                                                                    Selecting <strong>Approved</strong>, <strong>Ready for Pickup</strong>, or <strong>Rejected</strong> triggers an automatic email.
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="remarks<?php echo $row['id']; ?>" class="form-label small fw-semibold">Remarks (included in email)</label>
                                                                <textarea name="remarks" id="remarks<?php echo $row['id']; ?>" class="form-control form-control-sm" rows="3" placeholder="e.g. Please bring original Form 137 and student ID for counter verification."><?php echo sanitize($row['remarks'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-primary">Save & Send Notification</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

