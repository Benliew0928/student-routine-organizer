<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../includes/validation.php';

$helperPath = __DIR__ . '/../modules/journal/journal_helpers.php';
if (is_file($helperPath)) {
    require $helperPath;
}

test('journal helper module exists', function () use ($helperPath): void {
    assertTrueValue(is_file($helperPath), 'Expected modules/journal/journal_helpers.php to exist.');
});

test('templates expose the five approved keys', function (): void {
    assertSameValue(
        ['blank', 'daily_reflection', 'gratitude', 'mood_checkin', 'study_notes'],
        array_keys(journalTemplateOptions())
    );
});

test('structured templates provide labels descriptions and prompts', function (): void {
    foreach (journalTemplateOptions() as $key => $template) {
        assertTrueValue($template['label'] !== '', $key . ' needs a label.');
        assertTrueValue($template['description'] !== '', $key . ' needs a description.');

        if ($key !== 'blank') {
            assertTrueValue($template['content'] !== '', $key . ' needs content prompts.');
        }
    }
});

test('default form uses blank template and todays date', function (): void {
    $data = journalDefaultFormData();

    assertSameValue('', $data['title']);
    assertSameValue('', $data['content']);
    assertSameValue('', $data['mood_status']);
    assertSameValue(date('Y-m-d'), $data['entry_date']);
    assertSameValue('blank', $data['template_key']);
});

test('request data trims fields and preserves internal content lines', function (): void {
    $data = journalDataFromRequest([
        'title' => '  A full day  ',
        'content' => "  First thought\n\nSecond thought  ",
        'mood_status' => '  Calm  ',
        'entry_date' => ' 2026-07-16 ',
        'template_key' => ' gratitude ',
    ]);

    assertSameValue('A full day', $data['title']);
    assertSameValue("First thought\n\nSecond thought", $data['content']);
    assertSameValue('Calm', $data['mood_status']);
    assertSameValue('2026-07-16', $data['entry_date']);
    assertSameValue('gratitude', $data['template_key']);
});

test('validation reports every required field', function (): void {
    $errors = journalValidateData([
        'title' => '',
        'content' => '',
        'mood_status' => '',
        'entry_date' => '',
        'template_key' => 'blank',
    ]);

    assertSameValue(4, count($errors));
    assertTrueValue(in_array('Please enter a journal title.', $errors, true));
    assertTrueValue(in_array('Please write some journal content.', $errors, true));
    assertTrueValue(in_array('Please describe your mood.', $errors, true));
    assertTrueValue(in_array('Please choose a valid entry date.', $errors, true));
});

test('validation enforces all field limits and template allow list', function (): void {
    $errors = journalValidateData([
        'title' => str_repeat('T', 121),
        'content' => str_repeat('C', 10001),
        'mood_status' => str_repeat('M', 51),
        'entry_date' => '2026-07-16',
        'template_key' => 'unknown',
    ]);

    assertTrueValue(in_array('Journal title must be 120 characters or fewer.', $errors, true));
    assertTrueValue(in_array('Journal content must be 10,000 characters or fewer.', $errors, true));
    assertTrueValue(in_array('Mood must be 50 characters or fewer.', $errors, true));
    assertTrueValue(in_array('Please choose a valid journal template.', $errors, true));
});

test('date validation is strict', function (): void {
    assertTrueValue(journalIsValidDate('2026-07-16'));
    assertSameValue(false, journalIsValidDate('2026-02-30'));
    assertSameValue(false, journalIsValidDate('16-07-2026'));
});

test('filters trim values and reject invalid dates and sort', function (): void {
    $filters = journalFiltersFromRequest([
        'search' => '  focus  ',
        'mood' => '  Calm  ',
        'date_from' => '2026-02-30',
        'date_to' => '2026-07-16',
        'sort' => 'drop-table',
    ]);

    assertSameValue('focus', $filters['search']);
    assertSameValue('Calm', $filters['mood']);
    assertSameValue('', $filters['date_from']);
    assertSameValue('2026-07-16', $filters['date_to']);
    assertSameValue('newest', $filters['sort']);
});

