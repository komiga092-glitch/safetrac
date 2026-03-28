<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['safety_officer']);

$pageTitle = "New Inspection";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'danger';

$projects = [];
$categories = [];
$supervisors = [];

$pstmt = $conn->prepare("
    SELECT p.project_id, p.project_name, p.site_name, p.location
    FROM project_staff ps
    INNER JOIN projects p ON ps.project_id = p.project_id
    WHERE ps.company_id = ?
      AND ps.user_id = ?
      AND ps.role = 'safety_officer'
      AND ps.status = 'active'
      AND p.status = 'active'
    ORDER BY p.project_name ASC
");
$pstmt->bind_param("ii", $company_id, $user_id);
$pstmt->execute();
$pres = $pstmt->get_result();
while ($row = $pres->fetch_assoc()) {
    $projects[] = $row;
}

$cstmt = $conn->prepare("
    SELECT cc.category_id, cc.category_name, cc.sort_order,
           ci.item_id, ci.item_text, ci.default_severity, ci.sort_order AS item_sort
    FROM checklist_categories cc
    INNER JOIN checklist_items ci ON cc.category_id = ci.category_id
    WHERE cc.company_id = ? AND ci.company_id = ? AND cc.status = 'active' AND ci.status = 'active'
    ORDER BY cc.sort_order ASC, ci.sort_order ASC, ci.item_id ASC
");
$cstmt->bind_param("ii", $company_id, $company_id);
$cstmt->execute();
$cres = $cstmt->get_result();

while ($row = $cres->fetch_assoc()) {
    $catId = (int)$row['category_id'];
    if (!isset($categories[$catId])) {
        $categories[$catId] = [
            'category_name' => $row['category_name'],
            'items' => []
        ];
    }
    $categories[$catId]['items'][] = $row;
}

$sstmt = $conn->prepare("
    SELECT user_id, full_name
    FROM users
    WHERE company_id = ?
      AND role = 'supervisor'
      AND status = 'active'
    ORDER BY full_name ASC
");
$sstmt->bind_param("i", $company_id);
$sstmt->execute();
$sres = $sstmt->get_result();
while ($row = $sres->fetch_assoc()) {
    $supervisors[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_inspection'])) {
    $project_id       = (int)($_POST['project_id'] ?? 0);
    $inspection_date  = trim($_POST['inspection_date'] ?? date('Y-m-d'));
    $inspection_time  = trim($_POST['inspection_time'] ?? date('H:i'));
    $inspection_title = trim($_POST['inspection_title'] ?? 'Daily Safety Inspection');

    $checkProject = $conn->prepare("
        SELECT ps.project_id, p.project_name, p.site_name
        FROM project_staff ps
        INNER JOIN projects p ON ps.project_id = p.project_id
        WHERE ps.company_id = ?
          AND ps.user_id = ?
          AND ps.project_id = ?
          AND ps.role = 'safety_officer'
          AND ps.status = 'active'
        LIMIT 1
    ");
    $checkProject->bind_param("iii", $company_id, $user_id, $project_id);
    $checkProject->execute();
    $projectRes = $checkProject->get_result();
    $projectData = $projectRes->fetch_assoc();

    if ($project_id <= 0 || !$projectData) {
        $msg = "Invalid project selected.";
    } elseif (empty($_POST['response_value']) || !is_array($_POST['response_value'])) {
        $msg = "Checklist responses are required.";
    } else {
        $response_values = $_POST['response_value'];

        $pass_count = 0;
        $fail_count = 0;
        $na_count   = 0;
        $total_valid = 0;

        foreach ($response_values as $item_id => $value) {
            $value = trim($value);
            if ($value === 'Pass') {
                $pass_count++;
                $total_valid++;
            } elseif ($value === 'Fail') {
                $fail_count++;
                $total_valid++;
            } elseif ($value === 'NA') {
                $na_count++;
            }
        }

        $score = 0;
        if ($total_valid > 0) {
            $score = ($pass_count / $total_valid) * 100;
        }

        $risk_level = 'Risk';
        if ($score >= 90) {
            $risk_level = 'Safe';
        } elseif ($score >= 75) {
            $risk_level = 'Moderate';
        }

        $overall_status = ($fail_count > 0) ? 'Issues Found' : 'Passed';

        $stmt = $conn->prepare("
            INSERT INTO inspections (
                company_id, project_id, inspection_title, inspection_date, inspection_time,
                inspected_by, overall_score, risk_level, overall_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "iisssidss",
            $company_id,
            $project_id,
            $inspection_title,
            $inspection_date,
            $inspection_time,
            $user_id,
            $score,
            $risk_level,
            $overall_status
        );

        if ($stmt->execute()) {
            $inspection_id = $stmt->insert_id;

            foreach ($response_values as $item_id => $value) {
                $item_id = (int)$item_id;
                $value   = trim($value);

                $note        = trim($_POST['note'][$item_id] ?? '');
                $severity    = trim($_POST['severity'][$item_id] ?? '');
                $supervisor  = (int)($_POST['assigned_supervisor_id'][$item_id] ?? 0);
                $due_date    = trim($_POST['due_date'][$item_id] ?? '');

                if (!in_array($value, ['Pass', 'Fail', 'NA'])) {
                    continue;
                }

                if ($value === 'Fail') {
                    if ($note === '' || $severity === '' || $supervisor <= 0 || $due_date === '') {
                        continue;
                    }
                } else {
                    $note = '';
                    $severity = '';
                    $supervisor = 0;
                    $due_date = null;
                }

                $response_status = 'submitted';
                $is_issue_created = 0;
                $issue_created_at = null;
                $photo_required = ($value === 'Fail') ? 1 : 0;

                $rstmt = $conn->prepare("
                    INSERT INTO inspection_responses (
                        inspection_id, item_id, response_value, note, severity,
                        assigned_supervisor_id, due_date, response_status,
                        is_issue_created, issue_created_at, photo_required
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $rstmt->bind_param(
                    "iisssissisi",
                    $inspection_id,
                    $item_id,
                    $value,
                    $note,
                    $severity,
                    $supervisor,
                    $due_date,
                    $response_status,
                    $is_issue_created,
                    $issue_created_at,
                    $photo_required
                );
                $rstmt->execute();

                $response_id = $rstmt->insert_id;

                if ($value === 'Fail') {
                    $beforePhotoPath = '';

                    if (isset($_FILES['photo']['name'][$item_id]) && $_FILES['photo']['name'][$item_id] !== '') {
                        $uploadDir = '../uploads/inspection_responses/';
                        if (!is_dir($uploadDir)) {
                            mkdir($uploadDir, 0777, true);
                        }

                        $tmpName  = $_FILES['photo']['tmp_name'][$item_id];
                        $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $_FILES['photo']['name'][$item_id]);
                        $destPath = $uploadDir . $fileName;

                        if (move_uploaded_file($tmpName, $destPath)) {
                            $beforePhotoPath = 'uploads/inspection_responses/' . $fileName;

                            $fstmt = $conn->prepare("
                                INSERT INTO file_uploads (
                                    company_id, module_name, related_id, file_name, file_path, file_label, uploaded_by, created_at
                                ) VALUES (?, 'inspection_response', ?, ?, ?, 'before_photo', ?, NOW())
                            ");
                            $fstmt->bind_param("iissi", $company_id, $response_id, $fileName, $beforePhotoPath, $user_id);
                            $fstmt->execute();
                        }
                    }

                    $issue_status = 'open';
                    $priority_rank = 0;
                    if ($severity === 'Low') $priority_rank = 1;
                    if ($severity === 'Medium') $priority_rank = 2;
                    if ($severity === 'High') $priority_rank = 3;
                    if ($severity === 'Critical') $priority_rank = 4;

                    $istmt = $conn->prepare("
                        INSERT INTO issues (
                            company_id, project_id, inspection_id, response_id, issue_title,
                            description, severity, assigned_to, due_date, status,
                            fixed_date, closed_date, reopened_count, last_rechecked_at,
                            priority_rank, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0, NULL, ?, NOW())
                    ");

                    $issue_title = "Safety Issue - Checklist Item #" . $item_id;
                    $issue_desc  = $note;

                    $istmt->bind_param(
                        "iiiisssissi",
                        $company_id,
                        $project_id,
                        $inspection_id,
                        $response_id,
                        $issue_title,
                        $issue_desc,
                        $severity,
                        $supervisor,
                        $due_date,
                        $issue_status,
                        $priority_rank
                    );
                    $istmt->execute();

                    $issue_id = $istmt->insert_id;

                    $issue_created_at = date('Y-m-d H:i:s');
                    $is_issue_created = 1;

                    $upstmt = $conn->prepare("
                        UPDATE inspection_responses
                        SET is_issue_created = 1, issue_created_at = ?
                        WHERE response_id = ?
                    ");
                    $upstmt->bind_param("si", $issue_created_at, $response_id);
                    $upstmt->execute();

                    $supervisorName = '';
                    $supq = $conn->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
                    $supq->bind_param("i", $supervisor);
                    $supq->execute();
                    $supRow = $supq->get_result()->fetch_assoc();
                    $supervisorName = $supRow['full_name'] ?? '';

                    $ntitle = "New Safety Issue Assigned";
                    $nmsg = "A new " . $severity . " issue is assigned to you for project " . $projectData['project_name'] . ". Due date: " . $due_date;

                    $nstmt = $conn->prepare("
                        INSERT INTO notifications (company_id, user_id, title, message, type, is_read, created_at)
                        VALUES (?, ?, ?, ?, 'issue_assignment', 0, NOW())
                    ");
                    $nstmt->bind_param("iiss", $company_id, $supervisor, $ntitle, $nmsg);
                    $nstmt->execute();

                    $lstmt = $conn->prepare("
                        INSERT INTO activity_logs (
                            company_id, project_id, user_id, module_name, related_id,
                            action_type, action_description, ip_address, created_at
                        ) VALUES (?, ?, ?, 'issue', ?, 'create', ?, ?, NOW())
                    ");
                    $desc = "Created issue for failed checklist item and assigned to supervisor ID " . $supervisor;
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                    $lstmt->bind_param("iiiiss", $company_id, $project_id, $user_id, $issue_id, $desc, $ip);
                    $lstmt->execute();
                }
            }

            $log = $conn->prepare("
                INSERT INTO activity_logs (
                    company_id, project_id, user_id, module_name, related_id,
                    action_type, action_description, ip_address, created_at
                ) VALUES (?, ?, ?, 'inspection', ?, 'create', ?, ?, NOW())
            ");
            $desc = "Created inspection with score " . number_format($score, 2) . "% and risk level " . $risk_level;
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $inspection_id, $desc, $ip);
            $log->execute();

            $msg = "Inspection saved successfully.";
            $msg_type = 'success';
        } else {
            $msg = "Failed to save inspection.";
        }
    }
}

include '../includes/header.php';
?>


<?php include '../includes/sidebar.php'; ?>
<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type); ?>"><?= htmlspecialchars($msg); ?></div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="inspectionForm">
    <div class="card page-card p-4 mb-4">
        <h4 class="mb-3">Inspection Details</h4>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Project *</label>
                <select name="project_id" class="form-select" required>
                    <option value="">Select Project</option>
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= (int)$project['project_id']; ?>">
                        <?= htmlspecialchars($project['project_name'] . ' - ' . $project['site_name'] . ' - ' . $project['location']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Inspection Title *</label>
                <input type="text" name="inspection_title" class="form-control" value="Daily Safety Inspection"
                    required>
            </div>

            <div class="col-md-2">
                <label class="form-label">Date *</label>
                <input type="date" name="inspection_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
            </div>

            <div class="col-md-2">
                <label class="form-label">Time *</label>
                <input type="time" name="inspection_time" class="form-control" value="<?= date('H:i'); ?>" required>
            </div>
        </div>
    </div>

    <?php if (!empty($categories)): ?>
    <?php foreach ($categories as $category_id => $category): ?>
    <div class="card page-card p-4 mb-4">
        <h5 class="mb-3 text-primary"><?= htmlspecialchars($category['category_name']); ?></h5>

        <?php foreach ($category['items'] as $item): ?>
        <?php $item_id = (int)$item['item_id']; ?>
        <div class="border rounded p-3 mb-3">
            <div class="mb-2 fw-semibold">
                <?= htmlspecialchars($item['item_text']); ?>
            </div>

            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Response *</label>
                    <select name="response_value[<?= $item_id; ?>]" class="form-select response-select"
                        data-item="<?= $item_id; ?>" required>
                        <option value="">Select</option>
                        <option value="Pass">Pass</option>
                        <option value="Fail">Fail</option>
                        <option value="NA">NA</option>
                    </select>
                </div>

                <div class="fail-fields-<?= $item_id; ?>" style="display:none;">
                    <div class="row g-3 mt-1">
                        <div class="col-md-4">
                            <label class="form-label">Note *</label>
                            <textarea name="note[<?= $item_id; ?>]" class="form-control fail-required-<?= $item_id; ?>"
                                rows="2"></textarea>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Severity *</label>
                            <select name="severity[<?= $item_id; ?>]"
                                class="form-select fail-required-<?= $item_id; ?>">
                                <option value="">Select</option>
                                <option value="Low" <?= ($item['default_severity'] === 'Low') ? 'selected' : ''; ?>>Low
                                </option>
                                <option value="Medium"
                                    <?= ($item['default_severity'] === 'Medium') ? 'selected' : ''; ?>>
                                    Medium</option>
                                <option value="High" <?= ($item['default_severity'] === 'High') ? 'selected' : ''; ?>>
                                    High</option>
                                <option value="Critical"
                                    <?= ($item['default_severity'] === 'Critical') ? 'selected' : ''; ?>>
                                    Critical</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Assigned Supervisor *</label>
                            <select name="assigned_supervisor_id[<?= $item_id; ?>]"
                                class="form-select fail-required-<?= $item_id; ?>">
                                <option value="">Select Supervisor</option>
                                <?php foreach ($supervisors as $sup): ?>
                                <option value="<?= (int)$sup['user_id']; ?>">
                                    <?= htmlspecialchars($sup['full_name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label">Due Date *</label>
                            <input type="date" name="due_date[<?= $item_id; ?>]"
                                class="form-control fail-required-<?= $item_id; ?>">
                        </div>

                        <div class="col-md-1">
                            <label class="form-label">Photo *</label>
                            <input type="file" name="photo[<?= $item_id; ?>]"
                                class="form-control fail-required-<?= $item_id; ?>" accept=".jpg,.jpeg,.png,.webp">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

    <div class="card page-card p-4">
        <button type="submit" name="save_inspection" class="btn btn-main">
            <i class="bi bi-save me-1"></i> Save Inspection
        </button>
    </div>
    <?php else: ?>
    <div class="card page-card p-4">
        <div class="alert alert-warning mb-0">
            No active checklist items found. Please ask admin to create checklist categories and items.
        </div>
    </div>
    <?php endif; ?>
</form>

</div>
</div>
</div>
</div>

<script>
document.querySelectorAll('.response-select').forEach(function(select) {
    select.addEventListener('change', function() {
        var itemId = this.getAttribute('data-item');
        var failBlock = document.querySelector('.fail-fields-' + itemId);
        var failInputs = document.querySelectorAll('.fail-required-' + itemId);

        if (this.value === 'Fail') {
            failBlock.style.display = 'block';
            failInputs.forEach(function(input) {
                input.setAttribute('required', 'required');
            });
        } else {
            failBlock.style.display = 'none';
            failInputs.forEach(function(input) {
                input.removeAttribute('required');
                if (input.tagName === 'SELECT') {
                    input.selectedIndex = 0;
                } else {
                    input.value = '';
                }
            });
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>