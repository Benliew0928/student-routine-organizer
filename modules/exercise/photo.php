<?php
declare(strict_types=1);

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/exercise_helpers.php';

requireLogin();

$exerciseId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$exerciseId) {
    http_response_code(404);
    exit;
}

try {
    $connection = getDatabaseConnection();
    $exercise = exerciseLoadForUser($connection, (int) $exerciseId, (int) $_SESSION['user_id']);

    if (!$exercise || !exerciseHasPhoto($exercise)) {
        http_response_code(404);
        exit;
    }

    $path = exercisePhotoStoragePath($exercise['photo_filename']);
    $directory = realpath(exercisePhotoDirectory());
    $realPath = $path === null ? false : realpath($path);
    if ($directory === false || $realPath === false
        || !str_starts_with($realPath, $directory . DIRECTORY_SEPARATOR)
        || !is_file($realPath)) {
        http_response_code(404);
        exit;
    }

    header('Content-Type: ' . $exercise['photo_mime_type']);
    header('Content-Length: ' . (string) filesize($realPath));
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    readfile($realPath);
    exit;
} catch (Throwable $exception) {
    logApplicationException($exception, 'exercise photo delivery');
    http_response_code(500);
    exit;
}
