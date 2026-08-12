<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

/**
 * Writes server-side diagnostic details without exposing them to students.
 */
function applicationLogDirectory(): string
{
    return dirname(__DIR__) . '/storage/logs';
}

function logApplicationException(Throwable $exception, string $context = 'application'): void
{
    try {
        $directory = applicationLogDirectory();
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            return;
        }

        $request = (string) ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' '
            . (string) ($_SERVER['REQUEST_URI'] ?? 'command line');
        $line = sprintf(
            "[%s] context=%s request=%s exception=%s message=%s file=%s line=%d%s",
            date(DATE_ATOM),
            $context,
            $request,
            get_class($exception),
            str_replace(["\r", "\n"], ' ', $exception->getMessage()),
            $exception->getFile(),
            $exception->getLine(),
            PHP_EOL
        );

        error_log($line, 3, $directory . '/application.log');
    } catch (Throwable) {
        // Logging must never replace the original application response.
    }
}

function handleUncaughtApplicationException(Throwable $exception): never
{
    logApplicationException($exception, 'uncaught');

    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "An unexpected application error occurred. Check storage/logs/application.log.\n");
        exit(1);
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><title>Application error</title><p>Something went wrong. Please try again later.</p>';
    exit;
}

if (!defined('APPLICATION_ERROR_HANDLER_REGISTERED')) {
    define('APPLICATION_ERROR_HANDLER_REGISTERED', true);
    set_exception_handler('handleUncaughtApplicationException');
}
