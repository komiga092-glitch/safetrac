<?php
require_once 'includes/session.php';

require_once 'config/db.php';

$pageTitle = "Dashboard";
include 'includes/header.php';

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);
$role       = $_SESSION['role'] ?? '';
$full_name  = $_SESSION['full_name'] ?? 'User';

function getCount($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return (int)($res['total'] ?? 0);
}

function getAverageScore($conn, $sql, $types = '', $params = [])
{
    $stmt = $conn->prepare($sql);
    if ($types !== '' && !empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    return (float)($res['avg_score'] ?? 0);
}

$total_projects        = getCount($conn, "SELECT COUNT(*) AS total FROM projects WHERE company_id = ?", "i", [$company_id]);
$total_users           = getCount($conn, "SELECT COUNT(*) AS total FROM users WHERE company_id = ?", "i", [$company_id]);
$unread_notifications  = getCount($conn, "SELECT COUNT(*) AS total FROM notifications WHERE company_id = ? AND user_id = ? AND is_read = 0", "ii", [$company_id, $user_id]);

$total_inspections     = 0;
$total_open_issues     = 0;
$total_closed_issues   = 0;
$total_overdue_issues  = 0;
$total_recheck_pending = 0;
$total_critical_issues = 0;
$safety_score          = 0;

if ($role === 'company_admin') {
    $total_inspections = getCount($conn, "SELECT COUNT(*) AS total FROM inspections WHERE company_id = ?", "i", [$company_id]);
    $total_open_issues = getCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND status IN ('open','in_progress','reopened','overdue','recheck_pending')", "i", [$company_id]);
    $total_closed_issues = getCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND status = 'closed'", "i", [$company_id]);
    $total_overdue_issues = getCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND due_date IS NOT NULL AND due_date < CURDATE() AND status <> 'closed'", "i", [$company_id]);
    $total_recheck_pending = getCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND status = 'recheck_pending'", "i", [$company_id]);
    $total_critical_issues = getCount($conn, "SELECT COUNT(*) AS total FROM issues WHERE company_id = ? AND severity = 'Critical' AND status <> 'closed'", "i", [$company_id]);

    $safety_score = getAverageScore($conn, "
        SELECT AVG(overall_score) AS avg_score
        FROM inspections
        WHERE company_id = ?
    ", "i", [$company_id]);
}

if ($role === 'safety_officer') {
    $total_inspections = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM inspections
        WHERE company_id = ? AND conducted_by = ?
    ", "ii", [$company_id, $user_id]);

    $total_open_issues = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues i
        INNER JOIN inspections ins ON i.inspection_id = ins.inspection_id
        WHERE i.company_id = ?
          AND ins.conducted_by = ?
          AND i.status IN ('open','in_progress','reopened','overdue','recheck_pending')
    ", "ii", [$company_id, $user_id]);

    $total_closed_issues = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues i
        INNER JOIN inspections ins ON i.inspection_id = ins.inspection_id
        WHERE i.company_id = ?
          AND ins.conducted_by = ?
          AND i.status = 'closed'
    ", "ii", [$company_id, $user_id]);

    $total_recheck_pending = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues i
        INNER JOIN inspections ins ON i.inspection_id = ins.inspection_id
        WHERE i.company_id = ?
          AND ins.conducted_by = ?
          AND i.status = 'recheck_pending'
    ", "ii", [$company_id, $user_id]);

    $safety_score = getAverageScore($conn, "
        SELECT AVG(overall_score) AS avg_score
        FROM inspections
        WHERE company_id = ?
          AND conducted_by = ?
    ", "ii", [$company_id, $user_id]);
}

if ($role === 'supervisor') {
    $total_open_issues = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues
        WHERE company_id = ?
          AND assigned_supervisor_id = ?
          AND status IN ('open','in_progress','reopened','overdue')
    ", "ii", [$company_id, $user_id]);

    $total_closed_issues = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues
        WHERE company_id = ?
          AND assigned_supervisor_id = ?
          AND status = 'closed'
    ", "ii", [$company_id, $user_id]);

    $total_overdue_issues = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues
        WHERE company_id = ?
          AND assigned_supervisor_id = ?
          AND due_date IS NOT NULL
          AND due_date < CURDATE()
          AND status <> 'closed'
    ", "ii", [$company_id, $user_id]);

    $total_recheck_pending = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues
        WHERE company_id = ?
          AND assigned_supervisor_id = ?
          AND status = 'recheck_pending'
    ", "ii", [$company_id, $user_id]);

    $total_critical_issues = getCount($conn, "
        SELECT COUNT(*) AS total
        FROM issues
        WHERE company_id = ?
          AND assigned_supervisor_id = ?
          AND severity = 'Critical'
          AND status <> 'closed'
    ", "ii", [$company_id, $user_id]);

    $safety_score = getAverageScore($conn, "
        SELECT AVG(ins.overall_score) AS avg_score
        FROM issues i
        INNER JOIN inspections ins ON i.inspection_id = ins.inspection_id
        WHERE i.company_id = ?
          AND i.assigned_supervisor_id = ?
    ", "ii", [$company_id, $user_id]);
}

$safety_score = round($safety_score, 2);

$risk_label = 'Risk';
$meter_color = '#dc2626';

if ($safety_score >= 90) {
    $risk_label = 'Safe';
    $meter_color = '#16a34a';
} elseif ($safety_score >= 75) {
    $risk_label = 'Moderate';
    $meter_color = '#f97316';
}

$meter_percent = max(0, min(100, $safety_score));
$needle_angle = -180 + (($meter_percent / 100) * 180);

$project_score_cards = [];

if ($role === 'company_admin') {
    $stmtProjects = $conn->prepare("
        SELECT 
            p.project_id,
            p.project_name,
            p.site_name,
            p.location,
            p.status,
            COUNT(i.inspection_id) AS total_inspections,
            AVG(i.overall_score) AS avg_score
        FROM projects p
        LEFT JOIN inspections i ON p.project_id = i.project_id
        WHERE p.company_id = ?
        GROUP BY p.project_id, p.project_name, p.site_name, p.location, p.status
        ORDER BY p.project_id DESC
    ");
    $stmtProjects->bind_param("i", $company_id);
    $stmtProjects->execute();
    $resProjects = $stmtProjects->get_result();

    while ($row = $resProjects->fetch_assoc()) {
        $avg_score = round((float)($row['avg_score'] ?? 0), 2);

        $project_risk = 'Risk';
        $gauge_color = '#dc2626';

        if ($avg_score >= 90) {
            $project_risk = 'Safe';
            $gauge_color = '#16a34a';
        } elseif ($avg_score >= 75) {
            $project_risk = 'Moderate';
            $gauge_color = '#f97316';
        }

        $mini_percent = max(0, min(100, $avg_score));
        $mini_angle = -180 + (($mini_percent / 100) * 180);

        $row['avg_score'] = $avg_score;
        $row['risk_level'] = $project_risk;
        $row['gauge_color'] = $gauge_color;
        $row['mini_percent'] = $mini_percent;
        $row['mini_angle'] = $mini_angle;

        $project_score_cards[] = $row;
    }
}

$trend_labels = [];
$trend_scores = [];

if ($role === 'company_admin') {
    $trendStmt = $conn->prepare("
        SELECT 
            DATE_FORMAT(inspection_date, '%b') AS month_name,
            MONTH(inspection_date) AS month_no,
            AVG(overall_score) AS avg_score
        FROM inspections
        WHERE company_id = ?
          AND YEAR(inspection_date) = YEAR(CURDATE())
        GROUP BY MONTH(inspection_date), DATE_FORMAT(inspection_date, '%b')
        ORDER BY MONTH(inspection_date) ASC
    ");
    $trendStmt->bind_param("i", $company_id);
    $trendStmt->execute();
    $trendRes = $trendStmt->get_result();

    while ($row = $trendRes->fetch_assoc()) {
        $trend_labels[] = $row['month_name'];
        $trend_scores[] = round((float)$row['avg_score'], 2);
    }
}

$issue_chart_labels = ['Open', 'Closed', 'Critical', 'Overdue'];
$issue_chart_values = [
    $total_open_issues,
    $total_closed_issues,
    $total_critical_issues,
    $total_overdue_issues
];

include 'includes/sidebar.php';
?>

<div class="card page-card p-4 mb-4">
    <h3 class="mb-2">Welcome to SafeTrack</h3>
    <p class="text-muted mb-0">Professional multi-company construction safety inspection and issue tracking system.</p>
</div>

<div class="row g-4 mb-4">
    <?php if ($role === 'company_admin'): ?>
    <div class="col-md-3">
        <div class="stat-card bg-blue">
            <h6>Total Projects</h6>
            <h2><?= $total_projects; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-green">
            <h6>Total Users</h6>
            <h2><?= $total_users; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-orange">
            <h6>Total Inspections</h6>
            <h2><?= $total_inspections; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-red">
            <h6>Critical Issues</h6>
            <h2><?= $total_critical_issues; ?></h2>
        </div>
    </div>

    <div class="col-md-3">
        <div class="stat-card bg-orange">
            <h6>Open Issues</h6>
            <h2><?= $total_open_issues; ?></h2>
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
    <div class="col-md-3">
        <div class="stat-card bg-blue">
            <h6>Unread Alerts</h6>
            <h2><?= $unread_notifications; ?></h2>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($role === 'safety_officer'): ?>
    <div class="col-md-3">
        <div class="stat-card bg-blue">
            <h6>My Inspections</h6>
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
            <h6>Recheck Pending</h6>
            <h2><?= $total_recheck_pending; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-green">
            <h6>Closed Issues</h6>
            <h2><?= $total_closed_issues; ?></h2>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($role === 'supervisor'): ?>
    <div class="col-md-3">
        <div class="stat-card bg-orange">
            <h6>My Open Issues</h6>
            <h2><?= $total_open_issues; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-red">
            <h6>Overdue Issues</h6>
            <h2><?= $total_overdue_issues; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-blue">
            <h6>Recheck Pending</h6>
            <h2><?= $total_recheck_pending; ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card bg-green">
            <h6>Closed Issues</h6>
            <h2><?= $total_closed_issues; ?></h2>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="needle-meter-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0">Safety Score Meter</h4>
                <span class="badge" style="background: <?= $meter_color; ?>; color:#fff;">
                    <?= htmlspecialchars($risk_label, ENT_QUOTES, 'UTF-8'); ?>
                </span>
            </div>

            <div class="gauge-wrap">
                <div class="gauge-box">
                    <svg class="gauge-svg" viewBox="0 0 340 220">
                        <path d="M 40 170 A 130 130 0 0 1 111 57" fill="none" stroke="#dc2626" stroke-width="22"
                            stroke-linecap="round" />
                        <path d="M 111 57 A 130 130 0 0 1 229 57" fill="none" stroke="#f97316" stroke-width="22"
                            stroke-linecap="round" />
                        <path d="M 229 57 A 130 130 0 0 1 300 170" fill="none" stroke="#16a34a" stroke-width="22"
                            stroke-linecap="round" />
                        <path d="M 40 170 A 130 130 0 0 1 300 170" fill="none" stroke="#e5e7eb" stroke-width="4"
                            stroke-linecap="round" />
                    </svg>

                    <div class="gauge-score-box">
                        <div class="gauge-score-value"><?= number_format($meter_percent, 1); ?>%</div>
                        <div class="gauge-score-label" style="color: <?= $meter_color; ?>;">
                            <?= htmlspecialchars($risk_label, ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    </div>

                    <div class="gauge-needle" style="transform: rotate(<?= $needle_angle; ?>deg);"></div>
                    <div class="gauge-center-cap"></div>

                    <div class="gauge-scale">
                        <span>0</span>
                        <span>50</span>
                        <span>100</span>
                    </div>
                </div>
            </div>

            <div class="gauge-legend">
                <div class="gauge-legend-item"><span class="gauge-dot risk"></span> Risk</div>
                <div class="gauge-legend-item"><span class="gauge-dot moderate"></span> Moderate</div>
                <div class="gauge-legend-item"><span class="gauge-dot safe"></span> Safe</div>
            </div>

            <div class="row g-3 mt-3">
                <div class="col-md-4">
                    <div class="gauge-note-box text-center">
                        <div class="small text-muted">0–74%</div>
                        <div class="fw-bold text-danger">Risk</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gauge-note-box text-center">
                        <div class="small text-muted">75–89%</div>
                        <div class="fw-bold text-warning">Moderate</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="gauge-note-box text-center">
                        <div class="small text-muted">90–100%</div>
                        <div class="fw-bold text-success">Safe</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card page-card p-4 h-100">
            <h4 class="mb-3">Safety Overview</h4>

            <div class="mb-3">
                <span class="quick-chip">Open Issues: <?= $total_open_issues; ?></span>
                <span class="quick-chip">Closed Issues: <?= $total_closed_issues; ?></span>
                <span class="quick-chip">Unread Alerts: <?= $unread_notifications; ?></span>

                <?php if ($role === 'company_admin' || $role === 'supervisor'): ?>
                <span class="quick-chip">Critical Issues: <?= $total_critical_issues; ?></span>
                <span class="quick-chip">Overdue: <?= $total_overdue_issues; ?></span>
                <?php endif; ?>

                <?php if ($role === 'company_admin' || $role === 'safety_officer' || $role === 'supervisor'): ?>
                <span class="quick-chip">Recheck Pending: <?= $total_recheck_pending; ?></span>
                <?php endif; ?>
            </div>

            <p class="text-muted">
                இந்த needle meter inspections average score அடிப்படையில் calculate ஆகும்.
                Needle right sideக்கு போனால் safety level நல்லா இருக்கு.
                left sideக்கு இருந்தா immediate corrective action தேவை.
            </p>

            <div class="mt-3">
                <a href="/safetrac/notifications.php" class="btn btn-outline-primary">
                    <i class="bi bi-bell me-1"></i> Open Notifications
                </a>

                <?php if ($role === 'company_admin'): ?>
                <a href="/safetrac/admin/analytics.php" class="btn btn-main ms-2">
                    <i class="bi bi-bar-chart-line me-1"></i> View Analytics
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($role === 'company_admin'): ?>
<div class="row g-4 mt-1">
    <div class="col-lg-8">
        <div class="chart-card">
            <h4 class="mb-3">Monthly Safety Trend</h4>
            <div class="chart-box">
                <canvas id="monthlySafetyTrendChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="chart-card">
            <h4 class="mb-3">Issue Status Overview</h4>
            <div class="chart-box-small">
                <canvas id="issueStatusChart"></canvas>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($role === 'company_admin'): ?>
<div class="card page-card p-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Project-wise Safety Score</h4>
        <span class="text-muted"><?= count($project_score_cards); ?> project(s)</span>
    </div>

    <div class="row g-4">
        <?php if (!empty($project_score_cards)): ?>
        <?php foreach ($project_score_cards as $project): ?>
        <div class="col-md-6 col-xl-4">
            <div class="project-mini-card">
                <div class="project-mini-header">
                    <div class="fw-bold"><?= htmlspecialchars($project['project_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <small><?= htmlspecialchars($project['site_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                </div>

                <div class="project-mini-body">
                    <div class="project-meta">
                        <i class="bi bi-geo-alt me-1"></i>
                        <?= htmlspecialchars($project['location'] ?: 'No location', ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <div class="mini-gauge-wrap">
                        <div class="mini-gauge-box">
                            <svg class="mini-gauge-svg" viewBox="0 0 190 120">
                                <path d="M 20 95 A 75 75 0 0 1 61 30" fill="none" stroke="#dc2626" stroke-width="14"
                                    stroke-linecap="round" />
                                <path d="M 61 30 A 75 75 0 0 1 129 30" fill="none" stroke="#f97316" stroke-width="14"
                                    stroke-linecap="round" />
                                <path d="M 129 30 A 75 75 0 0 1 170 95" fill="none" stroke="#16a34a" stroke-width="14"
                                    stroke-linecap="round" />
                            </svg>

                            <div class="mini-score-box">
                                <div class="mini-score-value"><?= number_format($project['mini_percent'], 1); ?>%</div>
                                <div class="mini-score-label" style="color: <?= $project['gauge_color']; ?>;">
                                    <?= htmlspecialchars($project['risk_level'], ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </div>

                            <div class="mini-gauge-needle"
                                style="transform: rotate(<?= $project['mini_angle']; ?>deg);"></div>
                            <div class="mini-gauge-cap"></div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="badge" style="background: <?= $project['gauge_color']; ?>; color:#fff;">
                            <?= htmlspecialchars($project['risk_level'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>

                        <span class="badge bg-secondary text-uppercase">
                            <?= htmlspecialchars($project['status'], ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </div>

                    <div class="project-stat-line">
                        <span>Total Inspections</span>
                        <strong><?= (int)$project['total_inspections']; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div class="col-12">
            <div class="alert alert-warning mb-0">
                No project safety score data found yet.
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card page-card p-4 mt-4">
    <h5 class="mb-3">Quick Access</h5>

    <?php if ($role === 'company_admin'): ?>
    <p class="mb-2">Manage projects, users, staff assignment, checklist master, and company-wide safety monitoring.</p>
    <?php elseif ($role === 'safety_officer'): ?>
    <p class="mb-2">Start inspections, review failed items, compare before/after photos, and complete recheck decisions.
    </p>
    <?php elseif ($role === 'supervisor'): ?>
    <p class="mb-2">Take corrective actions, upload after-photo evidence, and clear assigned issues quickly.</p>
    <?php endif; ?>
</div>

<?php if ($role === 'company_admin'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const trendLabels = <?= json_encode($trend_labels); ?>;
const trendScores = <?= json_encode($trend_scores); ?>;

const issueLabels = <?= json_encode($issue_chart_labels); ?>;
const issueValues = <?= json_encode($issue_chart_values); ?>;

const trendCtx = document.getElementById('monthlySafetyTrendChart');
if (trendCtx) {
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Average Safety Score',
                data: trendScores,
                fill: false,
                tension: 0.35,
                borderWidth: 3,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            plugins: {
                legend: {
                    display: true
                }
            },
            scales: {
                y: {
                    min: 0,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

const issueCtx = document.getElementById('issueStatusChart');
if (issueCtx) {
    new Chart(issueCtx, {
        type: 'doughnut',
        data: {
            labels: issueLabels,
            datasets: [{
                data: issueValues,
                borderWidth: 1
            }]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>