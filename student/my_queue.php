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

$page_title = 'My Queue Ticket';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h3 class="mb-1">Live Queue Status</h3>
                <p class="text-muted small mb-0"><?php echo date('l, F j, Y'); ?> &bull; Registrar Office Service</p>
            </div>
            <div>
                <button onclick="window.location.reload();" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                </button>
            </div>
        </div>

        <?php if ($active_queue): ?>
            <!-- Active Queue Card -->
            <div class="card mb-4 text-center">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-ticket-perforated text-primary me-2"></i>My Today's Ticket</span>
                    <span class="badge badge-<?php echo strtolower($active_queue['status']); ?>">
                        <?php echo sanitize($active_queue['status']); ?>
                    </span>
                </div>

                <div class="card-body p-4 p-md-5">
                    <?php if ($active_queue['status'] === 'Serving'): ?>
                        <div class="alert alert-success d-inline-flex align-items-center py-2 px-3 mb-3">
                            <i class="bi bi-megaphone-fill me-2 fs-5"></i>
                            <strong>Your ticket is now being served! Please step to the registrar window.</strong>
                        </div>
                    <?php endif; ?>

                    <div class="text-muted small text-uppercase fw-semibold mb-1">Your Number</div>
                    <div class="queue-number-display mb-2">
                        <?php echo sanitize($active_queue['queue_number']); ?>
                    </div>
                    
                    <div class="text-muted small mb-4">
                        <span><i class="bi bi-tag me-1"></i>Service: <strong><?php echo sanitize($active_queue['service_type']); ?></strong></span>
                        <span class="mx-2">&bull;</span>
                        <span><i class="bi bi-clock me-1"></i>Generated at: <?php echo date('h:i A', strtotime($active_queue['created_at'])); ?></span>
                    </div>

                    <!-- Live Queue Stats Split -->
                    <div class="row g-3 justify-content-center mb-2">
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted small">Now Serving Counter</div>
                                <div class="queue-now-serving mt-1">
                                    <?php echo $now_serving ? sanitize($now_serving) : '&mdash;'; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="p-3 bg-light rounded-3 border">
                                <div class="text-muted small">Students Ahead in Line</div>
                                <div class="mt-1 fw-bold fs-2 text-primary">
                                    <?php echo ($active_queue['status'] === 'Serving') ? '0 (You)' : $people_ahead; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <!-- Empty State -->
            <div class="card mb-4 text-center">
                <div class="card-body py-5">
                    <i class="bi bi-ticket text-muted" style="font-size: 3rem;"></i>
                    <h5 class="fw-semibold mt-3 mb-1">No Active Ticket Today</h5>
                    <p class="text-muted small mb-3">You don't have any waiting or serving queue tickets for today.</p>
                    <a href="get_queue.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Get a Queue Number
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Today's Previous Queue History -->
        <?php if ($past_queues->num_rows > 0): ?>
            <div class="card">
                <div class="card-header bg-white">
                    <small class="fw-semibold text-muted text-uppercase">Today's Completed Tickets</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive border-0">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Service</th>
                                    <th>Status</th>
                                    <th>Time Finished</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($pq = $past_queues->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?php echo sanitize($pq['queue_number']); ?></strong></td>
                                        <td class="small"><?php echo sanitize($pq['service_type']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo strtolower($pq['status']); ?>">
                                                <?php echo sanitize($pq['status']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            <?php echo !empty($pq['completed_at']) ? date('h:i A', strtotime($pq['completed_at'])) : '&mdash;'; ?>
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

