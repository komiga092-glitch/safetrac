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

// Checklist categories are managed directly in the database.
// No create/edit form is provided in PHP for category insertion.


$categories = [];
$stmt = $conn->prepare("
    SELECT 
        cc.category_id,
        cc.category_name,
        cc.sort_order,
        cc.status,
        COUNT(ci.item_id) AS total_items
    FROM checklist_categories cc
    LEFT JOIN checklist_items ci
        ON cc.category_id = ci.category_id
    GROUP BY cc.category_id, cc.category_name, cc.sort_order, cc.status
    ORDER BY cc.sort_order ASC, cc.category_id ASC
");
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $categories[] = $row;
}

include '../includes/header.php';
?>


<?php include '../includes/sidebar.php'; ?>
<?php if ($msg !== ''): ?>
<div class="alert alert-<?= e($msg_type); ?>"><?= e($msg); ?></div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <h4 class="mb-0">Checklist Categories</h4>
    <p class="small-muted mb-0">
        Checklist categories are inserted and managed directly in the database.
        The PHP admin interface no longer provides create/edit controls for categories.
    </p>
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
                    <th>Category Name</th>
                    <th>Sort Order</th>
                    <th>Total Items</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $cat): ?>
                <?php $badge = ($cat['status'] === 'active') ? 'success' : 'danger'; ?>
                <tr>
                    <td class="fw-semibold"><?= e($cat['category_name']); ?></td>
                    <td><?= (int)$cat['sort_order']; ?></td>
                    <td><?= (int)$cat['total_items']; ?></td>
                    <td><span class="badge bg-<?= $badge; ?>"><?= e($cat['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
                <?php else: ?>
                <tr>
                    <td colspan="4" class="text-center text-muted">No checklist categories found.</td>
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