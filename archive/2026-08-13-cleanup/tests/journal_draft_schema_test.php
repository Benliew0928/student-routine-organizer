<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/database.php';

$connection = null;

try {
    $connection = getDatabaseConnection();
    $table = $connection->query(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'journal_drafts'"
    )->fetch_assoc();

    test('journal_drafts table is installed', function () use ($table): void {
        assertSameValue(1, (int) $table['total']);
    });

    $columns = [];
    $result = $connection->query('SHOW COLUMNS FROM journal_drafts');
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    test('journal_drafts exposes all editor columns', function () use ($columns): void {
        $expectedColumns = [
            'draft_id',
            'user_id',
            'title',
            'content',
            'mood_status',
            'entry_date',
            'template_key',
            'subject',
            'weather',
            'tags',
            'paper_style',
            'starred',
            'canvas_json',
            'created_at',
            'updated_at',
        ];

        sort($expectedColumns);
        sort($columns);

        assertSameValue(
            $expectedColumns,
            $columns
        );
    });
} catch (Throwable $exception) {
    test('journal draft schema inspection succeeds', function () use ($exception): void {
        throw $exception;
    });
}

finishTests();
