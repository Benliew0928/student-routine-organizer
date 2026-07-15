<?php
declare(strict_types=1);

const APP_NAME = 'Student Routine Organizer';

function detectBaseUrl(string $scriptName): string
{
    $path = trim(str_replace('\\', '/', $scriptName), '/');
    $segments = $path === '' ? [] : explode('/', $path);

    return count($segments) > 1 ? '/' . rawurlencode($segments[0]) : '';
}

define('BASE_URL', detectBaseUrl((string) ($_SERVER['SCRIPT_NAME'] ?? '/student-routine-organizer/index.php')));

