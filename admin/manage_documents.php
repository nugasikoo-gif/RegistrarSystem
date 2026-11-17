<?php
/**
 * Admin - Manage Document Types (admin/manage_documents.php)
 * 
 * Allows Administrators to:
 * - View all document types, fees, processing time, physical requirements & instructions
 * - Add new document types with fees and requirements
 * - Edit existing document type titles, descriptions, fees, processing time, requirements & instructions
 * - Toggle active/inactive state (hides or shows in student request catalog)
 * - Safe delete (only allowed if no student requests are linked, or warns about cascade)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/check_migration.php';

requireRole('admin');

$action_msg = '';
$action_type = '';

// Handle Actions (Add, Edit, Toggle, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_msg = 'Invalid security token. Please try again.';
        $action_type = 'danger';
    } else {
        $action = trim($_POST['action'] ?? '');

        // 1. ADD DOCUMENT TYPE
        if ($action === 'add_document') {
            $doc_name        = trim($_POST['document_name'] ?? '');
            $doc_desc        = trim($_POST['description'] ?? '');
            $fee             = floatval($_POST['fee'] ?? 0.00);
            $processing_time = trim($_POST['processing_time'] ?? '2-3 Business Days');
            $requirements    = trim($_POST['requirements'] ?? '');
            $instructions    = trim($_POST['instructions'] ?? '');
            $is_active       = isset($_POST['is_active']) ? 1 : 0;

            if (empty($doc_name)) {
                $action_msg = 'Document title is required.';
                $action_type = 'danger';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO document_types (document_name, description, fee, processing_time, requirements, instructions, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param("ssdsssi", $doc_name, $doc_desc, $fee, $processing_time, $requirements, $instructions, $is_active);
                if ($stmt->execute()) {
                    $action_msg = "Document type <strong>" . sanitize($doc_name) . "</strong> created successfully!";
                    $action_type = 'success';
                } else {
                    $action_msg = 'Failed to create document type.';
                    $action_type = 'danger';
                }
                $stmt->close();
            }

        // 2. EDIT DOCUMENT TYPE
        } elseif ($action === 'edit_document') {
            $doc_id          = (int)($_POST['document_id'] ?? 0);
            $doc_name        = trim($_POST['document_name'] ?? '');
            $doc_desc        = trim($_POST['description'] ?? '');
            $fee             = floatval($_POST['fee'] ?? 0.00);
            $processing_time = trim($_POST['processing_time'] ?? '2-3 Business Days');
            $requirements    = trim($_POST['requirements'] ?? '');
            $instructions    = trim($_POST['instructions'] ?? '');
            $is_active       = isset($_POST['is_active']) ? 1 : 0;

            if ($doc_id <= 0 || empty($doc_name)) {
                $action_msg = 'Valid document ID and title are required.';
                $action_type = 'danger';
            } else {
                $stmt = $conn->prepare("
                    UPDATE document_types 
                    SET document_name = ?, description = ?, fee = ?, processing_time = ?, requirements = ?, instructions = ?, is_active = ? 
                    WHERE id = ?
                ");
                $stmt->bind_param("ssdsssii", $doc_name, $doc_desc, $fee, $processing_time, $requirements, $instructions, $is_active, $doc_id);
                if ($stmt->execute()) {
                    $action_msg = "Document type <strong>" . sanitize($doc_name) . "</strong> updated successfully!";
                    $action_type = 'success';
                } else {
                    $action_msg = 'Failed to update document type.';
                    $action_type = 'danger';
                }
                $stmt->close();
            }

        // 3. TOGGLE ACTIVE STATUS
        } elseif ($action === 'toggle_status') {
            $doc_id = (int)($_POST['document_id'] ?? 0);
            $new_status = (int)($_POST['new_status'] ?? 0);

            if ($doc_id > 0) {
                $stmt = $conn->prepare("UPDATE document_types SET is_active = ? WHERE id = ?");
                $stmt->bind_param("ii", $new_status, $doc_id);
                if ($stmt->execute()) {
                    $action_msg = 'Document availability updated.';
                    $action_type = 'success';
                } else {
                    $action_msg = 'Failed to toggle document status.';
                    $action_type = 'danger';
                }
                $stmt->close();
            }

        // 4. DELETE DOCUMENT TYPE
        } elseif ($action === 'delete_document') {
            $doc_id = (int)($_POST['document_id'] ?? 0);
            if ($doc_id > 0) {
                $chk = $conn->prepare("SELECT COUNT(*) as cnt FROM document_requests WHERE document_type_id = ?");
                $chk->bind_param("i", $doc_id);
                $chk->execute();
                $req_count = $chk->get_result()->fetch_assoc()['cnt'] ?? 0;
                $chk->close();

                if ($req_count > 0) {
                    $action_msg = "Cannot delete: There are <strong>{$req_count}</strong> existing student request(s) linked to this document type. Deactivate it instead so students cannot select it.";
                    $action_type = 'warning';
                } else {
                    $del_stmt = $conn->prepare("DELETE FROM document_types WHERE id = ?");
                    $del_stmt->bind_param("i", $doc_id);
                    if ($del_stmt->execute()) {
                        $action_msg = 'Document type removed.';
                        $action_type = 'success';
                    } else {
                        $action_msg = 'Failed to delete document type.';
                        $action_type = 'danger';
                    }
                    $del_stmt->close();
                }
            }
        }
    }
}

// Fetch all document types with count of submitted requests
$query = "
    SELECT dt.*, COUNT(dr.id) as request_count
    FROM document_types dt
    LEFT JOIN document_requests dr ON dt.id = dr.document_type_id
    GROUP BY dt.id
    ORDER BY dt.id ASC
";
$doc_types = $conn->query($query);

$csrf_token = generateCSRFToken();
$page_title = 'Manage Document Types';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Document Types & Requirements</h3>
            <p class="text-muted small mb-0">Configure available credentials, official cashier fees, estimated turnaround time, and physical claiming requirements.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addDocModal">
                <i class="bi bi-plus-circle-fill me-1"></i>Add Document Type
            </button>
        </div>
    </div>

    <?php if (!empty($action_msg)): ?>
        <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div><?php echo $action_msg; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Document Types Card -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-files me-2 text-primary"></i>Configured Document Types</h6>
            <small class="text-muted"><?php echo $doc_types->num_rows; ?> total type(s)</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th class="ps-3">Document Name</th>
                            <th>Cashier Fee</th>
                            <th>Est. Time</th>
                            <th>Physical Requirements (To present on claim)</th>
                            <th>Usage</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($doc_types && $doc_types->num_rows > 0): ?>
                            <?php while ($d = $doc_types->fetch_assoc()): 
                                $fee_val = floatval($d['fee'] ?? 0);
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?php echo sanitize($d['document_name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($d['description'] ?: 'No general description.'); ?></small>
                                    </td>
                                    <td>
                                        <?php if ($fee_val > 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold fs-6">
                                                ₱<?php echo number_format($fee_val, 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Free</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted text-nowrap">
                                        <i class="bi bi-clock me-1 text-primary"></i><?php echo sanitize($d['processing_time'] ?? '2-3 Business Days'); ?>
                                    </td>
                                    <td class="small text-secondary" style="max-width: 280px;">
                                        <?php if (!empty($d['requirements'])): ?>
                                            <div class="small" style="white-space: pre-line; line-height: 1.35; max-height: 80px; overflow-y: auto;">
                                                <?php echo sanitize($d['requirements']); ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fst-italic">None required</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <?php echo (int)$d['request_count']; ?> request(s)
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($d['is_active']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-check-circle me-1"></i>Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">
                                                <i class="bi bi-eye-slash me-1"></i>Hidden
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <!-- Toggle Status -->
                                        <form method="POST" action="" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                            <input type="hidden" name="action" value="toggle_status">
                                            <input type="hidden" name="document_id" value="<?php echo $d['id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $d['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary me-1" title="<?php echo $d['is_active'] ? 'Deactivate (Hide)' : 'Activate (Show)'; ?>">
                                                <i class="bi <?php echo $d['is_active'] ? 'bi-toggle-on text-success' : 'bi-toggle-off text-muted'; ?> fs-6"></i>
                                            </button>
                                        </form>

                                        <!-- Edit Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editDocModal<?php echo $d['id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete Trigger -->
                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Delete this document type?');">
                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                            <input type="hidden" name="action" value="delete_document">
                                            <input type="hidden" name="document_id" value="<?php echo $d['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editDocModal<?php echo $d['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered text-start modal-lg">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold">Edit Document Type</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="edit_document">
                                                            <input type="hidden" name="document_id" value="<?php echo $d['id']; ?>">

                                                            <div class="row g-3 mb-3">
                                                                <div class="col-md-8">
                                                                    <label class="form-label small fw-semibold">Document Name *</label>
                                                                    <input type="text" name="document_name" class="form-control form-control-sm" value="<?php echo sanitize($d['document_name']); ?>" required>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label small fw-semibold">Cashier Fee (₱) *</label>
                                                                    <input type="number" step="0.01" min="0" name="fee" class="form-control form-control-sm" value="<?php echo number_format($fee_val, 2, '.', ''); ?>" required>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Estimated Processing Time</label>
                                                                <input type="text" name="processing_time" class="form-control form-control-sm" value="<?php echo sanitize($d['processing_time'] ?? '2-3 Business Days'); ?>" placeholder="e.g. 2-3 Working Days">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Description</label>
                                                                <textarea name="description" class="form-control form-control-sm" rows="2"><?php echo sanitize($d['description'] ?? ''); ?></textarea>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Required Physical Documents (To present when claiming)</label>
                                                                <textarea name="requirements" class="form-control form-control-sm" rows="3" placeholder="List items one per line, e.g.:&#10;• Valid Student ID&#10;• 2x2 Picture&#10;• Clearance Form"><?php echo sanitize($d['requirements'] ?? ''); ?></textarea>
                                                                <div class="form-text small">Displayed to students prior to submission and on Ready for Pickup notices.</div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Additional Claiming Instructions (Optional)</label>
                                                                <textarea name="instructions" class="form-control form-control-sm" rows="2" placeholder="e.g. Must be claimed by student in person or with notarized authorization letter."><?php echo sanitize($d['instructions'] ?? ''); ?></textarea>
                                                            </div>

                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" name="is_active" id="editActive<?php echo $d['id']; ?>" <?php echo $d['is_active'] ? 'checked' : ''; ?>>
                                                                <label class="form-check-label small" for="editActive<?php echo $d['id']; ?>">Active (Visible to students on request catalog)</label>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No document types configured yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Document Modal -->
<div class="modal fade" id="addDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Add New Document Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="action" value="add_document">

                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Document Name *</label>
                            <input type="text" name="document_name" class="form-control form-control-sm" placeholder="e.g. Certificate of Graduation" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Cashier Fee (₱) *</label>
                            <input type="number" step="0.01" min="0" name="fee" class="form-control form-control-sm" value="0.00" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Estimated Processing Time</label>
                        <input type="text" name="processing_time" class="form-control form-control-sm" placeholder="e.g. 2-3 Working Days" value="2-3 Business Days">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Description</label>
                        <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Brief explanation of this document..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Required Physical Documents (To present when claiming)</label>
                        <textarea name="requirements" class="form-control form-control-sm" rows="3" placeholder="List items one per line, e.g.:&#10;• Valid Student ID&#10;• 2x2 Picture&#10;• Clearance Form"></textarea>
                        <div class="form-text small">Notice: Students will NOT be asked to upload these. They will bring them when claiming the document.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Additional Claiming Instructions (Optional)</label>
                        <textarea name="instructions" class="form-control form-control-sm" rows="2" placeholder="e.g. Please bring dry seal authorization or official receipt upon claiming."></textarea>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="addActive" checked>
                        <label class="form-check-label small" for="addActive">Active (Visible to students immediately)</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Create Document Type</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
