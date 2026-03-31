<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$pageTitle = "Analytics Dashboard";

$company_id = (int)($_SESSION['company_id'] ?? 0);
$user_id    = (int)($_SESSION['user_id'] ?? 0);

function getSingleCount($conn, $sql, $types = '', $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return (int)($row['total'] ?? 0);
}

$total_projects = getSingleCount($conn, "SELECT COUNT(*) AS total FROM projects WHERE company_id = ?", "i", [$company_id]);
$total_inspections = getSingleCount($conn, "SELECT COUNT(*) AS total FROM inspections WHERE company_id = ?", "i", [$company_id]);
$total_open_issues = getSingleCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND status IN ('open','in_progress','reopened','recheck_pending','overdue')", "i", [$company_id]);
$total_closed_issues = getSingleCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND status = 'closed'", "i", [$company_id]);
$total_overdue_issues = getSingleCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND due_date IS NOT NULL AND due_date < CURDATE() AND status <> 'closed'", "i", [$company_id]);
$total_critical_issues = getSingleCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND severity = 'Critical' AND status <> 'closed'", "i", [$company_id]);

$project_scores = [];
$stmt = $conn->prepare("
    SELECT 
        p.project_id,
        p.project_name,
        p.site_name,
        COUNT(i.inspection_id) AS total_inspections,
        AVG(i.overall_score) AS avg_score
    FROM projects p
    LEFT JOIN inspections i ON p.project_id = i.project_id
    WHERE p.company_id = ?
    GROUP BY p.project_id, p.project_name, p.site_name
    ORDER BY avg_score DESC, p.project_id DESC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $avg = (float)($row['avg_score'] ?? 0);
    $risk = 'Risk';
    if ($avg >= 90) {
        $risk = 'Safe';
    } elseif ($avg >= 75) {
        $risk = 'Moderate';
    }

    $row['risk_level'] = $risk;
    $project_scores[] = $row;
}

$recent_inspections = [];
$stmt2 = $conn->prepare("
    SELECT i.inspection_id, i.inspection_code, i.inspection_date, i.overall_score, i.risk_level, i.status AS overall_status,
           p.project_name, p.site_name,
           u.full_name AS officer_name
    FROM inspections i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN users u ON i.conducted_by = u.user_id
    WHERE i.company_id = ?
    ORDER BY i.inspection_id DESC
    LIMIT 10
");
$stmt2->bind_param("i", $company_id);
$stmt2->execute();
$res2 = $stmt2->get_result();
while ($row = $res2->fetch_assoc()) {
    $recent_inspections[] = $row;
}

$recent_issues = [];
$stmt3 = $conn->prepare("
    SELECT i.issue_id, i.title AS issue_title, i.severity, i.status, i.due_date,
           p.project_name, p.site_name,
           u.full_name AS supervisor_name
    FROM issues i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN users u ON i.assigned_supervisor_id = u.user_id
    WHERE i.company_id = ?
    ORDER BY i.issue_id DESC
    LIMIT 10
");
$stmt3->bind_param("i", $company_id);
$stmt3->execute();
$res3 = $stmt3->get_result();
while ($row = $res3->fetch_assoc()) {
    $recent_issues[] = $row;
}

include '../includes/header.php';
?>



<?php include '../includes/sidebar.php'; ?>
<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card bg-blue">
            <h6>Total Projects</h6>
            <h2><?= $total_projects; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-green">
            <h6>Total Inspections</h6>
            <h2><?= $total_inspections; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-orange">
            <h6>Open Issues</h6>
            <h2><?= $total_open_issues; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-red">
            <h6>Critical Issues</h6>
            <h2><?= $total_critical_issues; ?></h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card bg-green">
            <h6>Closed Issues</h6>
            <h2><?= $total_closed_issues; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-red">
            <h6>Overdue Issues</h6>
            <h2><?= $total_overdue_issues; ?></h2>
        </div>
    </div>
</div>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">Project-wise Safety Score</h4>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Site</th>
                    <th>Total Inspections</th>
                    <th>Average Score</th>
                    <th>Risk Level</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($project_scores)): ?>
                <?php foreach ($project_scores as $p): ?>
                <?php
                                        $riskClass = 'danger';
                                        if ($p['risk_level'] === 'Safe') $riskClass = 'success';
                                        elseif ($p['risk_level'] === 'Moderate') $riskClass = 'warning';
                                        ?>
                <tr>
                    <td><?= htmlspecialchars($p['project_name']); ?></td>
                    <td><?= htmlspecialchars($p['site_name']); ?></td>
                    <td><?= (int)$p['total_inspections']; ?></td>
                    <td><?= number_format((float)$p['avg_score'], 2); ?>%</td>
                    <td><span class="badge bg-<?= $riskClass; ?>"><?= htmlspecialchars($p['risk_level']); ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted">No analytics data found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">Recent Inspections</h4>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project</th>
                    <th>Officer</th>
                    <th>Date</th>
                    <th>Score</th>
                    <th>Risk</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_inspections)): ?>
                <?php foreach ($recent_inspections as $r): ?>
                <tr>
                    <td>#<?= (int)$r['inspection_id']; ?></td>
                    <td><?= htmlspecialchars($r['project_name'] . ' - ' . $r['site_name']); ?></td>
                    <td><?= htmlspecialchars($r['officer_name'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($r['inspection_date']); ?></td>
                    <td><?= number_format((float)$r['overall_score'], 2); ?>%</td>
                    <td><?= htmlspecialchars($r['risk_level']); ?></td>
                    <td><?= htmlspecialchars($r['overall_status']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No inspections found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card page-card p-4">
    <h4 class="mb-3">Recent Issues</h4>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Project</th>
                    <th>Issue</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Due Date</th>
                    <th>Assigned Supervisor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_issues)): ?>
                <?php foreach ($recent_issues as $r): ?>
                <tr>
                    <td>#<?= (int)$r['issue_id']; ?></td>
                    <td><?= htmlspecialchars($r['project_name'] . ' - ' . $r['site_name']); ?></td>
                    <td><?= htmlspecialchars($r['issue_title']); ?></td>
                    <td><?= htmlspecialchars($r['severity']); ?></td>
                    <td><?= htmlspecialchars($r['status']); ?></td>
                    <td><?= htmlspecialchars($r['due_date'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($r['supervisor_name'] ?: '-'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted">No issues found.</td>
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