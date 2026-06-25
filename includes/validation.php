<?php
declare(strict_types=1);

function cleanInput(string $value): string
{
    return trim($value);
}

function escapeOutput(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function isPositiveNumber(string $value): bool
{
    return is_numeric($value) && (float) $value > 0;
}

function isNonNegativeNumber(string $value): bool
{
    return is_numeric($value) && (float) $value >= 0;
}

function csrfToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . escapeOutput(csrfToken()) . '">';
}

function verifyCsrfToken(?string $token): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
