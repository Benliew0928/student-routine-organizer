# Database-Backed Journal Drafts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Repair Journal template interactions and deliver secure, truthful, database-backed drafts that can be listed, resumed, published, and deleted across authenticated devices.

**Architecture:** Keep published entries unchanged in `journal_entries` and store partial work in a new `journal_drafts` table. Put draft rules and ownership-scoped MySQLi operations in `journal_helpers.php`, use dedicated endpoints and server fallbacks for persistence, and isolate all Journal client behavior in `assets/js/journal.js`.

**Tech Stack:** PHP 8.2, MariaDB/MySQL through MySQLi, HTML/CSS, browser JavaScript, Node.js built-in test runner, XAMPP Apache, and the project's custom PHP test harness.

## Global Constraints

- Follow `docs/superpowers/specs/2026-07-23-database-journal-drafts-design.md`.
- Preserve the existing `journal_entries` schema and published-entry CRUD behavior.
- Keep drafts in a separate `journal_drafts` table.
- Scope every draft read, update, publish, and delete by authenticated `user_id`.
- Use MySQLi prepared statements for every user-controlled SQL value.
- Require CSRF protection for autosave, explicit save, publish, and delete.
- Keep published Journal, dashboard, latest-mood, and admin totals unchanged by drafts.
- Load Journal interaction code from `assets/js/journal.js`, not the shared `assets/js/app.js`.
- Remove the obsolete browser-local draft, logout cleanup, saved marker, and `Discard Draft` contracts.
- Do not stage or commit `Assignment_Submission_Guideline_June2026.pdf`.
- Use `C:\xampp\php\php.exe` for PHP commands and the existing `student_routine_organizer` database.

---

## File Responsibility Map

- `database/journal_drafts_migration.sql`: non-destructive installation for existing databases.
- `database/student_routine_organizer.sql`: complete fresh database export including drafts.
- `database/schema_draft.sql`: documented schema aligned with the complete export.
- `modules/journal/journal_helpers.php`: draft validation, meaningful-state checks, owned loading/listing, persistence, deletion, and transactional publication.
- `modules/journal/draft_autosave.php`: JSON-only autosave controller.
- `modules/journal/draft_delete.php`: confirmation and CSRF-protected draft deletion.
- `modules/journal/create.php`: new entry, resume, explicit draft save, and publish controller/view.
- `modules/journal/index.php`: current-user Drafts section plus unchanged published library.
- `modules/journal/view.php`: published detail page without browser-local cleanup markers.
- `includes/footer.php`: optional page-specific script loading.
- `includes/navbar.php`: normal logout link without Journal storage hooks.
- `assets/js/journal.js`: testable pure Journal functions and DOM integration.
- `assets/css/style.css`: draft cards, save status, editor actions, and responsive states.
- `tests/journal_draft_schema_test.php`: installed-table contract.
- `tests/journal_helpers_test.php`: pure rules and page/source contracts.
- `tests/journal_drafts_database_test.php`: ownership and persistence integration.
- `tests/journal_ui_test.js`: pure JavaScript behavior under Node's built-in runner.

---

### Task 1: Add the Journal Draft Database Schema

**Files:**
- Create: `tests/journal_draft_schema_test.php`
- Create: `database/journal_drafts_migration.sql`
- Modify: `database/student_routine_organizer.sql`
- Modify: `database/schema_draft.sql`

**Interfaces:**
- Produces: table `journal_drafts`
- Produces: index `idx_journal_draft_user_updated`
- Consumes: `users.user_id`

- [ ] **Step 1: Write the failing installed-schema test**

Create `tests/journal_draft_schema_test.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/database.php';

$connection = null;

try {
    $connection = getDatabaseConnection();
    $table = $connection->query(
        "SELECT COUNT(*) AS total
         FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'journal_drafts'"
    )->fetch_assoc();

    test('journal_drafts table is installed', function () use ($table): void {
        assertSameValue(1, (int) $table['total']);
    });

    $columns = [];
    $result = $connection->query('SHOW COLUMNS FROM journal_drafts');
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'];
    }

    test('journal_drafts exposes the approved columns', function () use ($columns): void {
        assertSameValue(
            [
                'draft_id',
                'user_id',
                'title',
                'content',
                'mood_status',
                'entry_date',
                'template_key',
                'created_at',
                'updated_at',
            ],
            $columns
        );
    });
} catch (Throwable $exception) {
    test('journal draft schema inspection succeeds', function () use ($exception): void {
        throw $exception;
    });
}

finishTests();
```

- [ ] **Step 2: Run the schema test and verify the RED state**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_draft_schema_test.php
```

Expected: FAIL because `journal_drafts` does not exist in the current database.

- [ ] **Step 3: Create the non-destructive migration**

Create `database/journal_drafts_migration.sql`:

```sql
USE student_routine_organizer;

CREATE TABLE IF NOT EXISTS journal_drafts (
  draft_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(120) NOT NULL DEFAULT '',
  content TEXT NOT NULL,
  mood_status VARCHAR(50) NOT NULL DEFAULT '',
  entry_date DATE NULL,
  template_key VARCHAR(32) NOT NULL DEFAULT 'blank',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_journal_draft_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_journal_draft_user_updated (user_id, updated_at)
);
```

In both complete schema files:

1. Add `DROP TABLE IF EXISTS journal_drafts;` before `DROP TABLE IF EXISTS journal_entries;`.
2. Add the same `CREATE TABLE journal_drafts` definition after `journal_entries` and before tables that do not affect its foreign key.

- [ ] **Step 4: Apply the migration to the live local database**

Run:

```powershell
Get-Content -Raw database\journal_drafts_migration.sql |
    & 'C:\xampp\mysql\bin\mysql.exe' -u root student_routine_organizer
```

Expected: exit code 0 with no table-drop or data-loss output.

- [ ] **Step 5: Verify the schema test is GREEN**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_draft_schema_test.php
```

Expected: 2 tests, 0 failures.

- [ ] **Step 6: Verify schema files are syntactically and structurally aligned**

Run:

```powershell
rg -n "journal_drafts|idx_journal_draft_user_updated|fk_journal_draft_user" `
    database\journal_drafts_migration.sql `
    database\student_routine_organizer.sql `
    database\schema_draft.sql
```

Expected: all three files contain the table, foreign key, and index; both full schemas contain a foreign-key-safe drop.

- [ ] **Step 7: Commit the schema increment**

```powershell
git add -- `
    tests/journal_draft_schema_test.php `
    database/journal_drafts_migration.sql `
    database/student_routine_organizer.sql `
    database/schema_draft.sql
