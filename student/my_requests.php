<?php
/**
 * My Document Requests (student/my_requests.php)
 * 
 * Shows all document applications submitted by the logged-in student.
 * Tracks statuses: Pending -> Processing -> Ready for Pickup -> Completed / Rejected
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id = $_SESSION['user_id'];

// Fetch all requests for this student ordered by latest first
$stmt = $conn->prepare("
    SELECT dr.id, dr.tracking_number, dr.purpose, dr.status, dr.remarks, dr.requested_at, dr.updated_at,
           dt.document_name
    FROM document_requests dr
    JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE dr.user_id = ?
    ORDER BY dr.requested_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$requests_result = $stmt->get_result();
$stmt->close();

$page_title = 'My Document Requests';
include __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h3 class="mb-1">My Document Requests</h3>
        <p class="text-muted small mb-0">Track status updates and history of your document applications</p>
    </div>
    <div>
        <a href="request_document.php" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Request
        </a>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span><i class="bi bi-folder2-open text-primary me-2"></i>Submitted Requests</span>
        <span class="badge bg-light text-dark border"><?php echo $requests_result->num_rows; ?> Total</span>
    </div>

    <div class="card-body p-0">
        <?php if ($requests_result->num_rows > 0): ?>
            <div class="table-responsive border-0">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Tracking #</th>
                            <th>Document</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Requested On</th>
                            <th>Staff Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $requests_result->fetch_assoc()): 
                            $status_class = match($row['status']) {
                                'Pending' => 'badge-pending',
                                'Processing' => 'badge-processing',
                                'Ready for Pickup' => 'badge-ready',
                                'Completed' => 'badge-completed',
                                'Rejected' => 'badge-rejected',
                                default => 'bg-secondary text-white'
                            };
                        ?>
                            <tr>
                                <td>
                                    <code class="fw-bold text-primary"><?php echo sanitize($row['tracking_number']); ?></code>
                                </td>
                                <td class="fw-medium">
                                    <?php echo sanitize($row['document_name']); ?>
                                </td>
                                <td class="small text-muted" style="max-width: 240px;">
                                    <?php 
                                        $purpose_text = sanitize($row['purpose']);
                                        echo strlen($purpose_text) > 60 ? substr($purpose_text, 0, 60) . '...' : $purpose_text; 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                                <td class="small text-muted">
                                    <?php echo date('M d, Y h:i A', strtotime($row['requested_at'])); ?>
                                </td>
                                <td class="small">
                                    <?php if (!empty($row['remarks'])): ?>
                                        <span class="<?php echo $row['status'] === 'Rejected' ? 'text-danger' : 'text-muted'; ?>">
                                            <i class="bi bi-chat-left-dots me-1"></i><?php echo sanitize($row['remarks']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state py-5">
                <i class="bi bi-inbox text-muted"></i>
                <h5 class="fw-semibold mb-1">No Document Requests Found</h5>
                <p class="text-muted small mb-3">You haven't submitted any document requests yet.</p>
                <a href="request_document.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>Request a Document Now
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

