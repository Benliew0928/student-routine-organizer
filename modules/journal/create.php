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
$draftId = null;
$draft = null;

$rawDraftId = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['draft_id'] ?? '')
    : ($_GET['draft_id'] ?? '');

if (is_array($rawDraftId)) {
    setFlash('error', 'Journal draft was not found.');
    header('Location: ' . BASE_URL . '/modules/journal/index.php');
    exit;
}

$rawDraftId = trim((string) $rawDraftId);
if ($rawDraftId !== '') {
    $validatedDraftId = filter_var(
        $rawDraftId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($validatedDraftId === false) {
        setFlash('error', 'Journal draft was not found.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }

    $draftId = (int) $validatedDraftId;
}

try {
    $connection = getDatabaseConnection();
    $moodSuggestions = journalMoodSuggestions($connection, $userId);

    if ($draftId !== null) {
        $draft = journalLoadDraftForUser($connection, $draftId, $userId);

        if ($draft === null) {
            setFlash('error', 'Journal draft was not found.');
            header('Location: ' . BASE_URL . '/modules/journal/index.php');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $data = [
                'title' => (string) $draft['title'],
                'content' => (string) $draft['content'],
                'mood_status' => (string) $draft['mood_status'],
                'entry_date' => (string) ($draft['entry_date'] ?? ''),
                'template_key' => (string) $draft['template_key'],
            ];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = journalDataFromRequest($_POST);
        $intent = (string) ($_POST['intent'] ?? 'publish');

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        if ($intent === 'save_draft') {
            $errors = array_merge($errors, journalValidateDraftData($data));

            if ($draftId === null && !journalDraftHasMeaningfulContent($data)) {
                $errors[] = 'Add something before saving a draft.';
            }

            if (!$errors) {
                $savedId = journalSaveDraft(
                    $connection,
                    $userId,
                    $draftId,
                    $data
                );

                if ($savedId === null) {
                    setFlash('error', 'Journal draft was not found.');
                    header('Location: ' . BASE_URL . '/modules/journal/index.php');
                    exit;
                }

                setFlash('success', 'Draft saved successfully.');
                header('Location: ' . BASE_URL . '/modules/journal/index.php');
                exit;
            }
        } elseif ($intent === 'publish') {
            $errors = array_merge($errors, journalValidateData($data));

            if (!$errors) {
                $journalId = journalPublishDraft(
                    $connection,
                    $userId,
                    $draftId,
                    $data
                );

                if ($journalId === null) {
                    setFlash('error', 'Journal draft was not found.');
                    header('Location: ' . BASE_URL . '/modules/journal/index.php');
                    exit;
                }

                setFlash('success', 'Journal entry saved successfully.');
                header('Location: ' . BASE_URL . '/modules/journal/view.php?id=' . $journalId);
                exit;
            }
        } else {
            $errors[] = 'Please choose a valid journal action.';
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Journal entry creation is unavailable right now. Please check the database setup.';
}

$selectedTemplateKey = array_key_exists($data['template_key'], $templates) ? $data['template_key'] : 'blank';
$pageTitle = $draftId ? 'Continue Journal Draft' : 'Write Journal Entry';
$pageScripts = [BASE_URL . '/assets/js/journal.js'];
require __DIR__ . '/../../includes/header.php';
?>

<section class="journal-compose-heading">
    <div>
        <p class="eyebrow">A page for right now</p>
        <h1><?= $draftId ? 'Continue Journal Draft' : 'Write Journal Entry'; ?></h1>
        <p class="muted">
            <?= $draftId
                ? 'Continue writing from your last database save.'
                : 'Begin with a template or a blank page. Every prompt remains fully editable.'; ?>
        </p>
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

<form
    class="journal-compose-form"
    method="post"
    action="<?= BASE_URL; ?>/modules/journal/create.php"
    data-journal-form
    data-autosave-url="<?= BASE_URL; ?>/modules/journal/draft_autosave.php"
>
    <?= csrfInput(); ?>
    <input type="hidden" name="draft_id" value="<?= $draftId ? (int) $draftId : ''; ?>" data-draft-id>
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

        <div
            class="journal-save-status"
            data-journal-save-status
            data-state="<?= $draftId ? 'saved' : 'idle'; ?>"
            aria-live="polite"
        >
            <span data-journal-save-text>
                <?= $draftId && $draft
                    ? 'Draft saved at ' . escapeOutput(date('g:i A', strtotime($draft['updated_at'])))
                    : 'Not saved yet'; ?>
            </span>
            <button class="button small-button" type="button" data-journal-save-retry hidden>Retry</button>
        </div>

        <div class="journal-compose-actions">
            <button class="button primary" type="submit" name="intent" value="publish">Save Entry</button>
            <button class="button" type="submit" name="intent" value="save_draft" formnovalidate>
                Save Draft &amp; Exit
            </button>
            <?php if ($draftId): ?>
                <a class="button danger-button" href="<?= BASE_URL; ?>/modules/journal/draft_delete.php?id=<?= (int) $draftId; ?>">
                    Delete Draft
                </a>
            <?php endif; ?>
            <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
        </div>
    </section>
</form>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
