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
        <div class="portal-section-header mb-0">
            <h3 class="portal-section-title">My Document Requests</h3>
            <p class="text-muted small mb-0">Track the real-time processing and pickup status of your submitted applications</p>
            <div class="portal-title-bar"></div>
        </div>
    </div>
    <div>
        <a href="<?php echo url('student/request_document.php'); ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Application
        </a>
    </div>
</div>

<div class="card shadow-sm border">
    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
        <span class="fw-semibold text-dark"><i class="bi bi-folder2-open text-primary me-2"></i>Submitted Requests</span>
        <span class="badge bg-light text-dark border px-2 py-1"><?php echo $requests_result->num_rows; ?> Total</span>
    </div>

    <div class="card-body p-0">
        <?php if ($requests_result->num_rows > 0): ?>
            <div class="table-responsive border-0">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-3 py-2 text-muted small fw-semibold">Tracking #</th>
                            <th class="py-2 text-muted small fw-semibold">Document</th>
                            <th class="py-2 text-muted small fw-semibold">Purpose</th>
                            <th class="py-2 text-muted small fw-semibold">Status</th>
                            <th class="py-2 text-muted small fw-semibold">Date Submitted</th>
                            <th class="pe-3 py-2 text-muted small fw-semibold">Staff Remarks</th>
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
                                <td class="ps-3">
                                    <code class="fw-bold text-primary font-monospace"><?php echo sanitize($row['tracking_number']); ?></code>
                                </td>
                                <td class="fw-medium text-dark">
                                    <?php echo sanitize($row['document_name']); ?>
                                </td>
                                <td class="small text-muted" style="max-width: 240px;">
                                    <?php 
                                        $purpose_text = sanitize($row['purpose']);
                                        echo strlen($purpose_text) > 65 ? substr($purpose_text, 0, 65) . '...' : $purpose_text; 
                                    ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo $status_class; ?>">
                                        <?php echo sanitize($row['status']); ?>
                                    </span>
                                </td>
                                <td class="text-muted small">
                                    <?php echo date('M d, Y', strtotime($row['requested_at'])); ?>
                                </td>
                                <td class="pe-3 small text-secondary">
                                    <?php if (!empty($row['remarks'])): ?>
                                        <div class="p-2 bg-light border rounded-2">
                                            <i class="bi bi-chat-left-dots text-primary me-1"></i>
                                            <?php echo sanitize($row['remarks']); ?>
                                        </div>
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
            <div class="empty-state py-5 text-center">
                <div class="d-inline-flex p-3 bg-light text-primary rounded-circle mb-3">
                    <i class="bi bi-inbox fs-1"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">No Document Requests Found</h6>
                <p class="text-muted small mb-3">You have not submitted any academic document applications yet.</p>
                <a href="<?php echo url('student/request_document.php'); ?>" class="btn btn-primary btn-sm px-3">
                    <i class="bi bi-plus-lg me-1"></i>Submit Document Request
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
