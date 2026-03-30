<?php
require_once 'includes/session.php';
require_once 'config/db.php';

$pageTitle = "Notifications";

$user_id    = (int)($_SESSION['user_id'] ?? 0);
$company_id = (int)($_SESSION['company_id'] ?? 0);

$msg = '';
$msg_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_one_read'])) {
    $notification_id = (int)($_POST['notification_id'] ?? 0);

    if ($notification_id > 0) {
        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE notification_id = ? AND company_id = ? AND user_id = ?
        ");
        $stmt->bind_param("iii", $notification_id, $company_id, $user_id);
        $stmt->execute();

        $msg = "Notification marked as read.";
        $msg_type = 'success';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    $stmt = $conn->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE company_id = ? AND user_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $company_id, $user_id);
    $stmt->execute();

    $msg = "All notifications marked as read.";
    $msg_type = 'success';
}

$table_filter = trim($_GET['related_table'] ?? '');
$read_filter  = trim($_GET['read_status'] ?? '');

$sql = "
    SELECT notification_id, title, message, related_table, related_id, is_read, created_at
    FROM notifications
    WHERE company_id = ? AND user_id = ?
";
$params = [$company_id, $user_id];
$types  = "ii";

if ($table_filter !== '') {
    $sql .= " AND related_table = ? ";
    $params[] = $table_filter;
    $types .= "s";
}

if ($read_filter !== '') {
    if ($read_filter === 'read') {
        $sql .= " AND is_read = 1 ";
    } elseif ($read_filter === 'unread') {
        $sql .= " AND is_read = 0 ";
    }
}

$sql .= " ORDER BY notification_id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$notifications = [];
while ($row = $res->fetch_assoc()) {
    $notifications[] = $row;
}

include 'includes/header.php';
include 'includes/sidebar.php';
?>

<?php if ($msg !== ''): ?>
<div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?>">
    <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<div class="card page-card p-4 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-md-8">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Notification Type</label>
                    <select name="related_table" class="form-select">
                        <option value="">All Types</option>
                        <option value="project_staff" <?= $table_filter === 'project_staff' ? 'selected' : ''; ?>>
                            Project Assignment</option>
                        <option value="issues" <?= $table_filter === 'issues' ? 'selected' : ''; ?>>Issue Alerts
                        </option>
                        <option value="issue_updates" <?= $table_filter === 'issue_updates' ? 'selected' : ''; ?>>Issue
                            Updates</option>
                        <option value="inspections" <?= $table_filter === 'inspections' ? 'selected' : ''; ?>>Inspection
                            Alerts</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Read Status</label>
                    <select name="read_status" class="form-select">
                        <option value="">All</option>
                        <option value="unread" <?= $read_filter === 'unread' ? 'selected' : ''; ?>>Unread</option>
                        <option value="read" <?= $read_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <button type="submit" class="btn btn-main w-100">Filter Notifications</button>
                </div>
            </form>
        </div>

        <div class="col-md-4 text-md-end">
            <form method="POST">
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary">
                    <i class="bi bi-check2-all me-1"></i> Mark All as Read
                </button>
            </form>
        </div>
    </div>
</div>

<div class="card page-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">My Notifications</h4>
        <span class="text-muted"><?= count($notifications); ?> record(s)</span>
    </div>

    <?php if (!empty($notifications)): ?>
    <div class="row g-3">
        <?php foreach ($notifications as $n): ?>
        <?php
                $typeClass = 'secondary';
                $typeLabel = 'General';

                if ($n['related_table'] === 'project_staff') {
                    $typeClass = 'primary';
                    $typeLabel = 'Assignment';
                } elseif ($n['related_table'] === 'issues') {
                    $typeClass = 'danger';
                    $typeLabel = 'Issue';
                } elseif ($n['related_table'] === 'issue_updates') {
                    $typeClass = 'warning';
                    $typeLabel = 'Update';
                } elseif ($n['related_table'] === 'inspections') {
                    $typeClass = 'info';
                    $typeLabel = 'Inspection';
                }
                ?>
        <div class="col-md-12">
            <div class="border rounded-4 p-3 <?= ((int)$n['is_read'] === 0) ? 'bg-light' : ''; ?>">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <h6 class="mb-1">
                            <?= htmlspecialchars($n['title'], ENT_QUOTES, 'UTF-8'); ?>
                            <?php if ((int)$n['is_read'] === 0): ?>
                            <span class="badge bg-danger ms-2">New</span>
                            <?php endif; ?>
                        </h6>

                        <div class="mb-2">
                            <span class="badge bg-<?= $typeClass; ?>">
                                <?= htmlspecialchars($typeLabel, ENT_QUOTES, 'UTF-8'); ?>
                            </span>

                            <?php if (!empty($n['related_id'])): ?>
                            <span class="badge bg-secondary ms-1">
                                Ref #<?= (int)$n['related_id']; ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <p class="mb-2 text-muted">
                            <?= htmlspecialchars($n['message'], ENT_QUOTES, 'UTF-8'); ?>
                        </p>

                        <small class="text-muted">
                            <?= htmlspecialchars($n['created_at'], ENT_QUOTES, 'UTF-8'); ?>
                        </small>
                    </div>

                    <div>
                        <?php if ((int)$n['is_read'] === 0): ?>
                        <form method="POST">
                            <input type="hidden" name="notification_id" value="<?= (int)$n['notification_id']; ?>">
                            <button type="submit" name="mark_one_read" class="btn btn-sm btn-outline-success">
                                Mark Read
                            </button>
                        </form>
                        <?php else: ?>
                        <span class="badge bg-success">Read</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="text-center text-muted py-4">No notifications found.</div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>