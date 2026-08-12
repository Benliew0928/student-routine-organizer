<?php
declare(strict_types=1);

const APP_NAME = 'Student Routine Organizer';
const SESSION_IDLE_TIMEOUT_SECONDS = 1800;
// Change this one value if Apache is served from a different local host or port.
const APP_PUBLIC_ORIGIN = 'http://localhost';

date_default_timezone_set('Asia/Kuala_Lumpur');

function detectBaseUrl(string $scriptName, ?string $projectDirectory = null): string
{
    $path = trim(str_replace('\\', '/', $scriptName), '/');
    $segments = $path === '' ? [] : explode('/', $path);
    $projectDirectory ??= basename(dirname(__DIR__));

    return count($segments) > 1 && $segments[0] === $projectDirectory
        ? '/' . rawurlencode($projectDirectory)
        : '';
}

define('BASE_URL', detectBaseUrl((string) ($_SERVER['SCRIPT_NAME'] ?? '/student-routine-organizer/index.php')));
define('APP_PUBLIC_URL', APP_PUBLIC_ORIGIN . BASE_URL);

require_once __DIR__ . '/../includes/error_handler.php';

