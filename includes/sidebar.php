<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = trim((string)($_SESSION['role'] ?? ''));
$current_page = basename((string)($_SERVER['PHP_SELF'] ?? ''));
$full_name = trim((string)($_SESSION['full_name'] ?? 'User'));

$role_label = ucwords(str_replace('_', ' ', $role));

$sidebar_unread_count = 0;

if (isset($conn) && $conn instanceof mysqli && isset($_SESSION['user_id'], $_SESSION['company_id'])) {
    $sidebar_user_id = (int)$_SESSION['user_id'];
    $sidebar_company_id = (int)$_SESSION['company_id'];

    $nq = $conn->prepare("
        SELECT COUNT(*) AS total
        FROM notifications
        WHERE company_id = ? AND user_id = ? AND is_read = 0
    ");

    if ($nq) {
        $nq->bind_param("ii", $sidebar_company_id, $sidebar_user_id);
        $nq->execute();
        $nr = $nq->get_result()->fetch_assoc();
        $sidebar_unread_count = (int)($nr['total'] ?? 0);
        $nq->close();
    }
}
?>

<div class="topbar">
    <div class="d-flex align-items-center gap-3">
        <button class="menu-toggle" id="menuToggle" type="button" aria-label="Toggle menu">
            <i class="bi bi-list"></i>
        </button>

        <a href="/safetrac/dashboard.php" class="topbar-brand text-white">
            <i class="bi bi-shield-check"></i>
            <span>SafeTrack</span>
        </a>
    </div>

    <div class="topbar-right">
        <div class="topbar-user">
            Hello, <strong><?= htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></strong>
        </div>

        <span class="badge bg-light text-dark text-uppercase">
            <?= htmlspecialchars($role_label ?: 'User', ENT_QUOTES, 'UTF-8'); ?>
        </span>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-head">
        <h5 class="sidebar-title mb-0">SafeTrack Safety</h5>
        <div class="sidebar-subtitle">Professional Safety Management</div>
    </div>

    <div class="menu-section-title">Main</div>

    <a class="nav-link <?= $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="/safetrac/dashboard.php">
        <i class="bi bi-speedometer2"></i>
        <span>Dashboard</span>
    </a>

    <?php if ($role === 'company_admin'): ?>
    <div class="menu-section-title">Administration</div>

    <a class="nav-link <?= $current_page === 'projects.php' ? 'active' : ''; ?>" href="/safetrac/admin/projects.php">
        <i class="bi bi-building"></i>
        <span>Projects</span>
    </a>

    <a class="nav-link <?= $current_page === 'users.php' ? 'active' : ''; ?>" href="/safetrac/admin/users.php">
        <i class="bi bi-people"></i>
        <span>Users</span>
    </a>

    <a class="nav-link <?= $current_page === 'assign_staff.php' ? 'active' : ''; ?>"
        href="/safetrac/admin/assign_staff.php">
        <i class="bi bi-person-workspace"></i>
        <span>Assign Staff</span>
    </a>

    <a class="nav-link <?= $current_page === 'analytics.php' ? 'active' : ''; ?>" href="/safetrac/admin/analytics.php">
        <i class="bi bi-bar-chart-line"></i>
        <span>Analytics</span>
    </a>

    <a class="nav-link <?= $current_page === 'checklist_categories.php' ? 'active' : ''; ?>"
        href="/safetrac/admin/checklist_categories.php">
        <i class="bi bi-ui-checks-grid"></i>
        <span>Checklist Categories</span>
    </a>

    <a class="nav-link <?= $current_page === 'checklist_items.php' ? 'active' : ''; ?>"
        href="/safetrac/admin/checklist_items.php">
        <i class="bi bi-card-checklist"></i>
        <span>Checklist Items</span>
    </a>

    <a class="nav-link <?= $current_page === 'inspection_report.php' ? 'active' : ''; ?>"
        href="/safetrac/reports/inspection_report.php">
        <i class="bi bi-file-earmark-text"></i>
        <span>Inspection Report</span>
    </a>

    <a class="nav-link <?= $current_page === 'issue_report.php' ? 'active' : ''; ?>"
        href="/safetrac/reports/issue_report.php">
        <i class="bi bi-file-earmark-bar-graph"></i>
        <span>Issue Report</span>
    </a>
    <?php endif; ?>

    <?php if ($role === 'safety_officer'): ?>
    <div class="menu-section-title">Inspection</div>

    <a class="nav-link <?= $current_page === 'inspections.php' ? 'active' : ''; ?>"
        href="/safetrac/safety_officer/inspections.php">
        <i class="bi bi-clipboard-data"></i>
        <span>My Inspections</span>
    </a>

    <a class="nav-link <?= $current_page === 'new_inspection.php' ? 'active' : ''; ?>"
        href="/safetrac/safety_officer/new_inspection.php">
        <i class="bi bi-plus-square"></i>
        <span>New Inspection</span>
    </a>

    <a class="nav-link <?= in_array($current_page, ['recheck_issues.php', 'recheck_issue.php'], true) ? 'active' : ''; ?>"
        href="/safetrac/safety_officer/recheck_issues.php">
        <i class="bi bi-arrow-repeat"></i>
        <span>Recheck Issues</span>
    </a>
    <?php endif; ?>

    <?php if ($role === 'supervisor'): ?>
    <div class="menu-section-title">Corrective Actions</div>

    <a class="nav-link <?= in_array($current_page, ['issues_list.php', 'issue_update.php'], true) ? 'active' : ''; ?>"
        href="/safetrac/supervisor/issues_list.php">
        <i class="bi bi-tools"></i>
        <span>Assigned Issues</span>
    </a>
    <?php endif; ?>

    <div class="menu-section-title">Common</div>

    <a class="nav-link <?= $current_page === 'notifications.php' ? 'active' : ''; ?>"
        href="/safetrac/notifications.php">
        <i class="bi bi-bell"></i>
        <span>Notifications</span>
        <?php if ($sidebar_unread_count > 0): ?>
        <span class="sidebar-badge"><?= $sidebar_unread_count; ?></span>
        <?php endif; ?>
    </a>

    <a class="nav-link" href="/safetrac/logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
    </a>

    <div class="sidebar-footer">
        SafeTrack Panel<br>
        <small>Professional Safety System</small>
    </div>
</aside>

<div class="main-content">