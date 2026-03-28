<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$pageTitle = "Assign Staff";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_staff'])) {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $staff_user_id = (int)($_POST['staff_user_id'] ?? 0);
    $role = trim($_POST['role'] ?? '');

    if ($project_id <= 0 || $staff_user_id <= 0 || $role === '') {
        $msg = "Please select all required fields.";
        $msg_type = 'danger';
    } elseif (!in_array($role, ['safety_officer', 'supervisor'])) {
        $msg = "Invalid assignment role.";
        $msg_type = 'danger';
    } else {
        $checkProject = $conn->prepare("SELECT project_id FROM projects WHERE project_id = ? AND company_id = ? LIMIT 1");
        $checkProject->bind_param("ii", $project_id, $company_id);
        $checkProject->execute();
        $projectRes = $checkProject->get_result();

        $checkUser = $conn->prepare("
            SELECT user_id FROM users
            WHERE user_id = ? AND company_id = ? AND role = ? AND status = 'active'
            LIMIT 1
        ");
        $checkUser->bind_param("iis", $staff_user_id, $company_id, $role);
        $checkUser->execute();
        $userRes = $checkUser->get_result();

        if ($projectRes->num_rows === 0) {
            $msg = "Invalid project selected.";
            $msg_type = 'danger';
        } elseif ($userRes->num_rows === 0) {
            $msg = "Invalid staff user selected.";
            $msg_type = 'danger';
        } else {
            $checkAssign = $conn->prepare("
                SELECT project_staff_id
                FROM project_staff
                WHERE company_id = ? AND project_id = ? AND user_id = ? AND role = ?
                LIMIT 1
            ");
            $checkAssign->bind_param("iiis", $company_id, $project_id, $staff_user_id, $role);
            $checkAssign->execute();
            $assignRes = $checkAssign->get_result();

            if ($assignRes->num_rows > 0) {
                $msg = "This staff member is already assigned to the project.";
                $msg_type = 'warning';
            } else {
                $status = 'active';
                $stmt = $conn->prepare("
                    INSERT INTO project_staff (company_id, project_id, user_id, role, status, assigned_by, assigned_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iiissi", $company_id, $project_id, $staff_user_id, $role, $status, $user_id);

                if ($stmt->execute()) {
                    $project_staff_id = $stmt->insert_id;

                    $staffName = '';
                    $projectName = '';

                    $s1 = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                    $s1->bind_param("i", $staff_user_id);
                    $s1->execute();
                    $r1 = $s1->get_result()->fetch_assoc();
                    $staffName = $r1['full_name'] ?? '';

                    $s2 = $conn->prepare("SELECT project_name FROM projects WHERE project_id = ? LIMIT 1");
                    $s2->bind_param("i", $project_id);
                    $s2->execute();
                    $r2 = $s2->get_result()->fetch_assoc();
                    $projectName = $r2['project_name'] ?? '';

                    $notify = $conn->prepare("
                        INSERT INTO notifications (company_id, user_id, title, message, type, is_read, created_at)
                        VALUES (?, ?, ?, ?, ?, 0, NOW())
                    ");
                    $title   = "New Project Assignment";
                    $message = "You have been assigned as " . $role . " to project: " . $projectName;
                    $type    = "assignment";
                    $notify->bind_param("iisss", $company_id, $staff_user_id, $title, $message, $type);
                    $notify->execute();

                    $log = $conn->prepare("
                        INSERT INTO activity_logs (company_id, project_id, user_id, module_name, related_id, action_type, action_description, ip_address, created_at)
                        VALUES (?, ?, ?, 'project_staff', ?, 'assign', ?, ?, NOW())
                    ");
                    $desc = "Assigned " . $staffName . " as " . $role . " to project " . $projectName;
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $project_staff_id, $desc, $ip);
                    $log->execute();

                    $msg = "Staff assigned successfully.";
                    $msg_type = 'success';
                } else {
                    $msg = "Assignment failed.";
                    $msg_type = 'danger';
                }
            }
        }
    }
}

$projects = [];
$pstmt = $conn->prepare("
    SELECT project_id, project_name, site_name
    FROM projects
    WHERE company_id = ? AND status = 'active'
    ORDER BY project_name ASC
");
$pstmt->bind_param("i", $company_id);
$pstmt->execute();
$pres = $pstmt->get_result();
while ($row = $pres->fetch_assoc()) {
    $projects[] = $row;
}

$staff_users = [];
$ustmt = $conn->prepare("
    SELECT user_id, full_name, role
    FROM users
    WHERE company_id = ? AND status = 'active' AND role IN ('safety_officer', 'supervisor')
    ORDER BY full_name ASC
");
$ustmt->bind_param("i", $company_id);
$ustmt->execute();
$ures = $ustmt->get_result();
while ($row = $ures->fetch_assoc()) {
    $staff_users[] = $row;
}

$assignments = [];
$astmt = $conn->prepare("
    SELECT ps.project_staff_id, ps.role, ps.status, ps.assigned_at,
           p.project_name, p.site_name,
           u.full_name, u.email
    FROM project_staff ps
    INNER JOIN projects p ON ps.project_id = p.project_id
    INNER JOIN users u ON ps.user_id = u.user_id
    WHERE ps.company_id = ?
    ORDER BY ps.project_staff_id DESC
");
$astmt->bind_param("i", $company_id);
$astmt->execute();
$ares = $astmt->get_result();
while ($row = $ares->fetch_assoc()) {
    $assignments[] = $row;
}

include '../includes/header.php';
?>


<?php include 'includes/sidebar.php'; ?>
<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type); ?>"><?= htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">New Assignment</h4>

    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Select Project *</label>
            <select name="project_id" class="form-select" required>
                <option value="">Choose Project</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= (int)$p['project_id']; ?>">
                    <?= htmlspecialchars($p['project_name'] . ' - ' . $p['site_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Select Staff *</label>
            <select name="staff_user_id" class="form-select" required>
                <option value="">Choose User</option>
                <?php foreach ($staff_users as $u): ?>
                <option value="<?= (int)$u['user_id']; ?>">
                    <?= htmlspecialchars($u['full_name'] . ' (' . $u['role'] . ')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Assignment Role *</label>
            <select name="role" class="form-select" required>
                <option value="">Choose Role</option>
                <option value="safety_officer">Safety Officer</option>
                <option value="supervisor">Supervisor</option>
            </select>
        </div>

        <div class="col-12">
            <button type="submit" name="assign_staff" class="btn btn-main">Assign Staff</button>
        </div>
    </form>
</div>

<div class="card page-card p-4">
    <h4 class="mb-3">Project Assignments</h4>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project</th>
                    <th>Site</th>
                    <th>Staff</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Assigned At</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($assignments) > 0): ?>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= (int)$a['project_staff_id']; ?></td>
                    <td><?= htmlspecialchars($a['project_name']); ?></td>
                    <td><?= htmlspecialchars($a['site_name']); ?></td>
                    <td><?= htmlspecialchars($a['full_name']); ?></td>
                    <td><?= htmlspecialchars($a['email']); ?></td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($a['role']); ?></span>
                    </td>
                    <td>
                        <?php $badge = ($a['status'] === 'active') ? 'success' : 'danger'; ?>
                        <span class="badge bg-<?= $badge; ?>"><?= htmlspecialchars($a['status']); ?></span>
                    </td>
                    <td><?= htmlspecialchars($a['assigned_at']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No assignments found.</td>
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