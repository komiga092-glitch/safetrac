<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['safety_officer']);

$pageTitle = "Recheck Issue";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);
$issue_id   = (int)($_GET['issue_id'] ?? $_POST['issue_id'] ?? 0);

$msg = '';
$msg_type = 'danger';

if ($issue_id <= 0) {
    header("Location: recheck_issues.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT i.issue_id, i.company_id, i.project_id, i.inspection_id, i.response_id,
           i.issue_title, i.description, i.severity, i.assigned_to, i.due_date,
           i.status, i.fixed_date, i.closed_date, i.reopened_count, i.last_rechecked_at, i.created_at,
           p.project_name, p.site_name, p.location,
           ins.inspection_date, ins.inspected_by,
           su.full_name AS supervisor_name,
           su.email AS supervisor_email
    FROM issues i
    INNER JOIN inspections ins ON i.inspection_id = ins.inspection_id
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN users su ON i.assigned_to = su.user_id
    WHERE i.issue_id = ?
      AND i.company_id = ?
      AND ins.inspected_by = ?
      AND i.status IN ('recheck_pending', 'reopened')
    LIMIT 1
");
$stmt->bind_param("iii", $issue_id, $company_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$issue = $res->fetch_assoc();

if (!$issue) {
    header("Location: recheck_issues.php");
    exit;
}

$before_photos = [];
$bp = $conn->prepare("
    SELECT file_id, file_name, file_path, file_label, created_at
    FROM file_uploads
    WHERE company_id = ?
      AND module_name = 'inspection_response'
      AND related_id = ?
    ORDER BY file_id DESC
");
$response_id = (int)($issue['response_id'] ?? 0);
$bp->bind_param("ii", $company_id, $response_id);
$bp->execute();
$bpres = $bp->get_result();
while ($row = $bpres->fetch_assoc()) {
    $before_photos[] = $row;
}

$after_photos = [];
$ap = $conn->prepare("
    SELECT fu.file_id, fu.file_name, fu.file_path, fu.file_label, fu.created_at
    FROM file_uploads fu
    INNER JOIN issue_updates iu ON fu.related_id = iu.update_id
    WHERE fu.company_id = ?
      AND fu.module_name = 'issue_update'
      AND iu.issue_id = ?
    ORDER BY fu.file_id DESC
");
$ap->bind_param("ii", $company_id, $issue_id);
$ap->execute();
$apres = $ap->get_result();
while ($row = $apres->fetch_assoc()) {
    $after_photos[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recheck'])) {
    $recheck_result = trim($_POST['recheck_result'] ?? '');
    $comment_text   = trim($_POST['comment_text'] ?? '');
    $next_due_date  = trim($_POST['next_due_date'] ?? '');

    if (!in_array($recheck_result, ['closed', 'reopened'])) {
        $msg = "Please select a valid recheck result.";
    } elseif ($comment_text === '') {
        $msg = "Comment is required.";
    } elseif ($recheck_result === 'reopened' && $next_due_date === '') {
        $msg = "Next due date is required when reopening an issue.";
    } else {
        $old_status = $issue['status'];
        $new_status = $recheck_result;
        $update_type = 'recheck';

        $ustmt = $conn->prepare("
            INSERT INTO issue_updates (
                issue_id, updated_by, update_type, old_status, new_status,
                action_taken, fixed_date, comment_text, recheck_result, next_due_date, created_at
            ) VALUES (?, ?, ?, ?, ?, '', NULL, ?, ?, ?, NOW())
        ");
        $ustmt->bind_param(
            "iissssss",
            $issue_id,
            $user_id,
            $update_type,
            $old_status,
            $new_status,
            $comment_text,
            $recheck_result,
            $next_due_date
        );

        if ($ustmt->execute()) {
            $update_id = $ustmt->insert_id;

            $last_rechecked_at = date('Y-m-d H:i:s');
            $project_id = (int)$issue['project_id'];
            $assigned_to = (int)$issue['assigned_to'];

            if ($recheck_result === 'closed') {
                $closed_date = date('Y-m-d');

                $istmt = $conn->prepare("
                    UPDATE issues
                    SET status = 'closed',
                        closed_date = ?,
                        last_rechecked_at = ?
                    WHERE issue_id = ? AND company_id = ?
                ");
                $istmt->bind_param("ssii", $closed_date, $last_rechecked_at, $issue_id, $company_id);
                $istmt->execute();

                if ($assigned_to > 0) {
                    $ntitle = "Issue Closed";
                    $nmsg = "Issue #" . $issue_id . " has been verified and closed by Safety Officer.";

                    $nstmt = $conn->prepare("
                        INSERT INTO notifications (company_id, user_id, title, message, type, is_read, created_at)
                        VALUES (?, ?, ?, ?, 'issue_closed', 0, NOW())
                    ");
                    $nstmt->bind_param("iiss", $company_id, $assigned_to, $ntitle, $nmsg);
                    $nstmt->execute();
                }

                $log = $conn->prepare("
                    INSERT INTO activity_logs (
                        company_id, project_id, user_id, module_name, related_id,
                        action_type, action_description, ip_address, created_at
                    ) VALUES (?, ?, ?, 'issue', ?, 'close', ?, ?, NOW())
                ");
                $desc = "Safety Officer closed issue #" . $issue_id . " after recheck";
                $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $issue_id, $desc, $ip);
                $log->execute();

                $msg = "Issue closed successfully after recheck.";
                $msg_type = 'success';
            }

            if ($recheck_result === 'reopened') {
                $reopened_count = (int)$issue['reopened_count'] + 1;

                $istmt = $conn->prepare("
                    UPDATE issues
                    SET status = 'reopened',
                        reopened_count = ?,
                        last_rechecked_at = ?,
                        due_date = ?
                    WHERE issue_id = ? AND company_id = ?
                ");
                $istmt->bind_param("issii", $reopened_count, $last_rechecked_at, $next_due_date, $issue_id, $company_id);
                $istmt->execute();

                if ($assigned_to > 0) {
                    $ntitle = "Issue Reopened";
                    $nmsg = "Issue #" . $issue_id . " was reopened by Safety Officer. Please take corrective action again. New due date: " . $next_due_date;

                    $nstmt = $conn->prepare("
                        INSERT INTO notifications (company_id, user_id, title, message, type, is_read, created_at)
                        VALUES (?, ?, ?, ?, 'issue_reopened', 0, NOW())
                    ");
                    $nstmt->bind_param("iiss", $company_id, $assigned_to, $ntitle, $nmsg);
                    $nstmt->execute();
                }

                $log = $conn->prepare("
                    INSERT INTO activity_logs (
                        company_id, project_id, user_id, module_name, related_id,
                        action_type, action_description, ip_address, created_at
                    ) VALUES (?, ?, ?, 'issue', ?, 'reopen', ?, ?, NOW())
                ");
                $desc = "Safety Officer reopened issue #" . $issue_id . " after recheck";
                $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $issue_id, $desc, $ip);
                $log->execute();

                $msg = "Issue reopened successfully.";
                $msg_type = 'warning';
            }

            $stmt = $conn->prepare("
                SELECT i.issue_id, i.company_id, i.project_id, i.inspection_id, i.response_id,
                       i.issue_title, i.description, i.severity, i.assigned_to, i.due_date,
                       i.status, i.fixed_date, i.closed_date, i.reopened_count, i.last_rechecked_at, i.created_at,
                       p.project_name, p.site_name, p.location,
                       ins.inspection_date, ins.inspected_by,
                       su.full_name AS supervisor_name,
                       su.email AS supervisor_email
                FROM issues i
                INNER JOIN inspections ins ON i.inspection_id = ins.inspection_id
                INNER JOIN projects p ON i.project_id = p.project_id
                LEFT JOIN users su ON i.assigned_to = su.user_id
                WHERE i.issue_id = ?
                  AND i.company_id = ?
                  AND ins.inspected_by = ?
                LIMIT 1
            ");
            $stmt->bind_param("iii", $issue_id, $company_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $issue = $res->fetch_assoc();
        } else {
            $msg = "Failed to save recheck result.";
        }
    }
}

$update_history = [];
$hstmt = $conn->prepare("
    SELECT iu.update_id, iu.update_type, iu.old_status, iu.new_status,
           iu.action_taken, iu.fixed_date, iu.comment_text, iu.recheck_result, iu.next_due_date, iu.created_at,
           u.full_name
    FROM issue_updates iu
    LEFT JOIN users u ON iu.updated_by = u.user_id
    WHERE iu.issue_id = ?
    ORDER BY iu.update_id DESC
");
$hstmt->bind_param("i", $issue_id);
$hstmt->execute();
$hres = $hstmt->get_result();
while ($row = $hres->fetch_assoc()) {
    $update_history[] = $row;
}

include '../includes/header.php';
?>


<?php include '../includes/sidebar.php'; ?>
<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type); ?>"><?= htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <div class="row g-4">
        <div class="col-md-8">
            <h4 class="mb-3"><?= htmlspecialchars($issue['issue_title']); ?></h4>

            <div class="mb-2"><strong>Project:</strong> <?= htmlspecialchars($issue['project_name']); ?>
            </div>
            <div class="mb-2"><strong>Site:</strong> <?= htmlspecialchars($issue['site_name']); ?></div>
            <div class="mb-2"><strong>Location:</strong> <?= htmlspecialchars($issue['location']); ?>
            </div>
            <div class="mb-2"><strong>Description:</strong>
                <?= htmlspecialchars($issue['description']); ?></div>
            <div class="mb-2"><strong>Severity:</strong> <?= htmlspecialchars($issue['severity']); ?>
            </div>
            <div class="mb-2"><strong>Status:</strong> <span
                    class="badge bg-primary"><?= htmlspecialchars($issue['status']); ?></span></div>
            <div class="mb-2"><strong>Due Date:</strong>
                <?= htmlspecialchars($issue['due_date'] ?: '-'); ?></div>
            <div class="mb-2"><strong>Fixed Date:</strong>
                <?= htmlspecialchars($issue['fixed_date'] ?: '-'); ?></div>
            <div class="mb-2"><strong>Reopened Count:</strong> <?= (int)$issue['reopened_count']; ?>
            </div>
            <div class="mb-0"><strong>Supervisor:</strong>
                <?= htmlspecialchars($issue['supervisor_name'] ?: '-'); ?></div>
        </div>

        <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
                <h6 class="mb-3">Status Summary</h6>
                <div class="mb-2"><strong>Inspection Date:</strong>
                    <?= htmlspecialchars($issue['inspection_date'] ?: '-'); ?></div>
                <div class="mb-2"><strong>Created:</strong>
                    <?= htmlspecialchars($issue['created_at']); ?></div>
                <div class="mb-2"><strong>Last Rechecked:</strong>
                    <?= htmlspecialchars($issue['last_rechecked_at'] ?: '-'); ?></div>
                <div class="mb-0"><strong>Closed Date:</strong>
                    <?= htmlspecialchars($issue['closed_date'] ?: '-'); ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">Before / After Photo Evidence</h4>

    <div class="row g-4">
        <div class="col-md-6">
            <h6 class="mb-3 text-danger">Before Photos</h6>
            <?php if (!empty($before_photos)): ?>
            <div class="row g-3">
                <?php foreach ($before_photos as $photo): ?>
                <div class="col-md-6">
                    <div class="border rounded p-2">
                        <img src="../<?= htmlspecialchars($photo['file_path']); ?>" alt="Before Photo"
                            class="img-fluid rounded border">
                        <small class="text-muted d-block mt-2"><?= htmlspecialchars($photo['file_name']); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-muted">No before photos found.</div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h6 class="mb-3 text-success">After Photos</h6>
            <?php if (!empty($after_photos)): ?>
            <div class="row g-3">
                <?php foreach ($after_photos as $photo): ?>
                <div class="col-md-6">
                    <div class="border rounded p-2">
                        <img src="../<?= htmlspecialchars($photo['file_path']); ?>" alt="After Photo"
                            class="img-fluid rounded border">
                        <small class="text-muted d-block mt-2"><?= htmlspecialchars($photo['file_name']); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="text-muted">No after photos found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($issue['status'] !== 'closed'): ?>
<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">Safety Officer Recheck Decision</h4>

    <form method="POST" class="row g-3" id="recheckForm">
        <input type="hidden" name="issue_id" value="<?= (int)$issue['issue_id']; ?>">

        <div class="col-md-4">
            <label class="form-label">Recheck Result *</label>
            <select name="recheck_result" id="recheck_result" class="form-select" required>
                <option value="">Select Result</option>
                <option value="closed">Closed</option>
                <option value="reopened">Reopened</option>
            </select>
        </div>

        <div class="col-md-4" id="nextDueDateWrap" style="display:none;">
            <label class="form-label">Next Due Date *</label>
            <input type="date" name="next_due_date" id="next_due_date" class="form-control">
        </div>

        <div class="col-md-12">
            <label class="form-label">Comment / Recheck Note *</label>
            <textarea name="comment_text" class="form-control" rows="4" required></textarea>
        </div>

        <div class="col-md-12">
            <button type="submit" name="save_recheck" class="btn btn-main">
                <i class="bi bi-check2-square me-1"></i> Save Recheck Result
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card page-card p-4">
    <h4 class="mb-3">Full Issue History</h4>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Updated By</th>
                    <th>Type</th>
                    <th>Status Flow</th>
                    <th>Action Taken</th>
                    <th>Fixed Date</th>
                    <th>Comment</th>
                    <th>Recheck Result</th>
                    <th>Next Due Date</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($update_history)): ?>
                <?php foreach ($update_history as $history): ?>
                <tr>
                    <td>#<?= (int)$history['update_id']; ?></td>
                    <td><?= htmlspecialchars($history['full_name'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($history['update_type']); ?></td>
                    <td><?= htmlspecialchars($history['old_status'] . ' → ' . $history['new_status']); ?>
                    </td>
                    <td><?= htmlspecialchars($history['action_taken'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($history['fixed_date'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($history['comment_text'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($history['recheck_result'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($history['next_due_date'] ?: '-'); ?></td>
                    <td><?= htmlspecialchars($history['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="10" class="text-center text-muted">No history found yet.</td>
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

<script>
document.getElementById('recheck_result').addEventListener('change', function() {
    var wrap = document.getElementById('nextDueDateWrap');
    var input = document.getElementById('next_due_date');

    if (this.value === 'reopened') {
        wrap.style.display = 'block';
        input.setAttribute('required', 'required');
    } else {
        wrap.style.display = 'none';
        input.removeAttribute('required');
        input.value = '';
    }
});
</script>

<?php include '../includes/footer.php'; ?>