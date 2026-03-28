<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/db.php';

if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0) {
    header("Location: /safetrac/dashboard.php");
    exit;
}

$pageTitle = "Login";
include 'includes/header.php';

$msg = '';
$msg_type = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $msg = "Please enter email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("
            SELECT user_id, company_id, full_name, email, password_hash, role, status
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();

            if ($user['status'] !== 'active') {
                $msg = "Your account is inactive.";
            } elseif (password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id']    = (int)$user['user_id'];
                $_SESSION['company_id'] = (int)$user['company_id'];
                $_SESSION['role']       = $user['role'];
                $_SESSION['full_name']  = $user['full_name'];

                $login_user_id    = (int)$user['user_id'];
                $login_company_id = (int)$user['company_id'];
                $ip_address       = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent       = $_SERVER['HTTP_USER_AGENT'] ?? '';

                $log = $conn->prepare("
                    INSERT INTO login_history (
                        user_id, company_id, email_used, login_status, ip_address, user_agent, login_time
                    ) VALUES (?, ?, ?, 'success', ?, ?, NOW())
                ");
                $log->bind_param("iisss", $login_user_id, $login_company_id, $email, $ip_address, $user_agent);
                $log->execute();

                $updateLogin = $conn->prepare("
                    UPDATE users
                    SET last_login_at = NOW()
                    WHERE user_id = ?
                    LIMIT 1
                ");
                $updateLogin->bind_param("i", $login_user_id);
                $updateLogin->execute();

                header("Location: /safetrac/dashboard.php");
                exit;
            } else {
                $msg = "Invalid password.";

                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

                $log = $conn->prepare("
                    INSERT INTO login_history (
                        user_id, company_id, email_used, login_status, ip_address, user_agent, login_time, failure_reason
                    ) VALUES (?, ?, ?, 'failed', ?, ?, NOW(), 'Invalid password')
                ");
                $login_user_id    = (int)$user['user_id'];
                $login_company_id = (int)$user['company_id'];
                $log->bind_param("iisss", $login_user_id, $login_company_id, $email, $ip_address, $user_agent);
                $log->execute();
            }
        } else {
            $msg = "No account found with this email.";

            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

            $log = $conn->prepare("
                INSERT INTO login_history (
                    user_id, company_id, email_used, login_status, ip_address, user_agent, login_time, failure_reason
                ) VALUES (NULL, NULL, ?, 'failed', ?, ?, NOW(), 'Email not found')
            ");
            $log->bind_param("sss", $email, $ip_address, $user_agent);
            $log->execute();
        }
    }
}
?>

<div class="auth-wrap">
    <div class="card auth-card">
        <div class="auth-header">
            <h2 class="mb-1">Welcome Back</h2>
            <div class="small-muted text-white-50">Login to SafeTrack</div>
        </div>

        <div class="auth-body">
            <?php if ($msg !== ''): ?>
            <div class="alert alert-<?= htmlspecialchars($msg_type, ENT_QUOTES, 'UTF-8'); ?>">
                <?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control"
                        value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-main w-100">Login</button>

                <div class="text-center mt-3">
                    <span class="small-muted">Don't have an account?</span>
                    <a href="register.php" class="text-decoration-none fw-semibold"> Register</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'includes/auth_footer.php'; ?>