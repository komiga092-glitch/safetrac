<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$pageTitle = "Manage Projects";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_project'])) {
    $project_name = trim($_POST['project_name'] ?? '');
    $site_name    = trim($_POST['site_name'] ?? '');
    $location     = trim($_POST['location'] ?? '');
    $start_date   = trim($_POST['start_date'] ?? '');
    $end_date     = trim($_POST['end_date'] ?? '');

    if ($project_name === '' || $site_name === '' || $location === '') {
        $msg = "Please fill all required fields.";
        $msg_type = 'danger';
    } else {
        $check = $conn->prepare("
            SELECT project_id
            FROM projects
            WHERE company_id = ? AND project_name = ?
            LIMIT 1
        ");
        $check->bind_param("is", $company_id, $project_name);
        $check->execute();
        $checkRes = $check->get_result();

        if ($checkRes->num_rows > 0) {
            $msg = "Project name already exists.";
            $msg_type = 'warning';
        } else {
            $status = 'active';

            $stmt = $conn->prepare("
                INSERT INTO projects (
                    company_id, project_name, site_name, location, start_date, end_date, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "issssssi",
                $company_id,
                $project_name,
                $site_name,
                $location,
                $start_date,
                $end_date,
                $status,
                $user_id
            );

            if ($stmt->execute()) {
                $project_id = (int)$stmt->insert_id;

                $log = $conn->prepare("
                    INSERT INTO activity_logs (
                        company_id, project_id, user_id, module_name, related_id,
                        action_type, action_description, ip_address, created_at
                    ) VALUES (?, ?, ?, 'project', ?, 'create', ?, ?, NOW())
                ");
                $desc = "Created new project: " . $project_name;
                $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $project_id, $desc, $ip);
                $log->execute();

                $msg = "Project created successfully.";
                $msg_type = 'success';

                // Clear form values after success
                $_POST = [];
            } else {
                $msg = "Project creation failed.";
                $msg_type = 'danger';
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $project_id = (int)($_POST['project_id'] ?? 0);
    $new_status = trim($_POST['new_status'] ?? '');

    if ($project_id > 0 && in_array($new_status, ['active', 'completed', 'inactive'], true)) {
        $stmt = $conn->prepare("
            UPDATE projects
            SET status = ?
            WHERE project_id = ? AND company_id = ?
        ");
        $stmt->bind_param("sii", $new_status, $project_id, $company_id);

        if ($stmt->execute()) {
            $log = $conn->prepare("
                INSERT INTO activity_logs (
                    company_id, project_id, user_id, module_name, related_id,
                    action_type, action_description, ip_address, created_at
                ) VALUES (?, ?, ?, 'project', ?, 'status_update', ?, ?, NOW())
            ");
            $desc = "Updated project status to: " . $new_status;
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $project_id, $desc, $ip);
            $log->execute();

            $msg = "Project status updated.";
            $msg_type = 'success';
        } else {
            $msg = "Status update failed.";
            $msg_type = 'danger';
        }
    }
}

$projects = [];
$stmt = $conn->prepare("
    SELECT project_id, project_name, site_name, location, start_date, end_date, status, created_at
    FROM projects
    WHERE company_id = ?
    ORDER BY project_id DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $projects[] = $row;
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
    <h4 class="mb-3">Create New Project / Site</h4>

    <form method="POST" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Project Name *</label>
            <input type="text" name="project_name" class="form-control"
                value="<?= htmlspecialchars($_POST['project_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Site Name *</label>
            <input type="text" name="site_name" class="form-control"
                value="<?= htmlspecialchars($_POST['site_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="col-md-6">
            <label class="form-label">Location *</label>
            <input type="text" name="location" class="form-control"
                value="<?= htmlspecialchars($_POST['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        </div>

        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" class="form-control"
                value="<?= htmlspecialchars($_POST['start_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" class="form-control"
                value="<?= htmlspecialchars($_POST['end_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="col-12">
            <button type="submit" name="add_project" class="btn btn-main">Create Project</button>
        </div>
    </form>
</div>

<div class="card page-card p-4">
    <h4 class="mb-3">All Projects</h4>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project</th>
                    <th>Site</th>
                    <th>Location</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($projects)): ?>
                <?php foreach ($projects as $p): ?>
                <tr>
                    <td><?= (int)$p['project_id']; ?></td>
                    <td><?= htmlspecialchars($p['project_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($p['site_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($p['location'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?= htmlspecialchars($p['start_date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                        <br>
                        <small class="text-muted">to
                            <?= htmlspecialchars($p['end_date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></small>
                    </td>
                    <td>
                        <?php
                                $status = strtolower($p['status']);
                                $badge = 'secondary';
                                if ($status === 'active') $badge = 'success';
                                if ($status === 'completed') $badge = 'primary';
                                if ($status === 'inactive') $badge = 'danger';
                                ?>
                        <span class="badge bg-<?= $badge; ?> text-uppercase">
                            <?= htmlspecialchars($p['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($p['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="project_id" value="<?= (int)$p['project_id']; ?>">
                            <select name="new_status" class="form-select form-select-sm">
                                <option value="active" <?= $p['status'] === 'active' ? 'selected' : ''; ?>>Active
                                </option>
                                <option value="completed" <?= $p['status'] === 'completed' ? 'selected' : ''; ?>>
                                    Completed</option>
                                <option value="inactive" <?= $p['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive
                                </option>
                            </select>
                            <button type="submit" name="change_status"
                                class="btn btn-sm btn-outline-primary">Save</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No projects found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>