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
    logApplicationException($exception, 'journal view');
    $pageError = 'This journal entry is unavailable right now. Please check the database setup.';
}

$pageTitle = $entry ? $entry['title'] . ' | Noted.edu' : 'Journal Entry';
$pageScripts = [
    BASE_URL . '/assets/js/journal_editor.js?v=20260730-v11'
];
require __DIR__ . '/../../includes/header.php';
?>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
<?php elseif ($entry): ?>
    <?php
    $paperStyle = !empty($entry['paper_style']) ? (string) $entry['paper_style'] : 'lined';
    $subject = !empty($entry['subject']) ? (string) $entry['subject'] : 'General';
    $weather = !empty($entry['weather']) ? (string) $entry['weather'] : '☀️ Sunny';
    ?>
    <div class="noted-app-container">
        <header class="noted-header">
            <a class="noted-brand" href="<?= BASE_URL; ?>/modules/journal/index.php">
                <div class="noted-brand-icon"><i class="bi bi-journal-richtext"></i></div>
                <div class="noted-brand-text">
                    <h2>Noted.edu</h2>
                    <span>Reading Canvas View</span>
                </div>
            </a>

            <div class="button-row compact-actions">
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php"><i class="bi bi-arrow-left"></i> All Notes</a>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/edit.php?id=<?= (int) $entry['journal_id']; ?>"><i class="bi bi-pencil"></i> Edit Canvas</a>
                <button type="button" class="button" onclick="window.print()"><i class="bi bi-file-earmark-pdf"></i> PDF Export</button>
                <a class="button danger-button" href="<?= BASE_URL; ?>/modules/journal/delete.php?id=<?= (int) $entry['journal_id']; ?>"><i class="bi bi-trash3"></i> Delete</a>
            </div>
        </header>

        <article class="noted-canvas-wrapper" style="box-shadow:var(--nj-shadow-lg);">
            <!-- Canvas Meta Header -->
            <div class="canvas-meta-header" style="user-select: none; cursor: default;">
                <div class="canvas-header-top">
                    <div class="canvas-badges">
                        <span class="subject-tag-pill subject-general" style="font-size:12px; padding:6px 12px; cursor: default;"><i class="bi bi-book"></i> <?= escapeOutput($subject); ?></span>
                        <span class="subject-tag-pill subject-general" style="font-size:12px; padding:6px 12px; background:#e8effb; color:#3569b7; cursor: default;"><?= escapeOutput($weather); ?></span>
                        <span class="journal-mood-pill" style="font-size:12px; padding:6px 12px; background:var(--nj-rose-light); color:var(--nj-rose); font-weight:700; border-radius:14px; cursor: default;"><i class="bi bi-emoji-smile"></i> <?= escapeOutput($entry['mood_status']); ?></span>
                    </div>
                    <time datetime="<?= escapeOutput($entry['entry_date']); ?>" style="font-weight:700; color:var(--nj-muted); cursor: default;">
                        <i class="bi bi-calendar3"></i> <?= escapeOutput(date('F j, Y', strtotime($entry['entry_date']))); ?>
                    </time>
                </div>

                <h1 class="note-title-input" style="font-size:30px; line-height:1.2; padding:8px 0; cursor: default; user-select: none;"><?= escapeOutput($entry['title']); ?></h1>
                
                <p class="muted" style="margin:0; font-size:12px; cursor: default;">
                    Created <?= escapeOutput(date('M j, Y g:i A', strtotime($entry['created_at']))); ?>
                    <?php if ($entry['updated_at'] !== $entry['created_at']): ?>
                        &middot; Updated <?= escapeOutput(date('M j, Y g:i A', strtotime($entry['updated_at']))); ?>
                    <?php endif; ?>
                </p>
            </div>

            <!-- Paper Surface -->
            <div id="notedPaperContainer" class="noted-paper-container paper-<?= escapeOutput($paperStyle); ?>" style="min-height:500px; position:relative; overflow:hidden;">
                <input type="hidden" id="canvasJsonInput" value="<?= escapeOutput($entry['canvas_json'] ?? ''); ?>">
                <div id="notedPaperContent" class="paper-editor-content">
                    <?php
                    $rawContent = (string) ($entry['content'] ?? '');
                    if (strpos($rawContent, '<div') !== false || strpos($rawContent, '<audio') !== false || strpos($rawContent, '<p') !== false || strpos($rawContent, '<h2') !== false || strpos($rawContent, '<span') !== false || strpos($rawContent, '<button') !== false) {
                        echo $rawContent;
                    } else {
                        echo nl2br(escapeOutput($rawContent));
                    }
                    ?>
                </div>
                <canvas id="drawingCanvas" class="drawing-layer-canvas" style="pointer-events:none;"></canvas>
            </div>
        </article>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
