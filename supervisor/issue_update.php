<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['supervisor']);

$pageTitle = "Update Issue";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);
$issue_id   = (int)($_GET['issue_id'] ?? $_POST['issue_id'] ?? 0);

$msg = '';
$msg_type = 'danger';

if ($issue_id <= 0) {
    header("Location: issues_list.php");
    exit;
}

$stmt = $conn->prepare("
    SELECT i.issue_id, i.company_id, i.project_id, i.inspection_id, i.response_id,
           i.issue_title, i.description, i.severity, i.assigned_to, i.due_date,
           i.status, i.fixed_date, i.created_at,
           p.project_name, p.site_name, p.location,
           ins.inspected_by, ins.inspection_date,
           u.full_name AS safety_officer_name
    FROM issues i
    INNER JOIN projects p ON i.project_id = p.project_id
    LEFT JOIN inspections ins ON i.inspection_id = ins.inspection_id
    LEFT JOIN users u ON ins.inspected_by = u.user_id
    WHERE i.issue_id = ? AND i.company_id = ? AND i.assigned_to = ?
    LIMIT 1
");
$stmt->bind_param("iii", $issue_id, $company_id, $user_id);
$stmt->execute();
$res = $stmt->get_result();
$issue = $res->fetch_assoc();

if (!$issue) {
    header("Location: issues_list.php");
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_issue_update'])) {
    $action_taken = trim($_POST['action_taken'] ?? '');
    $fixed_date   = trim($_POST['fixed_date'] ?? '');
    $comment_text = trim($_POST['comment_text'] ?? '');
    $mark_status  = 'recheck_pending';

    if ($action_taken === '' || $fixed_date === '' || $comment_text === '') {
        $msg = "Please fill all required fields.";
    } elseif (!isset($_FILES['after_photo']) || $_FILES['after_photo']['name'] === '') {
        $msg = "After photo is required.";
    } else {
        $old_status = $issue['status'];
        $update_type = 'supervisor_fix';

        $ustmt = $conn->prepare("
            INSERT INTO issue_updates (
                issue_id, updated_by, update_type, old_status, new_status,
                action_taken, fixed_date, comment_text, recheck_result, next_due_date, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, NOW())
        ");
        $ustmt->bind_param(
            "iissssss",
            $issue_id,
            $user_id,
            $update_type,
            $old_status,
            $mark_status,
            $action_taken,
            $fixed_date,
            $comment_text
        );

        if ($ustmt->execute()) {
            $update_id = $ustmt->insert_id;

            $afterPhotoPath = '';
            $uploadDir = '../uploads/issue_updates/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $tmpName  = $_FILES['after_photo']['tmp_name'];
            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $_FILES['after_photo']['name']);
            $destPath = $uploadDir . $fileName;

            if (move_uploaded_file($tmpName, $destPath)) {
                $afterPhotoPath = 'uploads/issue_updates/' . $fileName;

                $fstmt = $conn->prepare("
                    INSERT INTO file_uploads (
                        company_id, module_name, related_id, file_name, file_path, file_label, uploaded_by, created_at
                    ) VALUES (?, 'issue_update', ?, ?, ?, 'after_photo', ?, NOW())
                ");
                $fstmt->bind_param("iissi", $company_id, $update_id, $fileName, $afterPhotoPath, $user_id);
                $fstmt->execute();
            }

            $fixed_date_db = $fixed_date;
            $istmt = $conn->prepare("
                UPDATE issues
                SET status = ?, fixed_date = ?
                WHERE issue_id = ? AND company_id = ? AND assigned_to = ?
            ");
            $istmt->bind_param("ssiii", $mark_status, $fixed_date_db, $issue_id, $company_id, $user_id);
            $istmt->execute();

            $safety_officer_id = (int)($issue['inspected_by'] ?? 0);
            if ($safety_officer_id > 0) {
                $ntitle = "Issue Ready for Recheck";
                $nmsg = "Issue #" . $issue_id . " has been fixed by supervisor and is waiting for recheck.";

                $nstmt = $conn->prepare("
                    INSERT INTO notifications (company_id, user_id, title, message, type, is_read, created_at)
                    VALUES (?, ?, ?, ?, 'recheck_pending', 0, NOW())
                ");
                $nstmt->bind_param("iiss", $company_id, $safety_officer_id, $ntitle, $nmsg);
                $nstmt->execute();
            }

            $log = $conn->prepare("
                INSERT INTO activity_logs (
                    company_id, project_id, user_id, module_name, related_id,
                    action_type, action_description, ip_address, created_at
                ) VALUES (?, ?, ?, 'issue_update', ?, 'update', ?, ?, NOW())
            ");
            $desc = "Supervisor updated issue #" . $issue_id . " and marked it as recheck pending";
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $project_id = (int)$issue['project_id'];
            $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $update_id, $desc, $ip);
            $log->execute();

            $msg = "Issue updated successfully. Sent for recheck.";
            $msg_type = 'success';

            $stmt = $conn->prepare("
                SELECT i.issue_id, i.company_id, i.project_id, i.inspection_id, i.response_id,
                       i.issue_title, i.description, i.severity, i.assigned_to, i.due_date,
                       i.status, i.fixed_date, i.created_at,
                       p.project_name, p.site_name, p.location,
                       ins.inspected_by, ins.inspection_date,
                       u.full_name AS safety_officer_name
                FROM issues i
                INNER JOIN projects p ON i.project_id = p.project_id
                LEFT JOIN inspections ins ON i.inspection_id = ins.inspection_id
                LEFT JOIN users u ON ins.inspected_by = u.user_id
                WHERE i.issue_id = ? AND i.company_id = ? AND i.assigned_to = ?
                LIMIT 1
            ");
            $stmt->bind_param("iii", $issue_id, $company_id, $user_id);
            $stmt->execute();
            $res = $stmt->get_result();
            $issue = $res->fetch_assoc();
        } else {
            $msg = "Failed to update issue.";
        }
    }
}

$update_history = [];
$hstmt = $conn->prepare("
    SELECT iu.update_id, iu.update_type, iu.old_status, iu.new_status,
           iu.action_taken, iu.fixed_date, iu.comment_text, iu.created_at,
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
            <div class="mb-2"><strong>Inspection Date:</strong>
                <?= htmlspecialchars($issue['inspection_date'] ?: '-'); ?></div>
            <div class="mb-2"><strong>Due Date:</strong>
                <?= htmlspecialchars($issue['due_date'] ?: '-'); ?></div>
            <div class="mb-2"><strong>Status:</strong> <span
                    class="badge bg-primary"><?= htmlspecialchars($issue['status']); ?></span></div>
            <div class="mb-0"><strong>Safety Officer:</strong>
                <?= htmlspecialchars($issue['safety_officer_name'] ?: '-'); ?></div>
        </div>

        <div class="col-md-4">
            <div class="border rounded p-3 bg-light">
                <h6 class="mb-3">Before Photo Evidence</h6>
                <?php if (!empty($before_photos)): ?>
                <?php foreach ($before_photos as $photo): ?>
                <div class="mb-3">
                    <img src="../<?= htmlspecialchars($photo['file_path']); ?>" alt="Before Photo"
                        class="img-fluid rounded border">
                    <small class="text-muted d-block mt-1"><?= htmlspecialchars($photo['file_name']); ?></small>
                </div>
                <?php endforeach; ?>
                <?php else: ?>
                <div class="text-muted">No before photo found.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($issue['status'] !== 'closed'): ?>
<div class="card page-card p-4 mb-4">
    <h4 class="mb-3">Supervisor Action Update</h4>

    <form method="POST" enctype="multipart/form-data" class="row g-3">
        <input type="hidden" name="issue_id" value="<?= (int)$issue['issue_id']; ?>">

        <div class="col-md-12">
            <label class="form-label">Action Taken *</label>
            <textarea name="action_taken" class="form-control" rows="4" required></textarea>
        </div>

        <div class="col-md-4">
            <label class="form-label">Fixed Date *</label>
            <input type="date" name="fixed_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
        </div>

        <div class="col-md-4">
            <label class="form-label">After Photo *</label>
            <input type="file" name="after_photo" class="form-control" accept=".jpg,.jpeg,.png,.webp" required>
        </div>

        <div class="col-md-12">
            <label class="form-label">Comment *</label>
            <textarea name="comment_text" class="form-control" rows="3" required></textarea>
        </div>

        <div class="col-md-12">
            <button type="submit" name="save_issue_update" class="btn btn-main">
                <i class="bi bi-check2-circle me-1"></i> Save and Send for Recheck
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<div class="card page-card p-4">
    <h4 class="mb-3">Issue Update History</h4>

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
                    <td><?= htmlspecialchars($history['created_at']); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No issue updates found yet.</td>
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