git commit -m "feat: add journal draft storage"
```

---

### Task 2: Add Draft Rules and Ownership-Scoped Persistence

**Files:**
- Modify: `tests/journal_helpers_test.php`
- Create: `tests/journal_drafts_database_test.php`
- Modify: `modules/journal/journal_helpers.php`

**Interfaces:**
- Produces: `journalValidateDraftData(array $data): array`
- Produces: `journalDraftHasMeaningfulContent(array $data): bool`
- Produces: `journalLoadDraftForUser(mysqli $connection, int $draftId, int $userId): ?array`
- Produces: `journalListDraftsForUser(mysqli $connection, int $userId): array`
- Produces: `journalSaveDraft(mysqli $connection, int $userId, ?int $draftId, array $data): ?int`
- Produces: `journalDeleteDraftForUser(mysqli $connection, int $draftId, int $userId): bool`
- Produces: `journalPublishDraft(mysqli $connection, int $userId, ?int $draftId, array $data): ?int`
- Consumes: existing `journalDataFromRequest()`, `journalIsValidDate()`, and `journalTemplateOptions()`

- [ ] **Step 1: Add failing pure draft-rule tests**

Append these tests before `finishTests()` in `tests/journal_helpers_test.php`:

```php
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
```

- [ ] **Step 2: Write the failing database ownership test**

Create `tests/journal_drafts_database_test.php` with temporary users and explicit cleanup:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/test_bootstrap.php';
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/validation.php';
require __DIR__ . '/../modules/journal/journal_helpers.php';

$connection = null;
$userIds = [];
$beforeDrafts = null;
$beforeEntries = null;

try {
    $connection = getDatabaseConnection();
    $beforeDrafts = (int) $connection->query(
        'SELECT COUNT(*) AS total FROM journal_drafts'
    )->fetch_assoc()['total'];
    $beforeEntries = (int) $connection->query(
        'SELECT COUNT(*) AS total FROM journal_entries'
    )->fetch_assoc()['total'];

    $suffix = bin2hex(random_bytes(5));
    $passwordHash = password_hash('temporary-password', PASSWORD_DEFAULT);
    $role = 'student';
    $insertUser = $connection->prepare(
        'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );

    foreach (['A', 'B'] as $label) {
        $name = 'Draft Test ' . $label;
        $email = 'draft-' . strtolower($label) . '-' . $suffix . '@example.test';
        $insertUser->bind_param('ssss', $name, $email, $passwordHash, $role);
        $insertUser->execute();
        $userIds[] = (int) $connection->insert_id;
    }

    [$studentAId, $studentBId] = $userIds;
    $draft = [
        'title' => '',
        'content' => 'An unfinished cross-device thought.',
        'mood_status' => '',
        'entry_date' => '',
        'template_key' => 'daily_reflection',
    ];

    $draftId = journalSaveDraft($connection, $studentAId, null, $draft);

    test('owner can create and load a partial draft', function () use ($connection, $draftId, $studentAId): void {
        assertTrueValue(is_int($draftId) && $draftId > 0);
        $saved = journalLoadDraftForUser($connection, $draftId, $studentAId);
        assertSameValue('An unfinished cross-device thought.', $saved['content'] ?? null);
        assertSameValue(null, $saved['entry_date'] ?? null);
    });

    test('another user cannot load or update the draft', function () use ($connection, $draftId, $studentBId, $draft): void {
        assertSameValue(null, journalLoadDraftForUser($connection, $draftId, $studentBId));
        assertSameValue(null, journalSaveDraft($connection, $studentBId, $draftId, $draft));
    });

    $draft['title'] = 'Updated draft';
    $draft['mood_status'] = 'Focused';
    $draft['entry_date'] = '2026-07-23';
    $updatedId = journalSaveDraft($connection, $studentAId, $draftId, $draft);

    test('owner can update and list the draft', function () use ($connection, $draftId, $updatedId, $studentAId): void {
        assertSameValue($draftId, $updatedId);
        $drafts = journalListDraftsForUser($connection, $studentAId);
        assertSameValue($draftId, (int) ($drafts[0]['draft_id'] ?? 0));
        assertSameValue('Updated draft', $drafts[0]['title'] ?? null);
    });

    $published = [
        'title' => 'Finished reflection',
        'content' => 'This content is ready to publish.',
        'mood_status' => 'Hopeful',
        'entry_date' => '2026-07-23',
        'template_key' => 'daily_reflection',
    ];
    $journalId = journalPublishDraft($connection, $studentAId, $draftId, $published);

    test('publishing creates one entry and consumes the owned draft', function () use ($connection, $journalId, $draftId, $studentAId): void {
        assertTrueValue(is_int($journalId) && $journalId > 0);
        assertSameValue(null, journalLoadDraftForUser($connection, $draftId, $studentAId));
        assertSameValue('Finished reflection', journalLoadForUser($connection, $journalId, $studentAId)['title'] ?? null);
    });
} catch (Throwable $exception) {
    test('journal draft database setup succeeds', function () use ($exception): void {
        throw $exception;
    });
} finally {
    if ($connection instanceof mysqli && $userIds) {
        $placeholders = implode(',', array_fill(0, count($userIds), '?'));
        $types = str_repeat('i', count($userIds));
        $delete = $connection->prepare("DELETE FROM users WHERE user_id IN ($placeholders)");
        $params = $userIds;
        journalBindParams($delete, $types, $params);
        $delete->execute();
    }
}

if ($connection instanceof mysqli && $beforeDrafts !== null && $beforeEntries !== null) {
    test('draft database test cleans up its records', function () use ($connection, $beforeDrafts, $beforeEntries): void {
        $afterDrafts = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_drafts')->fetch_assoc()['total'];
        $afterEntries = (int) $connection->query('SELECT COUNT(*) AS total FROM journal_entries')->fetch_assoc()['total'];
        assertSameValue($beforeDrafts, $afterDrafts);
        assertSameValue($beforeEntries, $afterEntries);
    });
}

finishTests();
```

