<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$company_id = (int)($_SESSION['company_id'] ?? 0);

$project_id = (int)($_GET['project_id'] ?? 0);
$status     = trim($_GET['status'] ?? '');
$severity   = trim($_GET['severity'] ?? '');

$projects = [];
$pstmt = $conn->prepare("
    SELECT project_id, project_name, site_name
    FROM projects
    WHERE company_id = ?
    ORDER BY project_name ASC
");
$pstmt->bind_param("i", $company_id);
$pstmt->execute();
$pres = $pstmt->get_result();
while ($row = $pres->fetch_assoc()) {
    $projects[] = $row;
}

$sql = "
    SELECT i.issue_id, i.issue_title, i.description, i.severity, i.status, i.due_date,
           i.fixed_date, i.closed_date, i.reopened_count, i.created_at,
           p.project_name, p.site_name,
           u.full_name AS supervisor_name
    FROM issues i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN users u ON i.assigned_to = u.user_id
    WHERE i.company_id = ?
";
$params = [$company_id];
$types  = "i";

if ($project_id > 0) {
    $sql .= " AND i.project_id = ? ";
    $params[] = $project_id;
    $types .= "i";
}
if ($status !== '') {
    $sql .= " AND i.status = ? ";
    $params[] = $status;
    $types .= "s";
}
if ($severity !== '') {
    $sql .= " AND i.severity = ? ";
    $params[] = $severity;
    $types .= "s";
}

$sql .= " ORDER BY i.issue_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$rows = [];
while ($row = $res->fetch_assoc()) {
    $rows[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Issue Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: #f5f7fa;
        font-family: Segoe UI, sans-serif;
    }

    .report-wrap {
        max-width: 1200px;
        margin: 30px auto;
    }

    .report-card {
        background: #fff;
        border-radius: 18px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, .08);
        padding: 24px;
    }

    .report-title {
        color: #0b1f3a;
        font-weight: 700;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        body {
            background: #fff;
        }

        .report-card {
            box-shadow: none;
            border: none;
        }
    }
    </style>
</head>

<body>
    <div class="report-wrap">
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="report-title mb-1">Issue Report</h2>
                    <div class="text-muted">SafeTrack Construction Safety System</div>
                </div>
                <div class="no-print">
                    <a href="../admin/analytics.php" class="btn btn-outline-secondary">Back</a>
                    <button onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
                </div>
            </div>

            <form method="GET" class="row g-3 mb-4 no-print">
                <div class="col-md-4">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="0">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= (int)$p['project_id']; ?>"
                            <?= $project_id === (int)$p['project_id'] ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($p['project_name'] . ' - ' . $p['site_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php
                    $statuses = ['open','in_progress','recheck_pending','reopened','overdue','closed'];
                    foreach ($statuses as $s):
                    ?>
                        <option value="<?= $s; ?>" <?= $status === $s ? 'selected' : ''; ?>>
                            <?= ucfirst(str_replace('_', ' ', $s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="">All Severities</option>
                        <?php foreach (['Low','Medium','High','Critical'] as $sev): ?>
                        <option value="<?= $sev; ?>" <?= $severity === $sev ? 'selected' : ''; ?>><?= $sev; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Generate</button>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Project</th>
                            <th>Issue</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Fixed Date</th>
                            <th>Closed Date</th>
                            <th>Reopened Count</th>
                            <th>Supervisor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td>#<?= (int)$r['issue_id']; ?></td>
                            <td><?= htmlspecialchars($r['project_name'] . ' - ' . $r['site_name']); ?></td>
                            <td>
                                <?= htmlspecialchars($r['issue_title']); ?><br>
                                <small class="text-muted"><?= htmlspecialchars($r['description']); ?></small>
                            </td>
                            <td><?= htmlspecialchars($r['severity']); ?></td>
                            <td><?= htmlspecialchars($r['status']); ?></td>
                            <td><?= htmlspecialchars($r['due_date'] ?: '-'); ?></td>
                            <td><?= htmlspecialchars($r['fixed_date'] ?: '-'); ?></td>
                            <td><?= htmlspecialchars($r['closed_date'] ?: '-'); ?></td>
                            <td><?= (int)$r['reopened_count']; ?></td>
                            <td><?= htmlspecialchars($r['supervisor_name'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">No report data found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</body>

</html>