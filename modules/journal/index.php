<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$userName = currentUserName();
$filters = journalFiltersFromRequest($_GET);
$records = [];
$drafts = [];
$moodSuggestions = [];
$templateOptions = journalTemplateOptions();
$summary = [
    'total' => 0,
    'this_month' => 0,
    'latest_mood' => 'No entries yet',
];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $drafts = journalListDraftsForUser($connection, $userId);

    $summaryStmt = $connection->prepare(
        "SELECT COUNT(*) AS total, "
        . "COALESCE(SUM(CASE WHEN DATE_FORMAT(entry_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN 1 ELSE 0 END), 0) AS this_month "
        . 'FROM journal_entries WHERE user_id = ?'
    );
    $summaryStmt->bind_param('i', $userId);
    $summaryStmt->execute();
    $summaryRow = $summaryStmt->get_result()->fetch_assoc();
    $summary['total'] = (int) $summaryRow['total'];
    $summary['this_month'] = (int) $summaryRow['this_month'];

    $latestStmt = $connection->prepare(
        'SELECT mood_status FROM journal_entries WHERE user_id = ? ORDER BY entry_date DESC, journal_id DESC LIMIT 1'
    );
    $latestStmt->bind_param('i', $userId);
    $latestStmt->execute();
    $latestEntry = $latestStmt->get_result()->fetch_assoc();
    if ($latestEntry) {
        $summary['latest_mood'] = (string) $latestEntry['mood_status'];
    }

    $moodSuggestions = journalMoodSuggestions($connection, $userId);
    $filterQuery = journalFilterQuery($filters, $userId);
    $sql = 'SELECT journal_id, title, content, mood_status, entry_date, created_at, updated_at '
        . 'FROM journal_entries WHERE ' . $filterQuery['where']
        . ' ORDER BY ' . journalOrderBy($filters['sort']);
    $stmt = $connection->prepare($sql);
    $params = $filterQuery['params'];
    journalBindParams($stmt, $filterQuery['types'], $params);
    $stmt->execute();
    $records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $exception) {
    $pageError = 'Journal entries are unavailable right now. Please check the database setup.';
}

$pageTitle = 'Noted.edu - Diary Journal';
$pageScripts = [
    BASE_URL . '/assets/js/journal_editor.js?v=20260730-v11'
];
require __DIR__ . '/../../includes/header.php';
?>

