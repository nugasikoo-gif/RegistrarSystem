<?php
/**
 * Admin - Reports (admin/reports.php)
 * 
 * Allows Administrators to:
 * - Filter and inspect queue transactions by date range & service type
 * - Filter and inspect document requests by date range, document type & status
 * - Export / Print ready view for registrar audits and reporting
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

// Defaults: current month date range
$start_date = trim($_GET['start_date'] ?? date('Y-m-01'));
$end_date   = trim($_GET['end_date'] ?? date('Y-m-d'));
$tab        = trim($_GET['tab'] ?? 'requests'); // 'requests' or 'queues'

// 1. Fetch distinct document types for filters
$doc_types_list = $conn->query("SELECT id, document_name FROM document_types ORDER BY document_name ASC");

// 2. Tab: Document Requests Report
$req_status_filter = trim($_GET['req_status'] ?? '');
$req_doc_filter    = (int)($_GET['doc_type_id'] ?? 0);

$req_sql = "
    SELECT dr.*, u.full_name, u.student_id, u.email, dt.document_name
    FROM document_requests dr
    JOIN users u ON dr.user_id = u.id
    JOIN document_types dt ON dr.document_type_id = dt.id
    WHERE DATE(dr.requested_at) BETWEEN ? AND ?
";
$req_params = [$start_date, $end_date];
$req_types  = "ss";

if (!empty($req_status_filter)) {
    $req_sql .= " AND dr.status = ?";
    $req_params[] = $req_status_filter;
    $req_types .= "s";
}
if ($req_doc_filter > 0) {
    $req_sql .= " AND dr.document_type_id = ?";
    $req_params[] = $req_doc_filter;
    $req_types .= "i";
}
$req_sql .= " ORDER BY dr.requested_at DESC";

$stmt = $conn->prepare($req_sql);
$stmt->bind_param($req_types, ...$req_params);
$stmt->execute();
$requests_report = $stmt->get_result();
$stmt->close();

// 3. Tab: Queue Logs Report
$queue_status_filter = trim($_GET['queue_status'] ?? '');
$queue_service_filter = trim($_GET['service_type'] ?? '');

$q_sql = "
    SELECT q.*, u.full_name, u.student_id, u.email
    FROM queues q
    JOIN users u ON q.user_id = u.id
    WHERE q.queue_date BETWEEN ? AND ?
";
$q_params = [$start_date, $end_date];
$q_types  = "ss";

if (!empty($queue_status_filter)) {
    $q_sql .= " AND q.status = ?";
    $q_params[] = $queue_status_filter;
    $q_types .= "s";
}
if (!empty($queue_service_filter)) {
    $q_sql .= " AND q.service_type = ?";
    $q_params[] = $queue_service_filter;
    $q_types .= "s";
}
$q_sql .= " ORDER BY q.queue_date DESC, q.id DESC";

$stmt = $conn->prepare($q_sql);
$stmt->bind_param($q_types, ...$q_params);
$stmt->execute();
$queues_report = $stmt->get_result();
$stmt->close();

$page_title = 'System Reports';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Operational Reports</h3>
            <p class="text-muted small mb-0">Audit and export transaction histories across student document applications and counter queue tickets.</p>
        </div>
        <div>
            <button onclick="window.print();" class="btn btn-outline-secondary btn-sm px-3">
                <i class="bi bi-printer me-1"></i>Print Report
            </button>
        </div>
    </div>

    <!-- Date Range & Tabs Form -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-end">
                <input type="hidden" name="tab" value="<?php echo sanitize($tab); ?>">

                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">From Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?php echo sanitize($start_date); ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">To Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?php echo sanitize($end_date); ?>" required>
                </div>

                <?php if ($tab === 'requests'): ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Status</label>
                        <select name="req_status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="Pending" <?php echo $req_status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="Processing" <?php echo $req_status_filter === 'Processing' ? 'selected' : ''; ?>>Processing</option>
                            <option value="Approved" <?php echo $req_status_filter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="Ready for Pickup" <?php echo $req_status_filter === 'Ready for Pickup' ? 'selected' : ''; ?>>Ready for Pickup</option>
                            <option value="Completed" <?php echo $req_status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Rejected" <?php echo $req_status_filter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                <?php else: ?>
                    <div class="col-md-3">
                        <label class="form-label small fw-semibold text-muted mb-1">Queue Status</label>
                        <select name="queue_status" class="form-select form-select-sm">
                            <option value="">All Statuses</option>
                            <option value="Waiting" <?php echo $queue_status_filter === 'Waiting' ? 'selected' : ''; ?>>Waiting</option>
                            <option value="Serving" <?php echo $queue_status_filter === 'Serving' ? 'selected' : ''; ?>>Serving</option>
                            <option value="Completed" <?php echo $queue_status_filter === 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            <option value="Skipped" <?php echo $queue_status_filter === 'Skipped' ? 'selected' : ''; ?>>Skipped</option>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Generate Filter</button>
                    <a href="<?php echo url('admin/reports.php?tab=' . $tab); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-tabs mb-3 border-bottom">
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'requests' ? 'active fw-bold text-primary' : 'text-muted'; ?>" href="<?php echo url('admin/reports.php?tab=requests&start_date=' . $start_date . '&end_date=' . $end_date); ?>">
                <i class="bi bi-folder-check me-1"></i>Document Requests Log (<?php echo $requests_report->num_rows; ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo $tab === 'queues' ? 'active fw-bold text-primary' : 'text-muted'; ?>" href="<?php echo url('admin/reports.php?tab=queues&start_date=' . $start_date . '&end_date=' . $end_date); ?>">
                <i class="bi bi-ticket-perforated me-1"></i>Queue Tickets Log (<?php echo $queues_report->num_rows; ?>)
            </a>
        </li>
    </ul>

    <!-- Report Tables -->
    <?php if ($tab === 'requests'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">Document Request Transactions</h6>
                <span class="text-muted small">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> &ndash; <?php echo date('M d, Y', strtotime($end_date)); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th class="ps-3">Tracking #</th>
                                <th>Student</th>
                                <th>Document</th>
                                <th>Purpose</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th class="pe-3">Staff Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($requests_report && $requests_report->num_rows > 0): ?>
                                <?php while ($r = $requests_report->fetch_assoc()): 
                                    $badge_class = match($r['status']) {
                                        'Pending' => 'bg-warning text-dark',
                                        'Processing' => 'bg-info text-dark',
                                        'Approved' => 'bg-primary text-white',
                                        'Ready for Pickup' => 'bg-success text-white',
                                        'Completed' => 'bg-secondary text-white',
                                        'Rejected' => 'bg-danger text-white',
                                        default => 'bg-light text-dark'
                                    };
                                ?>
                                    <tr>
                                        <td class="ps-3">
                                            <code class="fw-bold text-primary font-monospace"><?php echo sanitize($r['tracking_number']); ?></code>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark small"><?php echo sanitize($r['full_name']); ?></div>
                                            <small class="text-muted"><?php echo sanitize($r['student_id'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td class="small fw-medium text-dark"><?php echo sanitize($r['document_name']); ?></td>
                                        <td class="small text-muted" style="max-width: 200px;"><?php echo sanitize($r['purpose']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $badge_class; ?> px-2 py-1">
                                                <?php echo sanitize($r['status']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo date('M d, Y h:i A', strtotime($r['requested_at'])); ?>
                                        </td>
                                        <td class="pe-3 small text-muted">
                                            <?php echo sanitize($r['remarks'] ?: '&mdash;'); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No document requests found in the selected date range.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark">Registrar Counter Queue Records</h6>
                <span class="text-muted small">Period: <?php echo date('M d, Y', strtotime($start_date)); ?> &ndash; <?php echo date('M d, Y', strtotime($end_date)); ?></span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-muted">
                            <tr>
                                <th class="ps-3">Date</th>
                                <th>Ticket #</th>
                                <th>Student</th>
                                <th>Service Type</th>
                                <th>Status</th>
                                <th>Called At</th>
                                <th class="pe-3">Completed At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($queues_report && $queues_report->num_rows > 0): ?>
                                <?php while ($q = $queues_report->fetch_assoc()): 
                                    $q_badge = match($q['status']) {
                                        'Waiting' => 'bg-warning text-dark',
                                        'Serving' => 'bg-primary text-white',
                                        'Completed' => 'bg-success text-white',
                                        'Skipped' => 'bg-danger text-white',
                                        default => 'bg-secondary text-white'
                                    };
                                ?>
                                    <tr>
                                        <td class="ps-3 small text-dark fw-semibold">
                                            <?php echo date('M d, Y', strtotime($q['queue_date'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-dark font-monospace fs-6"><?php echo sanitize($q['queue_number']); ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark small"><?php echo sanitize($q['full_name']); ?></div>
                                            <small class="text-muted"><?php echo sanitize($q['student_id'] ?: 'N/A'); ?></small>
                                        </td>
                                        <td class="small text-dark"><?php echo sanitize($q['service_type']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $q_badge; ?> px-2 py-1">
                                                <?php echo sanitize($q['status']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo $q['called_at'] ? date('h:i A', strtotime($q['called_at'])) : '&mdash;'; ?>
                                        </td>
                                        <td class="pe-3 small text-muted">
                                            <?php echo $q['completed_at'] ? date('h:i A', strtotime($q['completed_at'])) : '&mdash;'; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">No queue logs found in the selected date range.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

