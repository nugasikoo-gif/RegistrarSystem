<?php
/**
 * Get Queue Number (student/get_queue.php)
 * 
 * Allows students to generate a daily queue number for registrar services.
 * Enforces rules:
 * - One active queue (Waiting or Serving) per student per day
 * - Numbers reset daily: Q001, Q002, etc.
 * - Concurrency protection using database transactions
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id = $_SESSION['user_id'];
$today   = date('Y-m-d');
$error   = '';

// 1. Check if the student already has an active queue today
$stmt = $conn->prepare("
    SELECT queue_number, service_type, status 
    FROM queues 
    WHERE user_id = ? AND queue_date = ? AND status IN ('Waiting', 'Serving') 
    LIMIT 1
");
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$existing_queue = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Available Registrar Services
$services = [
    'Document Pickup' => 'Claim approved and ready academic documents',
    'Enrollment Inquiry' => 'Questions regarding enrollment validation or subjects',
    'Record Request / Evaluation' => 'Academic records evaluation, cross-enrollment, TOR',
    'General Registrar Inquiry' => 'General questions and other registrar concerns'
];

// 2. Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // CSRF verification
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid submission token. Please refresh and try again.';
    } elseif ($existing_queue) {
        $error = "You already have an active queue ticket ({$existing_queue['queue_number']}) for today.";
    } else {
        $service_type = trim($_POST['service_type'] ?? '');
        
        if (!array_key_exists($service_type, $services)) {
            $error = 'Please select a valid registrar service from the list.';
        } else {
            // Begin transaction to safely generate the next queue number
            $conn->begin_transaction();
            try {
                // Find highest sequence number today using FOR UPDATE to lock row calculations
                $count_stmt = $conn->prepare("
                    SELECT COUNT(*) as total 
                    FROM queues 
                    WHERE queue_date = ? FOR UPDATE
                ");
                $count_stmt->bind_param("s", $today);
                $count_stmt->execute();
                $count_row = $count_stmt->get_result()->fetch_assoc();
                $next_seq  = ($count_row['total'] ?? 0) + 1;
                $count_stmt->close();
                
                // Format: Q001, Q002, etc.
                $queue_number = 'Q' . str_pad($next_seq, 3, '0', STR_PAD_LEFT);
                $status       = 'Waiting';
                
                // Insert into queues table
                $insert_stmt = $conn->prepare("
                    INSERT INTO queues (user_id, service_type, queue_number, queue_date, status) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insert_stmt->bind_param("issss", $user_id, $service_type, $queue_number, $today, $status);
                $insert_stmt->execute();
                $insert_stmt->close();
                
                // Generate student notification
                $notif_msg = "Your queue ticket {$queue_number} has been generated for '{$service_type}'. Please monitor your turn.";
                $notif_stmt = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $notif_stmt->bind_param("is", $user_id, $notif_msg);
                $notif_stmt->execute();
                $notif_stmt->close();
                
                $conn->commit();
                
                setFlashMessage('success', "Your queue ticket {$queue_number} has been generated successfully!");
                header("Location: my_queue.php");
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'An error occurred while generating your queue number. Please try again.';
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = 'Get Queue Number';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <!-- Back Navigation -->
        <div class="mb-3">
            <a href="dashboard.php" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

        <?php if ($existing_queue): ?>
            <!-- Already has active queue notification -->
            <div class="card border-warning">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex p-3 bg-warning-subtle text-warning rounded-circle mb-3">
                        <i class="bi bi-ticket-perforated fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Active Queue Ticket in Progress</h5>
                    <p class="text-muted small mb-3">
                        You already generated a queue ticket for today. To maintain fairness, each student can only hold one active ticket at a time.
                    </p>
                    <div class="p-3 bg-light rounded-3 border mb-3 d-inline-block px-4">
                        <div class="text-muted small">Your Ticket Number</div>
                        <div class="queue-number-display" style="font-size: 2.75rem;"><?php echo sanitize($existing_queue['queue_number']); ?></div>
                        <div class="small text-muted"><i class="bi bi-tag me-1"></i>Service: <?php echo sanitize($existing_queue['service_type']); ?></div>
                    </div>
                    <div>
                        <a href="my_queue.php" class="btn btn-primary">
                            <i class="bi bi-eye me-1"></i>View Live Queue Position
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Queue Ticket Request Form -->
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-1"><i class="bi bi-ticket-perforated text-primary me-2"></i>Get Daily Queue Ticket</h5>
                    <small class="text-muted">Select the service you need at the Registrar's Office counter today</small>
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

                        <label class="form-label mb-2">Select Registrar Counter Service <span class="text-danger">*</span></label>
                        
                        <div class="d-flex flex-column gap-2 mb-4">
                            <?php foreach ($services as $srv_name => $srv_desc): ?>
                                <label class="border rounded-3 p-3 d-flex align-items-start gap-3" style="cursor: pointer;">
                                    <input type="radio" name="service_type" value="<?php echo sanitize($srv_name); ?>" class="form-check-input mt-1" required>
                                    <div>
                                        <div class="fw-semibold text-dark"><?php echo sanitize($srv_name); ?></div>
                                        <div class="text-muted small"><?php echo sanitize($srv_desc); ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="alert alert-light border small text-muted mb-4">
                            <i class="bi bi-info-circle me-1"></i>
                            Tickets are called in sequential first-come, first-served order. Please stay near the Registrar window once your turn approaches.
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-check2-circle me-1"></i>Generate My Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

