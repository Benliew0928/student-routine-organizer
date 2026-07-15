<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../modules/journal/journal_helpers.php';

$connection = null;
$beforeCount = null;

try {
    $connection = getDatabaseConnection();
    $beforeCount = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_entries')->fetch_assoc()['total'];
    $connection->begin_transaction();

    $suffix = bin2hex(random_bytes(5));
    $passwordHash = password_hash('temporary-password', PASSWORD_DEFAULT);
    $role = 'student';

    $insertUser = $connection->prepare(
        'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );

    $studentAName = 'Journal Test A';
    $studentAEmail = 'journal-a-' . $suffix . '@example.test';
    $insertUser->bind_param('ssss', $studentAName, $studentAEmail, $passwordHash, $role);
    $insertUser->execute();
    $studentAId = (int) $connection->insert_id;

    $studentBName = 'Journal Test B';
    $studentBEmail = 'journal-b-' . $suffix . '@example.test';
    $insertUser->bind_param('ssss', $studentBName, $studentBEmail, $passwordHash, $role);
    $insertUser->execute();
    $studentBId = (int) $connection->insert_id;

    $title = 'Private reflection';
    $content = 'Only the owner should be able to read this entry.';
    $mood = 'Calm';
    $entryDate = '2026-07-16';
    $insertEntry = $connection->prepare(
        'INSERT INTO journal_entries (user_id, title, content, mood_status, entry_date) VALUES (?, ?, ?, ?, ?)'
    );
    $insertEntry->bind_param('issss', $studentAId, $title, $content, $mood, $entryDate);
    $insertEntry->execute();
    $journalId = (int) $connection->insert_id;

    test('owner can load the journal entry', function () use ($connection, $journalId, $studentAId): void {
        $owned = journalLoadForUser($connection, $journalId, $studentAId);
        assertSameValue('Private reflection', $owned['title'] ?? null);
    });

    test('another user cannot load the journal entry', function () use ($connection, $journalId, $studentBId): void {
        assertSameValue(null, journalLoadForUser($connection, $journalId, $studentBId));
    });

    test('mood suggestions are scoped to the owner', function () use ($connection, $studentAId, $studentBId): void {
        assertSameValue(['Calm'], journalMoodSuggestions($connection, $studentAId));
        assertSameValue([], journalMoodSuggestions($connection, $studentBId));
    });
} catch (Throwable $exception) {
    test('database test setup succeeds', function () use ($exception): void {
        throw $exception;
    });
} finally {
    if ($connection instanceof mysqli) {
        $connection->rollback();
    }
}

if ($connection instanceof mysqli && $beforeCount !== null) {
    test('database transaction rolls back test records', function () use ($connection, $beforeCount): void {
        $afterCount = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_entries')->fetch_assoc()['total'];
        assertSameValue($beforeCount, $afterCount);
    });
}

finishTests();
