<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Redirect helper
 */
function sessionRedirect(string $path): void
{
    header("Location: " . $path);
    exit;
}

/**
 * Check login session
 */
if (!isset($_SESSION['user_id']) || (int)$_SESSION['user_id'] <= 0) {
    sessionRedirect('/safetrac/login.php');
}
?>