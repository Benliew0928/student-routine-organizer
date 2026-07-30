<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userName = currentUserName();
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
                'subject' => (string) ($draft['subject'] ?? 'General'),
                'weather' => (string) ($draft['weather'] ?? '☀️ Sunny'),
                'tags' => (string) ($draft['tags'] ?? ''),
                'paper_style' => (string) ($draft['paper_style'] ?? 'lined'),
                'canvas_json' => (string) ($draft['canvas_json'] ?? ''),
            ];
        }
    } else if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_GET['entry_date']) && journalIsValidDate((string) $_GET['entry_date'])) {
        $data['entry_date'] = (string) $_GET['entry_date'];
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
$pageTitle = $draftId ? 'Continue Journal Draft' : 'Noted.edu - Interactive Journal';
$pageScripts = [
    BASE_URL . '/assets/js/notability_journal.js?v=20260730-v11',
    BASE_URL . '/assets/js/journal.js'
];
require __DIR__ . '/../../includes/header.php';
?>

<div class="noted-app-container" data-noted-journal-page>

    <!-- 1. Header Bar -->
    <header class="noted-header">
        <a class="noted-brand" href="<?= BASE_URL; ?>/modules/journal/index.php">
            <div class="noted-brand-icon"><i class="bi bi-journal-richtext"></i></div>
            <div class="noted-brand-text">
                <h2>Noted.edu</h2>
                <span>Student Paper Journal</span>
            </div>
        </a>

        <div class="noted-search-box">
            <i class="bi bi-search"></i>
            <input type="search" placeholder="Search notes by title or content...">
        </div>

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

    <!-- 2. Editor Canvas View -->
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
                    <span>Quick Filters</span>
                </div>
                <div class="quick-filter-group">
                    <a class="quick-filter-item active" href="<?= BASE_URL; ?>/modules/journal/index.php">
                        <span><i class="bi bi-journal-text"></i> All Entries</span>
                        <span class="badge-count">Active</span>
                    </a>
                    <a class="quick-filter-item" href="<?= BASE_URL; ?>/modules/journal/index.php?starred=1">
                        <span><i class="bi bi-star-fill" style="color:var(--nj-amber);"></i> Starred Notes</span>
                        <span class="badge-count">4</span>
                    </a>
                </div>
            </div>

            <div>
                <div class="sidebar-section-title">
                    <span>Subjects &amp; Courses</span>
                    <button type="button" class="add-subject-btn" id="newSubjectBtn">+ New</button>
                </div>
                <div class="subject-tag-list">
                    <span class="subject-tag-pill subject-math">Mathematics</span>
                    <span class="subject-tag-pill subject-biology">Biology</span>
                    <span class="subject-tag-pill subject-history">History</span>
                    <span class="subject-tag-pill subject-literature">Literature</span>
                    <span class="subject-tag-pill subject-cs">Computer Science</span>
                </div>
            </div>

            <div>
                <div class="sidebar-section-title">
                    <span>Writing Templates</span>
                </div>
                <div class="journal-template-grid" data-template-picker>
                    <?php foreach ($templates as $key => $template): ?>
                        <button
                            class="journal-template-card <?= $selectedTemplateKey === $key ? 'is-selected' : ''; ?>"
                            type="button"
                            data-template-key="<?= escapeOutput($key); ?>"
                            data-template-content="<?= escapeOutput($template['content']); ?>"
                        >
                            <strong><?= escapeOutput($template['label']); ?></strong>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

        </aside>

        <!-- Main Interactive Paper Canvas -->
        <form
            class="journal-compose-form noted-canvas-wrapper"
            method="post"
            action="<?= BASE_URL; ?>/modules/journal/create.php"
            data-journal-form
            data-autosave-url="<?= BASE_URL; ?>/modules/journal/draft_autosave.php"
        >
            <?= csrfInput(); ?>
            <input type="hidden" name="draft_id" value="<?= $draftId ? (int) $draftId : ''; ?>" data-draft-id>
            <input type="hidden" name="template_key" value="<?= escapeOutput($selectedTemplateKey); ?>" data-template-input>
            <input type="hidden" name="canvas_json" id="canvasJsonInput" value="<?= escapeOutput($data['canvas_json'] ?? ''); ?>">

            <!-- Metadata Header -->
            <div class="canvas-meta-header">
                <div class="canvas-header-top">
                    <div class="canvas-badges">
                        <select name="subject" class="meta-selector">
                            <option value="General">📚 General</option>
                            <option value="Mathematics">📐 Mathematics</option>
                            <option value="Biology">🧬 Biology</option>
                            <option value="History">📜 History</option>
                            <option value="Literature">📖 Literature</option>
                            <option value="CS">💻 Computer Science</option>
                        </select>

                        <select name="weather" class="meta-selector">
                            <option value="☀️ Sunny">☀️ Sunny</option>
                            <option value="☁️ Cloudy">☁️ Cloudy</option>
                            <option value="🌧️ Rainy">🌧️ Rainy</option>
                            <option value="🌙 Clear Night">🌙 Clear Night</option>
                        </select>

                        <input type="date" name="entry_date" value="<?= escapeOutput($data['entry_date']); ?>" class="meta-selector" required data-journal-date>
                    </div>

                    <div style="display:flex; gap:10px; align-items:center;">
                        <label style="font-size:12px; font-weight:700; color:var(--nj-muted);">Paper Style:</label>
                        <select id="paperStyleSelect" name="paper_style" class="meta-selector">
                            <option value="lined" selected>📄 Lined Paper</option>
                            <option value="blank">⚪ Blank Canvas</option>
                            <option value="grid">📐 Grid / Graph</option>
                            <option value="dot">🔘 Dot Matrix</option>
                            <option value="cornell">📑 Cornell Notes</option>
                            <option value="parchment">📜 Vintage Parchment</option>
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
                <button type="button" class="stamp-btn" data-stamp="⭐" title="Star Stamp">⭐</button>
                <button type="button" class="stamp-btn" data-stamp="✅" title="Check Stamp">✅</button>
                <button type="button" class="stamp-btn" data-stamp="🎯" title="Target Stamp">🎯</button>
                <button type="button" class="stamp-btn" data-stamp="💡" title="Idea Stamp">💡</button>
                <button type="button" class="stamp-btn" data-stamp="❤️" title="Heart Stamp">❤️</button>
                <button type="button" class="stamp-btn" data-stamp="📌" title="Pin Stamp">📌</button>
            </div>

            <!-- Interactive Paper Canvas Surface -->
            <div id="notedPaperContainer" class="noted-paper-container paper-lined">
                
                <!-- Contenteditable Text Area -->
                <div id="notedPaperContent" class="paper-editor-content" contenteditable="true"><?= $data['content'] !== '' ? nl2br(escapeOutput($data['content'])) : 'Begin writing your study reflection here...'; ?></div>
                <textarea id="content" name="content" style="display:none;" required data-journal-editor><?= escapeOutput($data['content']); ?></textarea>

                <!-- Freehand Drawing HTML5 Canvas Layer -->
                <canvas id="drawingCanvas" class="drawing-layer-canvas"></canvas>

                <!-- Floating Drawing Toolbar -->
                <div id="drawingToolbar" class="drawing-toolbar" style="display:none;">
                    <button type="button" class="tool-choice-btn selected" id="penToolBtn" title="Pen Tool"><i class="bi bi-pen-fill"></i></button>
                    <button type="button" class="tool-choice-btn" id="highlighterToolBtn" title="Highlighter"><i class="bi bi-highlighter"></i></button>
                    <button type="button" class="tool-choice-btn" id="eraserToolBtn" title="Eraser"><i class="bi bi-eraser-fill"></i></button>
                    <div class="fmt-divider"></div>
                    <div class="color-dot active" data-color="#236a54" style="background:#236a54;"></div>
                    <div class="color-dot" data-color="#b84a62" style="background:#b84a62;"></div>
                    <div class="color-dot" data-color="#3569b7" style="background:#3569b7;"></div>
                    <div class="color-dot" data-color="#d9822b" style="background:#d9822b;"></div>
                    <div class="color-dot" data-color="#17231b" style="background:#17231b;"></div>
                    <div class="fmt-divider"></div>
                    <input type="range" id="strokeWidthSlider" min="1" max="20" value="3" style="width:70px;" title="Stroke Size">
                    <div class="fmt-divider"></div>
                    <button type="button" class="tool-choice-btn" id="undoDrawingBtn" title="Undo"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button type="button" class="tool-choice-btn" id="clearDrawingBtn" title="Clear All Drawings"><i class="bi bi-trash"></i></button>
                </div>
            </div>

            <!-- Action Bar -->
            <div style="padding:14px 24px; background:var(--nj-surface-soft); border-top:1px solid var(--nj-border); display:flex; justify-content:space-between; align-items:center;">
                <div class="journal-save-status" data-journal-save-status data-state="<?= $draftId ? 'saved' : 'idle'; ?>">
                    <span data-journal-save-text><?= $draftId && $draft ? 'Draft saved at ' . escapeOutput(date('g:i A', strtotime($draft['updated_at']))) : 'Not saved yet'; ?></span>
                </div>
                
                <div style="display:flex; gap:10px;">
                    <button class="button" type="submit" name="intent" value="save_draft" formnovalidate><i class="bi bi-file-earmark"></i> Save Draft</button>
                    <button class="button primary" type="submit" name="intent" value="publish"><i class="bi bi-check-lg"></i> Save &amp; Publish Entry</button>
                </div>
            </div>
        </form>
    </div>

    <!-- 3. Calendar & Timeline View (Dynamic JS Calendar Engine) -->
    <div id="notedCalendarView" class="calendar-timeline-view" style="display:none;"></div>

</div>



<!-- MODAL: New Subject Tag -->
<div class="noted-modal" id="newSubjectModal">
    <div class="noted-modal-card" style="max-width:400px;">
        <div class="noted-modal-header">
            <h3>+ Create Subject Tag</h3>
            <button type="button" style="border:none; background:transparent; cursor:pointer;" id="closeSubjectModalBtn"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="noted-modal-body">
            <label style="font-size:12px; font-weight:700;">Subject / Course Name</label>
            <input type="text" id="newSubjectInput" placeholder="e.g. Physics 101" style="width:100%; padding:8px; border:1px solid var(--nj-border); border-radius:6px; margin-top:6px;">
        </div>
        <div class="noted-modal-footer">
            <button type="button" class="button primary" id="saveSubjectBtn">Add Tag</button>
        </div>
    </div>
</div>

<script>
// Contenteditable sync to hidden textarea for form post
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
