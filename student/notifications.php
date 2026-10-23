<?php
/**
 * Notifications (student/notifications.php)
 * 
 * Lists all notifications for the logged-in student.
 * Automatically marks all unread notifications as read upon visit.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('student');

$user_id = $_SESSION['user_id'];

// 1. Mark unread notifications as read
$update_stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// 2. Fetch all notifications ordered by most recent
$stmt = $conn->prepare("
    SELECT id, message, is_read, created_at 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$notifications = $stmt->get_result();
$stmt->close();

$page_title = 'My Notifications';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h3 class="mb-1">Notifications</h3>
                <p class="text-muted small mb-0">System updates on your document applications and queues</p>
            </div>
            <div>
                <span class="badge bg-light text-dark border"><?php echo $notifications->num_rows; ?> Total</span>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white">
                <span class="fw-semibold"><i class="bi bi-bell text-primary me-2"></i>Activity Feed</span>
            </div>

            <div class="card-body p-0">
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <div class="notification-item d-flex align-items-start gap-3">
                                <div class="p-2 bg-light text-primary rounded-circle mt-1">
                                    <i class="bi bi-info-circle-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-dark mb-1">
                                        <?php echo sanitize($notif['message']); ?>
                                    </div>
                                    <div class="text-muted small">
                                        <i class="bi bi-clock me-1"></i>
                                        <?php echo date('M d, Y &bull; h:i A', strtotime($notif['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-5">
                        <i class="bi bi-bell-slash text-muted"></i>
                        <h5 class="fw-semibold mb-1">No Notifications Yet</h5>
                        <p class="text-muted small mb-0">You will receive notifications here when staff updates your documents or calls your queue.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

