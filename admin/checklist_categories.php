<?php
require_once '../includes/session.php';
require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['company_admin']);

$pageTitle = "Checklist Categories";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'success';
$edit_mode = false;
$edit_category = null;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    $sort_order    = (int)($_POST['sort_order'] ?? 0);
    $status        = trim($_POST['status'] ?? 'active');
    $category_id   = (int)($_POST['category_id'] ?? 0);

    if ($category_name === '') {
        $msg = "Category name is required.";
        $msg_type = 'danger';
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $msg = "Invalid status selected.";
        $msg_type = 'danger';
    } else {
        if ($category_id > 0) {
            $check = $conn->prepare("
                SELECT category_id
                FROM checklist_categories
                WHERE company_id = ? AND category_name = ? AND category_id <> ?
                LIMIT 1
            ");
            $check->bind_param("isi", $company_id, $category_name, $category_id);
            $check->execute();
            $checkRes = $check->get_result();

            if ($checkRes->num_rows > 0) {
                $msg = "Another category with this name already exists.";
                $msg_type = 'warning';
            } else {
                $stmt = $conn->prepare("
                    UPDATE checklist_categories
                    SET category_name = ?, sort_order = ?, status = ?
                    WHERE category_id = ? AND company_id = ?
                ");
                $stmt->bind_param("sisii", $category_name, $sort_order, $status, $category_id, $company_id);

                if ($stmt->execute()) {
                    $log = $conn->prepare("
                        INSERT INTO activity_logs (
                            company_id, project_id, user_id, module_name, related_id,
                            action_type, action_description, ip_address, created_at
                        ) VALUES (?, NULL, ?, 'checklist_category', ?, 'update', ?, ?, NOW())
                    ");
                    $desc = "Updated checklist category: " . $category_name;
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log->bind_param("iiiss", $company_id, $user_id, $category_id, $desc, $ip);
                    $log->execute();

                    $msg = "Category updated successfully.";
                    $msg_type = 'success';
                } else {
                    $msg = "Failed to update category.";
                    $msg_type = 'danger';
                }
            }
        } else {
            $check = $conn->prepare("
                SELECT category_id
                FROM checklist_categories
                WHERE company_id = ? AND category_name = ?
                LIMIT 1
            ");
            $check->bind_param("is", $company_id, $category_name);
            $check->execute();
            $checkRes = $check->get_result();

            if ($checkRes->num_rows > 0) {
                $msg = "Category name already exists.";
                $msg_type = 'warning';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO checklist_categories (
                        company_id, category_name, sort_order, status, created_at
                    ) VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->bind_param("isis", $company_id, $category_name, $sort_order, $status);

                if ($stmt->execute()) {
                    $new_category_id = $stmt->insert_id;

                    $log = $conn->prepare("
                        INSERT INTO activity_logs (
                            company_id, project_id, user_id, module_name, related_id,
                            action_type, action_description, ip_address, created_at
                        ) VALUES (?, NULL, ?, 'checklist_category', ?, 'create', ?, ?, NOW())
                    ");
                    $desc = "Created checklist category: " . $category_name;
                    $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
                    $log->bind_param("iiiss", $company_id, $user_id, $new_category_id, $desc, $ip);
                    $log->execute();

                    $msg = "Category created successfully.";
                    $msg_type = 'success';
                } else {
                    $msg = "Failed to create category.";
                    $msg_type = 'danger';
                }
            }
        }
    }
}

if (isset($_GET['edit'])) {
    $edit_id = (int)($_GET['edit'] ?? 0);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("
            SELECT category_id, category_name, sort_order, status
            FROM checklist_categories
            WHERE category_id = ? AND company_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("ii", $edit_id, $company_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $edit_category = $res->fetch_assoc();

        if ($edit_category) {
            $edit_mode = true;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $category_id = (int)($_POST['category_id'] ?? 0);
    $new_status  = trim($_POST['new_status'] ?? '');

    if ($category_id > 0 && in_array($new_status, ['active', 'inactive'])) {
        $stmt = $conn->prepare("
            UPDATE checklist_categories
            SET status = ?
            WHERE category_id = ? AND company_id = ?
        ");
        $stmt->bind_param("sii", $new_status, $category_id, $company_id);

        if ($stmt->execute()) {
            $log = $conn->prepare("
                INSERT INTO activity_logs (
                    company_id, project_id, user_id, module_name, related_id,
                    action_type, action_description, ip_address, created_at
                ) VALUES (?, NULL, ?, 'checklist_category', ?, 'status_update', ?, ?, NOW())
            ");
            $desc = "Changed checklist category status to: " . $new_status;
            $ip   = $_SERVER['REMOTE_ADDR'] ?? '';
            $log->bind_param("iiiss", $company_id, $user_id, $category_id, $desc, $ip);
            $log->execute();

            $msg = "Category status updated.";
            $msg_type = 'success';
        } else {
            $msg = "Failed to update category status.";
            $msg_type = 'danger';
        }
    }
}

$categories = [];
$stmt = $conn->prepare("
    SELECT 
        cc.category_id,
        cc.category_name,
        cc.sort_order,
        cc.status,
        cc.created_at,
        COUNT(ci.item_id) AS total_items
    FROM checklist_categories cc
    LEFT JOIN checklist_items ci
        ON cc.category_id = ci.category_id
       AND ci.company_id = cc.company_id
    WHERE cc.company_id = ?
    GROUP BY cc.category_id, cc.category_name, cc.sort_order, cc.status, cc.created_at
    ORDER BY cc.sort_order ASC, cc.category_id ASC
");
$stmt->bind_param("i", $company_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

include '../includes/header.php';
?>


<?php include 'includes/sidebar.php'; ?>
<?php if ($msg !== ''): ?>
<div class="alert alert-<?= e($msg_type); ?>"><?= e($msg); ?></div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0"><?= $edit_mode ? 'Edit Category' : 'Create New Category'; ?></h4>
        <?php if ($edit_mode): ?>
        <a href="checklist_categories.php" class="btn btn-outline-secondary btn-sm">Cancel Edit</a>
        <?php endif; ?>
    </div>

    <form method="POST" class="row g-3">
        <input type="hidden" name="category_id" value="<?= $edit_mode ? (int)$edit_category['category_id'] : 0; ?>">

        <div class="col-md-5">
            <label class="form-label">Category Name *</label>
            <input type="text" name="category_name" class="form-control"
                value="<?= $edit_mode ? e($edit_category['category_name']) : ''; ?>"
                placeholder="Example: PPE Compliance" required>
        </div>

        <div class="col-md-3">
            <label class="form-label">Sort Order</label>
            <input type="number" name="sort_order" class="form-control"
                value="<?= $edit_mode ? (int)$edit_category['sort_order'] : 1; ?>" min="0">
        </div>

        <div class="col-md-2">
            <label class="form-label">Status *</label>
            <select name="status" class="form-select" required>
                <option value="active" <?= $edit_mode && $edit_category['status'] === 'active' ? 'selected' : ''; ?>>
                    Active
                </option>
                <option value="inactive"
                    <?= $edit_mode && $edit_category['status'] === 'inactive' ? 'selected' : ''; ?>>
                    Inactive</option>
            </select>
        </div>

        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" name="save_category" class="btn btn-main w-100">
                <?= $edit_mode ? 'Update' : 'Save'; ?>
            </button>
        </div>
    </form>
</div>

<div class="card page-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">All Categories</h4>
        <span class="text-muted"><?= count($categories); ?> record(s)</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Category Name</th>
                    <th>Sort Order</th>
                    <th>Total Items</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Edit</th>
                    <th>Status Change</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                <?php $badge = ($cat['status'] === 'active') ? 'success' : 'danger'; ?>
                <tr>
                    <td>#<?= (int)$cat['category_id']; ?></td>
                    <td class="fw-semibold"><?= e($cat['category_name']); ?></td>
                    <td><?= (int)$cat['sort_order']; ?></td>
                    <td><?= (int)$cat['total_items']; ?></td>
                    <td><span class="badge bg-<?= $badge; ?>"><?= e($cat['status']); ?></span></td>
                    <td><?= e($cat['created_at']); ?></td>
                    <td>
                        <a href="checklist_categories.php?edit=<?= (int)$cat['category_id']; ?>"
                            class="btn btn-sm btn-outline-primary">
                            Edit
                        </a>
                    </td>
                    <td>
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="category_id" value="<?= (int)$cat['category_id']; ?>">
                            <select name="new_status" class="form-select form-select-sm">
                                <option value="active" <?= $cat['status'] === 'active' ? 'selected' : ''; ?>>Active
                                </option>
                                <option value="inactive" <?= $cat['status'] === 'inactive' ? 'selected' : ''; ?>>
                                    Inactive
                                </option>
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
                    <td colspan="8" class="text-center text-muted">No checklist categories found.</td>
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