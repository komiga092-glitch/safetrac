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

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function generateInspectionCode(mysqli $conn): string
{
    $prefix = 'INS-' . date('Ymd') . '-';
    $like = $prefix . '%';

    $stmt = $conn->prepare("
        SELECT inspection_code
        FROM inspections
        WHERE inspection_code LIKE ?
        ORDER BY inspection_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $next = 1;
    if (!empty($res['inspection_code'])) {
        $lastCode = $res['inspection_code'];
        $lastNo = (int)substr($lastCode, -4);
        $next = $lastNo + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

function generateIssueCode(mysqli $conn): string
{
    $prefix = 'ISS-' . date('Ymd') . '-';
    $like = $prefix . '%';

    $stmt = $conn->prepare("
        SELECT issue_code
        FROM issues
        WHERE issue_code LIKE ?
        ORDER BY issue_id DESC
        LIMIT 1
    ");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();

    $next = 1;
    if (!empty($res['issue_code'])) {
        $lastCode = $res['issue_code'];
        $lastNo = (int)substr($lastCode, -4);
        $next = $lastNo + 1;
    }

    return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
}

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
    SELECT
        cc.category_id,
        cc.category_name,
        cc.sort_order,
        ci.item_id,
        ci.item_code,
        ci.item_text,
        ci.default_severity,
        ci.is_mandatory,
        ci.sort_order AS item_sort
    FROM checklist_categories cc
    INNER JOIN checklist_items ci ON cc.category_id = ci.category_id
    WHERE cc.status = 'active' AND ci.status = 'active'
    ORDER BY cc.sort_order ASC, ci.sort_order ASC, ci.item_id ASC
");
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
    $project_id      = (int)($_POST['project_id'] ?? 0);
    $inspection_date = trim($_POST['inspection_date'] ?? date('Y-m-d'));
    $inspection_time = trim($_POST['inspection_time'] ?? date('H:i'));
    $remarks         = trim($_POST['remarks'] ?? '');

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
        $na_count = 0;
        $total_valid = 0;
        $validation_errors = [];

        foreach ($response_values as $item_id => $value) {
            $item_id = (int)$item_id;
            $value = trim((string)$value);

            if ($value === 'Yes') {
                $pass_count++;
                $total_valid++;
            } elseif ($value === 'No') {
                $fail_count++;
                $total_valid++;

                $note = trim($_POST['note'][$item_id] ?? '');
                $severity = trim($_POST['severity'][$item_id] ?? '');
                $supervisor = (int)($_POST['assigned_supervisor_id'][$item_id] ?? 0);
                $due_date = trim($_POST['due_date'][$item_id] ?? '');

                if ($note === '' || $severity === '' || $supervisor <= 0 || $due_date === '') {
                    $validation_errors[] = $item_id;
                }
            } elseif ($value === 'NA') {
                $na_count++;
            }
        }

        if (!empty($validation_errors)) {
            $msg = "All failed items must include note, severity, supervisor, and due date.";
        } else {
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

            $inspection_status = 'submitted';
            $inspection_code = generateInspectionCode($conn);

            $stmt = $conn->prepare("
                INSERT INTO inspections (
                    company_id,
                    project_id,
                    inspection_code,
                    inspection_date,
                    inspection_time,
                    conducted_by,
                    overall_score,
                    risk_level,
                    remarks,
                    status,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "iisssidsss",
                $company_id,
                $project_id,
                $inspection_code,
                $inspection_date,
                $inspection_time,
                $user_id,
                $score,
                $risk_level,
                $remarks,
                $inspection_status
            );

            if ($stmt->execute()) {
                $inspection_id = (int)$stmt->insert_id;

                foreach ($response_values as $item_id => $value) {
                    $item_id = (int)$item_id;
                    $value   = trim((string)$value);

                    if (!in_array($value, ['Yes', 'No', 'NA'], true)) {
                        continue;
                    }

                    $note       = trim($_POST['note'][$item_id] ?? '');
                    $severity   = trim($_POST['severity'][$item_id] ?? '');
                    $supervisor = (int)($_POST['assigned_supervisor_id'][$item_id] ?? 0);
                    $due_date   = trim($_POST['due_date'][$item_id] ?? '');

                    if ($value !== 'No') {
                        $note = '';
                        $severity = null;
                        $supervisor = null;
                        $due_date = null;
                    }

                    $response_status = 'submitted';
                    $is_issue_created = 0;
                    $issue_created_at = null;
                    $photo_required = ($value === 'No') ? 1 : 0;

                    $rstmt = $conn->prepare("
                        INSERT INTO inspection_responses (
                            inspection_id,
                            item_id,
                            response_value,
                            note,
                            severity,
                            assigned_supervisor_id,
                            due_date,
                            response_status,
                            is_issue_created,
                            issue_created_at,
                            photo_required,
                            created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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

                    $response_id = (int)$rstmt->insert_id;

                    if ($value === 'No') {
                        $beforePhotoPath = '';
                        $uploadedFileName = '';

                        if (isset($_FILES['photo']['name'][$item_id]) && $_FILES['photo']['name'][$item_id] !== '') {
                            $uploadDir = '../uploads/inspection_responses/';
                            if (!is_dir($uploadDir)) {
                                mkdir($uploadDir, 0777, true);
                            }

                            $tmpName = $_FILES['photo']['tmp_name'][$item_id];
                            $originalName = $_FILES['photo']['name'][$item_id];
                            $safeName = preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $originalName);
                            $uploadedFileName = time() . '_' . $item_id . '_' . $safeName;
                            $destPath = $uploadDir . $uploadedFileName;

                            if (move_uploaded_file($tmpName, $destPath)) {
                                $beforePhotoPath = 'uploads/inspection_responses/' . $uploadedFileName;

                                $fstmt = $conn->prepare("
                                    INSERT INTO file_uploads (
                                        company_id,
                                        uploaded_by,
                                        module_name,
                                        related_id,
                                        file_name,
                                        file_path,
                                        file_label,
                                        uploaded_at
                                    ) VALUES (?, ?, 'inspection_response', ?, ?, ?, 'before_photo', NOW())
                                ");
                                $fstmt->bind_param(
                                    "iiiss",
                                    $company_id,
                                    $user_id,
                                    $response_id,
                                    $uploadedFileName,
                                    $beforePhotoPath
                                );
                                $fstmt->execute();
                            }
                        }

                        $priority_rank = 0;
                        if ($severity === 'Low') $priority_rank = 1;
                        if ($severity === 'Medium') $priority_rank = 2;
                        if ($severity === 'High') $priority_rank = 3;
                        if ($severity === 'Critical') $priority_rank = 4;

                        $issue_code = generateIssueCode($conn);
                        $issue_title = "Safety Issue - Item #" . $item_id;
                        $issue_desc  = $note;
                        $issue_status = 'open';

                        $istmt = $conn->prepare("
                            INSERT INTO issues (
                                company_id,
                                project_id,
                                inspection_id,
                                response_id,
                                issue_code,
                                title,
                                description,
                                severity,
                                assigned_supervisor_id,
                                due_date,
                                fixed_date,
                                closed_date,
                                reopened_count,
                                last_rechecked_at,
                                priority_rank,
                                status,
                                created_by,
                                created_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 0, NULL, ?, ?, ?, NOW())
                        ");
                        $istmt->bind_param(
                            "iiiissssisssi",
                            $company_id,
                            $project_id,
                            $inspection_id,
                            $response_id,
                            $issue_code,
                            $issue_title,
                            $issue_desc,
                            $severity,
                            $supervisor,
                            $due_date,
                            $priority_rank,
                            $issue_status,
                            $user_id
                        );
                        $istmt->execute();

                        $issue_id = (int)$istmt->insert_id;

                        $issue_created_at = date('Y-m-d H:i:s');

                        $upstmt = $conn->prepare("
                            UPDATE inspection_responses
                            SET is_issue_created = 1, issue_created_at = ?
                            WHERE response_id = ?
                        ");
                        $upstmt->bind_param("si", $issue_created_at, $response_id);
                        $upstmt->execute();

                        $ntitle = "New Safety Issue Assigned";
                        $nmsg = "A new " . $severity . " issue is assigned to you for project " . $projectData['project_name'] . ". Due date: " . $due_date;

                        $nstmt = $conn->prepare("
                            INSERT INTO notifications (
                                company_id,
                                user_id,
                                title,
                                message,
                                related_table,
                                related_id,
                                is_read,
                                created_at
                            ) VALUES (?, ?, ?, ?, 'issues', ?, 0, NOW())
                        ");
                        $nstmt->bind_param("iissi", $company_id, $supervisor, $ntitle, $nmsg, $issue_id);
                        $nstmt->execute();

                        $lstmt = $conn->prepare("
                            INSERT INTO activity_logs (
                                company_id,
                                project_id,
                                user_id,
                                module_name,
                                related_id,
                                action_type,
                                action_description,
                                ip_address,
                                created_at
                            ) VALUES (?, ?, ?, 'issue', ?, 'create', ?, ?, NOW())
                        ");
                        $desc = "Created issue " . $issue_code . " for failed checklist item and assigned to supervisor ID " . $supervisor;
                        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                        $lstmt->bind_param("iiiiss", $company_id, $project_id, $user_id, $issue_id, $desc, $ip);
                        $lstmt->execute();
                    }
                }

                $log = $conn->prepare("
                    INSERT INTO activity_logs (
                        company_id,
                        project_id,
                        user_id,
                        module_name,
                        related_id,
                        action_type,
                        action_description,
                        ip_address,
                        created_at
                    ) VALUES (?, ?, ?, 'inspection', ?, 'create', ?, ?, NOW())
                ");
                $desc = "Created inspection " . $inspection_code . " with score " . number_format($score, 2) . "% and risk level " . $risk_level;
                $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                $log->bind_param("iiiiss", $company_id, $project_id, $user_id, $inspection_id, $desc, $ip);
                $log->execute();

                $msg = "Inspection saved successfully.";
                $msg_type = 'success';

                $_POST = [];
            } else {
                $msg = "Failed to save inspection.";
            }
        }
    }
}

$inspection_code = generateInspectionCode($conn);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?>">
    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<div class="page-heading">
    <h1>Safety Inspection Checklist</h1>
    <p>Complete the inspection using the checklist below to capture hazards, assign corrective actions, and maintain compliance.</p>
</div>

<div class="page-card mb-4">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h5 class="mb-1">Inspection Reference</h5>
            <p class="small-muted mb-0">Inspection Code: <strong><?= e($inspection_code); ?></strong></p>
        </div>
        <div class="col-md-4 text-md-end">
            <span class="badge bg-success">Safety Score Target: 90%+</span>
        </div>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" id="inspectionForm">
    <div class="card page-card p-4 mb-4">
        <h4 class="mb-3">Inspection Details</h4>

        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Project *</label>
                <select name="project_id" class="form-select" required>
                    <option value="">Select Project</option>
                    <?php foreach ($projects as $project): ?>
                    <option value="<?= (int)$project['project_id']; ?>"
                        <?= ((int)($_POST['project_id'] ?? 0) === (int)$project['project_id']) ? 'selected' : ''; ?>>
                        <?= e($project['project_name'] . ' - ' . $project['site_name'] . ' - ' . $project['location']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Remarks</label>
                <input type="text" name="remarks" class="form-control"
                    value="<?= e($_POST['remarks'] ?? 'Daily Safety Inspection'); ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Date *</label>
                <input type="date" name="inspection_date" class="form-control"
                    value="<?= e($_POST['inspection_date'] ?? date('Y-m-d')); ?>" required>
            </div>

            <div class="col-md-2">
                <label class="form-label">Time *</label>
                <input type="time" name="inspection_time" class="form-control"
                    value="<?= e($_POST['inspection_time'] ?? date('H:i')); ?>" required>
            </div>
        </div>
    </div>

    <?php if (!empty($categories)): ?>
    <?php foreach ($categories as $category_id => $category): ?>
    <div class="card page-card p-4 mb-4">
        <h5 class="mb-3 text-primary"><?= e($category['category_name']); ?></h5>
        <div class="table-responsive">
            <table class="table checklist-table mb-0">
                <thead>
                    <tr>
                        <th>Criteria</th>
                        <th class="text-center">Yes</th>
                        <th class="text-center">No</th>
                        <th class="text-center">N/A</th>
                    </tr>
                </thead>
                <tbody>

        <?php foreach ($category['items'] as $item): ?>
        <?php $item_id = (int)$item['item_id']; ?>
        <tr class="checklist-item-row">
            <td>
                <div class="item-text">
                    <?= e(($item['item_code'] ?: 'ITEM-' . $item_id) . ' - ' . $item['item_text']); ?>
                </div>
                <div class="checklist-item-meta">
                    <div class="meta-label">Mandatory</div>
                    <div class="meta-value"><?= ((int)$item['is_mandatory'] === 1) ? 'Yes' : 'No'; ?></div>
                </div>
            </td>
            <td class="text-center">
                <label class="checklist-option">
                    <input type="radio" name="response_value[<?= $item_id; ?>]"
                        id="response_pass_<?= $item_id; ?>"
                        value="Yes"
                        data-item="<?= $item_id; ?>"
                        class="response-select"
                        required
                        <?= (($_POST['response_value'][$item_id] ?? '') === 'Yes') ? 'checked' : ''; ?> />
                    <span class="option-label"></span>
                </label>
            </td>
            <td class="text-center">
                <label class="checklist-option">
                    <input type="radio" name="response_value[<?= $item_id; ?>]"
                        id="response_fail_<?= $item_id; ?>"
                        value="No"
                        data-item="<?= $item_id; ?>"
                        class="response-select"
                        <?= (($_POST['response_value'][$item_id] ?? '') === 'No') ? 'checked' : ''; ?> />
                    <span class="option-label"></span>
                </label>
            </td>
            <td class="text-center">
                <label class="checklist-option">
                    <input type="radio" name="response_value[<?= $item_id; ?>]"
                        id="response_na_<?= $item_id; ?>"
                        value="NA"
                        data-item="<?= $item_id; ?>"
                        class="response-select"
                        <?= (($_POST['response_value'][$item_id] ?? '') === 'NA') ? 'checked' : ''; ?> />
                    <span class="option-label"></span>
                </label>
            </td>
        </tr>
        <tr class="fail-fields-row fail-fields-<?= $item_id; ?>" style="display:none;">
            <td colspan="4">
                <div class="row g-3 mt-1">
                    <div class="col-md-4">
                        <label class="form-label">Note *</label>
                        <textarea name="note[<?= $item_id; ?>]" class="form-control fail-required-<?= $item_id; ?>"
                            rows="2"><?= e($_POST['note'][$item_id] ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Severity *</label>
                        <select name="severity[<?= $item_id; ?>]" class="form-select fail-required-<?= $item_id; ?>">
                            <option value="">Select</option>
                            <option value="Low"
                                <?= (($_POST['severity'][$item_id] ?? strtolower($item['default_severity'])) === 'Low' || ($_POST['severity'][$item_id] ?? '') === 'low') ? 'selected' : ''; ?>>
                                Low</option>
                            <option value="Medium"
                                <?= (($_POST['severity'][$item_id] ?? strtolower($item['default_severity'])) === 'Medium' || ($_POST['severity'][$item_id] ?? '') === 'medium') ? 'selected' : ''; ?>>
                                Medium</option>
                            <option value="High"
                                <?= (($_POST['severity'][$item_id] ?? strtolower($item['default_severity'])) === 'High' || ($_POST['severity'][$item_id] ?? '') === 'high') ? 'selected' : ''; ?>>
                                High</option>
                            <option value="Critical"
                                <?= (($_POST['severity'][$item_id] ?? strtolower($item['default_severity'])) === 'Critical' || ($_POST['severity'][$item_id] ?? '') === 'critical') ? 'selected' : ''; ?>>
                                Critical</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Assigned Supervisor *</label>
                        <select name="assigned_supervisor_id[<?= $item_id; ?>]"
                            class="form-select fail-required-<?= $item_id; ?>">
                            <option value="">Select Supervisor</option>
                            <?php foreach ($supervisors as $sup): ?>
                            <option value="<?= (int)$sup['user_id']; ?>"
                                <?= ((int)($_POST['assigned_supervisor_id'][$item_id] ?? 0) === (int)$sup['user_id']) ? 'selected' : ''; ?>>
                                <?= e($sup['full_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date[<?= $item_id; ?>]"
                            class="form-control fail-required-<?= $item_id; ?>"
                            value="<?= e($_POST['due_date'][$item_id] ?? ''); ?>">
                    </div>

                    <div class="col-md-1">
                        <label class="form-label">Photo *</label>
                        <input type="file" name="photo[<?= $item_id; ?>]"
                            class="form-control fail-required-<?= $item_id; ?>" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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

<script src="/safetrac/assets/js/inspection-form.js"></script>

<?php include '../includes/footer.php'; ?>


