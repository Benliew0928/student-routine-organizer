<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/flash.php';

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
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
    if (!isLoggedIn()) {
        setFlash('error', 'Please log in to continue.');
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
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
