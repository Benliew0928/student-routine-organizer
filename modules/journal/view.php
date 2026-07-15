<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$journalId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$journalId) {
    setFlash('error', 'Journal entry was not found.');
    header('Location: ' . BASE_URL . '/modules/journal/index.php');
    exit;
}

$entry = null;
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $entry = journalLoadForUser($connection, (int) $journalId, $userId);

    if (!$entry) {
        setFlash('error', 'Journal entry was not found.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }
} catch (Throwable $exception) {
    $pageError = 'This journal entry is unavailable right now. Please check the database setup.';
}

$pageTitle = $entry ? $entry['title'] : 'Journal Entry';
require __DIR__ . '/../../includes/header.php';
?>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
<?php elseif ($entry): ?>
    <article class="journal-reading-page">
        <header class="journal-reading-header">
            <div>
                <a class="journal-back-link" href="<?= BASE_URL; ?>/modules/journal/index.php">&larr; Back to Journal</a>
                <div class="journal-reading-meta">
                    <span class="journal-mood-pill"><?= escapeOutput($entry['mood_status']); ?></span>
                    <time datetime="<?= escapeOutput($entry['entry_date']); ?>"><?= escapeOutput(date('F j, Y', strtotime($entry['entry_date']))); ?></time>
                </div>
                <h1><?= escapeOutput($entry['title']); ?></h1>
                <p class="muted">
                    Created <?= escapeOutput(date('M j, Y g:i A', strtotime($entry['created_at']))); ?>
                    <?php if ($entry['updated_at'] !== $entry['created_at']): ?>
                        &middot; Updated <?= escapeOutput(date('M j, Y g:i A', strtotime($entry['updated_at']))); ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="button-row compact-actions">
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/edit.php?id=<?= (int) $entry['journal_id']; ?>">Edit Entry</a>
                <a class="button danger-button" href="<?= BASE_URL; ?>/modules/journal/delete.php?id=<?= (int) $entry['journal_id']; ?>">Delete</a>
            </div>
        </header>

        <div class="journal-reading-content"><?= nl2br(escapeOutput($entry['content'])); ?></div>
    </article>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
