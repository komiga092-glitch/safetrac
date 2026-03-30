<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['safety_officer']);

$pageTitle = "My Inspections";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$inspections = [];

$stmt = $conn->prepare("
    SELECT 
        i.inspection_id,
        i.project_id,
        i.inspection_code,
        i.inspection_date,
        i.inspection_time,
        i.overall_score,
        i.risk_level,
        i.status,
        i.created_at,
        p.project_name,
        p.site_name,
        p.location
    FROM inspections i
    INNER JOIN projects p ON i.project_id = p.project_id
    WHERE i.company_id = ? AND i.conducted_by = ?
    ORDER BY i.inspection_id DESC
");
$stmt->bind_param("ii", $company_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $inspections[] = $row;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="card page-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">Inspection History</h4>
        <span class="text-muted">Assigned Safety Officer Records</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Code</th>
                    <th>Project</th>
                    <th>Site</th>
                    <th>Location</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Score</th>
                    <th>Risk</th>
                    <th>Status</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($inspections)): ?>
                <?php foreach ($inspections as $row): ?>
                <tr>
                    <td><?= (int)$row['inspection_id']; ?></td>
                    <td><?= htmlspecialchars($row['inspection_code'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['project_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['site_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['location'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['inspection_date'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <td><?= htmlspecialchars($row['inspection_time'] ?: '-', ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                        <span class="fw-bold">
                            <?= number_format((float)$row['overall_score'], 2); ?>%
                        </span>
                    </td>
                    <td>
                        <?php
                                $risk = strtolower((string)($row['risk_level'] ?? ''));
                                $riskClass = 'secondary';
                                if ($risk === 'safe') {
                                    $riskClass = 'success';
                                } elseif ($risk === 'moderate') {
                                    $riskClass = 'warning';
                                } elseif ($risk === 'risk') {
                                    $riskClass = 'danger';
                                }
                                ?>
                        <span class="badge bg-<?= $riskClass; ?>">
                            <?= htmlspecialchars($row['risk_level'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-primary">
                            <?= htmlspecialchars($row['status'] ?: '-', ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($row['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="11" class="text-center text-muted">No inspections found yet.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>