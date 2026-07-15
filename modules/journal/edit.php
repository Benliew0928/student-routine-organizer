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

$data = journalDefaultFormData();
$moodSuggestions = [];
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $entry = journalLoadForUser($connection, (int) $journalId, $userId);

    if (!$entry) {
        setFlash('error', 'Journal entry was not found.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }

    $moodSuggestions = journalMoodSuggestions($connection, $userId);
    $data = array_merge($data, [
        'title' => $entry['title'],
        'content' => $entry['content'],
        'mood_status' => $entry['mood_status'],
        'entry_date' => $entry['entry_date'],
        'template_key' => 'blank',
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = journalDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, journalValidateData($data));

        if (!$errors) {
            $stmt = $connection->prepare(
                'UPDATE journal_entries SET title = ?, content = ?, mood_status = ?, entry_date = ? '
                . 'WHERE journal_id = ? AND user_id = ?'
            );
            $stmt->bind_param(
                'ssssii',
                $data['title'],
                $data['content'],
                $data['mood_status'],
                $data['entry_date'],
                $journalId,
                $userId
            );
            $stmt->execute();

            setFlash('success', 'Journal entry updated successfully.');
            header('Location: ' . BASE_URL . '/modules/journal/view.php?id=' . (int) $journalId);
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Journal editing is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Journal Entry';
require __DIR__ . '/../../includes/header.php';
?>

<section class="journal-compose-heading">
    <div>
        <p class="eyebrow">Refine this page</p>
        <h1>Edit Journal Entry</h1>
        <p class="muted">Update the writing, mood, or date while keeping the entry private to your account.</p>
    </div>
    <a class="button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $journalId; ?>">Cancel</a>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <?php foreach ($errors as $error): ?>
                <p><?= escapeOutput($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="journal-compose-form" method="post" action="<?= BASE_URL; ?>/modules/journal/edit.php?id=<?= (int) $journalId; ?>">
        <?= csrfInput(); ?>

        <section class="panel journal-editor-panel">
            <div class="journal-field-grid">
                <div class="journal-field-wide">
                    <label for="title">Entry Title</label>
                    <input id="title" name="title" type="text" maxlength="120" value="<?= escapeOutput($data['title']); ?>" required>
                </div>

                <div>
                    <label for="mood_status">Mood</label>
                    <input id="mood_status" name="mood_status" type="text" maxlength="50" list="journal-mood-suggestions" value="<?= escapeOutput($data['mood_status']); ?>" required>
                    <datalist id="journal-mood-suggestions">
                        <?php foreach ($moodSuggestions as $mood): ?>
                            <option value="<?= escapeOutput($mood); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <div>
                    <label for="entry_date">Entry Date</label>
                    <input id="entry_date" name="entry_date" type="date" value="<?= escapeOutput($data['entry_date']); ?>" required>
                </div>
            </div>

            <div class="journal-content-label">
                <label for="content">Journal Content</label>
                <span aria-live="polite"><strong data-word-count>0</strong> words &middot; <strong data-character-count>0</strong>/10,000 characters</span>
            </div>
            <textarea id="content" name="content" maxlength="10000" rows="16" required data-journal-editor><?= escapeOutput($data['content']); ?></textarea>

            <div class="journal-compose-actions">
                <button class="button primary" type="submit">Save Changes</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $journalId; ?>">Cancel</a>
            </div>
        </section>
    </form>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
