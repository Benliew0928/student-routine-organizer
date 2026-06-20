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

