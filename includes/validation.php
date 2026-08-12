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

function passwordPolicyErrors(string $password): array
{
    $errors = [];
    $length = mb_strlen($password, 'UTF-8');

    if ($length < 12 || $length > 128) {
        $errors[] = 'Password must be between 12 and 128 characters.';
    }

    if (preg_match('/\s/u', $password) === 1) {
        $errors[] = 'Password cannot contain spaces or other whitespace.';
    }

    if (preg_match('/[A-Z]/', $password) !== 1) {
        $errors[] = 'Password must include an uppercase letter.';
    }

    if (preg_match('/[a-z]/', $password) !== 1) {
        $errors[] = 'Password must include a lowercase letter.';
    }

    if (preg_match('/\d/', $password) !== 1) {
        $errors[] = 'Password must include a number.';
    }

    if (preg_match('/[^A-Za-z0-9\s]/u', $password) !== 1) {
        $errors[] = 'Password must include a symbol.';
    }

    return $errors;
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
