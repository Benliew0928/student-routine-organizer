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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            setFlash('error', 'Your session token expired. Please try again.');
            header('Location: ' . BASE_URL . '/modules/journal/delete.php?id=' . (int) $journalId);
            exit;
        }

        $stmt = $connection->prepare(
            'DELETE FROM journal_entries WHERE journal_id = ? AND user_id = ?'
        );
        $stmt->bind_param('ii', $journalId, $userId);
        $stmt->execute();

        setFlash('success', 'Journal entry deleted successfully.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }
} catch (Throwable $exception) {
    logApplicationException($exception, 'journal delete');
    $pageError = 'Journal deletion is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Delete Journal Entry';
require __DIR__ . '/../../includes/header.php';
?>

<section class="panel journal-delete-panel">
    <p class="eyebrow">Permanent action</p>
    <h1>Delete Journal Entry</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
        <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
    <?php elseif ($entry): ?>
        <p class="muted">This removes the entry permanently. Review the details before confirming.</p>

        <div class="journal-delete-preview">
            <div class="journal-card-topline">
                <span class="journal-mood-pill"><?= escapeOutput($entry['mood_status']); ?></span>
                <time datetime="<?= escapeOutput($entry['entry_date']); ?>"><?= escapeOutput(date('F j, Y', strtotime($entry['entry_date']))); ?></time>
            </div>
            <h2><?= escapeOutput($entry['title']); ?></h2>
            <p><?= escapeOutput(journalPreview($entry['content'], 240)); ?></p>
        </div>

        <form method="post" action="<?= BASE_URL; ?>/modules/journal/delete.php?id=<?= (int) $journalId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button primary danger-primary" type="submit"><i class="bi bi-trash3"></i> Delete Entry Permanently</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $journalId; ?>"><i class="bi bi-shield-check"></i> Keep Entry</a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
