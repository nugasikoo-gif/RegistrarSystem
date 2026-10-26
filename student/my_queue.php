<?php
/**
 * My Queue Status (student/my_queue.php)
 * 
 * Shows the student's live queue ticket for today, the serving counter number,
 * and how many students are ahead in line.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id = $_SESSION['user_id'];
$today   = date('Y-m-d');

// 1. Fetch active queue for today (Waiting or Serving)
$stmt = $conn->prepare("
    SELECT id, queue_number, service_type, status, created_at, called_at 
    FROM queues 
    WHERE user_id = ? AND queue_date = ? AND status IN ('Waiting', 'Serving') 
    LIMIT 1
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$active_queue = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Fetch the queue number currently being served
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

// 3. If student is waiting, calculate how many people are ahead
$people_ahead = 0;
if ($active_queue && $active_queue['status'] === 'Waiting') {
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM queues 
        WHERE queue_date = ? AND status = 'Waiting' AND id < ?
    ");
    $stmt->bind_param("si", $today, $active_queue['id']);
    $stmt->execute();
    $ahead_row = $stmt->get_result()->fetch_assoc();
    $people_ahead = $ahead_row['count'] ?? 0;
    $stmt->close();
}

// 4. Fetch today's completed or skipped tickets for this student
$stmt = $conn->prepare("
    SELECT queue_number, service_type, status, created_at, completed_at 
    FROM queues 
    WHERE user_id = ? AND queue_date = ? AND status IN ('Completed', 'Skipped') 
    ORDER BY id DESC
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$past_queues = $stmt->get_result();
$stmt->close();

$page_title = 'Live Queue Status';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <div class="portal-section-header mb-0">
                    <h3 class="portal-section-title">Live Queue Status</h3>
                    <p class="text-muted small mb-0"><?php echo date('l, F j, Y'); ?> &bull; CTU Registrar's Office Counter</p>
                    <div class="portal-title-bar"></div>
                </div>
            </div>
            <div>
                <button onclick="window.location.reload();" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh Status
                </button>
            </div>
        </div>

        <?php if ($active_queue): ?>
            <!-- Active Queue Card -->
            <div class="card shadow-sm border mb-4">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        <i class="bi bi-broadcast text-primary me-2"></i>Active Serving Ticket
                    </span>
                    <span class="badge badge-<?php echo strtolower($active_queue['status']); ?> px-3 py-2">
                        <?php echo sanitize($active_queue['status']); ?>
                    </span>
                </div>
                
                <div class="card-body p-4 text-center">
                    <div class="row align-items-center g-4 my-2">
                        <!-- Left: Student's Ticket -->
                        <div class="col-md-6 border-end-md">
                            <div class="queue-ticket-box">
                                <div class="queue-ticket-title">Your Number</div>
                                <div class="queue-big-number"><?php echo sanitize($active_queue['queue_number']); ?></div>
                                <div class="text-muted small mb-1">
                                    <i class="bi bi-tag me-1"></i>Service: <strong><?php echo sanitize($active_queue['service_type']); ?></strong>
                                </div>
                                <div class="text-secondary" style="font-size: 0.78rem;">
                                    Generated at <?php echo date('h:i A', strtotime($active_queue['created_at'])); ?>
                                </div>
                            </div>
                        </div>

                        <!-- Right: Live Serving & People Ahead -->
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 border mb-3">
                                <div class="text-muted small text-uppercase fw-semibold mb-1">Now Serving at Counter</div>
                                <div class="queue-big-number" style="color: #0284c7; font-size: 2.8rem;">
                                    <?php echo $now_serving ? sanitize($now_serving) : '&mdash;'; ?>
                                </div>
                            </div>

                            <?php if ($active_queue['status'] === 'Waiting'): ?>
                                <div class="alert alert-info py-2 px-3 small d-flex align-items-center justify-content-center gap-2 mb-0">
                                    <i class="bi bi-people-fill text-primary fs-5"></i>
                                    <div>
                                        <strong><?php echo $people_ahead; ?></strong> 
                                        <?php echo $people_ahead === 1 ? 'person is' : 'people are'; ?> ahead of you
                                    </div>
                                </div>
                            <?php elseif ($active_queue['status'] === 'Serving'): ?>
                                <div class="alert alert-success py-2 px-3 small d-flex align-items-center justify-content-center gap-2 mb-0">
                                    <i class="bi bi-megaphone-fill text-success fs-5"></i>
                                    <div>
                                        <strong>Your ticket is being called!</strong> Please approach Window 1.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- No active queue empty state -->
            <div class="card shadow-sm border mb-4">
                <div class="card-body p-5 text-center">
                    <div class="d-inline-flex p-3 bg-light text-primary rounded-circle mb-3">
                        <i class="bi bi-ticket-perforated fs-1"></i>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">No Active Ticket Today</h5>
                    <p class="text-muted small mb-3">You currently do not have a queue number in line for today's office hours.</p>
                    <a href="<?php echo url('student/get_queue.php'); ?>" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-plus-lg me-1"></i>Get Queue Number
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Today's History Section -->
        <?php if ($past_queues->num_rows > 0): ?>
            <div class="card shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom">
                    <span class="fw-semibold text-dark"><i class="bi bi-clock-history text-secondary me-2"></i>Completed / Resolved Today</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0">
                        <table class="table align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-3 py-2 text-muted small fw-semibold">Ticket #</th>
                                    <th class="py-2 text-muted small fw-semibold">Service</th>
                                    <th class="py-2 text-muted small fw-semibold">Status</th>
                                    <th class="pe-3 py-2 text-muted small fw-semibold text-end">Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pq = $past_queues->fetch_assoc()): ?>
                                    <tr>
                                        <td class="ps-3">
                                            <code class="fw-bold font-monospace text-secondary"><?php echo sanitize($pq['queue_number']); ?></code>
                                        </td>
                                        <td><?php echo sanitize($pq['service_type']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($pq['status']); ?>">
                                                <?php echo sanitize($pq['status']); ?>
                                            </span>
                                        </td>
                                        <td class="pe-3 text-muted small text-end">
                                            <?php echo date('h:i A', strtotime($pq['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
