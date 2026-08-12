<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/error_handler.php';

function runMigrationFile(mysqli $connection, string $path): void
{
    $sql = file_get_contents($path);
    if ($sql === false) {
        throw new RuntimeException('Could not read migration file: ' . basename($path));
    }

    if (!$connection->multi_query($sql)) {
        throw new RuntimeException($connection->error);
    }

    do {
        if ($result = $connection->store_result()) {
            $result->free();
        }
    } while ($connection->more_results() && $connection->next_result());

    if ($connection->errno !== 0) {
        throw new RuntimeException($connection->error);
    }
}

try {
    $db = getDatabaseConnection();
    foreach ([
        'journal_drafts_migration.sql',
        'journal_migration.sql',
        'money_goals_migration.sql',
        'exercise_photo_migration.sql',
        'password_reset_migration.sql',
    ] as $filename) {
        runMigrationFile($db, __DIR__ . '/' . $filename);
    }

    echo "Database upgrade completed.\n";
} catch (Throwable $exception) {
    logApplicationException($exception, 'database migration');
    fwrite(STDERR, 'Database upgrade failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
