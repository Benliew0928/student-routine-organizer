<?php
declare(strict_types=1);

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$draftId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$draftId) {
    setFlash('error', 'Journal draft was not found.');
    header('Location: ' . BASE_URL . '/modules/journal/index.php');
    exit;
}

$draft = null;
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $draft = journalLoadDraftForUser($connection, (int) $draftId, $userId);
    if (!$draft) {
        setFlash('error', 'Journal draft was not found.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        } elseif (journalDeleteDraftForUser($connection, (int) $draftId, $userId)) {
            setFlash('success', 'Journal draft deleted.');
            header('Location: ' . BASE_URL . '/modules/journal/index.php');
            exit;
        } else {
            $errors[] = 'Journal draft was not found.';
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Journal draft deletion is unavailable right now.';
}

$pageTitle = 'Delete Journal Draft';
require __DIR__ . '/../../includes/header.php';
$draftTitle = $draft && trim((string) $draft['title']) !== ''
    ? (string) $draft['title']
    : 'Untitled draft';
?>

<section class="panel journal-delete-panel">
    <p class="eyebrow">Draft management</p>
    <h1>Delete Journal Draft</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
        <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
    <?php elseif ($draft): ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= escapeOutput($error); ?></div>
        <?php endforeach; ?>

        <div class="journal-delete-preview">
            <h2><?= escapeOutput($draftTitle); ?></h2>
            <p><?= escapeOutput(
                trim((string) $draft['content']) !== ''
                    ? journalPreview((string) $draft['content'], 240)
                    : 'No writing yet'
            ); ?></p>
            <p class="muted">
                Saved <?= escapeOutput(date('M j, Y g:i A', strtotime($draft['updated_at']))); ?>
            </p>
        </div>

        <form method="post" action="<?= BASE_URL; ?>/modules/journal/draft_delete.php?id=<?= (int) $draftId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button danger-button" type="submit"><i class="bi bi-trash3"></i> Delete Draft</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/create.php?draft_id=<?= (int) $draftId; ?>">
                    <i class="bi bi-shield-check"></i> Keep Draft
                </a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
