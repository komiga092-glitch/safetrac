<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$company_id = (int)($_SESSION['company_id'] ?? 0);

$project_id = (int)($_GET['project_id'] ?? 0);
$from_date  = trim($_GET['from_date'] ?? '');
$to_date    = trim($_GET['to_date'] ?? '');

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
        i.inspection_id,
        i.inspection_code,
        i.inspection_date,
        i.inspection_time,
        i.overall_score,
        i.risk_level,
        i.status,
        i.remarks,
        i.created_at,
        p.project_name,
        p.site_name,
        p.location,
        u.full_name AS officer_name
    FROM inspections i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN users u ON i.conducted_by = u.user_id
    WHERE i.company_id = ?
";
$params = [$company_id];
$types  = "i";

if ($project_id > 0) {
    $sql .= " AND i.project_id = ? ";
    $params[] = $project_id;
    $types .= "i";
}
if ($from_date !== '') {
    $sql .= " AND i.inspection_date >= ? ";
    $params[] = $from_date;
    $types .= "s";
}
if ($to_date !== '') {
    $sql .= " AND i.inspection_date <= ? ";
    $params[] = $to_date;
    $types .= "s";
}

$sql .= " ORDER BY i.inspection_id DESC";

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
    <title>Inspection Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
    body {
        background: #f5f7fa;
        font-family: Segoe UI, sans-serif;
        color: #0f172a;
    }

    .report-wrap {
        max-width: 1200px;
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

    .badge-safe {
        background: #16a34a;
        color: #fff;
    }

    .badge-moderate {
        background: #f97316;
        color: #fff;
    }

    .badge-risk {
        background: #dc2626;
        color: #fff;
    }

    .badge-status {
        background: #0b1f3a;
        color: #fff;
    }

    .remarks-text {
        min-width: 220px;
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
                    <h2 class="report-title mb-1">Inspection Report</h2>
                    <div class="report-sub">SafeTrack Construction Safety System</div>
                </div>

                <div class="no-print d-flex gap-2">
                    <a href="/safetrac/admin/analytics.php" class="btn btn-outline-secondary">Back</a>
                    <button onclick="window.print()" class="btn btn-primary">Print / Save PDF</button>
                </div>
            </div>

            <div class="summary-box">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div><strong>Total Records:</strong> <?= count($rows); ?></div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Project Filter:</strong>
                            <?= $project_id > 0 ? 'Selected Project' : 'All Projects'; ?></div>
                    </div>
                    <div class="col-md-4">
                        <div><strong>Date Range:</strong> <?= e($from_date ?: 'Any'); ?> to <?= e($to_date ?: 'Any'); ?>
                        </div>
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
                    <label class="form-label">From Date</label>
                    <input type="date" name="from_date" value="<?= e($from_date); ?>" class="form-control">
                </div>

                <div class="col-md-3">
                    <label class="form-label">To Date</label>
                    <input type="date" name="to_date" value="<?= e($to_date); ?>" class="form-control">
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
                            <th>Officer</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Score</th>
                            <th>Risk</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                        <?php foreach ($rows as $r): ?>
                        <?php
                                $risk = strtolower((string)$r['risk_level']);
                                $riskClass = 'badge-risk';
                                if ($risk === 'safe') {
                                    $riskClass = 'badge-safe';
                                } elseif ($risk === 'moderate') {
                                    $riskClass = 'badge-moderate';
                                }
                                ?>
                        <tr>
                            <td>#<?= (int)$r['inspection_id']; ?></td>
                            <td><?= e($r['inspection_code'] ?: '-'); ?></td>
                            <td>
                                <?= e($r['project_name']); ?><br>
                                <small class="text-muted">
                                    <?= e($r['site_name']); ?> - <?= e($r['location']); ?>
                                </small>
                            </td>
                            <td><?= e($r['officer_name'] ?: '-'); ?></td>
                            <td><?= e($r['inspection_date']); ?></td>
                            <td><?= e($r['inspection_time'] ?: '-'); ?></td>
                            <td><?= number_format((float)$r['overall_score'], 2); ?>%</td>
                            <td>
                                <span class="badge <?= $riskClass; ?>">
                                    <?= e($r['risk_level'] ?: '-'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-status">
                                    <?= e($r['status'] ?: '-'); ?>
                                </span>
                            </td>
                            <td class="remarks-text"><?= e($r['remarks'] ?: '-'); ?></td>
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