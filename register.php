<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header("Location: /safetrac/dashboard.php");
    exit;
}

$pageTitle = "Company Registration";
include 'includes/header.php';

$msg = '';
$msg_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name    = trim($_POST['company_name'] ?? '');
    $company_email   = trim($_POST['company_email'] ?? '');
    $company_phone   = trim($_POST['company_phone'] ?? '');
    $company_address = trim($_POST['company_address'] ?? '');

    $full_name    = trim($_POST['full_name'] ?? '');
    $user_email   = trim($_POST['user_email'] ?? '');
    $password     = $_POST['password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if (
        $company_name === '' || $company_email === '' || $company_phone === '' ||
        $full_name === '' || $user_email === '' || $password === '' || $confirm_pass === ''
    ) {
        $msg = "Please fill all required fields.";
    } elseif (!filter_var($company_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Company email is invalid.";
    } elseif (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $msg = "User email is invalid.";
    } elseif (strlen($password) < 6) {
        $msg = "Password must be at least 6 characters.";
    } elseif ($password !== $confirm_pass) {
        $msg = "Password and confirm password do not match.";
    } else {
        $check1 = $conn->prepare("SELECT company_id FROM companies WHERE email = ? LIMIT 1");
        $check1->bind_param("s", $company_email);
        $check1->execute();
        $res1 = $check1->get_result();

        $check2 = $conn->prepare("SELECT user_id FROM users WHERE email = ? LIMIT 1");
        $check2->bind_param("s", $user_email);
        $check2->execute();
        $res2 = $check2->get_result();

        if ($res1->num_rows > 0) {
            $msg = "Company email already exists.";
        } elseif ($res2->num_rows > 0) {
            $msg = "User email already exists.";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmtCompany = $conn->prepare("
                INSERT INTO companies (company_name, email, phone, address, status, created_at)
                VALUES (?, ?, ?, ?, 'active', NOW())
            ");
            $stmtCompany->bind_param("ssss", $company_name, $company_email, $company_phone, $company_address);

            if ($stmtCompany->execute()) {
                $company_id = $stmtCompany->insert_id;

                $stmtUser = $conn->prepare("
                    INSERT INTO users (company_id, full_name, email, password_hash, role, status, created_at)
                    VALUES (?, ?, ?, ?, 'company_admin', 'active', NOW())
                ");
                $stmtUser->bind_param("isss", $company_id, $full_name, $user_email, $password_hash);

                if ($stmtUser->execute()) {
                    $user_id = $stmtUser->insert_id;

                    $_SESSION['user_id']    = (int)$user_id;
                    $_SESSION['company_id'] = (int)$company_id;
                    $_SESSION['role']       = 'company_admin';
                    $_SESSION['full_name']  = $full_name;

                    header("Location: /safetrac/dashboard.php");
                    exit;
                } else {
                    $msg = "User registration failed.";
                }
            } else {
                $msg = "Company registration failed.";
            }
        }
    }
}
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-header">
            <h2 class="mb-1">Create Company Account</h2>
            <div class="small-muted text-white-50">Professional Construction Safety Management</div>
        </div>

        <div class="auth-body">
            <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <h6 class="mb-3 text-primary">Company Details</h6>

                <div class="mb-3">
                    <label class="form-label">Company Name *</label>
                    <input type="text" name="company_name" class="form-control"
                        value="<?= htmlspecialchars($_POST['company_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Company Email *</label>
                    <input type="email" name="company_email" class="form-control"
                        value="<?= htmlspecialchars($_POST['company_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Company Phone *</label>
                    <input type="text" name="company_phone" class="form-control"
                        value="<?= htmlspecialchars($_POST['company_phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Company Address</label>
                    <textarea name="company_address" class="form-control"
                        rows="2"><?= htmlspecialchars($_POST['company_address'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                </div>

                <hr>

                <h6 class="mb-3 text-primary">Admin Details</h6>

                <div class="mb-3">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="full_name" class="form-control"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Admin Email *</label>
                    <input type="email" name="user_email" class="form-control"
                        value="<?= htmlspecialchars($_POST['user_email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password *</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Confirm Password *</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-main w-100">Register Company</button>

                <div class="text-center mt-3">
                    <span class="small-muted">Already have an account?</span>
                    <a href="login.php" class="text-decoration-none fw-semibold"> Login</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/auth_footer.php'; ?>