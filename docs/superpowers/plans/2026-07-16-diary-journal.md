# Diary Journal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a complete, secure, per-user Diary Journal with CRUD, built-in writing templates, browsing tools, draft recovery, and verified XAMPP integration.

**Architecture:** Follow the repository's procedural three-tier pattern: Journal PHP pages form the presentation/controller tier, `journal_helpers.php` contains module application logic, and shared MySQLi prepared statements access the existing `journal_entries` table. Reuse the central session, CSRF, validation, flash, layout, dashboard, and database facilities.

**Tech Stack:** PHP 8.2, MySQLi, MariaDB/MySQL, XAMPP Apache, HTML5, shared CSS, vanilla JavaScript, and a dependency-free PHP test runner.

## Global Constraints

- Keep the existing `journal_entries` schema unchanged.
- Use MySQLi; do not add PDO.
- Use `requireLogin()` on every Journal page.
- Scope every journal record query by `journal_id` and `user_id` where a single record is involved.
- Source `user_id` only from `$_SESSION['user_id']`.
- Use prepared statements for every submitted value.
- Escape all stored user content before rendering.
- Require a CSRF token for create, update, and delete.
- Keep journal content plain text and cap it at 10,000 characters.
- Built-in templates prefill content but are not stored as a database type.
- Preserve the two untracked assignment PDFs and unrelated teammate changes.

---

## File Map

- `config/app.php`: detect the XAMPP checkout folder so shared links work in both `Server-Side` and `student-routine-organizer` checkouts.
- `modules/journal/journal_helpers.php`: all module constants, templates, parsing, validation, filters, SQL parameter binding, ownership loading, mood suggestions, and preview generation.
- `modules/journal/index.php`: per-user list, summary, search, mood/date filters, sort, and cards.
- `modules/journal/view.php`: complete safe reading view for one owned entry.
- `modules/journal/create.php`: CSRF-protected creation, template picker, form retention, and draft metadata.
- `modules/journal/edit.php`: CSRF-protected update for one owned entry.
- `modules/journal/delete.php`: GET confirmation plus CSRF-protected POST deletion.
- `assets/js/app.js`: template editor behavior, counts, auto-resize, draft restore/discard/save/clear, and logout cleanup.
- `assets/css/style.css`: Journal-specific responsive cards, filters, template picker, editor, and reading layout.
- `tests/test_bootstrap.php`: dependency-free assertion and test reporting helpers.
- `tests/journal_helpers_test.php`: pure helper and validation tests.
- `tests/journal_database_test.php`: transaction-scoped ownership and CRUD database checks.
- `docs/UCCD3243_Project_Step_By_Step_Plan.md`: Phase 6 status and evidence.
- `README.md`: implemented-feature and verification notes.

---

### Task 1: Portable Local URL and Test Harness

**Files:**
- Modify: `config/app.php`
- Create: `tests/test_bootstrap.php`
- Create: `tests/app_config_test.php`

**Interfaces:**
- Produces: `detectBaseUrl(string $scriptName): string`
- Produces: `test(string $name, callable $callback): void`, `assertSameValue(mixed $expected, mixed $actual): void`, `assertTrueValue(bool $condition, string $message): void`, and `finishTests(): never`

- [ ] **Step 1: Write the failing URL tests**

```php
<?php
require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/app.php';

test('detects Server-Side checkout', function (): void {
    assertSameValue('/Server-Side', detectBaseUrl('/Server-Side/modules/journal/index.php'));
});

test('detects documented checkout', function (): void {
    assertSameValue('/student-routine-organizer', detectBaseUrl('/student-routine-organizer/login.php'));
});

test('supports document root', function (): void {
    assertSameValue('', detectBaseUrl('/index.php'));
});

finishTests();
```

- [ ] **Step 2: Run the test and verify failure**

Run: `C:\xampp\php\php.exe tests\app_config_test.php`

