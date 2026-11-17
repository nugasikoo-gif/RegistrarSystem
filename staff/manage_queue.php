<?php
/**
 * Staff - Manage Queue (staff/manage_queue.php)
 * 
 * Allows registrar staff to operate the live counter queue:
 * - View today's queue list (Waiting, Serving, Completed, Skipped)
 * - Call Next ticket (moves next Waiting to Serving, sets called_at)
 * - Mark Currently Serving as Completed (sets completed_at)
 * - Mark Currently Serving as Skipped
 * - Filter by status and service type
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('staff');

$today = date('Y-m-d');
$staff_name = $_SESSION['full_name'] ?? 'Staff';
$action_message = '';
$action_type = '';

// CSRF check & actions handling
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_message = 'Invalid security token. Please try again.';
        $action_type = 'danger';
    } else {
        $action = trim($_POST['action'] ?? '');
        $queue_id = (int)($_POST['queue_id'] ?? 0);

        if ($action === 'call_next') {
            // Find next waiting ticket for today (earliest created_at/id)
            $conn->begin_transaction();
            try {
                // If there's an existing serving ticket, complete or hold it?
                // Standard registrar counter flow: mark currently serving as completed if auto-advance,
                // or ensure previous serving ticket is completed before next call, or let user complete it.
                // Here we find the next waiting ticket:
                $next_stmt = $conn->prepare("
                    SELECT id, queue_number, user_id, service_type 
                    FROM queues 
                    WHERE queue_date = ? AND status = 'Waiting' 
                    ORDER BY id ASC 
                    LIMIT 1 
                    FOR UPDATE
                ");
                $next_stmt->bind_param("s", $today);
                $next_stmt->execute();
                $next_res = $next_stmt->get_result();
                
                if ($next_res->num_rows === 1) {
                    $next_ticket = $next_res->fetch_assoc();
                    $next_stmt->close();

                    // Optional: automatically complete any currently serving ticket if selected or mark new one serving
                    $call_stmt = $conn->prepare("
                        UPDATE queues 
                        SET status = 'Serving', called_at = NOW() 
                        WHERE id = ?
                    ");
                    $call_stmt->bind_param("i", $next_ticket['id']);
                    $call_stmt->execute();
                    $call_stmt->close();

                    // In-app notification to student
                    $notif_msg = "Your queue ticket " . $next_ticket['queue_number'] . " is now being called at the Registrar Counter!";
                    $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $notif_stmt->bind_param("is", $next_ticket['user_id'], $notif_msg);
                    $notif_stmt->execute();
                    $notif_stmt->close();

                    $conn->commit();
                    $action_message = "Called ticket <strong>" . sanitize($next_ticket['queue_number']) . "</strong> to the counter!";
                    $action_type = 'success';
                } else {
                    $next_stmt->close();
                    $conn->commit();
                    $action_message = 'There are no students currently waiting in queue.';
                    $action_type = 'info';
                }
            } catch (Exception $e) {
                $conn->rollback();
                $action_message = 'Failed to call next ticket: ' . $e->getMessage();
                $action_type = 'danger';
            }

        } elseif ($action === 'complete_ticket' && $queue_id > 0) {
            $stmt = $conn->prepare("
                UPDATE queues 
                SET status = 'Completed', completed_at = NOW() 
                WHERE id = ? AND queue_date = ?
            ");
            $stmt->bind_param("is", $queue_id, $today);
            if ($stmt->execute()) {
                $action_message = 'Ticket marked as <strong>Completed</strong>.';
                $action_type = 'success';
            } else {
                $action_message = 'Failed to complete ticket.';
                $action_type = 'danger';
            }
            $stmt->close();

        } elseif ($action === 'skip_ticket' && $queue_id > 0) {
            $stmt = $conn->prepare("
                UPDATE queues 
                SET status = 'Skipped', completed_at = NOW() 
                WHERE id = ? AND queue_date = ?
            ");
            $stmt->bind_param("is", $queue_id, $today);
            if ($stmt->execute()) {
                $action_message = 'Ticket marked as <strong>Skipped</strong>.';
                $action_type = 'warning';
            } else {
                $action_message = 'Failed to skip ticket.';
                $action_type = 'danger';
            }
            $stmt->close();

        } elseif ($action === 'call_specific' && $queue_id > 0) {
            // Call a specific waiting ticket directly
            $stmt = $conn->prepare("
                UPDATE queues 
                SET status = 'Serving', called_at = NOW() 
                WHERE id = ? AND queue_date = ? AND status = 'Waiting'
            ");
            $stmt->bind_param("is", $queue_id, $today);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                // Also notify
                $info_stmt = $conn->prepare("SELECT queue_number, user_id FROM queues WHERE id = ?");
                $info_stmt->bind_param("i", $queue_id);
                $info_stmt->execute();
                $ticket_info = $info_stmt->get_result()->fetch_assoc();
                $info_stmt->close();

                if ($ticket_info) {
                    $notif_msg = "Your queue ticket " . $ticket_info['queue_number'] . " is now being called at the Registrar Counter!";
                    $n_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                    $n_stmt->bind_param("is", $ticket_info['user_id'], $notif_msg);
                    $n_stmt->execute();
                    $n_stmt->close();
                }

                $action_message = 'Ticket called to counter successfully.';
                $action_type = 'success';
            } else {
                $action_message = 'Ticket could not be called (it may not be in waiting state).';
                $action_type = 'warning';
            }
            $stmt->close();
        }
    }
}

// 1. Fetch Currently Serving Ticket
$serving_stmt = $conn->prepare("
    SELECT q.*, u.full_name, u.student_id, u.email, u.personal_gmail 
    FROM queues q
    JOIN users u ON q.user_id = u.id
    WHERE q.queue_date = ? AND q.status = 'Serving'
    ORDER BY q.called_at DESC
    LIMIT 1
");
$serving_stmt->bind_param("s", $today);
$serving_stmt->execute();
$current_serving = $serving_stmt->get_result()->fetch_assoc();
$serving_stmt->close();

// 2. Queue Summary Counts for Today
$counts_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Waiting' THEN 1 ELSE 0 END) as waiting,
        SUM(CASE WHEN status = 'Serving' THEN 1 ELSE 0 END) as serving,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Skipped' THEN 1 ELSE 0 END) as skipped
    FROM queues 
    WHERE queue_date = ?
");
$counts_stmt->bind_param("s", $today);
$counts_stmt->execute();
$counts = $counts_stmt->get_result()->fetch_assoc();
$counts_stmt->close();

// 3. Filter handling
$filter_status = trim($_GET['status'] ?? '');
$allowed_filters = ['Waiting', 'Serving', 'Completed', 'Skipped'];

$list_sql = "
    SELECT q.*, u.full_name, u.student_id, u.email
    FROM queues q
    JOIN users u ON q.user_id = u.id
    WHERE q.queue_date = ?
";
$params = [$today];
$types = "s";

if (in_array($filter_status, $allowed_filters)) {
    $list_sql .= " AND q.status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

$list_sql .= " ORDER BY CASE q.status 
    WHEN 'Serving' THEN 1 
    WHEN 'Waiting' THEN 2 
    WHEN 'Completed' THEN 3 
    WHEN 'Skipped' THEN 4 
    ELSE 5 END, q.id ASC";

$stmt = $conn->prepare($list_sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$queue_list = $stmt->get_result();
$stmt->close();

$csrf_token = generateCSRFToken();
$page_title = 'Manage Queue Counter';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header banner -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Registrar Queue Counter</h3>
            <p class="text-muted small mb-0">Call next tickets, serve students, and track real-time queue flow for <?php echo date('F d, Y'); ?>.</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.location.reload();" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
            <form method="POST" action="" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                <input type="hidden" name="action" value="call_next">
                <button type="submit" class="btn btn-primary btn-sm px-3 fw-bold" <?php echo ($counts['waiting'] ?? 0) === 0 ? 'disabled' : ''; ?>>
                    <i class="bi bi-megaphone-fill me-1"></i>Call Next Student
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($action_message)): ?>
        <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div><?php echo $action_message; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Serving Card & Stats -->
    <div class="row g-4 mb-4">
        <!-- Now Serving Active Box -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100" style="background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%); color: #fff;">
                <div class="card-body p-4 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-warning text-dark px-3 py-1 text-uppercase fw-bold">Currently at Counter</span>
                            <small class="opacity-75">
                                <?php echo $current_serving ? 'Called at ' . date('h:i A', strtotime($current_serving['called_at'])) : 'Counter Ready'; ?>
                            </small>
                        </div>

                        <?php if ($current_serving): ?>
                            <div class="display-3 fw-bold my-2 font-monospace">
                                <?php echo sanitize($current_serving['queue_number']); ?>
                            </div>
                            <h5 class="fw-semibold mb-1"><?php echo sanitize($current_serving['full_name']); ?></h5>
                            <div class="small opacity-75 mb-3">
                                <span><i class="bi bi-person-badge me-1"></i>ID: <?php echo sanitize($current_serving['student_id'] ?: 'N/A'); ?></span>
                                <span class="mx-2">&bull;</span>
                                <span><i class="bi bi-tag me-1"></i>Service: <?php echo sanitize($current_serving['service_type']); ?></span>
                            </div>
                        <?php else: ?>
                            <div class="py-4 text-center">
                                <i class="bi bi-person-check fs-1 opacity-50 mb-2 d-block"></i>
                                <h4 class="fw-bold mb-1">Counter is Currently Free</h4>
                                <p class="small opacity-75 mb-0">Click <strong>"Call Next Student"</strong> above to summon the next person in line.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($current_serving): ?>
                        <div class="pt-3 border-top border-white-50 d-flex gap-2 flex-wrap">
                            <!-- Complete Ticket -->
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                <input type="hidden" name="action" value="complete_ticket">
                                <input type="hidden" name="queue_id" value="<?php echo $current_serving['id']; ?>">
                                <button type="submit" class="btn btn-success fw-bold px-3">
                                    <i class="bi bi-check-circle-fill me-1"></i>Mark as Completed
                                </button>
                            </form>

                            <!-- Skip Ticket -->
                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Skip this ticket? The student will be marked as Skipped.');">
                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                <input type="hidden" name="action" value="skip_ticket">
                                <input type="hidden" name="queue_id" value="<?php echo $current_serving['id']; ?>">
                                <button type="submit" class="btn btn-outline-light px-3">
                                    <i class="bi bi-skip-forward-fill me-1"></i>Mark as Skipped
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Quick Counters -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 text-primary me-2"></i>Today's Counter Statistics</h6>
                </div>
                <div class="card-body d-flex flex-column justify-content-around">
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-hourglass-split text-warning me-2"></i>Waiting in Line</span>
                        <span class="badge bg-warning text-dark fs-6 px-3"><?php echo (int)($counts['waiting'] ?? 0); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-check2-circle text-success me-2"></i>Completed Service</span>
                        <span class="badge bg-success fs-6 px-3"><?php echo (int)($counts['completed'] ?? 0); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <span class="text-muted"><i class="bi bi-skip-end text-danger me-2"></i>Skipped / No Show</span>
                        <span class="badge bg-danger fs-6 px-3"><?php echo (int)($counts['skipped'] ?? 0); ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <span class="fw-bold text-dark"><i class="bi bi-ticket-detailed text-primary me-2"></i>Total Tickets Generated</span>
                        <span class="badge bg-primary fs-6 px-3"><?php echo (int)($counts['total'] ?? 0); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Pills & Full Queue Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="mb-0 fw-bold text-dark">
                <i class="bi bi-list-ol text-primary me-2"></i>Today's Queue Roster
            </h6>
            <!-- Filter Pills -->
            <div class="btn-group btn-group-sm">
                <a href="<?php echo url('staff/manage_queue.php'); ?>" class="btn <?php echo empty($filter_status) ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    All (<?php echo (int)($counts['total'] ?? 0); ?>)
                </a>
                <a href="<?php echo url('staff/manage_queue.php?status=Waiting'); ?>" class="btn <?php echo $filter_status === 'Waiting' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Waiting (<?php echo (int)($counts['waiting'] ?? 0); ?>)
                </a>
                <a href="<?php echo url('staff/manage_queue.php?status=Serving'); ?>" class="btn <?php echo $filter_status === 'Serving' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Serving (<?php echo (int)($counts['serving'] ?? 0); ?>)
                </a>
                <a href="<?php echo url('staff/manage_queue.php?status=Completed'); ?>" class="btn <?php echo $filter_status === 'Completed' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Completed (<?php echo (int)($counts['completed'] ?? 0); ?>)
                </a>
                <a href="<?php echo url('staff/manage_queue.php?status=Skipped'); ?>" class="btn <?php echo $filter_status === 'Skipped' ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                    Skipped (<?php echo (int)($counts['skipped'] ?? 0); ?>)
                </a>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th class="ps-3">Ticket</th>
                            <th>Student</th>
                            <th>Service Requested</th>
                            <th>Status</th>
                            <th>Time Generated</th>
                            <th class="pe-3 text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($queue_list && $queue_list->num_rows > 0): ?>
                            <?php while ($row = $queue_list->fetch_assoc()): 
                                $status_badge = match($row['status']) {
                                    'Waiting' => 'bg-warning text-dark',
                                    'Serving' => 'bg-primary text-white',
                                    'Completed' => 'bg-success text-white',
                                    'Skipped' => 'bg-danger text-white',
                                    default => 'bg-secondary text-white'
                                };
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <span class="badge bg-dark font-monospace fs-6"><?php echo sanitize($row['queue_number']); ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($row['full_name']); ?></div>
                                        <small class="text-muted"><?php echo sanitize($row['student_id'] ?: 'N/A'); ?> &bull; <?php echo sanitize($row['email']); ?></small>
                                    </td>
                                    <td class="small text-dark fw-medium">
                                        <?php echo sanitize($row['service_type']); ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $status_badge; ?> px-2 py-1">
                                            <?php echo sanitize($row['status']); ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <?php if ($row['status'] === 'Waiting'): ?>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="action" value="call_specific">
                                                <input type="hidden" name="queue_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-telephone-outbound me-1"></i>Call Now
                                                </button>
                                            </form>
                                        <?php elseif ($row['status'] === 'Serving'): ?>
                                            <form method="POST" action="" class="d-inline me-1">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="action" value="complete_ticket">
                                                <input type="hidden" name="queue_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-success">
                                                    <i class="bi bi-check2"></i> Complete
                                                </button>
                                            </form>
                                            <form method="POST" action="" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="action" value="skip_ticket">
                                                <input type="hidden" name="queue_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-skip-forward"></i> Skip
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    No queue tickets found matching this filter for today.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

