<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check whether user is logged in
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

/**
 * Get current logged in user role
 */
function currentRole(): string
{
    return trim((string)($_SESSION['role'] ?? ''));
}

/**
 * Redirect helper
 */
function redirectTo(string $path): void
{
    header("Location: " . $path);
    exit;
}

/**
 * Require login
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        redirectTo('/safetrac/login.php');
    }
}

/**
 * Require one of the allowed roles
 */
function requireRole(array $roles = []): void
{
    requireLogin();

    $role = currentRole();

    if (empty($roles) || !in_array($role, $roles, true)) {
        redirectTo('/safetrac/dashboard.php');
    }
}
?>