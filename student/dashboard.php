<?php
/**
 * Student Dashboard (student/dashboard.php)
 * 
 * Main hub for logged-in students.
 * - Shows student profile summary
 * - Displays active queue status for today
 * - Shows recent document requests
 * - Provides quick action buttons
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Role-based protection: only students can view this page
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

<!-- Welcome Banner -->
<div class="card mb-4">
    <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="text-muted small text-uppercase fw-semibold mb-1">Student Portal</div>
            <h3 class="mb-1">Hello, <?php echo sanitize($full_name); ?></h3>
            <div class="text-muted small">
                <span><i class="bi bi-person-badge me-1"></i>Student ID: <strong><?php echo sanitize($student_id); ?></strong></span>
                <span class="mx-2">&bull;</span>
                <span><i class="bi bi-calendar3 me-1"></i><?php echo date('F d, Y'); ?></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="request_document.php" class="btn btn-primary">
                <i class="bi bi-file-earmark-plus me-1"></i>Request Document
            </a>
            <a href="get_queue.php" class="btn btn-outline-primary">
                <i class="bi bi-ticket-perforated me-1"></i>Get Queue Ticket
            </a>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Active Queue Card -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-ticket-perforated text-primary me-2"></i>My Daily Queue Status</span>
                <?php if ($active_queue): ?>
                    <span class="badge badge-<?php echo strtolower($active_queue['status']); ?>">
                        <?php echo sanitize($active_queue['status']); ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                <?php if ($active_queue): ?>
                    <div class="text-muted small text-uppercase fw-semibold mb-1">Your Queue Number</div>
                    <div class="queue-number-display mb-2">
                        <?php echo sanitize($active_queue['queue_number']); ?>
                    </div>
                    <div class="text-muted small mb-3">
                        <i class="bi bi-tag me-1"></i>Service: <strong><?php echo sanitize($active_queue['service_type']); ?></strong>
                    </div>
                    
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="text-muted small">Counter Currently Serving</div>
                        <div class="queue-now-serving mt-1">
                            <?php echo $now_serving ? sanitize($now_serving) : '&mdash;'; ?>
                        </div>
                    </div>
                    
                    <a href="my_queue.php" class="btn btn-outline-primary btn-sm mx-auto">
                        View Live Queue Details <i class="bi bi-arrow-right ms-1"></i>
                    </a>
                <?php else: ?>
                    <div class="empty-state py-3">
                        <i class="bi bi-ticket text-muted" style="font-size: 2.5rem;"></i>
                        <h6 class="fw-semibold mt-2 mb-1">No Active Queue Ticket</h6>
                        <p class="text-muted small mb-3">Need to visit the Registrar today? Generate a queue number online.</p>
                        <a href="get_queue.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-lg me-1"></i>Get Queue Number
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Document Requests -->
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-folder2-open text-primary me-2"></i>Recent Document Requests</span>
                <a href="my_requests.php" class="small text-decoration-none">View All <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="card-body p-0">
                <?php if ($recent_requests->num_rows > 0): ?>
                    <div class="table-responsive border-0">
                        <table class="table align-middle">
                            <thead>
                                <tr>
                                    <th>Tracking #</th>
                                    <th>Document</th>
                                    <th>Status</th>
                                    <th>Date</th>
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
                                        <td>
                                            <code class="fw-semibold text-primary"><?php echo sanitize($req['tracking_number']); ?></code>
                                        </td>
                                        <td><?php echo sanitize($req['document_name']); ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?>">
                                                <?php echo sanitize($req['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-muted small">
                                            <?php echo date('M d, Y', strtotime($req['requested_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-inbox text-muted"></i>
                        <h6 class="fw-semibold mb-1">No Document Requests Yet</h6>
                        <p class="text-muted small mb-3">You have not submitted any academic document requests.</p>
                        <a href="request_document.php" class="btn btn-primary btn-sm">
                            Submit a Document Request
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

