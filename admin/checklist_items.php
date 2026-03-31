<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$pageTitle = "Checklist Items";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'success';
$edit_mode = false;
$edit_item = null;

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$categories = [];
$cstmt = $conn->prepare("
    SELECT category_id, category_name, status, sort_order
    FROM checklist_categories
    ORDER BY sort_order ASC, category_id ASC
");
$cstmt->execute();
$cres = $cstmt->get_result();
while ($row = $cres->fetch_assoc()) {
    $categories[] = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_item'])) {
    $item_id           = (int)($_POST['item_id'] ?? 0);
    $category_id       = (int)($_POST['category_id'] ?? 0);
    $item_code         = trim($_POST['item_code'] ?? '');
    $item_text         = trim($_POST['item_text'] ?? '');
    $default_severity  = trim($_POST['default_severity'] ?? 'Medium');
    $is_mandatory      = isset($_POST['is_mandatory']) ? 1 : 0;
    $sort_order        = (int)($_POST['sort_order'] ?? 0);
    $status            = trim($_POST['status'] ?? 'active');

    $item_code = strip_tags($item_code);
    $item_text = strip_tags($item_text);

    if ($category_id <= 0) {
        $msg = "Please select a category.";
        $msg_type = 'danger';
    } elseif ($item_text === '') {
        $msg = "Checklist item text is required.";
        $msg_type = 'danger';
    } elseif (!in_array($default_severity, ['Low', 'Medium', 'High', 'Critical'], true)) {
        $msg = "Invalid default severity selected.";
        $msg_type = 'danger';
    } elseif (!in_array($status, ['active', 'inactive'], true)) {
        $msg = "Invalid status selected.";
        $msg_type = 'danger';
    } else {
        $catCheck = $conn->prepare("
            SELECT category_id
            FROM checklist_categories
            WHERE category_id = ?
            LIMIT 1
        ");
        $catCheck->bind_param("i", $category_id);
        $catCheck->execute();
        $catRes = $catCheck->get_result();

        if ($catRes->num_rows === 0) {
            $msg = "Selected category is invalid.";
            $msg_type = 'danger';
        } else {
            if ($item_id > 0) {
                $dup = $conn->prepare("
                    SELECT item_id
                    FROM checklist_items
                    WHERE category_id = ? AND item_text = ? AND item_id <> ?
                    LIMIT 1
                ");
                $dup->bind_param("isi", $category_id, $item_text, $item_id);
                $dup->execute();
                $dupRes = $dup->get_result();

                if ($dupRes->num_rows > 0) {
                    $msg = "Another checklist item with the same text already exists in this category.";
                    $msg_type = 'warning';
                } else {
                    $stmt = $conn->prepare("
                        UPDATE checklist_items
                        SET category_id = ?, item_code = ?, item_text = ?, default_severity = ?, is_mandatory = ?, sort_order = ?, status = ?
                        WHERE item_id = ?
                    ");
                    $stmt->bind_param(
                        "isssiisi",
                        $category_id,
                        $item_code,
                        $item_text,
                        $default_severity,
                        $is_mandatory,
                        $sort_order,
                        $status,
                        $item_id
                    );

                    if ($stmt->execute()) {
                        $log = $conn->prepare("
                            INSERT INTO activity_logs (
                                company_id, project_id, user_id, module_name, related_id,
                                action_type, action_description, ip_address, created_at
                            ) VALUES (?, NULL, ?, 'checklist_item', ?, 'update', ?, ?, NOW())
                        ");
                        $desc = "Updated checklist item: " . $item_text;
                        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                        $log->bind_param("iiiss", $company_id, $user_id, $item_id, $desc, $ip);
                        $log->execute();

                        $msg = "Checklist item updated successfully.";
                        $msg_type = 'success';
                    } else {
                        $msg = "Failed to update checklist item.";
                        $msg_type = 'danger';
                    }
                }
            } else {
                $dup = $conn->prepare("
                    SELECT item_id
                    FROM checklist_items
                    WHERE category_id = ? AND item_text = ?
                    LIMIT 1
                ");
                $dup->bind_param("is", $category_id, $item_text);
                $dup->execute();
                $dupRes = $dup->get_result();

                if ($dupRes->num_rows > 0) {
                    $msg = "Checklist item already exists in this category.";
                    $msg_type = 'warning';
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO checklist_items (
                            category_id, item_code, item_text, default_severity, is_mandatory, sort_order, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->bind_param(
                        "isssiis",
                        $category_id,
                        $item_code,
                        $item_text,
                        $default_severity,
                        $is_mandatory,
                        $sort_order,
                        $status
                    );

                    if ($stmt->execute()) {
                        $new_item_id = (int)$stmt->insert_id;

                        $log = $conn->prepare("
                            INSERT INTO activity_logs (
                                company_id, project_id, user_id, module_name, related_id,
                                action_type, action_description, ip_address, created_at
                            ) VALUES (?, NULL, ?, 'checklist_item', ?, 'create', ?, ?, NOW())
                        ");
                        $desc = "Created checklist item: " . $item_text;
                        $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                        $log->bind_param("iiiss", $company_id, $user_id, $new_item_id, $desc, $ip);
                        $log->execute();

                        $msg = "Checklist item created successfully.";
                        $msg_type = 'success';
                        $_POST = [];
                    } else {
                        $msg = "Failed to create checklist item.";
                        $msg_type = 'danger';
                    }
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT item_id, category_id, item_code, item_text, default_severity, is_mandatory, sort_order, status
            FROM checklist_items
            WHERE item_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_item = $res->fetch_assoc();

        if ($edit_item) {
            $edit_mode = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $item_id     = (int)($_POST['item_id'] ?? 0);
    $new_status  = trim($_POST['new_status'] ?? '');

    if ($item_id > 0 && in_array($new_status, ['active', 'inactive'], true)) {
        $stmt = $conn->prepare("
            UPDATE checklist_items
            SET status = ?
            WHERE item_id = ?
        ");
        $stmt->bind_param("si", $new_status, $item_id);

        if ($stmt->execute()) {
            $log = $conn->prepare("
                INSERT INTO activity_logs (
                    company_id, project_id, user_id, module_name, related_id,
                    action_type, action_description, ip_address, created_at
                ) VALUES (?, NULL, ?, 'checklist_item', ?, 'status_update', ?, ?, NOW())
            ");
            $desc = "Changed checklist item status to: " . $new_status;
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $log->bind_param("iiiss", $company_id, $user_id, $item_id, $desc, $ip);
            $log->execute();

            $msg = "Checklist item status updated.";
            $msg_type = 'success';
        } else {
            $msg = "Failed to update checklist item status.";
            $msg_type = 'danger';
        }
    }
}

$selected_category = (int)($_GET['category_id'] ?? 0);

$sql = "
    SELECT 
        ci.item_id,
        ci.category_id,
        ci.item_code,
        ci.item_text,
        ci.default_severity,
        ci.is_mandatory,
        ci.sort_order,
        ci.status,
        cc.category_name
    FROM checklist_items ci
    INNER JOIN checklist_categories cc ON ci.category_id = cc.category_id
    WHERE 1=1
";
$params = [];
$types  = "";

if ($selected_category > 0) {
    $sql .= " AND ci.category_id = ? ";
    $params[] = $selected_category;
    $types .= "i";
}

$sql .= " ORDER BY cc.sort_order ASC, ci.sort_order ASC, ci.item_id ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$items = [];
while ($row = $res->fetch_assoc()) {
    $items[] = $row;
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<?php if ($msg !== ''): ?>
<div class="alert alert-<?= e($msg_type); ?>"><?= e($msg); ?></div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= $edit_mode ? 'Edit Checklist Item' : 'Create New Checklist Item'; ?></h4>
        <?php if ($edit_mode): ?>
        <a href="/safetrac/admin/checklist_items.php" class="btn btn-outline-secondary btn-sm">Cancel Edit</a>
        <?php endif; ?>
    </div>

    <form method="POST" class="row g-3">
        <input type="hidden" name="item_id" value="<?= $edit_mode ? (int)$edit_item['item_id'] : 0; ?>">

        <div class="col-md-3">
            <label class="form-label">Category *</label>
            <select name="category_id" class="form-select" required>
                <option value="">Select Category</option>
                <?php
                $selected_edit_category = $edit_mode
                    ? (int)$edit_item['category_id']
                    : (int)($_POST['category_id'] ?? 0);
                ?>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['category_id']; ?>"
                    <?= $selected_edit_category === (int)$cat['category_id'] ? 'selected' : ''; ?>>
                    <?= e($cat['category_name']); ?> (<?= e($cat['status']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Item Code</label>
            <input type="text" name="item_code" class="form-control"
                value="<?= $edit_mode ? e($edit_item['item_code']) : e($_POST['item_code'] ?? ''); ?>"
                placeholder="Ex: PPE-001">
        </div>

        <div class="col-md-3">
            <label class="form-label">Default Severity *</label>
            <?php
            $severity_value = $edit_mode
                ? $edit_item['default_severity']
                : ($_POST['default_severity'] ?? 'Medium');
            ?>
            <select name="default_severity" class="form-select" required>
                <option value="Low" <?= $severity_value === 'Low' ? 'selected' : ''; ?>>Low</option>
                <option value="Medium" <?= $severity_value === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="High" <?= $severity_value === 'High' ? 'selected' : ''; ?>>High</option>
                <option value="Critical" <?= $severity_value === 'Critical' ? 'selected' : ''; ?>>Critical</option>
            </select>
        </div>

        <div class="col-md-2">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control" min="0"
                value="<?= $edit_mode ? (int)$edit_item['sort_order'] : (int)($_POST['sort_order'] ?? 1); ?>">
        </div>

        <div class="col-md-2">
            <label class="form-label">Status *</label>
            <?php
            $status_value = $edit_mode
                ? $edit_item['status']
                : ($_POST['status'] ?? 'active');
            ?>
            <select name="status" class="form-select" required>
                <option value="active" <?= $status_value === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?= $status_value === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>

        <div class="col-md-10">
            <label class="form-label">Checklist Item Text *</label>
            <textarea name="item_text" class="form-control" rows="3"
                placeholder="Example: Are all workers wearing helmets, gloves, and safety boots?"
                required><?= $edit_mode ? e($edit_item['item_text']) : e($_POST['item_text'] ?? ''); ?></textarea>
        </div>

        <div class="col-md-2">
            <label class="form-label d-block">Mandatory</label>
            <?php
            $mandatory_checked = $edit_mode
                ? ((int)$edit_item['is_mandatory'] === 1)
                : isset($_POST['is_mandatory']);
            ?>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_mandatory" id="is_mandatory"
                    <?= $mandatory_checked ? 'checked' : ''; ?>>
                <label class="form-check-label" for="is_mandatory">
                    Yes
                </label>
            </div>
        </div>

        <div class="col-12">
            <button type="submit" name="save_item" class="btn btn-main">
                <?= $edit_mode ? 'Update' : 'Save'; ?>
            </button>
        </div>
    </form>
</div>

<div class="card page-card p-4 mb-4">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label class="form-label">Filter by Category</label>
            <select name="category_id" class="form-select">
                <option value="0">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= (int)$cat['category_id']; ?>"
                    <?= $selected_category === (int)$cat['category_id'] ? 'selected' : ''; ?>>
                    <?= e($cat['category_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
        </div>

        <div class="col-md-2">
            <a href="/safetrac/admin/checklist_items.php" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<div class="card page-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Checklist Items</h4>
        <span class="text-muted"><?= count($items); ?> record(s)</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Code</th>
                    <th>Item Text</th>
                    <th>Severity</th>
                    <th>Mandatory</th>
                    <th>Sort Order</th>
                    <th>Status</th>
                    <th>Edit</th>
                    <th>Status Change</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                <?php foreach ($items as $item): ?>
                <?php
                        $status_badge = ($item['status'] === 'active') ? 'success' : 'danger';
                        $sev_badge = 'secondary';
                        if ($item['default_severity'] === 'Low') $sev_badge = 'success';
                        if ($item['default_severity'] === 'Medium') $sev_badge = 'warning';
                        if ($item['default_severity'] === 'High') $sev_badge = 'danger';
                        if ($item['default_severity'] === 'Critical') $sev_badge = 'dark';
                        ?>
                <tr>
                    <td><?= e($item['category_name']); ?></td>
                    <td><?= e($item['item_code'] ?: '-'); ?></td>
                    <td style="min-width: 280px;"><?= e($item['item_text']); ?></td>
                    <td><span class="badge bg-<?= $sev_badge; ?>"><?= e($item['default_severity']); ?></span></td>
                    <td><?= ((int)$item['is_mandatory'] === 1) ? 'Yes' : 'No'; ?></td>
                    <td><?= (int)$item['sort_order']; ?></td>
                    <td><span class="badge bg-<?= $status_badge; ?>"><?= e($item['status']); ?></span></td>
                    <td>
                        <a href="/safetrac/admin/checklist_items.php?edit=<?= (int)$item['item_id']; ?>"
                            class="btn btn-sm btn-outline-primary">
                            Edit
                        </a>
                    </td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="item_id" value="<?= (int)$item['item_id']; ?>">
                            <select name="new_status" class="form-select form-select-sm">
                                <option value="active" <?= $item['status'] === 'active' ? 'selected' : ''; ?>>Active
                                </option>
                                <option value="inactive" <?= $item['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive</option>
                            </select>
                            <button type="submit" name="change_status" class="btn btn-sm btn-outline-success">
                                Save
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted">No checklist items found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include '../includes/footer.php'; ?>