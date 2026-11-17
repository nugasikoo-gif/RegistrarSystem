<?php
/**
 * Request Document (student/request_document.php)
 * 
 * Allows students to browse available registrar documents,
 * inspect cashier fees, estimated turnaround time, required physical documents,
 * and submit an application without upfront payment or digital upload requirements.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/check_migration.php';

requireRole('student');

$user_id = $_SESSION['user_id'];
$error   = '';

// 1. Fetch all active document types including fee and requirements
$doc_types_result = $conn->query("
    SELECT id, document_name, description, fee, processing_time, requirements, instructions 
    FROM document_types 
    WHERE is_active = 1 
    ORDER BY document_name ASC
");

// Pre-load as JSON for reactive dynamic document preview in JavaScript
$docs_catalog = [];
while ($row = $doc_types_result->fetch_assoc()) {
    $docs_catalog[$row['id']] = $row;
}
// Rewind for PHP select loop
$doc_types_result->data_seek(0);

// Preselected document from catalog if passed in query string (?doc=2)
$preselect_id = (int)($_GET['doc'] ?? 0);

// 2. Handle request form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF security check
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid submission token. Please reload and try again.';
    } else {
        $doc_type_id = (int)($_POST['document_type_id'] ?? 0);
        $purpose     = trim($_POST['purpose'] ?? '');

        // Input validation
        if ($doc_type_id <= 0 || empty($purpose)) {
            $error = 'Please select a document type and provide the purpose of your request.';
        } else {
            // Verify document type exists and is active
            $check_stmt = $conn->prepare("SELECT document_name, fee FROM document_types WHERE id = ? AND is_active = 1");
            $check_stmt->bind_param("i", $doc_type_id);
            $check_stmt->execute();
            $doc_res = $check_stmt->get_result();

            if ($doc_res->num_rows !== 1) {
                $error = 'The selected document type is invalid or currently unavailable.';
                $check_stmt->close();
            } else {
                $doc_info = $doc_res->fetch_assoc();
                $document_name = $doc_info['document_name'];
                $check_stmt->close();

                // Begin transaction to safely generate tracking number without collision
                $conn->begin_transaction();
                try {
                    $date_prefix = 'REQ-' . date('Ymd') . '-';

                    // Count how many requests were made today to determine next sequence number
                    $count_stmt = $conn->prepare("
                        SELECT COUNT(*) as total 
                        FROM document_requests 
                        WHERE tracking_number LIKE CONCAT(?, '%') FOR UPDATE
                    ");
                    $count_stmt->bind_param("s", $date_prefix);
                    $count_stmt->execute();
                    $seq_row = $count_stmt->get_result()->fetch_assoc();
                    $next_num = ($seq_row['total'] ?? 0) + 1;
                    $count_stmt->close();

                    // Format sequence as 3-digit padded number (e.g. 001, 002)
                    $tracking_number = $date_prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);

                    // Insert the new document request
                    $status = 'Pending';
                    $insert_stmt = $conn->prepare("
                        INSERT INTO document_requests (user_id, document_type_id, tracking_number, purpose, status) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $insert_stmt->bind_param("iisss", $user_id, $doc_type_id, $tracking_number, $purpose, $status);
                    $insert_stmt->execute();
                    $insert_stmt->close();

                    // Create in-app notification for student
                    $notif_msg = "Your request for {$document_name} has been submitted (Tracking #: {$tracking_number}). You will be notified once it is ready for pickup.";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notif_stmt->bind_param("is", $user_id, $notif_msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();

                    $conn->commit();

                    setFlashMessage('success', "Document request submitted successfully! Your tracking number is {$tracking_number}. Please keep this number for reference.");
                    header("Location: " . url('student/my_requests.php'));
                    exit();
                } catch (Exception $e) {
                    $conn->rollback();
                    $error = 'A database error occurred while submitting your request. Please try again.';
                }
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = 'Request Academic Document';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <!-- Back Navigation -->
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <a href="<?php echo url('student/dashboard.php'); ?>" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
            <a href="<?php echo url('student/my_requests.php'); ?>" class="text-decoration-none small text-primary fw-medium">
                <i class="bi bi-folder2-open me-1"></i>View My Requests
            </a>
        </div>

        <div class="portal-section-header">
            <h3 class="portal-section-title">Request Academic Credentials</h3>
            <p class="text-muted small mb-0">Browse document fees and physical claiming requirements, then submit your application. No upfront payment required.</p>
            <div class="portal-title-bar"></div>
        </div>

        <!-- 4-Step Procedure Banner -->
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3">
                <div class="row g-2 text-center small">
                    <div class="col-md-3 border-end-md">
                        <div class="fw-bold text-primary mb-1"><i class="bi bi-1-circle-fill me-1"></i> 1. Submit Request</div>
                        <span class="text-muted" style="font-size: 11px;">Select document and purpose online. No fee or files required now.</span>
                    </div>
                    <div class="col-md-3 border-end-md">
                        <div class="fw-bold text-primary mb-1"><i class="bi bi-2-circle-fill me-1"></i> 2. Registrar Prepares</div>
                        <span class="text-muted" style="font-size: 11px;">Staff verifies and prepares document to Ready for Pickup.</span>
                    </div>
                    <div class="col-md-3 border-end-md">
                        <div class="fw-bold text-primary mb-1"><i class="bi bi-3-circle-fill me-1"></i> 3. Pay at Cashier</div>
                        <span class="text-muted" style="font-size: 11px;">Pay document fee at the school physical cashier and get official receipt.</span>
                    </div>
                    <div class="col-md-3">
                        <div class="fw-bold text-success mb-1"><i class="bi bi-4-circle-fill me-1"></i> 4. Claim Document</div>
                        <span class="text-muted" style="font-size: 11px;">Present original receipt & physical requirements at Registrar.</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Left: Application Form -->
            <div class="col-lg-7">
                <div class="card shadow-sm border h-100">
                    <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                        <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" style="width: 28px; height: 28px; object-fit: contain;">
                        <span class="fw-semibold text-dark">Document Application Form</span>
                    </div>

                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger py-2 small d-flex align-items-center mb-4">
                                <i class="bi bi-exclamation-circle-fill me-2"></i>
                                <div><?php echo sanitize($error); ?></div>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">

                            <!-- Document Type Dropdown -->
                            <div class="mb-4">
                                <label for="document_type_id" class="form-label fw-semibold">
                                    Select Document Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select py-2" id="document_type_id" name="document_type_id" required>
                                    <option value="">-- Choose an official academic document --</option>
                                    <?php while ($doc = $doc_types_result->fetch_assoc()): 
                                        $selected = ($preselect_id > 0 && $preselect_id == $doc['id']) || (isset($_POST['document_type_id']) && $_POST['document_type_id'] == $doc['id']);
                                    ?>
                                        <option value="<?php echo $doc['id']; ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                            <?php echo sanitize($doc['document_name']); ?> 
                                            (₱<?php echo number_format($doc['fee'] ?? 0, 2); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="form-text">Choose a document to inspect its fees, processing time, and physical requirements.</div>
                            </div>

                            <!-- Purpose Textarea -->
                            <div class="mb-4">
                                <label for="purpose" class="form-label fw-semibold">
                                    Purpose of Request <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="purpose" name="purpose" rows="4"
                                    placeholder="e.g. Scholarship application, employment requirement, board exam credentials, transfer records..." required><?php echo sanitize($_POST['purpose'] ?? ''); ?></textarea>
                                <div class="form-text">Clearly describe your purpose for campus records and evaluation.</div>
                            </div>

                            <!-- Guidance Notice Box -->
                            <div class="p-3 bg-light border rounded-3 small mb-4 text-secondary">
                                <div class="fw-semibold text-dark mb-1"><i class="bi bi-info-circle text-primary me-1"></i>Submission Reminder:</div>
                                <p class="mb-0">You <strong>do not need to pay</strong> or submit any physical requirements right now. Your request will be queued for processing immediately upon submitting this form.</p>
                            </div>

                            <!-- Action Buttons -->
                            <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                                <a href="<?php echo url('student/dashboard.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4 fw-semibold">
                                    <i class="bi bi-send me-1"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right: Dynamic Document Preview & Requirements Card -->
            <div class="col-lg-5">
                <div class="card shadow-sm border h-100" id="docDetailsCard" style="display: none;">
                    <div class="card-header bg-primary text-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold small text-uppercase"><i class="bi bi-file-earmark-text me-1"></i>Document Information</span>
                            <span class="badge bg-white text-primary fw-bold" id="detailFee">₱0.00</span>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-1" id="detailTitle">Document Name</h5>
                        <p class="small text-muted mb-3" id="detailDesc">Description</p>

                        <div class="d-flex align-items-center gap-2 mb-3 pb-3 border-bottom text-muted small">
                            <i class="bi bi-clock-history text-primary fs-5"></i>
                            <div>
                                <div class="fw-semibold text-dark" style="font-size: 11px; text-transform: uppercase;">Estimated Turnaround</div>
                                <span id="detailTime">2-3 Business Days</span>
                            </div>
                        </div>

                        <!-- Requirements Checklist -->
                        <div class="mb-3 pb-3 border-bottom">
                            <h6 class="fw-bold text-dark small text-uppercase mb-2">
                                <i class="bi bi-card-checklist text-primary me-1"></i>Physical Requirements to Prepare:
                            </h6>
                            <div class="p-3 bg-light border rounded-3 small text-secondary" id="detailReqs" style="white-space: pre-line; line-height: 1.5;">
                                None specified.
                            </div>
                            <small class="text-muted d-block mt-1" style="font-size: 11px;">
                                <i class="bi bi-exclamation-circle text-warning me-1"></i>Bring the physical copies of these when claiming at the Registrar counter.
                            </small>
                        </div>

                        <!-- Cashier & Claiming Instructions -->
                        <div>
                            <h6 class="fw-bold text-dark small text-uppercase mb-2">
                                <i class="bi bi-cash-coin text-success me-1"></i>Payment & Claiming Procedure:
                            </h6>
                            <div class="small text-secondary" id="detailInstructions">
                                When marked <strong>Ready for Pickup</strong>, proceed to the school cashier to pay the document fee. Present the original official cashier receipt together with your physical requirements at the Registrar window.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Placeholder prompt when nothing is selected -->
                <div class="card shadow-sm border h-100 border-dashed" id="docPromptCard">
                    <div class="card-body p-5 text-center text-muted d-flex flex-column justify-content-center">
                        <i class="bi bi-journal-text fs-1 text-primary opacity-50 mb-2"></i>
                        <h6 class="fw-bold text-dark mb-1">Document Details & Fee Preview</h6>
                        <p class="small mb-0">Select any document from the dropdown to review its applicable cashier fee, required physical credentials, and estimated turnaround time.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Catalog data injected safely from PHP
const catalog = <?php echo json_encode($docs_catalog, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const docSelect = document.getElementById('document_type_id');
    const detailsCard = document.getElementById('docDetailsCard');
    const promptCard = document.getElementById('docPromptCard');

    function updatePreview() {
        const selectedId = docSelect.value;
        if (selectedId && catalog[selectedId]) {
            const item = catalog[selectedId];
            document.getElementById('detailTitle').textContent = item.document_name || '';
            document.getElementById('detailDesc').textContent = item.description || 'No additional description.';
            
            const feeVal = parseFloat(item.fee || 0);
            document.getElementById('detailFee').textContent = feeVal > 0 ? '₱' + feeVal.toFixed(2) : 'Free';
            
            document.getElementById('detailTime').textContent = item.processing_time || '2-3 Business Days';
            document.getElementById('detailReqs').textContent = item.requirements && item.requirements.trim() !== '' ? 
                item.requirements : '• Valid CTU Student ID or Government ID';
            
            if (item.instructions && item.instructions.trim() !== '') {
                document.getElementById('detailInstructions').innerHTML = 
                    '<p class="mb-2">' + item.instructions + '</p>' +
                    '<div class="p-2 bg-light border rounded small"><strong>Cashier Note:</strong> Pay the <strong>₱' + feeVal.toFixed(2) + '</strong> document fee at the campus physical cashier upon pickup. Present the original receipt at the counter.</div>';
            } else {
                document.getElementById('detailInstructions').innerHTML = 
                    'Once notified as <strong>Ready for Pickup</strong>, proceed to the school cashier, pay the applicable fee of <strong>₱' + feeVal.toFixed(2) + '</strong>, and present your original receipt and physical requirements to the Registrar.';
            }

            detailsCard.style.display = 'block';
            promptCard.style.display = 'none';
        } else {
            detailsCard.style.display = 'none';
            promptCard.style.display = 'block';
        }
    }

    docSelect.addEventListener('change', updatePreview);
    // Initial trigger if preselected
    updatePreview();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
