<?php
/**
 * Staff - Manage Document Requests
 * Allows registrar staff to review, update status, and add remarks to document requests.
 * Triggers automatic PHPMailer email notifications on status change (Approved, Ready for Pickup, Rejected, Completed).
 * Enforces school physical payment and physical document inspection prior to marking 'Completed'.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/request_status.php';

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

// Filter handling
$status_filter = trim($_GET['status'] ?? '');
$allowed_filters = ['Pending', 'Processing', 'Approved', 'Ready for Pickup', 'Completed', 'Rejected'];

// Count summary
$counts_query = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as ready,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM document_requests
";
$counts_res = $conn->query($counts_query)->fetch_assoc();

// Fetch requests with joined document requirements and fees
$requests_query = "
    SELECT dr.*, u.full_name, u.student_id, u.email as ctu_email, u.personal_gmail, 
           dt.document_name, dt.fee, dt.requirements
    FROM document_requests dr
    JOIN users u ON dr.user_id = u.id
    JOIN document_types dt ON dr.document_type_id = dt.id
";

if (in_array($status_filter, $allowed_filters)) {
    $requests_query .= " WHERE dr.status = '" . $conn->real_escape_string($status_filter) . "'";
}

$requests_query .= " ORDER BY CASE dr.status 
    WHEN 'Ready for Pickup' THEN 1 
    WHEN 'Pending' THEN 2 
    WHEN 'Processing' THEN 3 
    WHEN 'Approved' THEN 4 
    WHEN 'Completed' THEN 5 
    ELSE 6 END, dr.requested_at DESC";

$requests_res = $conn->query($requests_query);

$page_title = 'Manage Document Requests';
include __DIR__ . '/../includes/header.php';
$csrf_token = generateCSRFToken();
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Document Request Management</h3>
            <p class="text-muted small mb-0">Review student applications, update processing stages, and release documents upon physical inspection of original cashier receipt and requirements.</p>
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

    <!-- Document Releasing Operational Protocol Banner -->
    <div class="card border-0 shadow-sm mb-4 bg-white border-start border-4 border-success">
        <div class="card-body p-3">
            <div class="d-flex align-items-start gap-3">
                <div class="p-2 bg-success-subtle text-success rounded-circle mt-1">
                    <i class="bi bi-shield-check fs-4"></i>
                </div>
                <div>
                    <h6 class="fw-bold text-dark mb-1">Physical Document Releasing Protocol:</h6>
                    <p class="small text-muted mb-0">
                        When a student claims a <span class="badge bg-success">Ready for Pickup</span> credential:
                        <strong>(1)</strong> Physically inspect the student's <strong>original cashier paper receipt</strong> to ensure document fees were settled, 
                        <strong>(2)</strong> Verify their required physical documents and student ID, and 
                        <strong>(3)</strong> Update status to <span class="badge bg-secondary">Completed</span> only after releasing the physical document.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Buttons -->
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="btn-group btn-group-sm">
            <a href="<?php echo url('staff/manage_requests.php'); ?>" class="btn <?php echo empty($status_filter) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                All (<?php echo (int)($counts_res['total'] ?? 0); ?>)
            </a>
            <a href="<?php echo url('staff/manage_requests.php?status=Ready for Pickup'); ?>" class="btn <?php echo $status_filter === 'Ready for Pickup' ? 'btn-success fw-bold' : 'btn-outline-success'; ?>">
                Ready for Pickup (<?php echo (int)($counts_res['ready'] ?? 0); ?>)
            </a>
            <a href="<?php echo url('staff/manage_requests.php?status=Pending'); ?>" class="btn <?php echo $status_filter === 'Pending' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                Pending (<?php echo (int)($counts_res['pending'] ?? 0); ?>)
            </a>
            <a href="<?php echo url('staff/manage_requests.php?status=Processing'); ?>" class="btn <?php echo $status_filter === 'Processing' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                Processing (<?php echo (int)($counts_res['processing'] ?? 0); ?>)
            </a>
            <a href="<?php echo url('staff/manage_requests.php?status=Approved'); ?>" class="btn <?php echo $status_filter === 'Approved' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                Approved (<?php echo (int)($counts_res['approved'] ?? 0); ?>)
            </a>
            <a href="<?php echo url('staff/manage_requests.php?status=Completed'); ?>" class="btn <?php echo $status_filter === 'Completed' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                Completed (<?php echo (int)($counts_res['completed'] ?? 0); ?>)
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-folder2-open me-2 text-primary"></i>Submitted Document Applications
            </h6>
            <small class="text-muted"><?php echo $requests_res->num_rows; ?> request(s) listed</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="text-muted small">
                            <th>Tracking #</th>
                            <th>Student</th>
                            <th>Document & Fee</th>
                            <th>Purpose</th>
                            <th>Current Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($requests_res->num_rows === 0): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No document requests found matching filter.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = $requests_res->fetch_assoc()): 
                                $fee_val = floatval($row['fee'] ?? 0);
                                $is_ready = ($row['status'] === 'Ready for Pickup');
                            ?>
                                <tr class="<?php echo $is_ready ? 'table-success-subtle border-start border-4 border-success' : ''; ?>">
                                    <td>
                                        <span class="fw-bold text-primary font-monospace"><?php echo sanitize($row['tracking_number']); ?></span>
                                        <div class="text-muted" style="font-size: 11px;"><?php echo date('M d, Y h:i A', strtotime($row['requested_at'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($row['full_name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($row['student_id'] ?: 'N/A'); ?> &bull; <?php echo sanitize($row['ctu_email']); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($row['document_name']); ?></div>
                                        <small class="text-success fw-semibold">
                                            Fee: ₱<?php echo number_format($fee_val, 2); ?> (Pay at Cashier)
                                        </small>
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
                                            'Approved' => 'bg-primary text-white',
                                            'Ready for Pickup' => 'bg-success text-white',
                                            'Completed' => 'bg-secondary text-white',
                                            'Rejected' => 'bg-danger text-white',
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
                                        <button type="button" class="btn btn-sm <?php echo $is_ready ? 'btn-success fw-bold' : 'btn-outline-primary'; ?>" 
                                                data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $row['id']; ?>">
                                            <?php if ($is_ready): ?>
                                                <i class="bi bi-check2-circle me-1"></i>Release / Update
                                            <?php else: ?>
                                                <i class="bi bi-pencil-square me-1"></i>Update
                                            <?php endif; ?>
                                        </button>

                                        <!-- Update Modal -->
                                        <div class="modal fade" id="updateModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered modal-lg text-start">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header <?php echo $is_ready ? 'bg-success text-white' : ''; ?>">
                                                            <h5 class="modal-title fs-6 fw-bold">
                                                                Update Request #<?php echo sanitize($row['tracking_number']); ?>
                                                            </h5>
                                                            <button type="button" class="btn-close <?php echo $is_ready ? 'btn-close-white' : ''; ?>" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                            <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">

                                                            <div class="row g-3 mb-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-semibold text-muted">Student Name</label>
                                                                    <input type="text" class="form-control form-control-sm bg-light" value="<?php echo sanitize($row['full_name']); ?>" readonly>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-semibold text-muted">Student ID</label>
                                                                    <input type="text" class="form-control form-control-sm bg-light" value="<?php echo sanitize($row['student_id'] ?: 'N/A'); ?>" readonly>
                                                                </div>
                                                            </div>

                                                            <div class="row g-3 mb-3">
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-semibold text-muted">Document Name</label>
                                                                    <input type="text" class="form-control form-control-sm bg-light" value="<?php echo sanitize($row['document_name']); ?>" readonly>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label small fw-semibold text-muted">Cashier Fee to Collect</label>
                                                                    <input type="text" class="form-control form-control-sm bg-light fw-bold text-success" value="₱<?php echo number_format($fee_val, 2); ?>" readonly>
                                                                </div>
                                                            </div>

                                                            <!-- Physical Check Reminder for Staff -->
                                                            <div class="p-3 bg-light border rounded-3 mb-3 small">
                                                                <div class="fw-bold text-dark mb-1">
                                                                    <i class="bi bi-clipboard-check text-primary me-1"></i>Required Physical Documents to Inspect on Releasing:
                                                                </div>
                                                                <div class="text-secondary" style="white-space: pre-line;">
                                                                    <?php echo sanitize($row['requirements'] ?: '• Valid CTU Student ID or Government ID'); ?>
                                                                </div>
                                                                <div class="mt-2 text-danger fw-semibold" style="font-size: 11px;">
                                                                    *Confirm physical official paper receipt from Cashier before selecting 'Completed'.
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="status<?php echo $row['id']; ?>" class="form-label small fw-semibold">New Request Status *</label>
                                                                <select name="status" id="status<?php echo $row['id']; ?>" class="form-select form-select-sm" required>
                                                                    <option value="Pending" <?php echo ($row['status'] === 'Pending') ? 'selected' : ''; ?>>Pending (Waiting to process)</option>
                                                                    <option value="Processing" <?php echo ($row['status'] === 'Processing') ? 'selected' : ''; ?>>Processing (Being prepared by staff)</option>
                                                                    <option value="Approved" <?php echo ($row['status'] === 'Approved') ? 'selected' : ''; ?>>Approved (Emails Student)</option>
                                                                    <option value="Ready for Pickup" <?php echo ($row['status'] === 'Ready for Pickup') ? 'selected' : ''; ?>>Ready for Pickup (Notifies student to pay at cashier & bring requirements)</option>
                                                                    <option value="Completed" <?php echo ($row['status'] === 'Completed') ? 'selected' : ''; ?>>Completed (Document released to student after receipt check)</option>
                                                                    <option value="Rejected" <?php echo ($row['status'] === 'Rejected') ? 'selected' : ''; ?>>Rejected (Emails Student with reason)</option>
                                                                </select>
                                                                <div class="form-text" style="font-size: 11px;">
                                                                    Setting to <strong>Ready for Pickup</strong> automatically instructs student to pay fee at Cashier and bring original receipt. Setting to <strong>Completed</strong> records that the document has been physically released.
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="remarks<?php echo $row['id']; ?>" class="form-label small fw-semibold">Staff Remarks / Instructions to Student</label>
                                                                <textarea name="remarks" id="remarks<?php echo $row['id']; ?>" class="form-control form-control-sm" rows="3" placeholder="e.g. Please proceed to Window 2 with your receipt and 2x2 ID picture."><?php echo sanitize($row['remarks'] ?? ''); ?></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-primary fw-semibold">Save Status & Send Alert</button>
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

<?php include __DIR__ . '/../includes/footer.php'; ?>
