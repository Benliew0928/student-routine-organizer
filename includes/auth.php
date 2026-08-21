<?php
declare(strict_types=1);

require_once __DIR__ . '/flash.php';

function applicationCookiePath(): string
{
    return defined('BASE_URL') && BASE_URL !== '' ? BASE_URL : '/';
}

function applicationUsesHttps(): bool
{
    return (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function applicationCookieOptions(int $expires = 0): array
{
    return [
        'expires' => $expires,
        'path' => applicationCookiePath(),
        'secure' => applicationUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function applicationSessionCookieOptions(): array
{
    return [
        'lifetime' => 0,
        'path' => applicationCookiePath(),
        'secure' => applicationUsesHttps(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(applicationSessionCookieOptions());
    session_start();
}

function sessionAccessState(): string
{
    if (!isset($_SESSION['user_id'])) {
        return 'guest';
    }

    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity > 0 && (time() - $lastActivity) >= SESSION_IDLE_TIMEOUT_SECONDS) {
        return 'expired';
    }

    return 'active';
}

function refreshAuthenticatedSession(): void
{
    $_SESSION['last_activity'] = time();
}

function expireAuthenticatedSession(): void
{
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function destroyCurrentSession(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        setcookie(session_name(), '', applicationCookieOptions(time() - 42000));
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function isLoggedIn(): bool
{
    return sessionAccessState() === 'active';
}

function currentUserRole(): ?string
{
    return $_SESSION['role'] ?? null;
}

function currentUserName(): string
{
    return $_SESSION['full_name'] ?? 'User';
}

function redirectAfterLogin(string $role): void
{
    if ($role === 'admin') {
        header('Location: ' . BASE_URL . '/admin/dashboard.php');
        exit;
    }

    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

function requireLogin(): void
{
    $accessState = sessionAccessState();

    if ($accessState === 'expired') {
        expireAuthenticatedSession();
        setFlash('error', 'Your session expired after 30 minutes of inactivity. Please log in again.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    if ($accessState !== 'active') {
        setFlash('error', 'Please log in to continue.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }

    refreshAuthenticatedSession();
}

function requireAdmin(): void
{
    requireLogin();

    if (currentUserRole() !== 'admin') {
        setFlash('error', 'Admin access is required.');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function requireStudent(): void
{
    requireLogin();

    // Keep administrators on their own dashboard if they follow a student link.
    if (currentUserRole() === 'admin') {
        redirectAfterLogin('admin');
    }

    if (currentUserRole() !== 'student') {
        setFlash('error', 'Student access is required.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}
