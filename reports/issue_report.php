<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$company_id = (int)($_SESSION['company_id'] ?? 0);

$project_id = (int)($_GET['project_id'] ?? 0);
$status     = trim($_GET['status'] ?? '');
$severity   = trim($_GET['severity'] ?? '');

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

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
    SELECT 
        i.issue_id,
        i.issue_code,
        i.title,
        i.description,
        i.severity,
        i.status,
        i.due_date,
        i.fixed_date,
        i.closed_date,
        i.reopened_count,
        i.priority_rank,
        i.created_at,
        p.project_name,
        p.site_name,
        u.full_name AS supervisor_name
    FROM issues i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN users u ON i.assigned_supervisor_id = u.user_id
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: #f5f7fa;
        font-family: Segoe UI, sans-serif;
        color: #0f172a;
    }

    .report-wrap {
        max-width: 1280px;
        margin: 30px auto;
        padding: 0 12px;
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

    .report-sub {
        color: #64748b;
    }

    .filter-card {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 14px;
        padding: 16px;
    }

    .summary-box {
        background: linear-gradient(120deg, #0b1f3a, #163a63);
        color: #fff;
        border-radius: 16px;
        padding: 18px;
        margin-bottom: 20px;
    }

    .table thead th {
        white-space: nowrap;
    }

    .badge-low {
        background: #16a34a;
        color: #fff;
    }

    .badge-medium {
        background: #f97316;
        color: #fff;
    }

    .badge-high {
        background: #dc2626;
        color: #fff;
    }

    .badge-critical {
        background: #111827;
        color: #fff;
    }

    .badge-status {
        background: #0b1f3a;
        color: #fff;
    }

    .issue-desc {
        min-width: 260px;
        white-space: normal;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        body {
            background: #fff;
        }

        .report-wrap {
            max-width: 100%;
            margin: 0;
            padding: 0;
        }

        .report-card {
            box-shadow: none;
            border: none;
            border-radius: 0;
            padding: 0;
        }
    }
    </style>
</head>

<body>
    <div class="report-wrap">
        <div class="report-card">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <div>
                    <h2 class="report-title mb-1">Issue Report</h2>
                    <div class="report-sub">SafeTrack Construction Safety System</div>
                </div>

                <div class="no-print d-flex gap-2">
                    <a href="/safetrac/admin/analytics.php" class="btn btn-outline-secondary">Back</a>
                    <button onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
                </div>
            </div>

            <div class="summary-box">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div><strong>Total Records:</strong> <?= count($rows); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div><strong>Project Filter:</strong>
                            <?= $project_id > 0 ? 'Selected Project' : 'All Projects'; ?></div>
                    </div>
                    <div class="col-md-3">
                        <div><strong>Status Filter:</strong> <?= e($status ?: 'All'); ?></div>
                    </div>
                    <div class="col-md-3">
                        <div><strong>Severity Filter:</strong> <?= e($severity ?: 'All'); ?></div>
                    </div>
                </div>
            </div>

            <form method="GET" class="filter-card row g-3 mb-4 no-print">
                <div class="col-md-4">
                    <label class="form-label">Project</label>
                    <select name="project_id" class="form-select">
                        <option value="0">All Projects</option>
                        <?php foreach ($projects as $p): ?>
                        <option value="<?= (int)$p['project_id']; ?>"
                            <?= $project_id === (int)$p['project_id'] ? 'selected' : ''; ?>>
                            <?= e($p['project_name'] . ' - ' . $p['site_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['open','in_progress','recheck_pending','reopened','overdue','closed'] as $s): ?>
                        <option value="<?= e($s); ?>" <?= $status === $s ? 'selected' : ''; ?>>
                            <?= e(ucfirst(str_replace('_', ' ', $s))); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Severity</label>
                    <select name="severity" class="form-select">
                        <option value="">All Severities</option>
                        <?php foreach (['Low','Medium','High','Critical'] as $sev): ?>
                        <option value="<?= e($sev); ?>" <?= $severity === $sev ? 'selected' : ''; ?>>
                            <?= e($sev); ?>
                        </option>
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
                            <th>Code</th>
                            <th>Project</th>
                            <th>Issue</th>
                            <th>Severity</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Fixed Date</th>
                            <th>Closed Date</th>
                            <th>Reopened Count</th>
                            <th>Priority</th>
                            <th>Supervisor</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r): ?>
                        <?php
                                $severityClass = 'badge-medium';
                                if ($r['severity'] === 'Low') {
                                    $severityClass = 'badge-low';
                                } elseif ($r['severity'] === 'High') {
                                    $severityClass = 'badge-high';
                                } elseif ($r['severity'] === 'Critical') {
                                    $severityClass = 'badge-critical';
                                }
                                ?>
                        <tr>
                            <td>#<?= (int)$r['issue_id']; ?></td>
                            <td><?= e($r['issue_code'] ?: '-'); ?></td>
                            <td><?= e($r['project_name'] . ' - ' . $r['site_name']); ?></td>
                            <td class="issue-desc">
                                <?= e($r['title']); ?><br>
                                <small class="text-muted"><?= e($r['description'] ?: '-'); ?></small>
                            </td>
                            <td>
                                <span class="badge <?= $severityClass; ?>">
                                    <?= e($r['severity']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-status">
                                    <?= e($r['status']); ?>
                                </span>
                            </td>
                            <td><?= e($r['due_date'] ?: '-'); ?></td>
                            <td><?= e($r['fixed_date'] ?: '-'); ?></td>
                            <td><?= e($r['closed_date'] ?: '-'); ?></td>
                            <td><?= (int)$r['reopened_count']; ?></td>
                            <td><?= (int)$r['priority_rank']; ?></td>
                            <td><?= e($r['supervisor_name'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="12" class="text-center text-muted">No report data found.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>

</html>