<div class="noted-app-container">

    <!-- Header Bar -->
    <header class="noted-header">
        <a class="noted-brand" href="<?= BASE_URL; ?>/modules/journal/index.php">
            <div class="noted-brand-icon"><i class="bi bi-journal-richtext"></i></div>
            <div class="noted-brand-text">
                <h2>Noted.edu</h2>
                <span>Student Routine Journal</span>
            </div>
        </a>

        <form class="noted-search-box" method="get" action="<?= BASE_URL; ?>/modules/journal/index.php">
            <i class="bi bi-search"></i>
            <input type="search" name="search" value="<?= escapeOutput($filters['search']); ?>" placeholder="Search entries by title or tag...">
        </form>

        <div class="noted-view-switcher">
            <button type="button" class="noted-view-btn active" data-view="editor"><i class="bi bi-grid"></i> Note Library</button>
            <button type="button" class="noted-view-btn" data-view="calendar"><i class="bi bi-calendar3"></i> Timeline View</button>
        </div>

        <div class="noted-tools-bar">
            <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php" style="border-radius:20px; font-weight:800; font-size:13px;"><i class="bi bi-plus-lg"></i> Write New Entry</a>
        </div>
    </header>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
    <?php else: ?>

        <!-- Main Content Area -->
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
                            <span class="badge-count"><?= number_format($summary['total']); ?></span>
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
                        <span>Recent Notes</span>
                    </div>
                    <div class="sidebar-notes-list">
                        <?php if ($records): ?>
                            <?php foreach (array_slice($records, 0, 5) as $recent): ?>
                                <a class="sidebar-note-card" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $recent['journal_id']; ?>" style="text-decoration:none;">
                                    <span class="sidebar-note-title"><?= escapeOutput($recent['title']); ?></span>
                                    <div class="sidebar-note-meta">
                                        <span><?= escapeOutput($recent['mood_status']); ?></span>
                                        <span><?= escapeOutput(date('M j', strtotime($recent['entry_date']))); ?></span>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span style="font-size:11px; color:var(--nj-muted);">No entries yet.</span>
                        <?php endif; ?>
                    </div>
                </div>

            </aside>

            <!-- Main Library Content -->
            <main style="display:flex; flex-direction:column; gap:20px;">
                
                <!-- Summary Banner -->
                <section class="journal-hero" style="border-radius:var(--nj-radius); box-shadow:var(--nj-shadow-sm);">
                    <div>
                        <p class="eyebrow">Noted.edu Journal Workspace</p>
                        <h1>Your Study Journal</h1>
                        <p class="hero-copy">Capture lectures, daily reflections, and paper notes in one workspace.</p>
                        <div class="journal-summary-row" aria-label="Journal overview">
                            <span><i class="bi bi-book"></i> <strong><?= number_format($summary['total']); ?></strong> entries</span>
                            <span><i class="bi bi-calendar3"></i> <strong><?= number_format($summary['this_month']); ?></strong> this month</span>
                            <span><i class="bi bi-file-earmark-text"></i> <strong><?= number_format(count($drafts)); ?></strong> drafts</span>
                            <span><i class="bi bi-emoji-smile"></i> Mood: <strong><?= escapeOutput($summary['latest_mood']); ?></strong></span>
                        </div>
                    </div>
                </section>

                <!-- Drafts Section -->
                <?php if ($drafts): ?>
                    <section class="journal-draft-section" style="margin:0;">
                        <div class="journal-board-heading">
                            <div>
                                <p class="summary-label">Continue where you stopped</p>
                                <h2>Your Drafts</h2>
                            </div>
                            <span class="muted"><?= number_format(count($drafts)); ?> saved</span>
                        </div>

                        <div class="journal-draft-grid">
                            <?php foreach ($drafts as $draft): ?>
                                <?php
                                $draftTitle = trim((string) $draft['title']) !== '' ? (string) $draft['title'] : 'Untitled draft';
                                $draftPreview = trim((string) $draft['content']) !== '' ? journalPreview((string) $draft['content'], 100) : 'No writing yet';
                                ?>
                                <article class="journal-draft-card">
                                    <div class="journal-card-topline">
                                        <span class="journal-draft-badge">Draft</span>
                                        <time datetime="<?= escapeOutput($draft['updated_at']); ?>"><?= escapeOutput(date('M j, g:i A', strtotime($draft['updated_at']))); ?></time>
                                    </div>
                                    <h3><?= escapeOutput($draftTitle); ?></h3>
                                    <p><?= escapeOutput($draftPreview); ?></p>
                                    <div class="journal-card-actions">
                                        <a class="button small-button primary" href="<?= BASE_URL; ?>/modules/journal/create.php?draft_id=<?= (int) $draft['draft_id']; ?>"><i class="bi bi-pen"></i> Continue</a>
                                        <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/journal/draft_delete.php?id=<?= (int) $draft['draft_id']; ?>"><i class="bi bi-trash3"></i> Delete</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endif; ?>


                <!-- Entries Grid -->
                <section>
                    <div class="journal-board-heading">
                        <div>
                            <p class="summary-label">Notes Library</p>
                            <h2><?= number_format(count($records)); ?> Note<?= count($records) === 1 ? '' : 's'; ?></h2>
                        </div>
                        <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php"><i class="bi bi-plus-lg"></i> Add Entry</a>
                    </div>

                    <?php if (!$records): ?>
                        <section class="panel empty-state">
                            <h2>Your journal is ready for its first page</h2>
                            <p class="muted">Start writing using interactive paper templates, drawing tools, and AI reflection prompts.</p>
                            <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php">Write First Entry</a>
                        </section>
                    <?php else: ?>
                        <div class="journal-card-grid">
                            <?php foreach ($records as $record): ?>
                                <article class="journal-card">
                                    <div class="journal-card-topline">
                                        <span class="journal-mood-pill"><i class="bi bi-emoji-smile"></i> <?= escapeOutput($record['mood_status']); ?></span>
                                        <time datetime="<?= escapeOutput($record['entry_date']); ?>"><?= escapeOutput(date('M j, Y', strtotime($record['entry_date']))); ?></time>
                                    </div>
                                    <h2><a href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $record['journal_id']; ?>"><?= escapeOutput($record['title']); ?></a></h2>
                                    <p class="journal-preview"><?= escapeOutput(journalPreview($record['content'])); ?></p>
                                    <div class="journal-card-actions">
                                        <a class="button small-button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $record['journal_id']; ?>"><i class="bi bi-eye"></i> Read</a>
                                        <a class="button small-button" href="<?= BASE_URL; ?>/modules/journal/edit.php?id=<?= (int) $record['journal_id']; ?>"><i class="bi bi-pencil"></i> Edit</a>
                                        <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/journal/delete.php?id=<?= (int) $record['journal_id']; ?>"><i class="bi bi-trash3"></i> Delete</a>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

            </main>
        </div>

        <!-- Timeline View (Dynamic JS Calendar Engine) -->
        <div id="notedCalendarView" class="calendar-timeline-view" style="display:none;" data-journal-entries='<?= htmlspecialchars(json_encode($records ?? []), ENT_QUOTES, 'UTF-8'); ?>'></div>

    <?php endif; ?>

</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
