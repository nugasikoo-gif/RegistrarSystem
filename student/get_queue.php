<?php
/**
 * Get Queue Number (student/get_queue.php)
 * 
 * Allows students to generate a daily queue number for registrar services.
 * Features upgraded radio cards and live ticket state view.
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
    'Document Pickup' => [
        'desc' => 'Claim approved and ready academic documents (TOR, Cert of Grades, etc.)',
        'icon' => 'bi-file-earmark-check'
    ],
    'Enrollment Inquiry' => [
        'desc' => 'Questions regarding enrollment validation, subject load, or requisites',
        'icon' => 'bi-journal-check'
    ],
    'Record Request / Evaluation' => [
        'desc' => 'Academic records evaluation, cross-enrollment clearance, transcript checks',
        'icon' => 'bi-clipboard-data'
    ],
    'General Registrar Inquiry' => [
        'desc' => 'General office questions, certifications, and other student registrar concerns',
        'icon' => 'bi-question-circle'
    ]
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
                header("Location: " . url('student/my_queue.php'));
                exit();
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'An error occurred while generating your queue number. Please try again.';
            }
        }
    }
}

$csrf_token = generateCSRFToken();
$page_title = 'Get Queue Ticket';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <!-- Back Navigation -->
        <div class="mb-3">
            <a href="<?php echo url('student/dashboard.php'); ?>" class="text-decoration-none small text-muted">
                <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
            </a>
        </div>

        <div class="portal-section-header">
            <h3 class="portal-section-title">Daily Digital Queue</h3>
            <p class="text-muted small mb-0">Generate a live ticket number for in-person transactions at the Registrar's counter.</p>
            <div class="portal-title-bar"></div>
        </div>

        <?php if ($existing_queue): ?>
            <!-- Already has active queue notification -->
            <div class="card shadow-sm border border-primary border-opacity-25">
                <div class="card-body p-4 text-center">
                    <div class="d-inline-flex p-3 bg-primary-subtle text-primary rounded-circle mb-3">
                        <i class="bi bi-ticket-perforated fs-1"></i>
                    </div>
                    <h5 class="fw-bold mb-1">Active Queue Ticket in Progress</h5>
                    <p class="text-muted small mb-3">
                        You already hold an active queue ticket for today. To maintain a fair queue order, only one active ticket is permitted per student at a time.
                    </p>

                    <div class="queue-ticket-box mb-4 mx-auto" style="max-width: 320px;">
                        <div class="queue-ticket-title">Your Current Ticket</div>
                        <div class="queue-big-number"><?php echo sanitize($existing_queue['queue_number']); ?></div>
                        <div class="small text-muted"><i class="bi bi-tag me-1"></i>Service: <strong><?php echo sanitize($existing_queue['service_type']); ?></strong></div>
                    </div>

                    <div>
                        <a href="<?php echo url('student/my_queue.php'); ?>" class="btn btn-primary px-4">
                            <i class="bi bi-broadcast me-1"></i>View Live Queue Position
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Queue Ticket Request Form -->
            <div class="card shadow-sm border">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                    <img src="<?php echo url('assets/images/ctu-logo.png?v=3'); ?>" alt="CTU Logo" style="width: 28px; height: 28px; object-fit: contain;">
                    <span class="fw-semibold text-dark">Select Registrar Counter Service</span>
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

                        <div class="d-flex flex-column gap-3 mb-4">
                            <?php foreach ($services as $srv_name => $srv_meta): ?>
                                <label class="service-radio-card">
                                    <input type="radio" name="service_type" value="<?php echo sanitize($srv_name); ?>" class="form-check-input mt-1" required>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <i class="bi <?php echo $srv_meta['icon']; ?> text-primary"></i>
                                            <span class="fw-semibold text-dark"><?php echo sanitize($srv_name); ?></span>
                                        </div>
                                        <div class="text-muted small"><?php echo sanitize($srv_meta['desc']); ?></div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-3 bg-light border rounded-3 small mb-4 text-secondary">
                            <i class="bi bi-info-circle text-primary me-1"></i>
                            <strong>Policy Notice:</strong> Queue tickets are called in sequential numerical order. Please ensure you are within or near the Registrar Office lobby when your turn approaches.
                        </div>

                        <div class="d-flex justify-content-end gap-2 pt-2 border-top">
                            <a href="<?php echo url('student/dashboard.php'); ?>" class="btn btn-outline-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-ticket-perforated me-1"></i>Generate My Ticket
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