- [ ] **Step 3: Run both tests and verify the RED state**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
& 'C:\xampp\php\php.exe' tests\journal_drafts_database_test.php
```

Expected: failures for undefined draft functions.

- [ ] **Step 4: Add draft validation and meaningful-content helpers**

Add to `modules/journal/journal_helpers.php`:

```php
function journalValidateDraftData(array $data): array
{
    $errors = [];
    $title = (string) ($data['title'] ?? '');
    $content = (string) ($data['content'] ?? '');
    $mood = (string) ($data['mood_status'] ?? '');
    $entryDate = (string) ($data['entry_date'] ?? '');
    $templateKey = (string) ($data['template_key'] ?? 'blank');

    if (mb_strlen($title) > 120) {
        $errors[] = 'Draft title must be 120 characters or fewer.';
    }
    if (mb_strlen($content) > 10000) {
        $errors[] = 'Draft content must be 10,000 characters or fewer.';
    }
    if (mb_strlen($mood) > 50) {
        $errors[] = 'Draft mood must be 50 characters or fewer.';
    }
    if ($entryDate !== '' && !journalIsValidDate($entryDate)) {
        $errors[] = 'Please choose a valid draft date.';
    }
    if (!array_key_exists($templateKey, journalTemplateOptions())) {
        $errors[] = 'Please choose a valid journal template.';
    }

    return $errors;
}

function journalDraftHasMeaningfulContent(array $data): bool
{
    return trim((string) ($data['title'] ?? '')) !== ''
        || trim((string) ($data['content'] ?? '')) !== ''
        || trim((string) ($data['mood_status'] ?? '')) !== ''
        || (string) ($data['template_key'] ?? 'blank') !== 'blank';
}
```

- [ ] **Step 5: Add ownership-scoped draft persistence**

Add these functions to `journal_helpers.php`:

```php
function journalLoadDraftForUser(mysqli $connection, int $draftId, int $userId): ?array
{
    $stmt = $connection->prepare(
        'SELECT draft_id, user_id, title, content, mood_status, entry_date, template_key, created_at, updated_at
         FROM journal_drafts
         WHERE draft_id = ? AND user_id = ?
         LIMIT 1'
    );
    $stmt->bind_param('ii', $draftId, $userId);
    $stmt->execute();
    $draft = $stmt->get_result()->fetch_assoc();

    return $draft ?: null;
}

