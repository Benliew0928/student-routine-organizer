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

    $unauthorizedTitle = 'Changed by another user';
    $unauthorizedContent = 'This update must not be stored.';
    $unauthorizedMood = 'Intrusive';
    $unauthorizedDate = '2026-07-17';
    $unauthorizedUpdate = $connection->prepare(
        'UPDATE journal_entries SET title = ?, content = ?, mood_status = ?, entry_date = ? WHERE journal_id = ? AND user_id = ?'
    );
    $unauthorizedUpdate->bind_param(
        'ssssii',
        $unauthorizedTitle,
        $unauthorizedContent,
        $unauthorizedMood,
        $unauthorizedDate,
        $journalId,
        $studentBId
    );
    $unauthorizedUpdate->execute();

    test('another user cannot update the journal entry', function () use ($unauthorizedUpdate): void {
        assertSameValue(0, $unauthorizedUpdate->affected_rows);
    });

    $updatedTitle = 'Updated private reflection';
    $updatedContent = 'The owner can safely update this entry.';
    $updatedMood = 'Hopeful';
    $updatedDate = '2026-07-18';
    $ownerUpdate = $connection->prepare(
        'UPDATE journal_entries SET title = ?, content = ?, mood_status = ?, entry_date = ? WHERE journal_id = ? AND user_id = ?'
    );
    $ownerUpdate->bind_param(
        'ssssii',
        $updatedTitle,
        $updatedContent,
        $updatedMood,
        $updatedDate,
        $journalId,
        $studentAId
    );
    $ownerUpdate->execute();

    test('owner can update every journal field', function () use ($connection, $journalId, $studentAId, $ownerUpdate): void {
        assertSameValue(1, $ownerUpdate->affected_rows);
        $updated = journalLoadForUser($connection, $journalId, $studentAId);
        assertSameValue('Updated private reflection', $updated['title'] ?? null);
        assertSameValue('The owner can safely update this entry.', $updated['content'] ?? null);
        assertSameValue('Hopeful', $updated['mood_status'] ?? null);
        assertSameValue('2026-07-18', $updated['entry_date'] ?? null);
    });

    $unauthorizedDelete = $connection->prepare(
        'DELETE FROM journal_entries WHERE journal_id = ? AND user_id = ?'
    );
    $unauthorizedDelete->bind_param('ii', $journalId, $studentBId);
    $unauthorizedDelete->execute();

    test('another user cannot delete the journal entry', function () use ($unauthorizedDelete): void {
        assertSameValue(0, $unauthorizedDelete->affected_rows);
    });

    $ownerDelete = $connection->prepare(
        'DELETE FROM journal_entries WHERE journal_id = ? AND user_id = ?'
    );
    $ownerDelete->bind_param('ii', $journalId, $studentAId);
    $ownerDelete->execute();

    test('owner can delete the journal entry', function () use ($connection, $journalId, $studentAId, $ownerDelete): void {
        assertSameValue(1, $ownerDelete->affected_rows);
        assertSameValue(null, journalLoadForUser($connection, $journalId, $studentAId));
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
