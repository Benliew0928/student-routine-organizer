<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userName = currentUserName();
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
        'subject' => (string) ($entry['subject'] ?? 'General'),
        'weather' => (string) ($entry['weather'] ?? '☀️ Sunny'),
        'tags' => (string) ($entry['tags'] ?? ''),
        'paper_style' => (string) ($entry['paper_style'] ?? 'lined'),
        'canvas_json' => (string) ($entry['canvas_json'] ?? ''),
    ]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = journalDataFromRequest($_POST);

        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        }

        $errors = array_merge($errors, journalValidateData($data));

        if (!$errors) {
            journalUpdateEntry($connection, (int) $journalId, $userId, $data);

            setFlash('success', 'Journal entry updated successfully.');
            header('Location: ' . BASE_URL . '/modules/journal/view.php?id=' . (int) $journalId);
            exit;
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Journal editing is unavailable right now. Please check the database setup.';
}

$pageTitle = 'Edit Journal Entry - Noted.edu';
$pageScripts = [
    BASE_URL . '/assets/js/journal_editor.js?v=20260730-v11',
    BASE_URL . '/assets/js/journal.js'
];
require __DIR__ . '/../../includes/header.php';
?>

<div class="noted-app-container" data-noted-journal-page>

    <!-- Header Bar -->
    <header class="noted-header">
        <a class="noted-brand" href="<?= BASE_URL; ?>/modules/journal/index.php">
            <div class="noted-brand-icon"><i class="bi bi-journal-richtext"></i></div>
            <div class="noted-brand-text">
                <h2>Noted.edu</h2>
                <span>Editing Note #<?= (int) $journalId; ?></span>
            </div>
        </a>

        <div class="noted-view-switcher">
            <button type="button" class="noted-view-btn active" data-view="editor"><i class="bi bi-pencil-square"></i> Editor Canvas</button>
            <button type="button" class="noted-view-btn" data-view="calendar"><i class="bi bi-calendar3"></i> Timeline Calendar</button>
        </div>

        <div class="noted-tools-bar">
            <button type="button" class="noted-tool-btn" id="toggleDrawingBtn" title="Toggle Freehand Drawing"><i class="bi bi-pen"></i></button>
            <button type="button" class="noted-tool-btn" id="addImageBtn" title="Add Image Sticker"><i class="bi bi-image"></i></button>
            <input type="file" id="imageFileInput" accept="image/*" style="display:none;">
            <button type="button" class="noted-tool-btn" id="addStickyBtn" title="Insert Sticky Note"><i class="bi bi-sticky"></i></button>
        </div>
    </header>

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

    <!-- Main Grid -->
    <div id="notedEditorView" class="noted-main-grid">
        
        <!-- Sidebar -->
        <aside class="noted-sidebar">
            <div class="student-profile-banner">
                <div class="student-avatar"><?= strtoupper(substr($userName, 0, 1)); ?></div>
                <div class="student-info">
                    <h4><?= escapeOutput($userName); ?></h4>
                    <p>UTAR Student &middot; Science &amp; Tech</p>
                    <span class="streak-badge"><i class="bi bi-fire"></i> 12d Study Streak</span>
                </div>
            </div>

            <div>
                <div class="sidebar-section-title">
                    <span>Subjects &amp; Courses</span>
                </div>
                <div class="subject-tag-list">
                    <span class="subject-tag-pill subject-math">Mathematics</span>
                    <span class="subject-tag-pill subject-biology">Biology</span>
                    <span class="subject-tag-pill subject-history">History</span>
                    <span class="subject-tag-pill subject-literature">Literature</span>
                    <span class="subject-tag-pill subject-cs">Computer Science</span>
                </div>
            </div>

            <div class="button-row" style="margin-top:auto;">
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $journalId; ?>"><i class="bi bi-arrow-left"></i> Cancel Edit</a>
            </div>
        </aside>

        <!-- Main Canvas Form -->
        <form
            class="journal-compose-form noted-canvas-wrapper"
            method="post"
            action="<?= BASE_URL; ?>/modules/journal/edit.php?id=<?= (int) $journalId; ?>"
        >
            <?= csrfInput(); ?>
            <input type="hidden" name="canvas_json" id="canvasJsonInput" value="<?= escapeOutput($data['canvas_json'] ?? ''); ?>">

            <!-- Metadata Header -->
            <div class="canvas-meta-header">
                <div class="canvas-header-top">
                    <div class="canvas-badges">
                        <select name="subject" class="meta-selector">
                            <option value="General" <?= $data['subject'] === 'General' ? 'selected' : ''; ?>>📚 General</option>
                            <option value="Mathematics" <?= $data['subject'] === 'Mathematics' ? 'selected' : ''; ?>>📐 Mathematics</option>
                            <option value="Biology" <?= $data['subject'] === 'Biology' ? 'selected' : ''; ?>>🧬 Biology</option>
                            <option value="History" <?= $data['subject'] === 'History' ? 'selected' : ''; ?>>📜 History</option>
                            <option value="Literature" <?= $data['subject'] === 'Literature' ? 'selected' : ''; ?>>📖 Literature</option>
                            <option value="CS" <?= $data['subject'] === 'CS' ? 'selected' : ''; ?>>💻 Computer Science</option>
                        </select>

                        <select name="weather" class="meta-selector">
                            <option value="☀️ Sunny">☀️ Sunny</option>
                            <option value="☁️ Cloudy">☁️ Cloudy</option>
                            <option value="🌧️ Rainy">🌧️ Rainy</option>
                        </select>

                        <input type="date" name="entry_date" value="<?= escapeOutput($data['entry_date']); ?>" class="meta-selector" required data-journal-date>
                    </div>

                    <div style="display:flex; gap:10px; align-items:center;">
                        <label style="font-size:12px; font-weight:700; color:var(--nj-muted);">Paper Style:</label>
                        <select id="paperStyleSelect" name="paper_style" class="meta-selector">
                            <option value="lined" <?= $data['paper_style'] === 'lined' ? 'selected' : ''; ?>>📄 Lined Paper</option>
                            <option value="blank" <?= $data['paper_style'] === 'blank' ? 'selected' : ''; ?>>⚪ Blank Canvas</option>
                            <option value="grid" <?= $data['paper_style'] === 'grid' ? 'selected' : ''; ?>>📐 Grid / Graph</option>
                            <option value="dot" <?= $data['paper_style'] === 'dot' ? 'selected' : ''; ?>>🔘 Dot Matrix</option>
                            <option value="cornell" <?= $data['paper_style'] === 'cornell' ? 'selected' : ''; ?>>📑 Cornell Notes</option>
                            <option value="parchment" <?= $data['paper_style'] === 'parchment' ? 'selected' : ''; ?>>📜 Vintage Parchment</option>
                        </select>
                    </div>
                </div>

                <input id="title" name="title" type="text" maxlength="120" value="<?= escapeOutput($data['title']); ?>" placeholder="Title your journal entry..." class="note-title-input" required data-journal-title>
                
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <?php
                    $defaultMoodOptions = [
                        '😊 Happy',
                        '🎯 Focused',
                        '😌 Calm',
                        '⚡ Energetic',
                        '🔥 Productive',
                        '😴 Tired',
                        '🌧️ Sad',
                        '🧘 Relaxed',
                        '🤔 Thoughtful',
                        '✨ Inspired',
                        '🎉 Excited'
                    ];
                    $currentMoodVal = (string) ($data['mood_status'] ?? '');
                    if ($currentMoodVal !== '' && !in_array($currentMoodVal, $defaultMoodOptions, true)) {
                        $defaultMoodOptions[] = $currentMoodVal;
                    }
                    ?>
                    <select name="mood_status" class="meta-selector mood-selector" style="max-width:200px;" required data-journal-mood>
                        <option value="" disabled <?= empty($currentMoodVal) ? 'selected' : ''; ?>>😊 Select Mood...</option>
                        <?php foreach ($defaultMoodOptions as $mOpt): ?>
                            <option value="<?= escapeOutput($mOpt); ?>" <?= $currentMoodVal === $mOpt ? 'selected' : ''; ?>><?= escapeOutput($mOpt); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <div style="font-size:12px; color:var(--nj-muted); white-space:nowrap; flex-shrink:0;">
                        <span aria-live="polite"><strong data-word-count>0</strong> words &middot; <strong data-character-count>0</strong>/10,000 chars</span>
                    </div>
                </div>
            </div>

            <!-- Formatting & Stamp Toolbar -->
            <div class="noted-formatting-bar">
                <button type="button" class="fmt-btn" onclick="document.execCommand('bold', false, null)" title="Bold"><b>B</b></button>
                <button type="button" class="fmt-btn" onclick="document.execCommand('italic', false, null)" title="Italic"><i>I</i></button>
                <button type="button" class="fmt-btn" onclick="document.execCommand('underline', false, null)" title="Underline"><u>U</u></button>
                <div class="fmt-divider"></div>
                <button type="button" class="fmt-btn" onclick="document.execCommand('formatBlock', false, 'h2')" title="Header 2">H2</button>
                <button type="button" class="fmt-btn" onclick="document.execCommand('formatBlock', false, 'h3')" title="Header 3">H3</button>
                <button type="button" class="fmt-btn" onclick="document.execCommand('insertUnorderedList', false, null)" title="Bullet List"><i class="bi bi-list-ul"></i></button>
                <button type="button" class="fmt-btn" onclick="document.execCommand('insertOrderedList', false, null)" title="Numbered List"><i class="bi bi-list-ol"></i></button>
                <div class="fmt-divider"></div>
                <span style="font-size:11px; font-weight:800; color:var(--nj-muted); margin-right:4px;">Stamps:</span>
                <button type="button" class="stamp-btn" data-stamp="⭐">⭐</button>
                <button type="button" class="stamp-btn" data-stamp="✅">✅</button>
                <button type="button" class="stamp-btn" data-stamp="🎯">🎯</button>
                <button type="button" class="stamp-btn" data-stamp="💡">💡</button>
            </div>

            <!-- Paper Surface -->
            <div id="notedPaperContainer" class="noted-paper-container paper-<?= escapeOutput($data['paper_style'] ?: 'lined'); ?>">
                <div id="notedPaperContent" class="paper-editor-content" contenteditable="true"><?= nl2br(escapeOutput($data['content'])); ?></div>
                <textarea id="content" name="content" style="display:none;" required data-journal-editor><?= escapeOutput($data['content']); ?></textarea>

                <canvas id="drawingCanvas" class="drawing-layer-canvas"></canvas>

                <div id="drawingToolbar" class="drawing-toolbar" style="display:none;">
                    <button type="button" class="tool-choice-btn selected" id="penToolBtn" title="Pen"><i class="bi bi-pen-fill"></i></button>
                    <button type="button" class="tool-choice-btn" id="highlighterToolBtn" title="Highlighter"><i class="bi bi-highlighter"></i></button>
                    <button type="button" class="tool-choice-btn" id="eraserToolBtn" title="Eraser"><i class="bi bi-eraser-fill"></i></button>
                    <div class="fmt-divider"></div>
                    <div class="color-dot active" data-color="#236a54" style="background:#236a54;"></div>
                    <div class="color-dot" data-color="#b84a62" style="background:#b84a62;"></div>
                    <div class="color-dot" data-color="#3569b7" style="background:#3569b7;"></div>
                    <div class="color-dot" data-color="#d9822b" style="background:#d9822b;"></div>
                    <div class="fmt-divider"></div>
                    <input type="range" id="strokeWidthSlider" min="1" max="20" value="3" style="width:70px;">
                    <button type="button" class="tool-choice-btn" id="undoDrawingBtn" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" class="tool-choice-btn" id="clearDrawingBtn" title="Clear"><i class="bi bi-trash"></i></button>
                </div>
            </div>

            <!-- Action Bar -->
            <div style="padding:14px 24px; background:var(--nj-surface-soft); border-top:1px solid var(--nj-border); display:flex; justify-content:space-between; align-items:center;">
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $journalId; ?>"><i class="bi bi-arrow-left"></i> Back to Reading View</a>
                <button class="button primary" type="submit"><i class="bi bi-check-lg"></i> Update Journal Entry</button>
            </div>
        </form>
    </div>

    <!-- Timeline View (Dynamic JS Calendar Engine) -->
    <div id="notedCalendarView" class="calendar-timeline-view" style="display:none;"></div>

</div>



<script>
document.addEventListener('DOMContentLoaded', function() {
    const editable = document.getElementById('notedPaperContent');
    const textarea = document.getElementById('content');
    if (editable && textarea) {
        editable.addEventListener('input', function() {
            textarea.value = editable.innerHTML;
        });
    }
});
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