Expected: non-zero exit because `test_bootstrap.php` or `detectBaseUrl()` does not exist.

- [ ] **Step 3: Add the test runner and URL detection**

`config/app.php` must define `APP_NAME`, implement `detectBaseUrl()`, and define `BASE_URL` from `$_SERVER['SCRIPT_NAME']`. The detector returns the first directory segment prefixed by `/`, or an empty string when the application is served from the document root.

```php
function detectBaseUrl(string $scriptName): string
{
    $path = trim(str_replace('\\', '/', $scriptName), '/');
    $segments = $path === '' ? [] : explode('/', $path);

    return count($segments) > 1 ? '/' . rawurlencode($segments[0]) : '';
}
```

The test runner records failures, prints `[PASS]` or `[FAIL]`, and exits `0` only when all registered tests pass.

- [ ] **Step 4: Run URL tests and PHP lint**

Run: `C:\xampp\php\php.exe tests\app_config_test.php`

Expected: three passing tests and exit code `0`.

Run: `C:\xampp\php\php.exe -l config\app.php`

Expected: `No syntax errors detected`.

- [ ] **Step 5: Commit**

```text
git add config/app.php tests/test_bootstrap.php tests/app_config_test.php
git commit -m "test: add portable app URL detection"
```

---

### Task 2: Journal Domain Helpers

**Files:**
- Create: `modules/journal/journal_helpers.php`
- Create: `tests/journal_helpers_test.php`

**Interfaces:**
- Consumes: `cleanInput()`, `mysqli`, and `mysqli_stmt`
- Produces: `journalTemplateOptions(): array`
- Produces: `journalSortOptions(): array`
- Produces: `journalDefaultFormData(): array`
- Produces: `journalDataFromRequest(array $source): array`
- Produces: `journalIsValidDate(string $value): bool`
- Produces: `journalValidateData(array $data): array`
- Produces: `journalFiltersFromRequest(array $source): array`
- Produces: `journalFilterQuery(array $filters, int $userId): array`
- Produces: `journalOrderBy(string $sort): string`
- Produces: `journalBindParams(mysqli_stmt $stmt, string $types, array &$params): void`
- Produces: `journalLoadForUser(mysqli $connection, int $journalId, int $userId): ?array`
- Produces: `journalMoodSuggestions(mysqli $connection, int $userId): array`
- Produces: `journalPreview(string $content, int $limit = 170): string`
- Produces: `journalReturnQuery(array $filters): string`

- [ ] **Step 1: Write failing pure-function tests**

Cover the exact template keys `blank`, `daily_reflection`, `gratitude`, `mood_checkin`, and `study_notes`; default date shape; trimming; preservation of content line breaks; all four required-field errors; 120/10,000/50 character limits; strict date validation; filter sanitization; per-user base SQL; fixed sort fragments; multibyte preview truncation; and return-query omission of default sort.

```php
test('templates expose the five approved keys', function (): void {
    assertSameValue(
        ['blank', 'daily_reflection', 'gratitude', 'mood_checkin', 'study_notes'],
        array_keys(journalTemplateOptions())
    );
});

test('filters always begin with user ownership', function (): void {
    $query = journalFilterQuery(journalFiltersFromRequest([]), 17);
    assertSameValue('user_id = ?', $query['where']);
    assertSameValue('i', $query['types']);
    assertSameValue([17], $query['params']);
});
```

- [ ] **Step 2: Run helper tests and verify failure**

Run: `C:\xampp\php\php.exe tests\journal_helpers_test.php`

Expected: non-zero exit because Journal helper functions do not exist.

- [ ] **Step 3: Implement the complete helper module**

Template records use this exact shape:

```php
[
    'label' => 'Daily Reflection',
    'description' => 'Review the day, its lessons, and what comes next.',
    'content' => "Highlights of the day\n\nWhat challenged me?\n\nWhat did I learn?\n\nTomorrow's focus",
]
```

