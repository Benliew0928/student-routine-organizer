<?php
require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
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

$activeFilterLabels = [];
if ($filters['search'] !== '') {
    $activeFilterLabels[] = 'Search: ' . $filters['search'];
}
if ($filters['mood'] !== '') {
    $activeFilterLabels[] = 'Mood: ' . $filters['mood'];
}
if ($filters['date_from'] !== '') {
    $activeFilterLabels[] = 'From: ' . $filters['date_from'];
}
if ($filters['date_to'] !== '') {
    $activeFilterLabels[] = 'To: ' . $filters['date_to'];
}
if ($filters['sort'] !== 'newest') {
    $activeFilterLabels[] = 'Sort: ' . (journalSortOptions()[$filters['sort']] ?? $filters['sort']);
}

$pageTitle = 'Diary Journal';
require __DIR__ . '/../../includes/header.php';
?>

<section class="journal-hero">
    <div>
        <p class="eyebrow">Your private writing space</p>
        <h1>Diary Journal</h1>
        <p class="hero-copy">Capture thoughts, notice mood patterns, and return to the moments that matter.</p>
        <div class="journal-summary-row" aria-label="Journal overview">
            <span><strong><?= number_format($summary['total']); ?></strong> total entries</span>
            <span><strong><?= number_format($summary['this_month']); ?></strong> this month</span>
            <span><strong><?= number_format(count($drafts)); ?></strong> saved drafts</span>
            <span>Latest mood: <strong><?= escapeOutput($summary['latest_mood']); ?></strong></span>
        </div>
    </div>
    <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php">Write New Entry</a>
</section>

<?php if ($pageError): ?>
    <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