test('filter SQL always begins with ownership and uses parameters', function (): void {
    $filters = journalFiltersFromRequest([
        'search' => 'focus',
        'mood' => 'Calm',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-31',
        'sort' => 'oldest',
    ]);
    $query = journalFilterQuery($filters, 17);

    assertSameValue(
        'user_id = ? AND (title LIKE ? OR content LIKE ?) AND mood_status = ? AND entry_date >= ? AND entry_date <= ?',
        $query['where']
    );
    assertSameValue('isssss', $query['types']);
    assertSameValue([17, '%focus%', '%focus%', 'Calm', '2026-07-01', '2026-07-31'], $query['params']);
});

test('sort SQL is selected from a fixed allow list', function (): void {
    assertSameValue('entry_date DESC, journal_id DESC', journalOrderBy('newest'));
    assertSameValue('entry_date ASC, journal_id ASC', journalOrderBy('oldest'));
    assertSameValue('entry_date DESC, journal_id DESC', journalOrderBy('invalid'));
});

test('preview collapses whitespace and safely truncates multibyte text', function (): void {
    assertSameValue('First thought Second thought', journalPreview("First thought\n\nSecond thought", 50));
    assertSameValue('Mood…', journalPreview('Mood journal', 4));
});

test('return query omits defaults and keeps active filters', function (): void {
    $query = journalReturnQuery([
        'search' => 'focus',
        'mood' => '',
        'date_from' => '',
        'date_to' => '2026-07-31',
        'sort' => 'newest',
    ]);

    assertSameValue('search=focus&date_to=2026-07-31', $query);
});

test('journal list page uses filters instead of placeholder content', function (): void {
    $source = file_get_contents(__DIR__ . '/../modules/journal/index.php');

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, 'journalFilterQuery('));
    assertSameValue(false, str_contains($source, 'will be implemented'));
});

test('journal detail page enforces owned loading', function (): void {
    $path = __DIR__ . '/../modules/journal/view.php';
    assertTrueValue(is_file($path), 'Expected modules/journal/view.php to exist.');
    $source = file_get_contents($path);

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, 'journalLoadForUser('));
    assertTrueValue(str_contains($source, 'nl2br(escapeOutput('));
});

test('journal create page supports database draft and publish intents', function (): void {
    $source = file_get_contents(__DIR__ . '/../modules/journal/create.php');

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'journalLoadDraftForUser('));
    assertTrueValue(str_contains($source, 'journalValidateDraftData('));
    assertTrueValue(str_contains($source, 'journalSaveDraft('));
    assertTrueValue(str_contains($source, 'journalPublishDraft('));
    assertTrueValue(str_contains($source, 'value="save_draft"'));
    assertTrueValue(str_contains($source, 'value="publish"'));
    assertTrueValue(str_contains($source, 'data-autosave-url'));
    assertTrueValue(str_contains($source, 'data-journal-save-status'));
    assertSameValue(false, str_contains($source, 'Discard Draft'));
});

test('journal edit page verifies ownership csrf and scoped update', function (): void {
    $source = file_get_contents(__DIR__ . '/../modules/journal/edit.php');

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, 'journalLoadForUser('));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'UPDATE journal_entries'));
    assertTrueValue(str_contains($source, 'WHERE journal_id = ? AND user_id = ?'));
    assertSameValue(false, str_contains($source, 'will be implemented'));
});

test('journal delete page requires post csrf and scoped deletion', function (): void {
    $source = file_get_contents(__DIR__ . '/../modules/journal/delete.php');

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, 'journalLoadForUser('));
    assertTrueValue(str_contains($source, "REQUEST_METHOD'] === 'POST'"));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'DELETE FROM journal_entries WHERE journal_id = ? AND user_id = ?'));
    assertSameValue(false, str_contains($source, 'will be implemented'));
});

test('journal uses a dedicated page script', function (): void {
    $source = @file_get_contents(__DIR__ . '/../assets/js/journal.js');
    $create = file_get_contents(__DIR__ . '/../modules/journal/create.php');
    $footer = file_get_contents(__DIR__ . '/../includes/footer.php');

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, '[data-template-picker]'));
    assertTrueValue(str_contains($source, '[data-journal-editor]'));
    assertTrueValue(str_contains($source, 'window.confirm'));
    assertSameValue(false, str_contains($source, 'localStorage'));
    assertTrueValue(str_contains($create, '/assets/js/journal.js'));
    assertTrueValue(str_contains($footer, '$pageScripts'));
});