The dynamic filter builder starts with ownership and adds only prepared conditions:

```php
$where = ['user_id = ?'];
$types = 'i';
$params = [$userId];
```

Search adds `(title LIKE ? OR content LIKE ?)`, mood adds `mood_status = ?`, and dates add `entry_date >= ?` or `entry_date <= ?`. Sort returns only `entry_date DESC, journal_id DESC` or `entry_date ASC, journal_id ASC`.

Ownership loading uses:

```sql
SELECT journal_id, user_id, title, content, mood_status, entry_date, created_at, updated_at
FROM journal_entries
WHERE journal_id = ? AND user_id = ?
LIMIT 1
```

- [ ] **Step 4: Run helper tests and lint**

Run: `C:\xampp\php\php.exe tests\journal_helpers_test.php`

Expected: all helper tests pass.

Run: `C:\xampp\php\php.exe -l modules\journal\journal_helpers.php`

Expected: no syntax errors.

- [ ] **Step 5: Commit**

```text
git add modules/journal/journal_helpers.php tests/journal_helpers_test.php
git commit -m "feat: add journal domain helpers"
```

---

### Task 3: Owned List and Complete Entry View

**Files:**
- Modify: `modules/journal/index.php`
- Create: `modules/journal/view.php`
- Create: `tests/journal_database_test.php`

**Interfaces:**
- Consumes: all helper interfaces from Task 2 and shared authentication/layout helpers
- Produces: GET `/modules/journal/index.php` with `search`, `mood`, `date_from`, `date_to`, and `sort`
- Produces: GET `/modules/journal/view.php?id=<journal_id>`

- [ ] **Step 1: Write the failing transaction-scoped ownership test**

The database test begins a transaction, inserts two temporary student users, inserts one journal entry for Student A, verifies `journalLoadForUser()` succeeds for A and returns `null` for B, then rolls back in `finally`.

```php
$owned = journalLoadForUser($connection, $journalId, $studentAId);
$unowned = journalLoadForUser($connection, $journalId, $studentBId);
assertSameValue('Private reflection', $owned['title'] ?? null);
assertSameValue(null, $unowned);
```

- [ ] **Step 2: Run database test and verify the intended failure**

Run: `C:\xampp\php\php.exe tests\journal_database_test.php`

Expected before the pages are built: helper ownership passes, while HTTP/page expectations remain unimplemented.

- [ ] **Step 3: Replace the list placeholder**

The list page must:

- call `requireLogin()` before database work;
- load per-user total and distinct moods;
- parse allow-listed filters;
- prepare the filtered SQL and bind dynamic parameters;
- show title, mood, date, updated state, escaped preview, View, Edit, and Delete actions;
- distinguish an empty journal from zero filter results;
- preserve active filters and expose Reset Filters.

- [ ] **Step 4: Add the owned detail page**

Validate `id` with `FILTER_VALIDATE_INT`, load only through `journalLoadForUser()`, redirect missing/unowned records with `Journal entry was not found.`, and render content using:

```php
<?= nl2br(escapeOutput($entry['content'])); ?>
```

- [ ] **Step 5: Lint, run database test, and smoke-check HTTP**

Run PHP lint on both pages and run `tests\journal_database_test.php`.

Expected: lint passes, transaction rolls back, and ownership assertions pass.

- [ ] **Step 6: Commit**

```text
git add modules/journal/index.php modules/journal/view.php tests/journal_database_test.php
git commit -m "feat: add journal browsing and detail view"
```

---

### Task 4: Create Entry With Built-In Templates

**Files:**
- Modify: `modules/journal/create.php`
- Modify: `tests/journal_database_test.php`

**Interfaces:**
- Consumes: `journalDefaultFormData()`, `journalDataFromRequest()`, `journalValidateData()`, `journalTemplateOptions()`, `csrfInput()`, and `verifyCsrfToken()`
- Produces: GET/POST `/modules/journal/create.php`
- Produces: `data-journal-editor`, `data-template-picker`, `data-draft-key`, and `data-journal-saved` DOM hooks

