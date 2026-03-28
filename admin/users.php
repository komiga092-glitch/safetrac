<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$pageTitle = "Manage Users";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $role      = trim($_POST['role'] ?? '');
    $password  = $_POST['password'] ?? '';

    if ($full_name === '' || $email === '' || $role === '' || $password === '') {
        $msg = "Please fill all required fields.";
        $msg_type = 'danger';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Invalid email address.";
        $msg_type = 'danger';
    } elseif (!in_array($role, ['safety_officer', 'supervisor'])) {
        $msg = "Invalid role selected.";
        $msg_type = 'danger';
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters.";
        $msg_type = 'danger';
    } else {
        $check = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $check->bind_param("s", $email);
        $check->execute();
        $checkRes = $check->get_result();

        if ($checkRes->num_rows > 0) {
            $msg = "Email already exists.";
            $msg_type = 'warning';
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $status = 'active';

            $stmt = $conn->prepare("
                INSERT INTO users (company_id, full_name, email, phone, password_hash, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param("issssss", $company_id, $full_name, $email, $phone, $password_hash, $role, $status);

            if ($stmt->execute()) {
                $new_user_id = $stmt->insert_id;

                $log = $conn->prepare("
                    INSERT INTO activity_logs (company_id, project_id, user_id, module_name, related_id, action_type, action_description, ip_address, created_at)
                    VALUES (?, NULL, ?, 'user', ?, 'create', ?, ?, NOW())
                ");
                $desc = "Created user: " . $full_name . " (" . $role . ")";
                $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                $log->bind_param("iiiss", $company_id, $user_id, $new_user_id, $desc, $ip);
                $log->execute();

                $msg = "User added successfully.";
                $msg_type = 'success';
            } else {
                $msg = "Failed to add user.";
                $msg_type = 'danger';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_user_status'])) {
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    $new_status     = trim($_POST['new_status'] ?? '');

    if ($target_user_id > 0 && in_array($new_status, ['active', 'inactive'])) {
        $stmt = $conn->prepare("
            UPDATE users
            SET status = ?
            WHERE user_id = ? AND company_id = ? AND role IN ('safety_officer', 'supervisor')
        ");
        $stmt->bind_param("sii", $new_status, $target_user_id, $company_id);

        if ($stmt->execute()) {
            $log = $conn->prepare("
                INSERT INTO activity_logs (company_id, project_id, user_id, module_name, related_id, action_type, action_description, ip_address, created_at)
                VALUES (?, NULL, ?, 'user', ?, 'status_update', ?, ?, NOW())
            ");
            $desc = "Updated user status to: " . $new_status;
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $log->bind_param("iiiss", $company_id, $user_id, $target_user_id, $desc, $ip);
            $log->execute();

            $msg = "User status updated.";
            $msg_type = 'success';
        } else {
            $msg = "Status update failed.";
            $msg_type = 'danger';
        }
    }
}

$users = [];
$stmt = $conn->prepare("
    SELECT user_id, full_name, email, phone, role, status, created_at
    FROM users
    WHERE company_id = ?
    ORDER BY user_id DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $users[] = $row;
}

include '../includes/header.php';
?>


<?php include '../includes/sidebar.php'; ?>
<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type); ?>"><?= htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">Add Staff User</h4>
    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Phone</label>
            <input type="text" name="phone" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">Role *</label>
            <select name="role" class="form-select" required>
                <option value="">Select Role</option>
                <option value="safety_officer">Safety Officer</option>
                <option value="supervisor">Supervisor</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <div class="col-12">
            <button type="submit" name="add_user" class="btn btn-main">Add User</button>
        </div>
    </form>
</div>

<div class="card page-card p-4">
    <h4 class="mb-3">Company Users</h4>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Change Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($users) > 0): ?>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= (int)$u['user_id']; ?></td>
                    <td><?= htmlspecialchars($u['full_name']); ?></td>
                    <td><?= htmlspecialchars($u['email']); ?></td>
                    <td><?= htmlspecialchars($u['phone'] ?: '-'); ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($u['role']); ?></span>
                    </td>
                    <td>
                        <?php $badge = ($u['status'] === 'active') ? 'success' : 'danger'; ?>
                        <span class="badge bg-<?= $badge; ?>"><?= htmlspecialchars($u['status']); ?></span>
                    </td>
                    <td><?= htmlspecialchars($u['created_at']); ?></td>
                    <td>
                        <?php if ($u['role'] !== 'company_admin'): ?>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="target_user_id" value="<?= (int)$u['user_id']; ?>">
                            <select name="new_status" class="form-select form-select-sm">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            <button type="submit" name="change_user_status"
                                class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                        <?php else: ?>
                        <span class="text-muted">Admin Protected</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No users found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</div>
</div>
</div>
</div>

<?php include '../includes/footer.php'; ?>