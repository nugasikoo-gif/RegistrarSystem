<?php
/**
 * Staff Dashboard (staff/dashboard.php)
 * 
 * Main hub for Registrar Staff:
 * - Summary statistics (today's queue, serving now, pending document requests)
 * - Quick actions to manage queue and document requests
 * - Live counter queue preview for today
 * - Recent document requests waiting for review
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('staff');

$today = date('Y-m-d');
$staff_name = $_SESSION['full_name'] ?? 'Staff';

// 1. Queue statistics for today
$q_stats_query = "
    SELECT 
        COUNT(*) as total_today,
        SUM(CASE WHEN status = 'Waiting' THEN 1 ELSE 0 END) as total_waiting,
        SUM(CASE WHEN status = 'Serving' THEN 1 ELSE 0 END) as total_serving,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as total_completed,
        SUM(CASE WHEN status = 'Skipped' THEN 1 ELSE 0 END) as total_skipped
    FROM queues 
    WHERE queue_date = ?
";
$stmt = $conn->prepare($q_stats_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$q_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$total_today     = (int)($q_stats['total_today'] ?? 0);
$total_waiting   = (int)($q_stats['total_waiting'] ?? 0);
$total_serving   = (int)($q_stats['total_serving'] ?? 0);
$total_completed = (int)($q_stats['total_completed'] ?? 0);
$total_skipped   = (int)($q_stats['total_skipped'] ?? 0);

// 2. Currently serving ticket number
$stmt = $conn->prepare("
    SELECT queue_number, service_type 
    FROM queues 
    WHERE queue_date = ? AND status = 'Serving' 
    ORDER BY called_at DESC 
    LIMIT 1
");
$stmt->bind_param("s", $today);
$stmt->execute();
$current_serving = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 3. Document requests statistics
$doc_stats_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as total_pending,
        SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as total_processing,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as total_ready
    FROM document_requests
";
$doc_stats_res = $conn->query($doc_stats_query);
$doc_stats = $doc_stats_res ? $doc_stats_res->fetch_assoc() : [];

$pending_requests    = (int)($doc_stats['total_pending'] ?? 0);
$processing_requests = (int)($doc_stats['total_processing'] ?? 0);
$ready_requests      = (int)($doc_stats['total_ready'] ?? 0);

// 4. Recent pending document requests (up to 5)
$recent_requests_query = "
    SELECT dr.id, dr.tracking_number, dr.purpose, dr.status, dr.requested_at,
           u.full_name, u.student_id, dt.document_name
    FROM document_requests dr
    JOIN users u ON dr.user_id = u.id
    JOIN document_types dt ON dr.document_type_id = dt.id
    ORDER BY dr.requested_at DESC
    LIMIT 5
";
$recent_requests = $conn->query($recent_requests_query);

// 5. Active queues for today waiting or serving (up to 5)
$stmt = $conn->prepare("
    SELECT q.id, q.queue_number, q.service_type, q.status, q.created_at, u.full_name, u.student_id
    FROM queues q
    JOIN users u ON q.user_id = u.id
    WHERE q.queue_date = ? AND q.status IN ('Waiting', 'Serving')
    ORDER BY q.id ASC
    LIMIT 5
");
$stmt->bind_param("s", $today);
$stmt->execute();
$active_queue_list = $stmt->get_result();
$stmt->close();

$page_title = 'Staff Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Staff Overview</h3>
            <p class="text-muted small mb-0">Welcome back, <strong><?php echo sanitize($staff_name); ?></strong> &bull; <?php echo date('l, F j, Y'); ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo url('staff/manage_queue.php'); ?>" class="btn btn-primary btn-sm px-3">
                <i class="bi bi-people me-1"></i>Manage Queue Counter
            </a>
            <a href="<?php echo url('staff/manage_requests.php'); ?>" class="btn btn-outline-primary btn-sm px-3">
                <i class="bi bi-file-earmark-check me-1"></i>Manage Requests
            </a>
        </div>
    </div>

    <!-- Quick Stat Cards -->
    <div class="row g-3 mb-4">
        <!-- Now Serving -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%); color: #fff;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-uppercase small fw-semibold opacity-75">Now Serving</span>
                        <i class="bi bi-megaphone fs-4 opacity-75"></i>
                    </div>
                    <div class="display-6 fw-bold mb-1">
                        <?php echo $current_serving ? sanitize($current_serving['queue_number']) : '&mdash;'; ?>
                    </div>
                    <small class="opacity-75">
                        <?php echo $current_serving ? sanitize($current_serving['service_type']) : 'No active ticket'; ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Waiting in Queue -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Waiting in Queue</span>
                        <i class="bi bi-hourglass-split text-warning fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo $total_waiting; ?></div>
                    <small class="text-muted"><?php echo $total_today; ?> total ticket(s) today</small>
                </div>
            </div>
        </div>

        <!-- Pending Document Requests -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-danger">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Pending Requests</span>
                        <i class="bi bi-clock-history text-danger fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo $pending_requests; ?></div>
                    <small class="text-muted"><?php echo $processing_requests; ?> in processing</small>
                </div>
            </div>
        </div>

        <!-- Ready for Pickup -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Ready for Pickup</span>
                        <i class="bi bi-check2-circle text-success fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo $ready_requests; ?></div>
                    <small class="text-muted"><?php echo $total_completed; ?> queue(s) completed today</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="row g-4">
        <!-- Live Queue Panel -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-ticket-perforated text-primary me-2"></i>Active Daily Queue
                    </span>
                    <a href="<?php echo url('staff/manage_queue.php'); ?>" class="btn btn-sm btn-outline-primary">
                        Open Counter &rarr;
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($active_queue_list && $active_queue_list->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr class="small text-muted">
                                        <th class="ps-3">Ticket</th>
                                        <th>Student</th>
                                        <th>Service</th>
                                        <th class="pe-3 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($q = $active_queue_list->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <span class="badge bg-primary font-monospace fs-6"><?php echo sanitize($q['queue_number']); ?></span>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark small"><?php echo sanitize($q['full_name']); ?></div>
                                                <small class="text-muted"><?php echo sanitize($q['student_id'] ?: 'N/A'); ?></small>
                                            </td>
                                            <td class="small text-muted"><?php echo sanitize($q['service_type']); ?></td>
                                            <td class="pe-3 text-end">
                                                <span class="badge badge-<?php echo strtolower($q['status']); ?>">
                                                    <?php echo sanitize($q['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-check2-circle fs-1 text-success d-block mb-2"></i>
                            <p class="mb-0 small">No pending queue tickets waiting at the counter today.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Document Requests Panel -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-folder2-open text-primary me-2"></i>Recent Document Applications
                    </span>
                    <a href="<?php echo url('staff/manage_requests.php'); ?>" class="btn btn-sm btn-outline-primary">
                        Review All &rarr;
                    </a>
                </div>
                <div class="card-body p-0">
                    <?php if ($recent_requests && $recent_requests->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover">
                                <thead class="table-light">
                                    <tr class="small text-muted">
                                        <th class="ps-3">Tracking #</th>
                                        <th>Student / Document</th>
                                        <th class="pe-3 text-end">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($r = $recent_requests->fetch_assoc()): 
                                        $badge_class = match($r['status']) {
                                            'Pending' => 'badge-pending',
                                            'Processing' => 'badge-processing',
                                            'Approved' => 'bg-primary text-white',
                                            'Ready for Pickup' => 'badge-ready',
                                            'Completed' => 'badge-completed',
                                            'Rejected' => 'badge-rejected',
                                            default => 'bg-secondary text-white'
                                        };
                                    ?>
                                        <tr>
                                            <td class="ps-3">
                                                <code class="fw-bold text-primary font-monospace"><?php echo sanitize($r['tracking_number']); ?></code>
                                                <div class="text-muted" style="font-size: 11px;">
                                                    <?php echo date('M d, Y', strtotime($r['requested_at'])); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold text-dark small"><?php echo sanitize($r['full_name']); ?></div>
                                                <small class="text-muted"><?php echo sanitize($r['document_name']); ?></small>
                                            </td>
                                            <td class="pe-3 text-end">
                                                <span class="badge <?php echo $badge_class; ?>">
                                                    <?php echo sanitize($r['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-muted"></i>
                            <p class="mb-0 small">No document requests found.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