test('obsolete browser draft controls are absent', function (): void {
    $create = file_get_contents(__DIR__ . '/../modules/journal/create.php');
    $view = file_get_contents(__DIR__ . '/../modules/journal/view.php');
    $navbar = file_get_contents(__DIR__ . '/../includes/navbar.php');

    assertSameValue(false, str_contains($create, 'data-draft-key'));
    assertSameValue(false, str_contains($view, 'data-journal-saved'));
    assertSameValue(false, str_contains($navbar, 'data-journal-logout'));
});

test('journal list renders owned database drafts separately', function (): void {
    $source = file_get_contents(__DIR__ . '/../modules/journal/index.php');

    assertTrueValue(str_contains($source, 'journalListDraftsForUser('));
    assertTrueValue(str_contains($source, 'Your Drafts'));
    assertTrueValue(str_contains($source, 'Continue Writing'));
    assertTrueValue(str_contains($source, 'draft_delete.php?id='));
});

test('draft delete page confirms and scopes deletion', function (): void {
    $path = __DIR__ . '/../modules/journal/draft_delete.php';
    assertTrueValue(is_file($path), 'Expected draft deletion page.');
    $source = is_file($path) ? file_get_contents($path) : '';

    assertTrueValue(str_contains($source, 'journalLoadDraftForUser('));
    assertTrueValue(str_contains($source, "REQUEST_METHOD'] === 'POST'"));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'journalDeleteDraftForUser('));
});

test('journal stylesheet defines all major responsive components', function (): void {
    $source = file_get_contents(__DIR__ . '/../assets/css/style.css');

    assertTrueValue(is_string($source));
    foreach ([
        '.journal-hero',
        '.journal-filter-form',
        '.journal-card-grid',
        '.journal-reading-page',
        '.journal-template-grid',
        '.journal-editor-panel',
        '.journal-draft-grid',
        '.journal-draft-card',
        '.journal-save-status',
        '.journal-delete-panel',
    ] as $selector) {
        assertTrueValue(str_contains($source, $selector), 'Missing CSS selector ' . $selector);
    }
    assertSameValue(false, str_contains($source, '.journal-draft-banner'));
});

test('draft validation allows incomplete fields', function (): void {
    $errors = journalValidateDraftData([
        'title' => '',
        'content' => '',
        'mood_status' => '',
        'entry_date' => '',
        'template_key' => 'blank',
    ]);

    assertSameValue([], $errors);
});

test('draft validation enforces limits date and template allow list', function (): void {
    $errors = journalValidateDraftData([
        'title' => str_repeat('T', 121),
        'content' => str_repeat('C', 10001),
        'mood_status' => str_repeat('M', 51),
        'entry_date' => '2026-02-30',
        'template_key' => 'untrusted',
    ]);

    assertTrueValue(in_array('Draft title must be 120 characters or fewer.', $errors, true));
    assertTrueValue(in_array('Draft content must be 10,000 characters or fewer.', $errors, true));
    assertTrueValue(in_array('Draft mood must be 50 characters or fewer.', $errors, true));
    assertTrueValue(in_array('Please choose a valid draft date.', $errors, true));
    assertTrueValue(in_array('Please choose a valid journal template.', $errors, true));
});

test('meaningful draft ignores the default date alone', function (): void {
    $blank = journalDefaultFormData();
    assertSameValue(false, journalDraftHasMeaningfulContent($blank));

    $blank['title'] = 'A thought';
    assertTrueValue(journalDraftHasMeaningfulContent($blank));

    $blank['title'] = '';
    $blank['template_key'] = 'gratitude';
    assertTrueValue(journalDraftHasMeaningfulContent($blank));
});

test('draft autosave endpoint enforces method session csrf and ownership', function (): void {
    $path = __DIR__ . '/../modules/journal/draft_autosave.php';
    assertTrueValue(is_file($path), 'Expected the draft autosave endpoint.');
    $source = is_file($path) ? file_get_contents($path) : '';

    assertTrueValue(is_string($source));
    assertTrueValue(str_contains($source, "REQUEST_METHOD'] !== 'POST'"));
    assertTrueValue(str_contains($source, 'isLoggedIn()'));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'journalValidateDraftData('));
    assertTrueValue(str_contains($source, 'journalSaveDraft('));
    assertTrueValue(str_contains($source, 'Content-Type: application/json'));
});

finishTests();