- [ ] **Step 1: Extend the database test with create persistence**

Within the existing transaction, insert a test entry using the same prepared INSERT as the page and assert title, mood, date, and `user_id` after reloading through `journalLoadForUser()`.

- [ ] **Step 2: Run the database test and observe failure before create code is shared**

Run: `C:\xampp\php\php.exe tests\journal_database_test.php`

Expected: the new create assertion fails until the prepared create workflow is implemented.

- [ ] **Step 3: Implement create GET and POST**

The page uses this prepared statement:

```sql
INSERT INTO journal_entries (user_id, title, content, mood_status, entry_date)
VALUES (?, ?, ?, ?, ?)
```

The form includes CSRF, five template cards, title, free-text mood with a per-user `datalist`, date, content, counts, Save Entry, Discard Draft, and Cancel. On success, set `$_SESSION['journal_draft_clear'] = true`, flash success, and redirect to `view.php?id=<insert_id>`.

- [ ] **Step 4: Run tests and lint**

Expected: create persistence passes, the test transaction leaves row counts unchanged, and PHP lint passes.

- [ ] **Step 5: Commit**

```text
git add modules/journal/create.php tests/journal_database_test.php
git commit -m "feat: create journal entries from templates"
```

---

### Task 5: Secure Edit and Delete

**Files:**
- Modify: `modules/journal/edit.php`
- Modify: `modules/journal/delete.php`
- Modify: `tests/journal_database_test.php`

**Interfaces:**
- Consumes: ownership loader, parser, validator, CSRF, flash, and shared layout
- Produces: GET/POST `/modules/journal/edit.php?id=<journal_id>`
- Produces: GET confirmation and POST mutation `/modules/journal/delete.php?id=<journal_id>`

- [ ] **Step 1: Add failing ownership-sensitive update/delete tests**

The transaction test must prove an update and delete using Student B's `user_id` affect zero rows, while the same operations using Student A's ID affect exactly one row.

```sql
UPDATE journal_entries
SET title = ?, content = ?, mood_status = ?, entry_date = ?
WHERE journal_id = ? AND user_id = ?
```

```sql
DELETE FROM journal_entries
WHERE journal_id = ? AND user_id = ?
```

- [ ] **Step 2: Run the test and confirm the new assertions initially fail**

Run: `C:\xampp\php\php.exe tests\journal_database_test.php`

- [ ] **Step 3: Implement edit**

Load before rendering, retain values on validation failure, verify CSRF, update with both identifiers, flash success, and redirect to the detail page. Do not offer templates during edit.

- [ ] **Step 4: Implement delete**

GET only renders confirmation. POST verifies CSRF and deletes with both identifiers. Missing, invalid, and unowned records all redirect with `Journal entry was not found.`

- [ ] **Step 5: Run database tests and lint**

Expected: cross-user affected rows are `0`, owner affected rows are `1`, rollback succeeds, and both pages lint.

- [ ] **Step 6: Commit**

```text
git add modules/journal/edit.php modules/journal/delete.php tests/journal_database_test.php
git commit -m "feat: secure journal editing and deletion"
```

---

### Task 6: Journal Interaction and Responsive Visual Design

**Files:**
- Modify: `assets/js/app.js`
- Modify: `assets/css/style.css`
- Modify: `modules/journal/create.php`
- Modify: `modules/journal/index.php`
- Modify: `modules/journal/view.php`

**Interfaces:**
- Consumes: DOM hooks emitted by Journal pages
- Produces: template replacement confirmation, live counts, editor auto-resize, Restore/Discard draft prompt, post-save cleanup, logout cleanup, responsive cards, and accessible focus states

- [ ] **Step 1: Add browser-facing DOM contract checks to helper test**

