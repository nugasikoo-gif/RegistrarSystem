<?php
/**
 * Request Document (student/request_document.php)
 * 
 * Allows students to submit a request for academic documents.
 * Generates a unique tracking number (e.g., REQ-20261021-001)
 * and creates an initial notification.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id = $_SESSION['user_id'];
$error   = '';

// 1. Fetch all active document types for the dropdown
$doc_types_result = $conn->query("
    SELECT id, document_name, description 
    FROM document_types 
    WHERE is_active = 1 
    ORDER BY document_name ASC
");

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
            $check_stmt = $conn->prepare("SELECT document_name FROM document_types WHERE id = ? AND is_active = 1");
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
                    
                    // Create notification for student
                    $notif_msg = "Your request for {$document_name} has been submitted. Tracking Number: {$tracking_number}";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notif_stmt->bind_param("is", $user_id, $notif_msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();
                    
                    $conn->commit();
                    
                    setFlashMessage('success', "Document request submitted successfully! Your tracking number is {$tracking_number}.");
                    header("Location: my_requests.php");
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
    <div class="col-lg-8">
        <!-- Back Navigation -->
        <div class="mb-3">
            <a href="dashboard.php" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-1"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Request Academic Document</h5>
                <small class="text-muted">Fill out the details below to submit a document application to the Registrar</small>
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
                    <div class="mb-3">
                        <label for="document_type_id" class="form-label">Select Document Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="document_type_id" name="document_type_id" required>
                            <option value="">-- Choose a document --</option>
                            <?php while ($doc = $doc_types_result->fetch_assoc()): ?>
                                <option value="<?php echo $doc['id']; ?>" <?php echo (isset($_POST['document_type_id']) && $_POST['document_type_id'] == $doc['id']) ? 'selected' : ''; ?>>
                                    <?php echo sanitize($doc['document_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <div class="form-text">Choose the official academic document you need.</div>
                    </div>

                    <!-- Purpose Textarea -->
                    <div class="mb-4">
                        <label for="purpose" class="form-label">Purpose of Request <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="4" 
                                  placeholder="e.g. Scholarship application, employment requirement, board examination, transfer credentials..." required><?php echo sanitize($_POST['purpose'] ?? ''); ?></textarea>
                        <div class="form-text">Clearly state why you need this document so staff can evaluate your request.</div>
                    </div>

                    <!-- Important Notice -->
                    <div class="alert alert-info py-2 small mb-4">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Note:</strong> Standard document processing takes 2 to 3 working days. Once your document status changes to <em>"Ready for Pickup"</em>, generate a queue ticket to claim it at the Registrar's Office.
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-send me-1"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

