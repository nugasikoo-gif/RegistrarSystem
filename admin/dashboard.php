<?php
/**
 * Admin Dashboard (admin/dashboard.php)
 * 
 * System overview for Administrators:
 * - High-level metrics: Users, Document Types, Document Requests, Today's Queues
 * - Distribution breakdown
 * - Quick shortcuts to admin tools
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$admin_name = $_SESSION['full_name'] ?? 'Administrator';
$today = date('Y-m-d');

// 1. Total counts
$users_count_query = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as total_students,
        SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as total_staff,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as total_admins
    FROM users
";
$users_res = $conn->query($users_count_query)->fetch_assoc();

$docs_count_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing,
        SUM(CASE WHEN status = 'Ready for Pickup' THEN 1 ELSE 0 END) as ready,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM document_requests
";
$docs_res = $conn->query($docs_count_query)->fetch_assoc();

$types_count_query = "
    SELECT 
        COUNT(*) as total_types,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_types
    FROM document_types
";
$types_res = $conn->query($types_count_query)->fetch_assoc();

$queue_count_query = "
    SELECT 
        COUNT(*) as total_queues,
        SUM(CASE WHEN queue_date = ? THEN 1 ELSE 0 END) as today_queues
    FROM queues
";
$stmt = $conn->prepare($queue_count_query);
$stmt->bind_param("s", $today);
$stmt->execute();
$queue_res = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. Recent user registrations (up to 5)
$recent_users = $conn->query("
    SELECT id, student_id, full_name, email, role, created_at 
    FROM users 
    ORDER BY id DESC 
    LIMIT 5
");

// 3. Document request breakdown by document type
$doc_breakdown = $conn->query("
    SELECT dt.document_name, COUNT(dr.id) as request_count
    FROM document_types dt
    LEFT JOIN document_requests dr ON dt.id = dr.document_type_id
    GROUP BY dt.id, dt.document_name
    ORDER BY request_count DESC
    LIMIT 5
");

$page_title = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">Administrator Portal</h3>
            <p class="text-muted small mb-0">System-wide monitoring & controls &bull; Logged in as <strong><?php echo sanitize($admin_name); ?></strong></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo url('admin/manage_users.php'); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-people me-1"></i>Manage Users
            </a>
            <a href="<?php echo url('admin/manage_documents.php'); ?>" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-files me-1"></i>Document Types
            </a>
            <a href="<?php echo url('admin/reports.php'); ?>" class="btn btn-primary btn-sm">
                <i class="bi bi-bar-chart-line me-1"></i>View Reports
            </a>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="row g-3 mb-4">
        <!-- Students -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-primary">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Registered Students</span>
                        <i class="bi bi-mortarboard text-primary fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo (int)($users_res['total_students'] ?? 0); ?></div>
                    <small class="text-muted"><?php echo (int)($users_res['total_staff'] ?? 0); ?> staff member(s)</small>
                </div>
            </div>
        </div>

        <!-- Total Requests -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Document Requests</span>
                        <i class="bi bi-folder-check text-warning fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo (int)($docs_res['total_requests'] ?? 0); ?></div>
                    <small class="text-muted"><?php echo (int)($docs_res['pending'] ?? 0); ?> pending review</small>
                </div>
            </div>
        </div>

        <!-- Today Queues -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-info">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Queues Today</span>
                        <i class="bi bi-ticket-perforated text-info fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo (int)($queue_res['today_queues'] ?? 0); ?></div>
                    <small class="text-muted"><?php echo (int)($queue_res['total_queues'] ?? 0); ?> historical total</small>
                </div>
            </div>
        </div>

        <!-- Active Document Types -->
        <div class="col-sm-6 col-lg-3">
            <div class="card shadow-sm border-0 h-100 bg-white">
                <div class="card-body border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small fw-semibold text-uppercase">Available Documents</span>
                        <i class="bi bi-file-earmark-ruled text-success fs-4"></i>
                    </div>
                    <div class="h2 fw-bold text-dark mb-0"><?php echo (int)($types_res['active_types'] ?? 0); ?></div>
                    <small class="text-muted"><?php echo (int)($types_res['total_types'] ?? 0); ?> total configured</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Section -->
    <div class="row g-4">
        <!-- Recent Users -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="bi bi-people-fill text-primary me-2"></i>Recent Accounts</span>
                    <a href="<?php echo url('admin/manage_users.php'); ?>" class="btn btn-sm btn-outline-primary">Manage Users &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-muted">
                                <tr>
                                    <th class="ps-3">User</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th class="pe-3 text-end">Registered</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_users && $recent_users->num_rows > 0): ?>
                                    <?php while ($u = $recent_users->fetch_assoc()): ?>
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-semibold text-dark"><?php echo sanitize($u['full_name']); ?></div>
                                                <small class="text-muted"><?php echo sanitize($u['student_id'] ?: 'N/A'); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'staff' ? 'primary' : 'secondary'); ?> text-capitalize">
                                                    <?php echo sanitize($u['role']); ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?php echo sanitize($u['email']); ?></td>
                                            <td class="pe-3 text-end small text-muted">
                                                <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Requested Document Types -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark"><i class="bi bi-pie-chart text-primary me-2"></i>Most Requested Credentials</span>
                    <a href="<?php echo url('admin/manage_documents.php'); ?>" class="btn btn-sm btn-outline-primary">Edit Types &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php if ($doc_breakdown && $doc_breakdown->num_rows > 0): ?>
                            <?php while ($d = $doc_breakdown->fetch_assoc()): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                    <div class="fw-medium text-dark small">
                                        <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                        <?php echo sanitize($d['document_name']); ?>
                                    </div>
                                    <span class="badge bg-light text-dark border px-3 py-1">
                                        <?php echo (int)$d['request_count']; ?> request(s)
                                    </span>
                                </li>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <li class="list-group-item text-center py-4 text-muted">No request data yet.</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

