<?php
/**
 * Student Dashboard (student/dashboard.php)
 * 
 * Main hub for logged-in students with SWU/CTU clean aesthetic.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id    = $_SESSION['user_id'];
$full_name  = $_SESSION['full_name'];
$student_id = $_SESSION['student_id'] ?? 'N/A';
$today      = date('Y-m-d');

// 1. Check for student's active queue today (Waiting or Serving)
$stmt = $conn->prepare("
    SELECT queue_number, service_type, status, created_at 
    FROM queues 
    WHERE user_id = ? AND queue_date = ? AND status IN ('Waiting', 'Serving') 
    LIMIT 1
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$active_queue = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Check which queue number is currently being served at the counter
$stmt = $conn->prepare("
    SELECT queue_number 
    FROM queues 
    WHERE queue_date = ? AND status = 'Serving' 
    ORDER BY called_at DESC 
    LIMIT 1
");
$stmt->bind_param("s", $today);
$stmt->execute();
$serving_row = $stmt->get_result()->fetch_assoc();
$now_serving = $serving_row ? $serving_row['queue_number'] : null;
$stmt->close();

// 3. Get student's 5 most recent document requests
$stmt = $conn->prepare("
    SELECT dr.tracking_number, dt.document_name, dr.status, dr.requested_at 
    FROM document_requests dr 
    JOIN document_types dt ON dr.document_type_id = dt.id 
    WHERE dr.user_id = ? 
    ORDER BY dr.requested_at DESC 
    LIMIT 5
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$recent_requests = $stmt->get_result();
$stmt->close();

$page_title = 'Student Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<!-- Clean Campus Hero Header -->
<div class="student-hero-banner">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 position-relative" style="z-index: 1;">
        <div>
            <div class="hero-badge">
                <i class="bi bi-shield-check"></i> CTU Student Portal
            </div>
            <h2>Welcome, <?php echo sanitize($full_name); ?></h2>
            <div class="meta-info">
                <span><i class="bi bi-person-badge me-1"></i>Student ID: <strong><?php echo sanitize($student_id); ?></strong></span>
                <span class="mx-2 opacity-50">&bull;</span>
                <span><i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?></span>
                <span class="mx-2 opacity-50">&bull;</span>
                <span><i class="bi bi-geo-alt me-1"></i>Daanbantayan Campus</span>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo url('student/request_document.php'); ?>" class="btn btn-hero-action px-3 py-2">
                <i class="bi bi-file-earmark-plus me-1"></i>Request Document
            </a>
            <a href="<?php echo url('student/get_queue.php'); ?>" class="btn btn-hero-outline px-3 py-2">
                <i class="bi bi-ticket-perforated me-1"></i>Get Queue Ticket
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Active Queue Card -->
    <div class="col-lg-5">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="fw-semibold text-dark d-flex align-items-center">
                    <i class="bi bi-ticket-perforated text-primary me-2 fs-5"></i>Daily Queue Ticket
                </div>
                <?php if ($active_queue): ?>
                    <span class="badge badge-<?php echo strtolower($active_queue['status']); ?>">
                        <?php echo sanitize($active_queue['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                <?php if ($active_queue): ?>
                    <div class="queue-ticket-box mb-3">
                        <div class="queue-ticket-title">Your Active Ticket</div>
                        <div class="queue-big-number">
                            <?php echo sanitize($active_queue['queue_number']); ?>
                        </div>
                        <div class="text-muted small">
                            <i class="bi bi-tag me-1"></i>Service: <strong><?php echo sanitize($active_queue['service_type']); ?></strong>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="serving-display-pill">
                            <span class="small text-uppercase text-light opacity-75">Serving Now:</span>
                            <span class="serving-number-text">
                                <?php echo $now_serving ? sanitize($now_serving) : '&mdash;'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <a href="<?php echo url('student/my_queue.php'); ?>" class="btn btn-outline-primary btn-sm mx-auto">
                        <i class="bi bi-broadcast me-1"></i>Live Queue Details &rarr;
                    </a>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="d-inline-flex p-3 bg-light text-primary rounded-circle mb-3">
                            <i class="bi bi-ticket-perforated fs-1"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">No Active Queue Ticket</h6>
                        <p class="text-muted small mb-3">Visiting the campus Registrar today? Generate your daily queue ticket online to monitor your turn.</p>
                        <a href="<?php echo url('student/get_queue.php'); ?>" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-plus-lg me-1"></i>Get Queue Number
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Document Requests -->
    <div class="col-lg-7">
        <div class="card h-100 shadow-sm border">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div class="fw-semibold text-dark d-flex align-items-center">
                    <i class="bi bi-folder2-open text-primary me-2 fs-5"></i>Recent Document Applications
                </div>
                <a href="<?php echo url('student/my_requests.php'); ?>" class="small text-decoration-none fw-medium text-primary">
                    View All <i class="bi bi-arrow-right"></i>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_requests->num_rows > 0): ?>
                    <div class="table-responsive border-0">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 py-2 text-muted small fw-semibold">Tracking #</th>
                                    <th class="py-2 text-muted small fw-semibold">Document</th>
                                    <th class="py-2 text-muted small fw-semibold">Status</th>
                                    <th class="pe-3 py-2 text-muted small fw-semibold text-end">Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($req = $recent_requests->fetch_assoc()): 
                                    $status_class = match($req['status']) {
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
                                            <code class="fw-semibold text-primary font-monospace"><?php echo sanitize($req['tracking_number']); ?></code>
                                        </td>
                                        <td class="fw-medium text-dark"><?php echo sanitize($req['document_name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo sanitize($req['status']); ?>
                                            </span>
                                        </td>
                                        <td class="pe-3 text-muted small text-end">
                                            <?php echo date('M d, Y', strtotime($req['requested_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="d-inline-flex p-3 bg-light text-primary rounded-circle mb-3">
                            <i class="bi bi-folder-plus fs-1"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">No Document Requests Yet</h6>
                        <p class="text-muted small mb-3">You have not submitted any academic document requests yet.</p>
                        <a href="<?php echo url('student/request_document.php'); ?>" class="btn btn-primary btn-sm px-3">
                            <i class="bi bi-plus-lg me-1"></i>Submit a Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
