<?php
/**
 * My Document Requests (student/my_requests.php)
 * 
 * Shows all document applications submitted by the logged-in student.
 * Clearly highlights requests that are "Ready for Pickup" with instructions:
 * 1. Pay document fee at the campus physical cashier.
 * 2. Bring original official receipt.
 * 3. Bring required physical documents.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id = $_SESSION['user_id'];

// Fetch all requests for this student ordered by latest first, joining document fee, requirements & instructions
$stmt = $conn->prepare("
    SELECT dr.id, dr.tracking_number, dr.purpose, dr.status, dr.remarks, dr.requested_at, dr.updated_at,
           dt.document_name, dt.fee, dt.processing_time, dt.requirements, dt.instructions
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
            <p class="text-muted small mb-0">Track processing statuses, physical claiming requirements, and cashier payment instructions</p>
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
                            <th class="py-2 text-muted small fw-semibold">Cashier Fee</th>
                            <th class="py-2 text-muted small fw-semibold">Purpose</th>
                            <th class="py-2 text-muted small fw-semibold">Status</th>
                            <th class="py-2 text-muted small fw-semibold">Date Submitted</th>
                            <th class="pe-3 py-2 text-muted small fw-semibold text-end">Action & Claiming Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $requests_result->fetch_assoc()): 
                            $status_class = match($row['status']) {
                                'Pending' => 'badge-pending',
                                'Processing' => 'badge-processing',
                                'Approved' => 'bg-primary text-white',
                                'Ready for Pickup' => 'badge-ready',
                                'Completed' => 'badge-completed',
                                'Rejected' => 'badge-rejected',
                                default => 'bg-secondary text-white'
                            };
                            $fee_val = floatval($row['fee'] ?? 0);
                        ?>
                            <tr>
                                <td class="ps-3">
                                    <code class="fw-bold text-primary font-monospace"><?php echo sanitize($row['tracking_number']); ?></code>
                                </td>
                                <td>
                                    <div class="fw-medium text-dark"><?php echo sanitize($row['document_name']); ?></div>
                                    <small class="text-muted"><i class="bi bi-clock me-1"></i>Est: <?php echo sanitize($row['processing_time'] ?? '2-3 Business Days'); ?></small>
                                </td>
                                <td>
                                    <?php if ($fee_val > 0): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle fw-semibold">
                                            ₱<?php echo number_format($fee_val, 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">Free</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted" style="max-width: 200px;">
                                    <?php 
                                        $purpose_text = sanitize($row['purpose']);
                                        echo strlen($purpose_text) > 55 ? substr($purpose_text, 0, 55) . '...' : $purpose_text; 
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
                                <td class="pe-3 text-end">
                                    <button type="button" class="btn btn-sm <?php echo ($row['status'] === 'Ready for Pickup') ? 'btn-success fw-bold' : 'btn-outline-primary'; ?>" 
                                            data-bs-toggle="modal" data-bs-target="#claimModal<?php echo $row['id']; ?>">
                                        <?php if ($row['status'] === 'Ready for Pickup'): ?>
                                            <i class="bi bi-box-seam me-1"></i>How to Claim &rarr;
                                        <?php else: ?>
                                            <i class="bi bi-info-circle me-1"></i>Details
                                        <?php endif; ?>
                                    </button>

                                    <!-- Claiming Info Modal -->
                                    <div class="modal fade" id="claimModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-dialog-centered text-start">
                                            <div class="modal-content">
                                                <div class="modal-header <?php echo ($row['status'] === 'Ready for Pickup') ? 'bg-success text-white' : ''; ?>">
                                                    <h5 class="modal-title fs-6 fw-bold">
                                                        <i class="bi bi-file-earmark-check me-1"></i>Request #<?php echo sanitize($row['tracking_number']); ?>
                                                    </h5>
                                                    <button type="button" class="btn-close <?php echo ($row['status'] === 'Ready for Pickup') ? 'btn-close-white' : ''; ?>" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3 d-flex justify-content-between align-items-center pb-2 border-bottom">
                                                        <div>
                                                            <div class="text-muted small">Document Requested</div>
                                                            <strong class="text-dark"><?php echo sanitize($row['document_name']); ?></strong>
                                                        </div>
                                                        <span class="badge <?php echo $status_class; ?> fs-6 px-3 py-2">
                                                            <?php echo sanitize($row['status']); ?>
                                                        </span>
                                                    </div>

                                                    <?php if ($row['status'] === 'Ready for Pickup'): ?>
                                                        <!-- Highlighted Ready for Pickup instructions -->
                                                        <div class="p-3 bg-success-subtle border border-success-subtle rounded-3 mb-3">
                                                            <h6 class="fw-bold text-success mb-2">
                                                                <i class="bi bi-bell-fill me-1"></i>Your document is prepared and ready for pickup!
                                                            </h6>
                                                            <p class="small text-dark mb-0">
                                                                Please proceed to the <strong>school cashier</strong> to pay the applicable document fee, then present your original official receipt and required physical documents at the Registrar Counter.
                                                            </p>
                                                        </div>

                                                        <div class="card mb-3 border-0 bg-light">
                                                            <div class="card-body p-3">
                                                                <div class="fw-bold small text-uppercase text-dark mb-2">
                                                                    <i class="bi bi-cash-stack text-success me-1"></i>Cashier Payment Summary:
                                                                </div>
                                                                <div class="d-flex justify-content-between align-items-center mb-1 small">
                                                                    <span class="text-muted">Total Amount to Pay at Cashier:</span>
                                                                    <strong class="text-dark fs-6">₱<?php echo number_format($fee_val, 2); ?></strong>
                                                                </div>
                                                                <div class="text-muted" style="font-size: 11px;">
                                                                    *Payment is made in person at the campus Cashier's Office. Keep the physical paper receipt!
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="mb-3">
                                                            <div class="fw-bold small text-uppercase text-dark mb-1">
                                                                <i class="bi bi-card-checklist text-primary me-1"></i>Physical Documents to Submit on Claim:
                                                            </div>
                                                            <div class="p-3 bg-white border rounded small text-secondary" style="white-space: pre-line; line-height: 1.5;">
                                                                <?php echo sanitize($row['requirements'] ?: '• Valid Student ID or Government-Issued ID'); ?>
                                                            </div>
                                                        </div>
                                                    <?php else: ?>
                                                        <!-- Non-ready info -->
                                                        <div class="mb-3">
                                                            <span class="text-muted small d-block">Cashier Fee (Pay upon pickup):</span>
                                                            <strong class="text-dark">₱<?php echo number_format($fee_val, 2); ?></strong>
                                                        </div>

                                                        <div class="mb-3">
                                                            <span class="text-muted small d-block">Physical Requirements to Prepare:</span>
                                                            <div class="p-2 bg-light border rounded small mt-1" style="white-space: pre-line;">
                                                                <?php echo sanitize($row['requirements'] ?: '• Valid Student ID or Government ID'); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (!empty($row['remarks'])): ?>
                                                        <div class="mb-3">
                                                            <span class="text-muted small d-block">Registrar Staff Remarks:</span>
                                                            <div class="p-2 bg-light border-start border-3 border-primary rounded small text-dark mt-1">
                                                                <?php echo sanitize($row['remarks']); ?>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="text-muted" style="font-size: 11px;">
                                                        <i class="bi bi-info-circle me-1"></i>Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($row['requested_at'])); ?>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <?php if ($row['status'] === 'Ready for Pickup'): ?>
                                                        <a href="<?php echo url('student/get_queue.php'); ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-ticket-perforated me-1"></i>Get Queue Ticket for Pickup
                                                        </a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
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
