<?php
/**
 * Admin - Manage Users (admin/manage_users.php)
 * 
 * Allows Administrators to:
 * - View list of all accounts (students, staff, admin)
 * - Filter by role (student, staff, admin) or search by name/email/ID
 * - Create new staff or admin accounts
 * - Edit existing accounts (name, email, role, personal_gmail, or update password)
 * - Delete user accounts (with confirmation and self-deletion prevention)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

requireRole('admin');

$current_admin_id = $_SESSION['user_id'];
$action_msg = '';
$action_type = '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $action_msg = 'Invalid security token. Please try again.';
        $action_type = 'danger';
    } else {
        $action = trim($_POST['action'] ?? '');

        // 1. ADD USER (Staff / Admin / Student)
        if ($action === 'add_user') {
            $full_name      = trim($_POST['full_name'] ?? '');
            $email          = trim($_POST['email'] ?? '');
            $personal_gmail = trim($_POST['personal_gmail'] ?? '');
            $student_id     = trim($_POST['student_id'] ?? '');
            $role           = trim($_POST['role'] ?? 'staff');
            $password       = $_POST['password'] ?? '';

            if (empty($full_name) || empty($email) || empty($password)) {
                $action_msg = 'Please complete all required fields.';
                $action_type = 'danger';
            } elseif (!isValidCtuEmail($email)) {
                $action_msg = 'Official CTU email address (@ctu.edu.ph) is required for user accounts.';
                $action_type = 'danger';
            } elseif (!empty($personal_gmail) && !filter_var($personal_gmail, FILTER_VALIDATE_EMAIL)) {
                $action_msg = 'Please enter a valid personal notification email address.';
                $action_type = 'danger';
            } elseif (!in_array($role, ['student', 'staff', 'admin'])) {
                $action_msg = 'Invalid role specified.';
                $action_type = 'danger';
            } else {
                $pwdOk = true;
                if ($role === 'student') {
                    $chk = validateStudentStrongPassword($password, $email, $full_name);
                    if (!$chk['valid']) {
                        $action_msg = 'Student password does not meet security requirements: ' . implode(' ', $chk['errors']);
                        $action_type = 'danger';
                        $pwdOk = false;
                    }
                } else {
                    if (strlen($password) < 6) {
                        $action_msg = 'Password must be at least 6 characters.';
                        $action_type = 'danger';
                        $pwdOk = false;
                    }
                }

                if ($pwdOk) {
                    // Check email uniqueness
                    $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $check_stmt->bind_param("s", $email);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        $action_msg = 'That email address is already in use.';
                        $action_type = 'danger';
                    } else {
                        $check_stmt->close();
                        $hashed_pwd = password_hash($password, PASSWORD_BCRYPT);
                        $sid_val = !empty($student_id) ? $student_id : null;
                        $gmail_val = !empty($personal_gmail) ? $personal_gmail : null;

                        $insert_stmt = $conn->prepare("
                            INSERT INTO users (student_id, full_name, email, personal_gmail, password, role) 
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $insert_stmt->bind_param("ssssss", $sid_val, $full_name, $email, $gmail_val, $hashed_pwd, $role);
                        if ($insert_stmt->execute()) {
                            $action_msg = "User <strong>" . sanitize($full_name) . "</strong> created successfully!";
                            $action_type = 'success';
                        } else {
                            $action_msg = 'Failed to create user. Student ID or email may already be taken.';
                            $action_type = 'danger';
                        }
                        $insert_stmt->close();
                    }
                }
            }

        // 2. EDIT USER
        } elseif ($action === 'edit_user') {
            $edit_id        = (int)($_POST['user_id'] ?? 0);
            $full_name      = trim($_POST['full_name'] ?? '');
            $email          = trim($_POST['email'] ?? '');
            $personal_gmail = trim($_POST['personal_gmail'] ?? '');
            $student_id     = trim($_POST['student_id'] ?? '');
            $role           = trim($_POST['role'] ?? 'student');
            $new_password   = $_POST['password'] ?? '';

            if ($edit_id <= 0 || empty($full_name) || empty($email)) {
                $action_msg = 'Name and email are required.';
                $action_type = 'danger';
            } elseif (!isValidCtuEmail($email)) {
                $action_msg = 'Official CTU email address (@ctu.edu.ph) is required.';
                $action_type = 'danger';
            } elseif (!empty($personal_gmail) && !filter_var($personal_gmail, FILTER_VALIDATE_EMAIL)) {
                $action_msg = 'Please enter a valid personal notification email address.';
                $action_type = 'danger';
            } elseif (!in_array($role, ['student', 'staff', 'admin'])) {
                $action_msg = 'Invalid role specified.';
                $action_type = 'danger';
            } else {
                $pwdOk = true;
                if (!empty($new_password)) {
                    if ($role === 'student') {
                        $chk = validateStudentStrongPassword($new_password, $email, $full_name);
                        if (!$chk['valid']) {
                            $action_msg = 'Student password does not meet security requirements: ' . implode(' ', $chk['errors']);
                            $action_type = 'danger';
                            $pwdOk = false;
                        }
                    } else {
                        if (strlen($new_password) < 6) {
                            $action_msg = 'Password must be at least 6 characters.';
                            $action_type = 'danger';
                            $pwdOk = false;
                        }
                    }
                }

                if ($pwdOk) {
                    // Check email collision
                    $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                    $chk->bind_param("si", $email, $edit_id);
                    $chk->execute();
                    if ($chk->get_result()->num_rows > 0) {
                        $action_msg = 'That email is used by another account.';
                        $action_type = 'danger';
                    } else {
                        $chk->close();
                        $sid_val = !empty($student_id) ? $student_id : null;
                        $gmail_val = !empty($personal_gmail) ? $personal_gmail : null;

                        if (!empty($new_password)) {
                            $hashed_pwd = password_hash($new_password, PASSWORD_BCRYPT);
                            $upd_stmt = $conn->prepare("
                                UPDATE users 
                                SET full_name = ?, email = ?, personal_gmail = ?, student_id = ?, role = ?, password = ?
                                WHERE id = ?
                            ");
                            $upd_stmt->bind_param("ssssssi", $full_name, $email, $gmail_val, $sid_val, $role, $hashed_pwd, $edit_id);
                        } else {
                            $upd_stmt = $conn->prepare("
                                UPDATE users 
                                SET full_name = ?, email = ?, personal_gmail = ?, student_id = ?, role = ?
                                WHERE id = ?
                            ");
                            $upd_stmt->bind_param("sssssi", $full_name, $email, $gmail_val, $sid_val, $role, $edit_id);
                        }

                        if ($upd_stmt->execute()) {
                            $action_msg = "Account for <strong>" . sanitize($full_name) . "</strong> updated successfully!";
                            $action_type = 'success';
                        } else {
                            $action_msg = 'Failed to update user.';
                            $action_type = 'danger';
                        }
                        $upd_stmt->close();
                    }
                }
            }

        // 3. DELETE USER
        } elseif ($action === 'delete_user') {
            $del_id = (int)($_POST['user_id'] ?? 0);
            if ($del_id === $current_admin_id) {
                $action_msg = 'You cannot delete your own logged-in administrator account!';
                $action_type = 'danger';
            } elseif ($del_id > 0) {
                $del_stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
                $del_stmt->bind_param("i", $del_id);
                if ($del_stmt->execute()) {
                    $action_msg = 'User account deleted successfully.';
                    $action_type = 'success';
                } else {
                    $action_msg = 'Failed to delete user.';
                    $action_type = 'danger';
                }
                $del_stmt->close();
            }
        }
    }
}

// Search and Filter parameters
$role_filter = trim($_GET['role'] ?? '');
$search = trim($_GET['q'] ?? '');

$query = "SELECT * FROM users WHERE 1=1";
$params = [];
$types = "";

if (in_array($role_filter, ['student', 'staff', 'admin'])) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR student_id LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_list = $stmt->get_result();
$stmt->close();

$csrf_token = generateCSRFToken();
$page_title = 'Manage Users';
include __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0d47a1;">User Management</h3>
            <p class="text-muted small mb-0">View, create, edit, and control student and staff accounts.</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-1"></i>Add New User
            </button>
        </div>
    </div>

    <?php if (!empty($action_msg)): ?>
        <div class="alert alert-<?php echo $action_type; ?> alert-dismissible fade show d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-info-circle-fill me-2 fs-5"></i>
            <div><?php echo $action_msg; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter Card -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-5">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search by name, email, or Student ID..." value="<?php echo sanitize($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="role" class="form-select form-select-sm">
                        <option value="">All Roles</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Students Only</option>
                        <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff Only</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admins Only</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-primary flex-fill">Filter</button>
                    <?php if (!empty($search) || !empty($role_filter)): ?>
                        <a href="<?php echo url('admin/manage_users.php'); ?>" class="btn btn-sm btn-outline-secondary">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-people me-2 text-primary"></i>All Users</h6>
            <small class="text-muted"><?php echo $users_list->num_rows; ?> account(s) listed</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th class="ps-3">Name / Student ID</th>
                            <th>Role</th>
                            <th>Login Email</th>
                            <th>Personal Gmail (Alerts)</th>
                            <th>Created</th>
                            <th class="pe-3 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($users_list && $users_list->num_rows > 0): ?>
                            <?php while ($u = $users_list->fetch_assoc()): 
                                $badge_role = match($u['role']) {
                                    'admin' => 'bg-danger text-white',
                                    'staff' => 'bg-primary text-white',
                                    default => 'bg-secondary text-white'
                                };
                            ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark"><?php echo sanitize($u['full_name']); ?></div>
                                        <?php if (!empty($u['student_id'])): ?>
                                            <small class="text-primary font-monospace"><?php echo sanitize($u['student_id']); ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Staff / Admin</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badge_role; ?> text-capitalize px-2 py-1">
                                            <?php echo sanitize($u['role']); ?>
                                        </span>
                                    </td>
                                    <td class="small text-dark"><?php echo sanitize($u['email']); ?></td>
                                    <td class="small">
                                        <?php if (!empty($u['personal_gmail'])): ?>
                                            <span class="text-success"><i class="bi bi-google text-danger me-1"></i><?php echo sanitize($u['personal_gmail']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small text-muted">
                                        <?php echo date('M d, Y', strtotime($u['created_at'])); ?>
                                    </td>
                                    <td class="pe-3 text-end">
                                        <!-- Edit trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $u['id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <!-- Delete trigger -->
                                        <?php if ($u['id'] !== $current_admin_id): ?>
                                            <form method="POST" action="" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? All their submitted requests, queue tickets, and notifications will be removed.');">
                                                <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Edit Modal -->
                                        <div class="modal fade" id="editModal<?php echo $u['id']; ?>" tabindex="-1" aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered text-start">
                                                <div class="modal-content">
                                                    <form method="POST" action="">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title fs-6 fw-bold">Edit User Account</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                                                            <input type="hidden" name="action" value="edit_user">
                                                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Full Name *</label>
                                                                <input type="text" name="full_name" class="form-control form-control-sm" value="<?php echo sanitize($u['full_name']); ?>" required>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Login Email (@ctu.edu.ph) *</label>
                                                                <input type="email" name="email" class="form-control form-control-sm" value="<?php echo sanitize($u['email']); ?>" placeholder="username@ctu.edu.ph" required>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Personal Notification Email (Gmail, Yahoo, Outlook, etc.)</label>
                                                                <input type="email" name="personal_gmail" class="form-control form-control-sm" value="<?php echo sanitize($u['personal_gmail'] ?? ''); ?>" placeholder="personal@gmail.com">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Student ID (Leave blank for staff/admin)</label>
                                                                <input type="text" name="student_id" class="form-control form-control-sm" value="<?php echo sanitize($u['student_id'] ?? ''); ?>">
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Role *</label>
                                                                <select name="role" class="form-select form-select-sm" required>
                                                                    <option value="student" <?php echo $u['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                                    <option value="staff" <?php echo $u['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                                    <option value="admin" <?php echo $u['role'] === 'admin' ? 'selected' : ''; ?>>Administrator</option>
                                                                </select>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label class="form-label small fw-semibold">Change Password (Leave blank to keep unchanged)</label>
                                                                <input type="password" name="password" class="form-control form-control-sm" placeholder="New password (optional)">
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-sm btn-primary">Save Changes</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No accounts match the current filters.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Create New User Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="action" value="add_user">

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Role *</label>
                        <select name="role" class="form-select form-select-sm" required>
                            <option value="staff" selected>Registrar Staff</option>
                            <option value="student">Student</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input type="text" name="full_name" class="form-control form-control-sm" placeholder="e.g. Maria Santos" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Login Email * (@ctu.edu.ph)</label>
                        <input type="email" name="email" class="form-control form-control-sm" placeholder="e.g. maria.staff@ctu.edu.ph" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Personal Notification Email (Optional - Gmail, Yahoo, Outlook, etc.)</label>
                        <input type="email" name="personal_gmail" class="form-control form-control-sm" placeholder="e.g. maria@gmail.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Student ID (Only if student)</label>
                        <input type="text" name="student_id" class="form-control form-control-sm" placeholder="e.g. 2024-0002">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Initial Password * <small class="text-muted">(Min. 6 chars for staff/admin; 12+ strong chars for students)</small></label>
                        <input type="password" name="password" class="form-control form-control-sm" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

