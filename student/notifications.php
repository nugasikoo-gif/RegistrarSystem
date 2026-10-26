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
        
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <div class="portal-section-header mb-0">
                    <h3 class="portal-section-title">Notifications Feed</h3>
                    <p class="text-muted small mb-0">Real-time status updates on your document applications and counter tickets</p>
                    <div class="portal-title-bar"></div>
                </div>
            </div>
            <div>
                <span class="badge bg-light text-dark border px-3 py-2"><?php echo $notifications->num_rows; ?> Total</span>
            </div>
        </div>

        <div class="card shadow-sm border">
            <div class="card-header bg-white py-3 border-bottom d-flex align-items-center gap-2">
                <i class="bi bi-bell text-primary fs-5"></i>
                <span class="fw-semibold text-dark">Recent Activity</span>
            </div>

            <div class="card-body p-0">
                <?php if ($notifications->num_rows > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($notif = $notifications->fetch_assoc()): ?>
                            <div class="notification-item d-flex align-items-start gap-3 py-3 px-4">
                                <div class="p-2 bg-primary-subtle text-primary rounded-circle mt-1 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                    <i class="bi bi-bell-fill"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="text-dark fw-medium mb-1" style="line-height: 1.45;">
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
                    <div class="empty-state py-5 text-center">
                        <div class="d-inline-flex p-3 bg-light text-primary rounded-circle mb-3">
                            <i class="bi bi-bell-slash fs-1"></i>
                        </div>
                        <h6 class="fw-bold text-dark mb-1">No Notifications Yet</h6>
                        <p class="text-muted small mb-0">You will receive notifications here once your document request is reviewed or your queue number is called.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
