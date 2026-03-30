<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['supervisor']);

$pageTitle = "Assigned Issues";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$status_filter = trim($_GET['status'] ?? '');
$search        = trim($_GET['search'] ?? '');

$sql = "
    SELECT 
        i.issue_id,
        i.project_id,
        i.inspection_id,
        i.issue_code,
        i.title,
        i.description,
        i.severity,
        i.due_date,
        i.status,
        i.fixed_date,
        i.created_at,
        p.project_name,
        p.site_name,
        ins.inspection_date,
        u.full_name AS safety_officer_name
    FROM issues i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN inspections ins ON i.inspection_id = ins.inspection_id
    LEFT JOIN users u ON ins.conducted_by = u.user_id
    WHERE i.company_id = ?
      AND i.assigned_supervisor_id = ?
";
$params = [$company_id, $user_id];
$types  = "ii";

if ($status_filter !== '' && in_array($status_filter, ['open', 'in_progress', 'recheck_pending', 'reopened', 'overdue', 'closed'], true)) {
    $sql .= " AND i.status = ? ";
    $params[] = $status_filter;
    $types .= "s";
}

if ($search !== '') {
    $sql .= " AND (i.title LIKE ? OR i.description LIKE ? OR p.project_name LIKE ? OR p.site_name LIKE ? OR i.issue_code LIKE ?) ";
    $like = "%" . $search . "%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= "sssss";
}

$sql .= " ORDER BY 
            CASE 
                WHEN i.status = 'overdue' THEN 1
                WHEN i.severity = 'Critical' THEN 2
                WHEN i.severity = 'High' THEN 3
                WHEN i.severity = 'Medium' THEN 4
                ELSE 5
            END,
            i.issue_id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$issues = [];
while ($row = $res->fetch_assoc()) {
    $issues[] = $row;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="card page-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-3">
            <label class="form-label">Status Filter</label>
            <select name="status" class="form-select">
                <option value="">All Statuses</option>
                <option value="open" <?= $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress
                </option>
                <option value="recheck_pending" <?= $status_filter === 'recheck_pending' ? 'selected' : ''; ?>>Recheck
                    Pending</option>
                <option value="reopened" <?= $status_filter === 'reopened' ? 'selected' : ''; ?>>Reopened</option>
                <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                <option value="closed" <?= $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
            </select>
        </div>

        <div class="col-md-6">
            <label class="form-label">Search</label>
            <input type="text" name="search" class="form-control"
                value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                placeholder="Search by issue, description, project, site or issue code">
        </div>

        <div class="col-md-3">
            <button type="submit" class="btn btn-main w-100">Filter Issues</button>
        </div>
    </form>
</div>

<div class="card page-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">My Issue Queue</h4>
        <span class="text-muted"><?= count($issues); ?> record(s)</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Project</th>
                    <th>Issue</th>
                    <th>Severity</th>
                    <th>Inspection Date</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Safety Officer</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($issues)): ?>
                <?php foreach ($issues as $issue): ?>
                <?php
                        $severity = strtolower((string)$issue['severity']);
                        $severityClass = 'secondary';
                        if ($severity === 'low') $severityClass = 'success';
                        elseif ($severity === 'medium') $severityClass = 'warning';
                        elseif ($severity === 'high') $severityClass = 'danger';
                        elseif ($severity === 'critical') $severityClass = 'dark';

                        $status = strtolower((string)$issue['status']);
                        $statusClass = 'secondary';
                        if ($status === 'open') $statusClass = 'primary';
                        elseif ($status === 'in_progress') $statusClass = 'info';
                        elseif ($status === 'recheck_pending') $statusClass = 'warning';
                        elseif ($status === 'reopened') $statusClass = 'danger';
                        elseif ($status === 'overdue') $statusClass = 'danger';
                        elseif ($status === 'closed') $statusClass = 'success';

                        $is_overdue = false;
                        if (!empty($issue['due_date']) && $issue['status'] !== 'closed') {
                            $is_overdue = (strtotime($issue['due_date']) < strtotime(date('Y-m-d')));
                        }
                        ?>
                <tr class="<?= $is_overdue ? 'table-danger' : ''; ?>">
                    <td>#<?= (int)$issue['issue_id']; ?></td>
                    <td><?= htmlspecialchars($issue['issue_code'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($issue['project_name'], ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                        <small
                            class="text-muted"><?= htmlspecialchars($issue['site_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                    </td>
                    <td>
                        <div class="fw-semibold"><?= htmlspecialchars($issue['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                        <small
                            class="text-muted"><?= htmlspecialchars($issue['description'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></small>
                    </td>
                    <td>
                        <span class="badge bg-<?= $severityClass; ?>">
                            <?= htmlspecialchars($issue['severity'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($issue['inspection_date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?= htmlspecialchars($issue['due_date'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                        <?php if ($is_overdue): ?>
                        <br><small class="text-danger fw-semibold">Overdue</small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge bg-<?= $statusClass; ?>">
                            <?= htmlspecialchars($issue['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($issue['safety_officer_name'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <?php if ($issue['status'] !== 'closed'): ?>
                        <a href="/safetrac/supervisor/issue_update.php?issue_id=<?= (int)$issue['issue_id']; ?>"
                            class="btn btn-sm btn-outline-primary">
                            Update Issue
                        </a>
                        <?php else: ?>
                        <span class="text-success fw-semibold">Closed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="10" class="text-center text-muted">No assigned issues found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>