Read the relevant source files and assert required stable hooks exist: `data-journal-editor`, `data-template-picker`, `data-draft-key`, `data-journal-saved`, `data-journal-draft-restore`, `data-journal-draft-discard`, and `data-journal-user`.

- [ ] **Step 2: Run the test and confirm missing-hook failures**

Run: `C:\xampp\php\php.exe tests\journal_helpers_test.php`

- [ ] **Step 3: Implement JavaScript behavior**

Use `localStorage` with the server-rendered key `journalDraft:<userId>:create`. Never auto-overwrite fields: reveal a restore banner with Restore and Discard buttons. Save title, mood, date, content, and template after input changes; clear on successful create, explicit discard, and current-user logout. Keep the form functional without JavaScript.

- [ ] **Step 4: Implement Journal CSS**

Add scoped classes for the Journal hero/action bar, summary chips, filter form, template cards, active selection, editor shell, counts, draft banner, card grid, mood pill, metadata, preview, reading paper, content typography, and mobile stacking. Reuse the existing CSS custom properties and `@media (max-width: 760px)` behavior.

- [ ] **Step 5: Run source-contract tests, lint, and browser checks**

Expected: all hooks exist, scripts load with no console errors, templates/counts/drafts behave correctly, and desktop/mobile layouts remain usable.

- [ ] **Step 6: Commit**

```text
git add assets/js/app.js assets/css/style.css modules/journal/create.php modules/journal/index.php modules/journal/view.php tests/journal_helpers_test.php
git commit -m "feat: polish journal writing experience"
```

---

### Task 7: Documentation and Full Verification

**Files:**
- Modify: `README.md`
- Modify: `docs/UCCD3243_Project_Step_By_Step_Plan.md`
- Modify: `tests/journal_database_test.php` only if verification exposes a missing assertion

**Interfaces:**
- Consumes: completed module and all tests
- Produces: accurate Phase 6 status, local setup notes, demo workflow, and final evidence

- [ ] **Step 1: Update documentation**

Mark Phase 6 complete and list CRUD, ownership, templates, search/filter/sort, mood suggestions, CSRF, validation, draft recovery, helper tests, database tests, and browser verification. Document that `BASE_URL` is detected from the checkout directory.

- [ ] **Step 2: Run the complete automated suite**

```text
C:\xampp\php\php.exe tests\app_config_test.php
C:\xampp\php\php.exe tests\journal_helpers_test.php
C:\xampp\php\php.exe tests\journal_database_test.php
```

Expected: every test passes and the database transaction reports rollback success.

- [ ] **Step 3: Lint every PHP file**

Run every tracked `*.php` file through `C:\xampp\php\php.exe -l`.

Expected: zero syntax failures.

- [ ] **Step 4: Verify live browser workflow**

Using the sample student account:

1. Log in through the working local URL.
2. Open Journal from shared navigation.
3. Create entries using all five template choices.
4. Verify detail reading, search, mood/date filters, and both sort orders.
5. Edit one entry and confirm dashboard latest mood/count.
6. Test draft restore/discard and switching-template confirmation.
7. Delete one entry through confirmation.
8. Register or use a second student and prove copied IDs cannot view, edit, or delete the first student's entry.
9. Verify escaped `<script>` text does not execute.
10. Check desktop and mobile-width layouts and browser console errors.

- [ ] **Step 5: Audit the approved specification**

Map every requirement in `docs/superpowers/specs/2026-07-16-diary-journal-design.md` to code, automated output, live database evidence, or browser evidence. Fix any missing or weakly verified requirement before completion.

- [ ] **Step 6: Commit documentation**

```text
git add README.md docs/UCCD3243_Project_Step_By_Step_Plan.md
git commit -m "docs: complete diary journal module guide"
```

- [ ] **Step 7: Final repository check**

Confirm `git status --short` contains only the user's two untracked assignment PDFs, review the Journal commit list, and do not claim completion until all automated and browser checks are current and passing.
