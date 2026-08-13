<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../modules/journal/journal_helpers.php';

$connection = null;
$userIds = [];
$beforeDrafts = null;
$beforeEntries = null;

try {
    $connection = getDatabaseConnection();
    $beforeDrafts = (int) $connection->query(
        'SELECT COUNT(*) AS total FROM journal_drafts'
    )->fetch_assoc()['total'];
    $beforeEntries = (int) $connection->query(
        'SELECT COUNT(*) AS total FROM journal_entries'
    )->fetch_assoc()['total'];

    $suffix = bin2hex(random_bytes(5));
    $passwordHash = password_hash('temporary-password', PASSWORD_DEFAULT);
    $role = 'student';
    $insertUser = $connection->prepare(
        'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );

    foreach (['A', 'B'] as $label) {
        $name = 'Draft Test ' . $label;
        $email = 'draft-' . strtolower($label) . '-' . $suffix . '@example.test';
        $insertUser->bind_param('ssss', $name, $email, $passwordHash, $role);
        $insertUser->execute();
        $userIds[] = (int) $connection->insert_id;
    }

    [$studentAId, $studentBId] = $userIds;
    $draft = [
        'title' => '',
        'content' => 'An unfinished cross-device thought.',
        'mood_status' => '',
        'entry_date' => '',
        'template_key' => 'daily_reflection',
    ];

    $draftId = journalSaveDraft($connection, $studentAId, null, $draft);

    test('owner can create and load a partial draft', function () use ($connection, $draftId, $studentAId): void {
        assertTrueValue(is_int($draftId) && $draftId > 0);
        $saved = journalLoadDraftForUser($connection, $draftId, $studentAId);
        assertSameValue('An unfinished cross-device thought.', $saved['content'] ?? null);
        assertSameValue(null, $saved['entry_date'] ?? null);
    });

    test('another user cannot load or update the draft', function () use ($connection, $draftId, $studentBId, $draft): void {
        assertSameValue(null, journalLoadDraftForUser($connection, $draftId, $studentBId));
        assertSameValue(null, journalSaveDraft($connection, $studentBId, $draftId, $draft));
    });

    test('another user cannot delete the draft', function () use ($connection, $draftId, $studentBId): void {
        assertSameValue(false, journalDeleteDraftForUser($connection, $draftId, $studentBId));
    });

    test('another user cannot publish the draft', function () use ($connection, $draftId, $studentAId, $studentBId): void {
        $entryCount = (int) $connection->query(
            'SELECT COUNT(*) AS total FROM journal_entries'
        )->fetch_assoc()['total'];
        $published = [
            'title' => 'Unauthorized publication',
            'content' => 'This entry must not be inserted.',
            'mood_status' => 'Intrusive',
            'entry_date' => '2026-07-23',
            'template_key' => 'daily_reflection',
        ];

        assertSameValue(null, journalPublishDraft($connection, $studentBId, $draftId, $published));
        assertTrueValue(journalLoadDraftForUser($connection, $draftId, $studentAId) !== null);
        $afterCount = (int) $connection->query(
            'SELECT COUNT(*) AS total FROM journal_entries'
        )->fetch_assoc()['total'];
        assertSameValue($entryCount, $afterCount);
    });

    $draft['title'] = 'Updated draft';
    $draft['mood_status'] = 'Focused';
    $draft['entry_date'] = '2026-07-23';
    $updatedId = journalSaveDraft($connection, $studentAId, $draftId, $draft);

    test('owner can update and list the draft', function () use ($connection, $draftId, $updatedId, $studentAId): void {
        assertSameValue($draftId, $updatedId);
        $drafts = journalListDraftsForUser($connection, $studentAId);
        assertSameValue($draftId, (int) ($drafts[0]['draft_id'] ?? 0));
        assertSameValue('Updated draft', $drafts[0]['title'] ?? null);
    });

    $published = [
        'title' => 'Finished reflection',
        'content' => 'This content is ready to publish.',
        'mood_status' => 'Hopeful',
        'entry_date' => '2026-07-23',
        'template_key' => 'daily_reflection',
    ];
    $journalId = journalPublishDraft($connection, $studentAId, $draftId, $published);

    test('publishing creates one entry and consumes the owned draft', function () use ($connection, $journalId, $draftId, $studentAId): void {
        assertTrueValue(is_int($journalId) && $journalId > 0);
        assertSameValue(null, journalLoadDraftForUser($connection, $draftId, $studentAId));
        assertSameValue('Finished reflection', journalLoadForUser($connection, $journalId, $studentAId)['title'] ?? null);
    });
} catch (Throwable $exception) {
    test('journal draft database setup succeeds', function () use ($exception): void {
        throw $exception;
    });
} finally {
    if ($connection instanceof mysqli && $userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        $delete = $connection->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
        $params = $userIds;
        journalBindParams($delete, $types, $params);
        $delete->execute();
    }
}

if ($connection instanceof mysqli && $beforeDrafts !== null && $beforeEntries !== null) {
    test('draft database test cleans up its records', function () use ($connection, $beforeDrafts, $beforeEntries): void {
        $afterDrafts = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_drafts')->fetch_assoc()['total'];
        $afterEntries = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_entries')->fetch_assoc()['total'];
        assertSameValue($beforeDrafts, $afterDrafts);
        assertSameValue($beforeEntries, $afterEntries);
    });
}

finishTests();