function journalListDraftsForUser(mysqli $connection, int $userId): array
{
    $stmt = $connection->prepare(
        'SELECT draft_id, user_id, title, content, mood_status, entry_date, template_key, created_at, updated_at
         FROM journal_drafts
         WHERE user_id = ?
         ORDER BY updated_at DESC, draft_id DESC'
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();

    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function journalSaveDraft(
    mysqli $connection,
    int $userId,
    ?int $draftId,
    array $data
): ?int {
    $title = (string) ($data['title'] ?? '');
    $content = (string) ($data['content'] ?? '');
    $mood = (string) ($data['mood_status'] ?? '');
    $entryDate = (string) ($data['entry_date'] ?? '');
    $dateValue = $entryDate === '' ? null : $entryDate;
    $templateKey = (string) ($data['template_key'] ?? 'blank');

    if ($draftId === null) {
        $stmt = $connection->prepare(
            'INSERT INTO journal_drafts
             (user_id, title, content, mood_status, entry_date, template_key)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('isssss', $userId, $title, $content, $mood, $dateValue, $templateKey);
        $stmt->execute();

        return (int) $connection->insert_id;
    }

    if (journalLoadDraftForUser($connection, $draftId, $userId) === null) {
        return null;
    }

    $stmt = $connection->prepare(
        'UPDATE journal_drafts
         SET title = ?, content = ?, mood_status = ?, entry_date = ?, template_key = ?
         WHERE draft_id = ? AND user_id = ?'
    );
    $stmt->bind_param(
        'sssssii',
        $title,
        $content,
        $mood,
        $dateValue,
        $templateKey,
        $draftId,
        $userId
    );
    $stmt->execute();

    if ($stmt->affected_rows === 0
        && journalLoadDraftForUser($connection, $draftId, $userId) === null
    ) {
        return null;
    }

    return $draftId;
}

function journalDeleteDraftForUser(mysqli $connection, int $draftId, int $userId): bool
{
    $stmt = $connection->prepare(
        'DELETE FROM journal_drafts WHERE draft_id = ? AND user_id = ?'
    );
    $stmt->bind_param('ii', $draftId, $userId);
    $stmt->execute();

    return $stmt->affected_rows === 1;
}
```

- [ ] **Step 6: Add transactional publication**

Add:

```php
function journalPublishDraft(
    mysqli $connection,
    int $userId,
    ?int $draftId,
    array $data
): ?int {
    $connection->begin_transaction();

    try {
        if ($draftId !== null && journalLoadDraftForUser($connection, $draftId, $userId) === null) {
            $connection->rollback();
            return null;
        }

        $title = (string) $data['title'];
        $content = (string) $data['content'];
        $mood = (string) $data['mood_status'];
        $entryDate = (string) $data['entry_date'];
        $stmt = $connection->prepare(
            'INSERT INTO journal_entries
             (user_id, title, content, mood_status, entry_date)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'issss',
            $userId,
            $title,
            $content,
            $mood,
            $entryDate
        );
        $stmt->execute();
        $journalId = (int) $connection->insert_id;

        if ($draftId !== null && !journalDeleteDraftForUser($connection, $draftId, $userId)) {
            throw new RuntimeException('Owned draft was not removed during publication.');
        }

        $connection->commit();
        return $journalId;
    } catch (Throwable $exception) {
        $connection->rollback();
        throw $exception;
    }
}
```

- [ ] **Step 7: Verify both suites are GREEN**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
& 'C:\xampp\php\php.exe' tests\journal_drafts_database_test.php
```

Expected: all tests pass; temporary users, drafts, and entries are removed.

- [ ] **Step 8: Commit the draft domain increment**

```powershell
git add -- `
    modules/journal/journal_helpers.php `
    tests/journal_helpers_test.php `
    tests/journal_drafts_database_test.php
git commit -m "feat: add secure journal draft persistence"
```

---

### Task 3: Isolate and Test Journal Client Behavior

**Files:**
- Create: `tests/journal_ui_test.js`
- Create: `assets/js/journal.js`
- Modify: `tests/journal_helpers_test.php`
- Modify: `includes/footer.php`
- Modify: `modules/journal/create.php`

**Interfaces:**
- Produces browser/CommonJS API: `countWords(content)`
- Produces browser/CommonJS API: `nextTemplateState(currentContent, nextContent, confirmed)`
- Produces browser/CommonJS API: `hasMeaningfulDraft(draft)`
- Produces: optional `$pageScripts` rendering after `assets/js/app.js`
- Consumes: template and editor `data-*` hooks from `create.php`

- [ ] **Step 1: Write failing pure JavaScript tests**

Create `tests/journal_ui_test.js`:

```javascript
'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const journal = require('../assets/js/journal.js');

test('countWords ignores surrounding whitespace and counts separated words', () => {
    assert.equal(journal.countWords('  one\n two   three  '), 3);
    assert.equal(journal.countWords('   '), 0);
});

test('nextTemplateState applies a template to an empty editor', () => {
    assert.deepEqual(
        journal.nextTemplateState('', 'Daily prompt', false),
        { applied: true, content: 'Daily prompt' }
    );
});

test('nextTemplateState preserves writing when replacement is cancelled', () => {
    assert.deepEqual(
        journal.nextTemplateState('My own writing', 'Gratitude prompt', false),
        { applied: false, content: 'My own writing' }
    );
});

test('nextTemplateState replaces writing after confirmation', () => {
    assert.deepEqual(
        journal.nextTemplateState('My own writing', 'Gratitude prompt', true),
        { applied: true, content: 'Gratitude prompt' }
    );
});

test('hasMeaningfulDraft ignores the default date alone', () => {
    assert.equal(journal.hasMeaningfulDraft({
        title: '',
        content: '',
        mood_status: '',
        entry_date: '2026-07-23',
        template_key: 'blank',
    }), false);
    assert.equal(journal.hasMeaningfulDraft({
        title: '',
        content: 'A sentence',
        mood_status: '',
        entry_date: '2026-07-23',
        template_key: 'blank',
    }), true);
});
```

- [ ] **Step 2: Replace obsolete static Journal script assertions**

Replace the old local-storage JavaScript test in `tests/journal_helpers_test.php` with:

```php
test('journal uses a dedicated page script', function (): void {
    $source = file_get_contents(__DIR__ . '/../assets/js/journal.js');
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
```

- [ ] **Step 3: Run JavaScript and PHP tests and verify RED**

Run:

```powershell
node --test tests\journal_ui_test.js
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
```

Expected: JavaScript fails because `journal.js` does not exist; PHP fails because the page-specific script and new hooks are absent.

- [ ] **Step 4: Add the testable Journal core**

Create `assets/js/journal.js` with a CommonJS/browser wrapper:

```javascript
'use strict';

(function exposeJournalCore(root, factory) {
    const api = factory();

    if (typeof module === 'object' && module.exports) {
        module.exports = api;
    }
    if (root) {
        root.JournalDrafts = api;
    }
}(typeof globalThis !== 'undefined' ? globalThis : this, function buildJournalCore() {
    function countWords(content) {
        const normalized = String(content || '').trim();
        return normalized === '' ? 0 : normalized.split(/\s+/u).length;
    }

    function nextTemplateState(currentContent, nextContent, confirmed) {
        const current = String(currentContent || '');
        const next = String(nextContent || '');
        const replacementRequired = current.trim() !== '' && current !== next;

        return replacementRequired && !confirmed
            ? { applied: false, content: current }
            : { applied: true, content: next };
    }

    function hasMeaningfulDraft(draft) {
        return String(draft.title || '').trim() !== ''
            || String(draft.content || '').trim() !== ''
            || String(draft.mood_status || '').trim() !== ''
            || String(draft.template_key || 'blank') !== 'blank';
    }

    return {
        countWords,
        nextTemplateState,
        hasMeaningfulDraft,
    };
}));
```

- [ ] **Step 5: Add page-specific script support**

At the bottom of `includes/footer.php`, keep `app.js` first and render only internal page scripts:

```php
    <script src="<?= BASE_URL; ?>/assets/js/app.js"></script>
    <?php foreach (($pageScripts ?? []) as $pageScript): ?>
        <script src="<?= escapeOutput($pageScript); ?>"></script>
    <?php endforeach; ?>
</body>
</html>
```

Before including `header.php` in `create.php`, set:

```php
$pageScripts = [BASE_URL . '/assets/js/journal.js'];
```

- [ ] **Step 6: Add template and counter DOM behavior**

Append a guarded browser initializer to `journal.js`:

```javascript
(function initializeJournalPage() {
    if (typeof document === 'undefined') {
        return;
    }

    const core = globalThis.JournalDrafts;
    const form = document.querySelector('[data-journal-form]');
    const editor = document.querySelector('[data-journal-editor]');
    const templatePicker = document.querySelector('[data-template-picker]');
    const templateInput = document.querySelector('[data-template-input]');
    const wordCount = document.querySelector('[data-word-count]');
    const characterCount = document.querySelector('[data-character-count]');

    if (!(form instanceof HTMLFormElement) || !(editor instanceof HTMLTextAreaElement)) {
        return;
    }

    function updateEditorMetrics() {
        if (wordCount instanceof HTMLElement) {
            wordCount.textContent = String(core.countWords(editor.value));
        }
        if (characterCount instanceof HTMLElement) {
            characterCount.textContent = String(editor.value.length);
        }

        editor.style.height = 'auto';
        editor.style.height = `${Math.min(Math.max(editor.scrollHeight, 280), 900)}px`;
    }

    function setSelectedTemplate(templateKey) {
        if (templateInput instanceof HTMLInputElement) {
            templateInput.value = templateKey;
        }
        templatePicker?.querySelectorAll('[data-template-key]').forEach((button) => {
            const selected = button instanceof HTMLButtonElement
                && button.dataset.templateKey === templateKey;
            button.classList.toggle('is-selected', selected);
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
        });
    }

    templatePicker?.querySelectorAll('[data-template-key]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            const nextContent = button.dataset.templateContent || '';
            const replacementRequired = editor.value.trim() !== ''
                && editor.value !== nextContent;
            const confirmed = !replacementRequired || window.confirm(
                'Replace your current journal content with this template?'
            );
            const next = core.nextTemplateState(editor.value, nextContent, confirmed);

            if (!next.applied) {
                return;
            }

            editor.value = next.content;
            setSelectedTemplate(button.dataset.templateKey || 'blank');
            editor.dispatchEvent(new Event('input', { bubbles: true }));
            editor.focus();
        });
    });

    editor.addEventListener('input', updateEditorMetrics);
    updateEditorMetrics();
}());
```

- [ ] **Step 7: Verify GREEN**

Run:

```powershell
node --check assets\js\journal.js
node --test tests\journal_ui_test.js
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
```

Expected: JavaScript syntax succeeds, 5 Node tests pass, and all PHP Journal helper tests pass.

- [ ] **Step 8: Commit the isolated client increment**

```powershell
git add -- `
    assets/js/journal.js `
    includes/footer.php `
    modules/journal/create.php `
    tests/journal_ui_test.js `
    tests/journal_helpers_test.php
git commit -m "fix: restore isolated journal template behavior"
```

---

### Task 4: Add the Authenticated Autosave Endpoint

**Files:**
- Create: `modules/journal/draft_autosave.php`
- Modify: `tests/journal_helpers_test.php`

**Interfaces:**
- Produces: `POST /modules/journal/draft_autosave.php`
- Consumes: CSRF token, optional draft ID, draft fields, and session user ID
- Consumes: `journalValidateDraftData()`, `journalDraftHasMeaningfulContent()`, `journalSaveDraft()`, and `journalLoadDraftForUser()`

- [ ] **Step 1: Add a failing endpoint contract test**

Append:

```php
test('draft autosave endpoint enforces method session csrf and ownership', function (): void {
    $path = __DIR__ . '/../modules/journal/draft_autosave.php';
    assertTrueValue(is_file($path), 'Expected the draft autosave endpoint.');
    $source = file_get_contents($path);

    assertTrueValue(str_contains($source, "REQUEST_METHOD'] !== 'POST'"));
    assertTrueValue(str_contains($source, 'isLoggedIn()'));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'journalValidateDraftData('));
    assertTrueValue(str_contains($source, 'journalSaveDraft('));
    assertTrueValue(str_contains($source, "Content-Type: application/json"));
});
```

- [ ] **Step 2: Verify RED**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
```

Expected: FAIL because `draft_autosave.php` does not exist.

- [ ] **Step 3: Implement the JSON endpoint**

Create `modules/journal/draft_autosave.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

function journalDraftJson(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    journalDraftJson(405, ['success' => false, 'message' => 'Method not allowed.']);
}

if (!isLoggedIn()) {
    journalDraftJson(401, ['success' => false, 'message' => 'Please log in again.']);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    journalDraftJson(403, ['success' => false, 'message' => 'Your session expired. Refresh and try again.']);
}

$draftId = null;
$rawDraftIdValue = $_POST['draft_id'] ?? '';
if (is_array($rawDraftIdValue)) {
    journalDraftJson(422, ['success' => false, 'message' => 'The draft reference is invalid.']);
}
$rawDraftId = trim((string) $rawDraftIdValue);
if ($rawDraftId !== '') {
    $validatedId = filter_var(
        $rawDraftId,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($validatedId === false) {
        journalDraftJson(422, ['success' => false, 'message' => 'The draft reference is invalid.']);
    }
    $draftId = (int) $validatedId;
}

$data = journalDataFromRequest($_POST);
$errors = journalValidateDraftData($data);
if ($errors) {
    journalDraftJson(422, ['success' => false, 'message' => $errors[0]]);
}
if ($draftId === null && !journalDraftHasMeaningfulContent($data)) {
    journalDraftJson(422, ['success' => false, 'message' => 'Add something before saving a draft.']);
}

try {
    $connection = getDatabaseConnection();
    $savedId = journalSaveDraft(
        $connection,
        (int) $_SESSION['user_id'],
        $draftId,
        $data
    );

    if ($savedId === null) {
        journalDraftJson(404, ['success' => false, 'message' => 'Journal draft was not found.']);
    }

    $saved = journalLoadDraftForUser(
        $connection,
        $savedId,
        (int) $_SESSION['user_id']
    );
    $savedTimestamp = strtotime((string) $saved['updated_at']);

    journalDraftJson(200, [
        'success' => true,
        'draft_id' => $savedId,
        'saved_at' => date(DATE_ATOM, $savedTimestamp),
        'saved_label' => 'Draft saved at ' . date('g:i A', $savedTimestamp),
    ]);
} catch (Throwable $exception) {
    journalDraftJson(500, [
        'success' => false,
        'message' => 'Could not save the draft. Please retry.',
    ]);
}
```

- [ ] **Step 4: Verify endpoint syntax and contract**

Run:

```powershell
& 'C:\xampp\php\php.exe' -l modules\journal\draft_autosave.php
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
```

Expected: no syntax errors and all helper/contract tests pass.

- [ ] **Step 5: Commit the endpoint increment**

```powershell
git add -- modules/journal/draft_autosave.php tests/journal_helpers_test.php
git commit -m "feat: add journal draft autosave endpoint"
```

---

### Task 5: Build New, Resume, Explicit Save, and Publish Flows

**Files:**
- Modify: `tests/journal_helpers_test.php`
- Modify: `modules/journal/create.php`
- Modify: `modules/journal/view.php`
- Modify: `includes/navbar.php`
- Modify: `assets/js/journal.js`

**Interfaces:**
- Produces: `GET create.php`
- Produces: `GET create.php?draft_id=<positive-id>`
- Produces: `POST create.php` with `intent=save_draft`
- Produces: `POST create.php` with `intent=publish`
- Produces DOM hooks: `data-autosave-url`, `data-draft-id`, `data-journal-save-status`, `data-journal-save-text`, and `data-journal-save-retry`
- Consumes: all Task 2 draft helpers and Task 4 autosave endpoint

- [ ] **Step 1: Add failing create-flow contracts**

Delete the old tests named `journal detail page can signal successful draft cleanup` and `logout link exposes the current user for scoped draft cleanup`, because database drafts have no browser-storage cleanup marker.

Replace the create-page contract with:

```php
test('journal create page supports database draft and publish intents', function (): void {
    $source = file_get_contents(__DIR__ . '/../modules/journal/create.php');

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

test('obsolete browser draft controls are absent', function (): void {
    $create = file_get_contents(__DIR__ . '/../modules/journal/create.php');
    $view = file_get_contents(__DIR__ . '/../modules/journal/view.php');
    $navbar = file_get_contents(__DIR__ . '/../includes/navbar.php');

    assertSameValue(false, str_contains($create, 'data-draft-key'));
    assertSameValue(false, str_contains($view, 'data-journal-saved'));
    assertSameValue(false, str_contains($navbar, 'data-journal-logout'));
});
```

- [ ] **Step 2: Verify RED**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
```

Expected: FAIL because create.php still contains browser-local metadata and no database draft intents.

- [ ] **Step 3: Replace create controller state**

In `create.php`:

1. Initialize `$draftId = null`, `$draft = null`, and `$data = journalDefaultFormData()`.
2. Parse a GET or POST draft ID with `FILTER_VALIDATE_INT` and `min_range => 1`.
3. On GET with an ID, load only through `journalLoadDraftForUser()`.
4. On POST, branch on `$_POST['intent'] ?? 'publish'`.

Use this exact ID parsing before opening the database connection:

```php
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
```

After connecting on GET, load and map an existing draft:

```php
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $draftId !== null) {
    $draft = journalLoadDraftForUser($connection, $draftId, $userId);
    if ($draft === null) {
        setFlash('error', 'Journal draft was not found.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }

    $data = [
        'title' => (string) $draft['title'],
        'content' => (string) $draft['content'],
        'mood_status' => (string) $draft['mood_status'],
        'entry_date' => (string) ($draft['entry_date'] ?? ''),
        'template_key' => (string) $draft['template_key'],
    ];
}
```

Use this exact intent logic inside the existing database `try` block:

```php
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
            $savedId = journalSaveDraft($connection, $userId, $draftId, $data);
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
```

- [ ] **Step 4: Replace obsolete draft markup**

Remove the old restore banner, `data-draft-key`, and both `data-journal-draft-discard` buttons.

Render the form start and hidden fields as:

```php
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
```

Add this status block above the action row:

```php
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
```

Render the actions as:

```php
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
```

- [ ] **Step 5: Remove browser-local cleanup**

In `view.php`, remove:

- `$_SESSION['journal_draft_clear']`
- `$draftClearKey`
- `data-journal-saved`

In `includes/navbar.php`, remove `data-journal-logout` and `data-journal-user` while preserving the normal logout URL and label.

- [ ] **Step 6: Add autosave DOM behavior**

Inside the browser initializer in `journal.js`, collect:

```javascript
const draftIdInput = form.querySelector('[data-draft-id]');
const saveStatus = form.querySelector('[data-journal-save-status]');
const saveText = form.querySelector('[data-journal-save-text]');
const retryButton = form.querySelector('[data-journal-save-retry]');
const titleField = form.querySelector('[data-journal-title]');
const moodField = form.querySelector('[data-journal-mood]');
const dateField = form.querySelector('[data-journal-date]');
const autosaveUrl = form.dataset.autosaveUrl || '';
```

Add sequential, revision-aware saving:

```javascript
let revision = 0;
let savedRevision = 0;
let timerId = null;
let activeSave = Promise.resolve();
let submitting = false;

function collectDraft() {
    return {
        csrf_token: form.querySelector('input[name="csrf_token"]')?.value || '',
        draft_id: draftIdInput instanceof HTMLInputElement ? draftIdInput.value : '',
        title: titleField instanceof HTMLInputElement ? titleField.value : '',
        content: editor.value,
        mood_status: moodField instanceof HTMLInputElement ? moodField.value : '',
        entry_date: dateField instanceof HTMLInputElement ? dateField.value : '',
        template_key: templateInput instanceof HTMLInputElement ? templateInput.value : 'blank',
    };
}

function setSaveState(state, message) {
    if (saveStatus instanceof HTMLElement) {
        saveStatus.dataset.state = state;
    }
    if (saveText instanceof HTMLElement) {
        saveText.textContent = message;
    }
    if (retryButton instanceof HTMLButtonElement) {
        retryButton.hidden = state !== 'error';
    }
}

async function saveRevision(targetRevision) {
    const draft = collectDraft();
    if (!draft.draft_id && !core.hasMeaningfulDraft(draft)) {
        savedRevision = targetRevision;
        setSaveState('idle', 'Not saved yet');
        return;
    }

    setSaveState('saving', 'Saving...');
    const response = await fetch(autosaveUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
        body: new URLSearchParams(draft),
    });
    const result = await response.json();

    if (!response.ok || result.success !== true) {
        throw new Error(result.message || 'Could not save the draft.');
    }

    if (draftIdInput instanceof HTMLInputElement) {
        draftIdInput.value = String(result.draft_id);
    }
    const resumedUrl = new URL(window.location.href);
    resumedUrl.searchParams.set('draft_id', String(result.draft_id));
    window.history.replaceState({}, '', resumedUrl);
    savedRevision = targetRevision;

    if (revision === targetRevision) {
        setSaveState('saved', result.saved_label);
    } else {
        queueAutosave(0);
    }
}

function queueAutosave(delay = 900) {
    window.clearTimeout(timerId);
    setSaveState('idle', 'Not saved yet');
    timerId = window.setTimeout(() => {
        const targetRevision = revision;
        activeSave = activeSave
            .catch(() => undefined)
            .then(() => saveRevision(targetRevision))
            .catch(() => {
                setSaveState('error', "Couldn't save draft");
            });
    }, delay);
}

function markDirty() {
    revision += 1;
    queueAutosave();
}

async function flushAutosave() {
    window.clearTimeout(timerId);
    if (revision > savedRevision) {
        const targetRevision = revision;
        activeSave = activeSave
            .catch(() => undefined)
            .then(() => saveRevision(targetRevision));
    }
    await activeSave;
}
```

Connect all fields, retry, submit flushing, and navigation protection:

```javascript
form.addEventListener('input', markDirty);
form.addEventListener('change', markDirty);

retryButton?.addEventListener('click', () => queueAutosave(0));

form.addEventListener('submit', (event) => {
    if (submitting) {
        return;
    }

    event.preventDefault();
    const submitter = event.submitter;
    flushAutosave()
        .catch(() => undefined)
        .finally(() => {
            submitting = true;
            form.requestSubmit(submitter instanceof HTMLElement ? submitter : undefined);
        });
});

window.addEventListener('beforeunload', (event) => {
    if (!submitting && revision > savedRevision) {
        event.preventDefault();
        event.returnValue = '';
    }
});
```

Ensure the template handler calls `markDirty()` only once. Because it dispatches an `input` event, do not call `markDirty()` separately in that handler.

- [ ] **Step 7: Verify create flow contracts and all unit tests**

Run:

```powershell
& 'C:\xampp\php\php.exe' -l modules\journal\create.php
& 'C:\xampp\php\php.exe' -l modules\journal\view.php
node --check assets\js\journal.js
node --test tests\journal_ui_test.js
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
& 'C:\xampp\php\php.exe' tests\journal_drafts_database_test.php
```

Expected: all commands exit 0 and no obsolete draft contract remains.

- [ ] **Step 8: Commit the create/resume/publish increment**

```powershell
git add -- `
    modules/journal/create.php `
    modules/journal/view.php `
    includes/navbar.php `
    assets/js/journal.js `
    tests/journal_helpers_test.php
git commit -m "feat: save and publish database journal drafts"
```

---

### Task 6: Show, Resume, and Delete Drafts from the Journal Page

**Files:**
- Modify: `tests/journal_helpers_test.php`
- Modify: `modules/journal/index.php`
- Create: `modules/journal/draft_delete.php`
- Modify: `assets/css/style.css`

**Interfaces:**
- Produces: `Your Drafts` section ordered by `updated_at DESC`
- Produces: `GET/POST draft_delete.php?id=<positive-id>`
- Consumes: `journalListDraftsForUser()`, `journalLoadDraftForUser()`, `journalDeleteDraftForUser()`, `journalPreview()`, and `journalTemplateOptions()`

- [ ] **Step 1: Add failing list and delete contracts**

Append:

```php
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
    $source = file_get_contents($path);

    assertTrueValue(str_contains($source, 'journalLoadDraftForUser('));
    assertTrueValue(str_contains($source, "REQUEST_METHOD'] === 'POST'"));
    assertTrueValue(str_contains($source, 'verifyCsrfToken('));
    assertTrueValue(str_contains($source, 'journalDeleteDraftForUser('));
});
```

Update the stylesheet selector contract to require:

```php
'.journal-draft-grid',
'.journal-draft-card',
'.journal-save-status',
```

and remove `.journal-draft-banner`.

- [ ] **Step 2: Verify RED**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
```

Expected: failures for missing list, delete page, and new selectors.

- [ ] **Step 3: Load drafts without changing published queries**

In `index.php`, initialize `$drafts = [];` and load them after connecting:

```php
$drafts = journalListDraftsForUser($connection, $userId);
```

Do not alter:

- the `journal_entries` summary query,
- latest-mood query,
- `journalFilterQuery()`,
- or the published record query.

Add a separate hero chip:

```php
<span><strong><?= number_format(count($drafts)); ?></strong> saved drafts</span>
```

- [ ] **Step 4: Render the Drafts section**

Place this after the hero and before published filters:

```php
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
                $template = journalTemplateOptions()[$draft['template_key']] ?? journalTemplateOptions()['blank'];
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
```

- [ ] **Step 5: Implement confirmed draft deletion**

Create `modules/journal/draft_delete.php`:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/../../config/app.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../includes/auth.php';
require __DIR__ . '/../../includes/validation.php';
require __DIR__ . '/journal_helpers.php';

requireLogin();

$userId = (int) $_SESSION['user_id'];
$draftId = filter_var(
    $_GET['id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

if (!$draftId) {
    setFlash('error', 'Journal draft was not found.');
    header('Location: ' . BASE_URL . '/modules/journal/index.php');
    exit;
}

$draft = null;
$errors = [];
$pageError = null;

try {
    $connection = getDatabaseConnection();
    $draft = journalLoadDraftForUser($connection, (int) $draftId, $userId);
    if (!$draft) {
        setFlash('error', 'Journal draft was not found.');
        header('Location: ' . BASE_URL . '/modules/journal/index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
            $errors[] = 'Your session token expired. Please try again.';
        } elseif (journalDeleteDraftForUser($connection, (int) $draftId, $userId)) {
            setFlash('success', 'Journal draft deleted.');
            header('Location: ' . BASE_URL . '/modules/journal/index.php');
            exit;
        } else {
            $errors[] = 'Journal draft was not found.';
        }
    }
} catch (Throwable $exception) {
    $pageError = 'Journal draft deletion is unavailable right now.';
}

$pageTitle = 'Delete Journal Draft';
require __DIR__ . '/../../includes/header.php';
$draftTitle = $draft && trim((string) $draft['title']) !== ''
    ? (string) $draft['title']
    : 'Untitled draft';
?>

<section class="panel journal-delete-panel">
    <p class="eyebrow">Draft management</p>
    <h1>Delete Journal Draft</h1>

    <?php if ($pageError): ?>
        <div class="alert alert-error"><?= escapeOutput($pageError); ?></div>
        <a class="button" href="<?= BASE_URL; ?>/modules/journal/index.php">Back to Journal</a>
    <?php elseif ($draft): ?>
        <?php foreach ($errors as $error): ?>
            <div class="alert alert-error"><?= escapeOutput($error); ?></div>
        <?php endforeach; ?>

        <div class="journal-delete-preview">
            <h2><?= escapeOutput($draftTitle); ?></h2>
            <p><?= escapeOutput(
                trim((string) $draft['content']) !== ''
                    ? journalPreview((string) $draft['content'], 240)
                    : 'No writing yet'
            ); ?></p>
            <p class="muted">
                Saved <?= escapeOutput(date('M j, Y g:i A', strtotime($draft['updated_at']))); ?>
            </p>
        </div>

        <form method="post" action="<?= BASE_URL; ?>/modules/journal/draft_delete.php?id=<?= (int) $draftId; ?>">
            <?= csrfInput(); ?>
            <div class="button-row">
                <button class="button danger-button" type="submit">Delete Draft</button>
                <a class="button" href="<?= BASE_URL; ?>/modules/journal/create.php?draft_id=<?= (int) $draftId; ?>">
                    Keep Draft
                </a>
            </div>
        </form>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
```

The file now provides the required title fallback, preview, last-saved time, CSRF input, POST deletion, and Keep Draft link without relying on GET for mutation.

- [ ] **Step 6: Add focused responsive styles**

Add:

```css
.journal-draft-section {
    margin-bottom: 24px;
}

.journal-draft-grid {
    display: grid;
    gap: 14px;
    grid-template-columns: repeat(auto-fit, minmax(270px, 1fr));
}

.journal-draft-card {
    background: linear-gradient(145deg, #fffaf1, #fff);
    border: 1px dashed #d7b46a;
    border-radius: var(--radius);
    box-shadow: var(--shadow-sm);
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-height: 220px;
    padding: 20px;
}

.journal-draft-card h3,
.journal-draft-card p {
    margin: 0;
}

.journal-draft-card p {
    color: var(--muted);
    line-height: 1.65;
}

.journal-draft-badge,
.journal-template-label {
    border-radius: 999px;
    display: inline-flex;
    font-size: 12px;
    font-weight: 900;
    width: fit-content;
}

.journal-draft-badge {
    background: #fff0c7;
    color: #74520d;
    padding: 5px 10px;
}

.journal-template-label {
    background: var(--rose-soft);
    color: var(--rose);
    padding: 4px 9px;
}

.journal-save-status {
    align-items: center;
    color: var(--muted);
    display: flex;
    gap: 9px;
    margin-top: 16px;
}

.journal-save-status[data-state="saving"] {
    color: var(--primary);
}

.journal-save-status[data-state="saved"] {
    color: #32704a;
}

.journal-save-status[data-state="error"] {
    color: var(--danger);
}

@media (max-width: 760px) {
    .journal-draft-grid {
        grid-template-columns: 1fr;
    }

    .journal-compose-actions,
    .journal-save-status {
        align-items: stretch;
        display: grid;
        grid-template-columns: 1fr;
    }

    .journal-compose-actions .button {
        width: 100%;
    }
}
```

- [ ] **Step 7: Verify list/delete behavior and CSS contracts**

Run:

```powershell
& 'C:\xampp\php\php.exe' -l modules\journal\index.php
& 'C:\xampp\php\php.exe' -l modules\journal\draft_delete.php
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
& 'C:\xampp\php\php.exe' tests\journal_drafts_database_test.php
```

Expected: all syntax and tests pass.

- [ ] **Step 8: Commit the Journal list/delete increment**

```powershell
git add -- `
    modules/journal/index.php `
    modules/journal/draft_delete.php `
    assets/css/style.css `
    tests/journal_helpers_test.php
git commit -m "feat: list and manage journal drafts"
```

---

### Task 7: Run Migration, End-to-End Browser Verification, and Documentation

**Files:**
- Modify: `README.md`
- Modify: `docs/UCCD3243_Project_Step_By_Step_Plan.md`
- Modify implementation or tests only when a verification failure exposes a specific defect

**Interfaces:**
- Verifies all prior task outputs as one live XAMPP workflow.

- [ ] **Step 1: Run every automated check from a clean process**

Run:

```powershell
& 'C:\xampp\php\php.exe' tests\app_config_test.php
& 'C:\xampp\php\php.exe' tests\journal_draft_schema_test.php
& 'C:\xampp\php\php.exe' tests\journal_helpers_test.php
& 'C:\xampp\php\php.exe' tests\journal_database_test.php
& 'C:\xampp\php\php.exe' tests\journal_drafts_database_test.php
node --check assets\js\app.js
node --check assets\js\journal.js
node --test tests\journal_ui_test.js
```

Expected: every suite reports 0 failures and both scripts pass syntax checking.

- [ ] **Step 2: Lint the full PHP project**

Run:

```powershell
$phpFailures = 0
Get-ChildItem -Recurse -Filter *.php | ForEach-Object {
    & 'C:\xampp\php\php.exe' -l $_.FullName
    if ($LASTEXITCODE -ne 0) {
        $phpFailures++
    }
}
if ($phpFailures -ne 0) {
    throw "$phpFailures PHP file(s) failed syntax validation."
}
```

Expected: every PHP file reports `No syntax errors detected`.

- [ ] **Step 3: Verify the local server**

Run:

```powershell
$response = Invoke-WebRequest `
    -Uri 'http://localhost/student-routine-organizer/' `
    -UseBasicParsing `
    -TimeoutSec 10
$response.StatusCode
```

Expected: `200`.

- [ ] **Step 4: Browser-test template behavior**

Use the sample student account.

Verify:

1. Open `/modules/journal/create.php`.
2. Select Daily Reflection and confirm its prompts appear and its `aria-pressed` state is true.
3. Type original writing, select Gratitude, cancel replacement, and confirm the original content remains.
4. Select Gratitude again, accept replacement, and confirm the prompts and counts update.
5. Confirm the browser console has no Journal error.

- [ ] **Step 5: Browser-test database autosave and recovery**

Verify:

1. Type only content and observe `Saving...`.
2. Wait for the server-confirmed `Draft saved at <time>`.
3. Return to the Journal page and confirm a Draft card appears above filters.
4. Reload the page and confirm the draft remains.
5. Continue Writing and confirm all saved fields and selected template return.
6. Open another authenticated browser session and confirm the same Draft card and content are available.
7. Create a second draft and confirm both remain independently resumable.

- [ ] **Step 6: Browser-test truthful failure behavior**

Temporarily enable offline network emulation for the controlled browser tab after the editor page has loaded. Do not stop Apache or MySQL, and restore the browser tab to online mode immediately after the failure-state check.

Verify:

1. New input changes show `Couldn't save draft`.
2. Retry is visible.
3. Current text remains in the editor.
4. Attempting to leave triggers the browser's data-loss warning.
5. Restore online mode, choose Retry, and confirm a real saved timestamp appears.

- [ ] **Step 7: Browser-test explicit save, publish, and delete**

Verify:

1. Save Draft & Exit immediately returns to the list with a success flash.
2. Resume that draft and publish it.
3. Confirm it disappears from Drafts and appears exactly once under published entries.
4. Confirm the dashboard Journal count increases by one and latest mood matches the published entry.
5. Create another draft, open Delete Draft, choose Keep Draft, and confirm it remains.
6. Reopen Delete Draft, confirm deletion through POST, and verify it disappears.
7. Confirm a blank new-entry page has no Delete Draft or Discard Draft action.

- [ ] **Step 8: Verify authorization and summary isolation**

Using two temporary student accounts or the database integration test:

- Student B cannot open, autosave over, publish, or delete Student A's draft ID.
- Drafts do not change published total entries, this-month count, latest mood, dashboard Journal count, or admin Journal total.
- Published search, mood filter, date filter, and sort still operate only on `journal_entries`.

- [ ] **Step 9: Verify responsive layouts**

At desktop width and at a viewport no wider than 760 pixels:

- Template cards are usable.
- Draft cards do not overflow.
- Save status remains readable.
- Editor actions stack without clipping.
- Every button and link remains keyboard focusable.

- [ ] **Step 10: Update project documentation**

In `README.md`, add to Implemented So Far:

```markdown
- Database-backed Journal drafts with autosave, cross-device resume, and safe publication
```

In the Phase 6 Journal section of `docs/UCCD3243_Project_Step_By_Step_Plan.md`, record:

```markdown
- [x] Store unfinished Journal drafts in the database.
- [x] Resume, publish, and delete owned drafts across authenticated devices.
- [x] Show truthful autosave, retry, and navigation-protection states.
```

- [ ] **Step 11: Re-run the complete verification gate**

Run the exact commands from Steps 1 through 3 again after documentation and any browser-found fixes.

Expected: every automated test passes, every PHP file lints, both JavaScript files parse, the Node suite passes, and localhost responds with HTTP 200.

- [ ] **Step 12: Review the final diff and commit**

Run:

```powershell
git diff --check
git status --short
git diff --stat
```

Expected: no whitespace errors; the only unrelated untracked file remains `Assignment_Submission_Guideline_June2026.pdf`.

Commit:

```powershell
git add -- README.md docs/UCCD3243_Project_Step_By_Step_Plan.md
git commit -m "docs: document database journal drafts"
```

Do not claim completion until the full automated output and live browser checks from this task are fresh and successful.