<?php else: ?>
    <?php if ($drafts): ?>
        <section class="journal-draft-section" aria-labelledby="journal-drafts-heading">
            <div class="journal-board-heading">
                <div>
                    <p class="summary-label">Continue where you stopped</p>
                    <h2 id="journal-drafts-heading">Your Drafts</h2>
                </div>
                <span class="muted"><?= number_format(count($drafts)); ?> unfinished</span>
            </div>

            <div class="journal-draft-grid">
                <?php foreach ($drafts as $draft): ?>
                    <?php
                    $draftTitle = trim((string) $draft['title']) !== ''
                        ? (string) $draft['title']
                        : 'Untitled draft';
                    $draftPreview = trim((string) $draft['content']) !== ''
                        ? journalPreview((string) $draft['content'], 120)
                        : 'No writing yet';
                    $template = $templateOptions[$draft['template_key']] ?? $templateOptions['blank'];
                    ?>
                    <article class="journal-draft-card">
                        <div class="journal-card-topline">
                            <span class="journal-draft-badge">Draft</span>
                            <time datetime="<?= escapeOutput($draft['updated_at']); ?>">
                                Saved <?= escapeOutput(date('M j, g:i A', strtotime($draft['updated_at']))); ?>
                            </time>
                        </div>
                        <h3><?= escapeOutput($draftTitle); ?></h3>
                        <p><?= escapeOutput($draftPreview); ?></p>
                        <?php if ($draft['template_key'] !== 'blank'): ?>
                            <span class="journal-template-label"><?= escapeOutput($template['label']); ?></span>
                        <?php endif; ?>
                        <div class="journal-card-actions">
                            <a class="button small-button primary" href="<?= BASE_URL; ?>/modules/journal/create.php?draft_id=<?= (int) $draft['draft_id']; ?>">
                                Continue Writing
                            </a>
                            <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/journal/draft_delete.php?id=<?= (int) $draft['draft_id']; ?>">
                                Delete Draft
                            </a>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="panel journal-filter-panel" aria-labelledby="journal-filter-heading">
        <div class="journal-filter-heading">
            <div>
                <p class="summary-label">Find a memory</p>
                <h2 id="journal-filter-heading">Search and filter</h2>
            </div>
            <?php if ($activeFilterLabels): ?>
                <div class="active-filter-list" aria-label="Active filters">
                    <?php foreach ($activeFilterLabels as $label): ?>
                        <span><?= escapeOutput($label); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <form class="journal-filter-form" method="get" action="<?= BASE_URL; ?>/modules/journal/index.php">
            <div>
                <label for="search">Search</label>
                <input id="search" name="search" type="search" value="<?= escapeOutput($filters['search']); ?>" placeholder="Title or journal content">
            </div>

            <div>
                <label for="mood">Mood</label>
                <select id="mood" name="mood">
                    <option value="">All moods</option>
                    <?php foreach ($moodSuggestions as $mood): ?>
                        <option value="<?= escapeOutput($mood); ?>" <?= $filters['mood'] === $mood ? 'selected' : ''; ?>><?= escapeOutput($mood); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="date_from">From</label>
                <input id="date_from" name="date_from" type="date" value="<?= escapeOutput($filters['date_from']); ?>">
            </div>

            <div>
                <label for="date_to">To</label>
                <input id="date_to" name="date_to" type="date" value="<?= escapeOutput($filters['date_to']); ?>">
            </div>

            <div>
                <label for="sort">Sort</label>
                <select id="sort" name="sort">
                    <?php foreach (journalSortOptions() as $value => $label): ?>
                        <option value="<?= escapeOutput($value); ?>" <?= $filters['sort'] === $value ? 'selected' : ''; ?>><?= escapeOutput($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="journal-filter-actions">
                <button class="button primary" type="submit">Apply</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Reset</a>
            </div>
        </form>
    </section>

    <section class="journal-board-heading">
        <div>
            <p class="summary-label">Journal library</p>
            <h2><?= number_format(count($records)); ?> entr<?= count($records) === 1 ? 'y' : 'ies'; ?> showing</h2>
        </div>
        <a class="button" href="<?= BASE_URL; ?>/modules/journal/create.php">Add Entry</a>
    </section>

    <?php if (!$records): ?>
        <section class="panel empty-state">
            <?php if ($activeFilterLabels): ?>
                <h2>No entries match these filters</h2>
                <p class="muted">Try a different search, mood, or date range.</p>
                <div class="button-row">
                    <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Reset Filters</a>
                    <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php">Write New Entry</a>
                </div>
            <?php else: ?>
                <h2>Your journal is ready for its first page</h2>
                <p class="muted">Choose a writing template or begin with a blank page.</p>
                <a class="button primary" href="<?= BASE_URL; ?>/modules/journal/create.php">Write First Entry</a>
            <?php endif; ?>
        </section>
    <?php else: ?>
        <section class="journal-card-grid" aria-label="Journal entries">
            <?php foreach ($records as $record): ?>
                <?php $wasUpdated = $record['updated_at'] !== $record['created_at']; ?>
                <article class="journal-card">
                    <div class="journal-card-topline">
                        <span class="journal-mood-pill"><?= escapeOutput($record['mood_status']); ?></span>
                        <time datetime="<?= escapeOutput($record['entry_date']); ?>"><?= escapeOutput(date('M j, Y', strtotime($record['entry_date']))); ?></time>
                    </div>
                    <h2><a href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $record['journal_id']; ?>"><?= escapeOutput($record['title']); ?></a></h2>
                    <p class="journal-preview"><?= escapeOutput(journalPreview($record['content'])); ?></p>
                    <p class="journal-updated-note"><?= $wasUpdated ? 'Updated ' . escapeOutput(date('M j, Y g:i A', strtotime($record['updated_at']))) : 'Created ' . escapeOutput(date('M j, Y g:i A', strtotime($record['created_at']))); ?></p>
                    <div class="journal-card-actions">
                        <a class="button small-button" href="<?= BASE_URL; ?>/modules/journal/view.php?id=<?= (int) $record['journal_id']; ?>">Read</a>
                        <a class="button small-button" href="<?= BASE_URL; ?>/modules/journal/edit.php?id=<?= (int) $record['journal_id']; ?>">Edit</a>
                        <a class="button small-button danger-button" href="<?= BASE_URL; ?>/modules/journal/delete.php?id=<?= (int) $record['journal_id']; ?>">Delete</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
