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
    $project_id     = (int)($_POST['project_id'] ?? 0);
    $staff_user_id  = (int)($_POST['staff_user_id'] ?? 0);
    $assign_role    = trim($_POST['role'] ?? '');

    if ($project_id <= 0 || $staff_user_id <= 0 || $assign_role === '') {
        $msg = "Please select all required fields.";
        $msg_type = 'danger';
    } elseif (!in_array($assign_role, ['safety_officer', 'supervisor'], true)) {
        $msg = "Invalid assignment role.";
        $msg_type = 'danger';
    } else {
        $checkProject = $conn->prepare("
            SELECT project_id, project_name
            FROM projects
            WHERE project_id = ? AND company_id = ?
            LIMIT 1
        ");
        $checkProject->bind_param("ii", $project_id, $company_id);
        $checkProject->execute();
        $projectRes = $checkProject->get_result();
        $projectRow = $projectRes->fetch_assoc();

        $checkUser = $conn->prepare("
            SELECT user_id, full_name
            FROM users
            WHERE user_id = ?
              AND company_id = ?
              AND role = ?
              AND status = 'active'
            LIMIT 1
        ");
        $checkUser->bind_param("iis", $staff_user_id, $company_id, $assign_role);
        $checkUser->execute();
        $userRes = $checkUser->get_result();
        $userRow = $userRes->fetch_assoc();

        if (!$projectRow) {
            $msg = "Invalid project selected.";
            $msg_type = 'danger';
        } elseif (!$userRow) {
            $msg = "Invalid staff user selected for that role.";
            $msg_type = 'danger';
        } else {
            $checkAssign = $conn->prepare("
                SELECT project_staff_id
                FROM project_staff
                WHERE company_id = ?
                  AND project_id = ?
                  AND user_id = ?
                  AND role = ?
                LIMIT 1
            ");
            $checkAssign->bind_param("iiis", $company_id, $project_id, $staff_user_id, $assign_role);
            $checkAssign->execute();
            $assignRes = $checkAssign->get_result();

            if ($assignRes->num_rows > 0) {
                $msg = "This staff member is already assigned to the project.";
                $msg_type = 'warning';
            } else {
                $status = 'active';

                $stmt = $conn->prepare("
                    INSERT INTO project_staff (
                        company_id, project_id, user_id, role, status, assigned_by, assigned_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("iiissi", $company_id, $project_id, $staff_user_id, $assign_role, $status, $user_id);

                if ($stmt->execute()) {
                    $project_staff_id = (int)$stmt->insert_id;

                    $staffName   = $userRow['full_name'] ?? '';
                    $projectName = $projectRow['project_name'] ?? '';

                    // Notification insert
                    // If your notifications table has related_table / related_id, this will work well.
                    $title        = "New Project Assignment";
                    $message      = "You have been assigned as " . str_replace('_', ' ', $assign_role) . " to project: " . $projectName;
                    $relatedTable = "project_staff";
                    $relatedId    = $project_staff_id;

                    $notify = $conn->prepare("
                        INSERT INTO notifications (
                            company_id, user_id, title, message, related_table, related_id, is_read, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())
                    ");
                    $notify->bind_param("iisssi", $company_id, $staff_user_id, $title, $message, $relatedTable, $relatedId);
                    $notify->execute();

                    $log = $conn->prepare("
                        INSERT INTO activity_logs (
                            company_id, project_id, user_id, module_name, related_id,
                            action_type, action_description, ip_address, created_at
                        ) VALUES (?, ?, ?, 'project_staff', ?, 'assign', ?, ?, NOW())
                    ");
                    $desc = "Assigned " . $staffName . " as " . $assign_role . " to project " . $projectName;
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $project_staff_id, $desc, $ip);
                    $log->execute();

                    $msg = "Staff assigned successfully.";
                    $msg_type = 'success';

                    $_POST = [];
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
    WHERE company_id = ?
      AND status = 'active'
      AND role IN ('safety_officer', 'supervisor')
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
    SELECT
        ps.project_staff_id,
        ps.role,
        ps.status,
        ps.assigned_at,
        p.project_name,
        p.site_name,
        u.full_name,
        u.email
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
include '../includes/sidebar.php';
?>

<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?>">
    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">New Assignment</h4>

    <form method="POST" class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Select Project *</label>
            <select name="project_id" class="form-select" required>
                <option value="">Choose Project</option>
                <?php foreach ($projects as $p): ?>
                <option value="<?= (int)$p['project_id']; ?>"
                    <?= ((int)($_POST['project_id'] ?? 0) === (int)$p['project_id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($p['project_name'] . ' - ' . $p['site_name'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Select Staff *</label>
            <select name="staff_user_id" class="form-select" required>
                <option value="">Choose User</option>
                <?php foreach ($staff_users as $u): ?>
                <option value="<?= (int)$u['user_id']; ?>"
                    <?= ((int)($_POST['staff_user_id'] ?? 0) === (int)$u['user_id']) ? 'selected' : ''; ?>>
                    <?= htmlspecialchars($u['full_name'] . ' (' . $u['role'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Assignment Role *</label>
            <select name="role" class="form-select" required>
                <option value="">Choose Role</option>
                <option value="safety_officer" <?= (($_POST['role'] ?? '') === 'safety_officer') ? 'selected' : ''; ?>>
                    Safety Officer</option>
                <option value="supervisor" <?= (($_POST['role'] ?? '') === 'supervisor') ? 'selected' : ''; ?>>
                    Supervisor</option>
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
                <?php if (!empty($assignments)): ?>
                <?php foreach ($assignments as $a): ?>
                <tr>
                    <td><?= (int)$a['project_staff_id']; ?></td>
                    <td><?= htmlspecialchars($a['project_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($a['site_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($a['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <span class="badge bg-secondary">
                            <?= htmlspecialchars(str_replace('_', ' ', $a['role']), ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td>
                        <?php $badge = ($a['status'] === 'active') ? 'success' : 'danger'; ?>
                        <span class="badge bg-<?= $badge; ?>">
                            <?= htmlspecialchars($a['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($a['assigned_at'], ENT_QUOTES, 'UTF-8'); ?></td>
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

<?php include '../includes/footer.php'; ?>