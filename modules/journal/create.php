<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$data = journalDefaultFormData();
$templates = journalTemplateOptions();
$moodSuggestions = [];
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $moodSuggestions = journalMoodSuggestions($connection, $userId);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = journalDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, journalValidateData($data));

        if (!$errors) {
            $stmt = $connection->prepare(
                'INSERT INTO journal_entries (user_id, title, content, mood_status, entry_date) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'issss',
                $userId,
                $data['title'],
                $data['content'],
                $data['mood_status'],
                $data['entry_date']
            );
            $stmt->execute();
            $journalId = (int) $connection->insert_id;

            $_SESSION['journal_draft_clear'] = 'journalDraft:' . $userId . ':create';
            setFlash('success', 'Journal entry saved successfully.');
            header('Location: ' . BASE_URL . '/modules/journal/view.php?id=' . $journalId);
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Journal entry creation is unavailable right now. Please check the database setup.';
}

$selectedTemplateKey = array_key_exists($data['template_key'], $templates) ? $data['template_key'] : 'blank';
$draftKey = 'journalDraft:' . $userId . ':create';
$pageTitle = 'Write Journal Entry';
$pageScripts = [BASE_URL . '/assets/js/journal.js'];
require __DIR__ . '/../../includes/header.php';
?>

<section class="journal-compose-heading">
    <div>
        <p class="eyebrow">A page for right now</p>
        <h1>Write Journal Entry</h1>
        <p class="muted">Begin with a template or a blank page. Every prompt remains fully editable.</p>
    </div>
    <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <?php foreach ($errors as $error): ?>
            <p><?= escapeOutput($error); ?></p>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="journal-draft-banner" data-journal-draft-banner hidden>
    <div>
        <strong>Unfinished writing found</strong>
        <p>A draft from this account is saved in this browser.</p>
    </div>
    <div class="button-row compact-actions">
        <button class="button small-button" type="button" data-journal-draft-restore>Restore Draft</button>
        <button class="button small-button danger-button" type="button" data-journal-draft-discard>Discard</button>
    </div>
</div>

<form
    class="journal-compose-form"
    method="post"
    action="<?= BASE_URL; ?>/modules/journal/create.php"
    data-journal-form
    data-draft-key="<?= escapeOutput($draftKey); ?>"
    data-journal-user="<?= $userId; ?>"
>
    <?= csrfInput(); ?>
    <input type="hidden" name="template_key" value="<?= escapeOutput($selectedTemplateKey); ?>" data-template-input>

    <section class="panel journal-template-section" aria-labelledby="template-heading">
        <div class="journal-section-heading">
            <div>
                <p class="summary-label">Writing templates</p>
                <h2 id="template-heading">Choose how to begin</h2>
            </div>
            <span class="muted">You can change every prompt.</span>
        </div>

        <div class="journal-template-grid" data-template-picker>
            <?php foreach ($templates as $key => $template): ?>
                <button
                    class="journal-template-card <?= $selectedTemplateKey === $key ? 'is-selected' : ''; ?>"
                    type="button"
                    data-template-key="<?= escapeOutput($key); ?>"
                    data-template-content="<?= escapeOutput($template['content']); ?>"
                    aria-pressed="<?= $selectedTemplateKey === $key ? 'true' : 'false'; ?>"
                >
                    <strong><?= escapeOutput($template['label']); ?></strong>
                    <span><?= escapeOutput($template['description']); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="panel journal-editor-panel">
        <div class="journal-field-grid">
            <div class="journal-field-wide">
                <label for="title">Entry Title</label>
                <input id="title" name="title" type="text" maxlength="120" value="<?= escapeOutput($data['title']); ?>" placeholder="Give this page a meaningful title" required data-journal-title>
            </div>

            <div>
                <label for="mood_status">Mood</label>
                <input id="mood_status" name="mood_status" type="text" maxlength="50" list="journal-mood-suggestions" value="<?= escapeOutput($data['mood_status']); ?>" placeholder="Calm, hopeful, tired..." required data-journal-mood>
                <datalist id="journal-mood-suggestions">
                    <?php foreach ($moodSuggestions as $mood): ?>
                        <option value="<?= escapeOutput($mood); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div>
                <label for="entry_date">Entry Date</label>
                <input id="entry_date" name="entry_date" type="date" value="<?= escapeOutput($data['entry_date']); ?>" required data-journal-date>
            </div>
        </div>

        <div class="journal-content-label">
            <label for="content">Journal Content</label>
            <span aria-live="polite"><strong data-word-count>0</strong> words &middot; <strong data-character-count>0</strong>/10,000 characters</span>
        </div>
        <textarea id="content" name="content" maxlength="10000" rows="16" placeholder="Write what is on your mind..." required data-journal-editor><?= escapeOutput($data['content']); ?></textarea>

        <div class="journal-compose-actions">
            <button class="button primary" type="submit">Save Entry</button>
            <button class="button" type="button" data-journal-draft-discard>Discard Draft</button>
            <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Cancel</a>
        </div>
    </section>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
