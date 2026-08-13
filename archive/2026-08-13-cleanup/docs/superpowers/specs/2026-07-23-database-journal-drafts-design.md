# Database-Backed Journal Drafts Design Specification

- **Status:** Approved
- **Date:** 2026-07-23
- **Module owner:** Member 2
- **Project:** UCCD3243 Student Routine Organizer

## 1. Goal

Repair the broken Journal template controls and replace the ineffective browser-only draft behavior with secure, database-backed drafts that appear on the Journal page and can be resumed from another browser or device after login.

The finished flow must make every action truthful and visible:

- Template cards populate the editor and show which template is selected.
- Meaningful writing is automatically saved to the database.
- The editor reports whether a draft is saving, saved, or failed.
- A user can explicitly save and exit, resume, publish, or delete a real draft.
- A brand-new blank form does not show a misleading discard or delete action.

## 2. Confirmed Root Cause

The Journal PHP markup still renders the template, editor, counter, and draft hooks, but the later shared JavaScript merge removed the Journal event handlers from `assets/js/app.js`.

The failure is reproducible in the live XAMPP application:

- Selecting Daily Reflection leaves Blank Page selected.
- The content editor remains empty.
- Typed title, mood, and content disappear after a reload.
- No recoverable-draft banner appears.

The existing `tests/journal_helpers_test.php` suite independently reports one failure at `journal javascript supports templates counts and scoped drafts`, while its other Journal tests pass. The defect is therefore missing client behavior rather than missing template data or invalid PHP markup.

## 3. Approved Product Decisions

### 3.1 Database-backed drafts

Drafts are stored in MariaDB/MySQL and belong to the authenticated user. They are not limited to one browser and must be accessible through the Journal page after signing in elsewhere.

### 3.2 Multiple drafts

A user may keep more than one unfinished entry. Choosing Write New Entry starts a fresh form. Existing drafts are resumed from the Drafts section.

### 3.3 Separate draft storage

Drafts use a new `journal_drafts` table instead of adding a status column to `journal_entries`.

This separation is required because:

- Draft fields may be incomplete, while published entries retain strict required-field rules.
- Existing dashboard, Journal, and admin totals must continue counting only published entries.
- Latest-mood summaries must not use incomplete draft moods.
- Existing CRUD and filtering queries do not need status conditions added throughout the project.

### 3.4 Replace the ineffective control

The current bottom-level `Discard Draft` button is removed.

- A new blank form has no destructive draft action.
- `Save Draft & Exit` performs an immediate database save and returns to the Journal page.
- `Delete Draft` appears only when the user is editing an existing database draft.
- Deletion uses a dedicated confirmation screen and a CSRF-protected POST.

## 4. Scope

### Included

- Restore all five template-selection behaviors.
- Preserve template replacement confirmation when the editor already contains writing.
- Restore live word and character counts and editor auto-expansion.
- Add a separate database table for partial drafts.
- Automatically create or update a draft after meaningful form changes.
- Show accurate saving, saved, failure, and retry states.
- Add Save Draft & Exit with a non-JavaScript server fallback.
- List the current user's drafts above published Journal entries.
- Resume, publish, and delete an owned draft.
- Remove a draft after successful publication.
- Protect every draft query by authenticated `user_id`.
- Add schema export changes and a non-destructive migration for existing installations.
- Add automated helper/database tests and live browser verification.
- Validate the editor at desktop and mobile widths.

### Excluded

- Draft history or version rollback.
- Simultaneous collaborative editing.
- Real-time WebSocket synchronization.
- Custom user-created writing templates.
- Rich text, images, audio, attachments, or journal sharing.
- Automatic expiry of old drafts.
- Converting the project from MySQLi to another database library.

## 5. Architecture and File Boundaries

The implementation retains the project's procedural PHP and MySQLi structure.

### New files

- `database/journal_drafts_migration.sql`
  - Creates `journal_drafts` without dropping or rebuilding existing tables.
- `modules/journal/draft_autosave.php`
  - Accepts authenticated, CSRF-protected autosave requests and returns JSON.
- `modules/journal/draft_delete.php`
  - Shows an owned-draft confirmation and performs deletion through POST.
- `assets/js/journal.js`
  - Owns Journal-only template, counter, autosave, status, and navigation behavior.
- `tests/journal_drafts_database_test.php`
  - Verifies draft ownership, partial saves, updates, publication cleanup, and deletion against the live test database.

### Existing files to modify

- `database/student_routine_organizer.sql`
  - Adds the draft table to a fresh full import in foreign-key-safe order.
- `database/schema_draft.sql`
  - Keeps the documented schema aligned with the runnable export.
- `modules/journal/journal_helpers.php`
  - Adds partial-draft parsing, validation, meaningful-content detection, owned loading, saving, and listing helpers.
- `modules/journal/create.php`
  - Loads an owned draft, handles publish and Save Draft & Exit intents, renders save state hooks, and removes the ineffective Discard Draft control.
- `modules/journal/index.php`
  - Loads and displays a separate Drafts section without changing published-entry filters or totals.
- `includes/footer.php`
  - Supports an optional page-script list so `journal.js` loads only where it is required and after the shared script.
- `assets/css/style.css`
  - Styles draft cards, state badges, save feedback, retry action, and responsive layouts.
- `tests/journal_helpers_test.php`
  - Replaces browser-local draft assumptions with dedicated-script and database-draft contracts.

### Isolation decision

Journal behavior must not be restored inside the shared `assets/js/app.js`. The merge that introduced this defect replaced shared code while leaving Journal markup intact. A dedicated `assets/js/journal.js`, loaded through an optional page-script mechanism, reduces merge conflicts and gives the module a clear testable boundary.

## 6. Database Design

The existing `journal_entries` table remains unchanged and continues to contain published entries only.

The new table is:

```sql
CREATE TABLE journal_drafts (
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

### Field rules

- `draft_id` is the server-issued draft identifier.
- `user_id` always comes from the authenticated session, never from form input.
- `title`, `content`, and `mood_status` may be empty while a draft is unfinished.
- `entry_date` may be `NULL` for a partial draft, although a new form initially displays today's date.
- `template_key` must be one of the five allow-listed template keys.
- `updated_at` is the authoritative last-saved time displayed in the interface.
- Deleting a user also removes that user's drafts through `ON DELETE CASCADE`.

### Migration behavior

`journal_drafts_migration.sql` uses `CREATE TABLE IF NOT EXISTS` and does not delete or alter `journal_entries`, users, or existing module data.

The full database export drops `journal_drafts` before `users` and recreates it after `users`, preserving valid foreign-key ordering.

## 7. Draft Data and Validation

Published-entry validation remains strict:

- Title is required and limited to 120 characters.
- Content is required and limited to 10,000 characters.
- Mood is required and limited to 50 characters.
- Entry date must be a valid `Y-m-d` calendar date.
- Template key must be allow-listed.

Draft validation allows incomplete values but still rejects unsafe or structurally invalid data:

- Optional title may contain at most 120 characters.
- Optional content may contain at most 10,000 characters.
- Optional mood may contain at most 50 characters.
- Optional entry date must be empty or a valid `Y-m-d` date.
- Template key must be allow-listed.
- Draft IDs must be positive integers.

The first automatic save is not sent until the form has a meaningful change. Today's default date alone does not create a draft. Meaningful data is any non-empty title, content, or mood, or selection of a non-blank template.

Once a draft exists, clearing its fields updates it to the cleared state rather than silently deleting it. Only the explicit Delete Draft flow removes it.

## 8. User Flows

### 8.1 Start a new entry

1. The user opens `create.php` without a draft ID.
2. The page shows a blank form with today's date.
3. Save Entry and Save Draft & Exit are available.
4. Delete Draft is not rendered.
5. The first meaningful change schedules an automatic save.
6. A successful insert returns `draft_id`, which is stored in a hidden form field and used for later updates.

### 8.2 Select a template

1. Clicking a template updates the selected card and hidden template key.
2. Its prompts replace the editor content.
3. Word and character counts update immediately.
4. The changed content schedules an automatic database save.
5. If existing content would be replaced, the user must confirm first.
6. Cancelling the confirmation preserves the content, template selection, and saved draft.

### 8.3 Automatic save

1. Input and change events mark the form as unsaved.
2. After a short debounce, `journal.js` sends the current fields and CSRF token to `draft_autosave.php`.
3. Only one save request runs at a time.
4. If more edits occur during a request, one newer save follows it.
5. The interface progresses through Saving, Draft saved, or Couldn't save states.
6. The successful response provides the server draft ID and saved timestamp.

### 8.4 Save Draft & Exit

1. The button submits `intent=save_draft`.
2. JavaScript flushes any scheduled autosave before normal submission.
3. `create.php` validates the partial draft, inserts or updates it for the session user, flashes success, and redirects to the Journal page.
4. Without JavaScript, the same POST flow remains fully functional.
5. If the form has no meaningful content and no existing draft, the page remains open and explains that there is nothing to save yet.

### 8.5 Resume a draft

1. The Journal page shows Drafts above search and published entries.
2. Continue Writing links to `create.php?draft_id=<id>`.
3. `create.php` loads through an ownership-scoped helper.
4. Invalid, missing, or unowned IDs use the same safe not-found response.
5. The saved fields, template selection, and server timestamp are rendered.

### 8.6 Publish

1. Save Entry submits `intent=publish`.
2. JavaScript flushes the latest pending save before allowing submission.
3. PHP applies the full published-entry validation rules.
4. A database transaction inserts into `journal_entries`.
5. When a draft ID is present, the same transaction deletes it with both `draft_id` and `user_id`.
6. The transaction commits only when both operations succeed.
7. Success redirects to the published entry's detail page.
8. A failure rolls back and retains the submitted fields on the editor page.

### 8.7 Delete a draft

1. Delete Draft appears only for an owned existing draft.
2. The link opens `draft_delete.php?id=<id>`.
3. GET displays the draft title fallback, preview, and last-saved time.
4. POST requires a valid CSRF token and deletes with both `draft_id` and `user_id`.
5. Success returns to the Journal page with a flash message.

## 9. Journal List Design

Drafts appear in a clearly labelled `Your Drafts` section between the Journal hero and the published-entry search panel.

Each draft card shows:

- A visible Draft status badge.
- Its title or `Untitled draft`.
- A short escaped content preview or `No writing yet`.
- The selected template label when it is not Blank Page.
- `Saved <date and time>` from the database timestamp.
- Continue Writing and Delete Draft actions.

The Journal hero may show a separate draft count, but published totals and this-month counts continue to query only `journal_entries`.

Drafts are ordered by most recently updated. Published-entry search, mood, date, and sorting controls remain scoped to published entries so recovery cards never disappear because of an unrelated filter.

## 10. Editor Actions and Feedback

The action hierarchy is:

1. **Save Entry** — primary action that publishes a complete entry.
2. **Save Draft & Exit** — secondary action that guarantees a save before leaving.
3. **Back to Journal** — navigation that relies on the visible save state.
4. **Delete Draft** — destructive action shown only for an existing draft.

The old Discard Draft action is removed from the new-entry form and recoverable-draft banner.

The save-status region uses `aria-live="polite"` and one of these truthful states:

- `Not saved yet`
- `Saving...`
- `Draft saved at 3:42 PM`
- `Couldn't save draft`

The failure state includes a Retry button. The page does not display a saved message until the server confirms the write.

When the page is dirty, saving, or in a failed state, leaving or reloading triggers a browser data-loss warning. Once the server confirms the latest data, normal navigation does not warn.

## 11. Autosave Endpoint Contract

`POST /modules/journal/draft_autosave.php` accepts form-encoded data:

- `csrf_token`
- optional `draft_id`
- `title`
- `content`
- `mood_status`
- `entry_date`
- `template_key`

Successful response:

```json
{
  "success": true,
  "draft_id": 7,
  "saved_at": "2026-07-23T15:42:00+08:00",
  "saved_label": "Draft saved at 3:42 PM"
}
```

Failure responses use a suitable HTTP status and a generic JSON message:

- `401` when the session is unavailable.
- `403` for a missing or invalid CSRF token.
- `404` for a missing or unowned supplied draft ID.
- `422` for draft field validation errors.
- `500` for an unavailable database save.

No response includes SQL, stack traces, credentials, or another user's record details.

## 12. Error Handling and Concurrency

- Autosaves are sequential to prevent an older request from overwriting newer text.
- Form submission waits for the active save attempt to settle before publishing or exiting.
- A failed autosave leaves all current browser fields untouched.
- Save Draft & Exit uses the server-rendered POST fallback and therefore returns field-specific errors even if client scripting fails.
- Publication uses a transaction so an entry is not duplicated while its draft remains active.
- Database exceptions display generic recovery messages and are not exposed to the browser console or page.
- Missing and unowned drafts use the same not-found wording.
- If an automatic save fails, navigation protection remains active until the user retries, publishes successfully, explicitly saves through POST, or chooses to leave despite the warning.

## 13. Security and Privacy

- Every draft page and endpoint requires an authenticated session.
- Ownership always comes from `$_SESSION['user_id']`.
- Draft reads, updates, publication cleanup, and deletion include `user_id` in their SQL conditions.
- Every SQL value uses a MySQLi prepared statement.
- Autosave, Save Draft & Exit, publish, and delete require CSRF validation.
- Draft and entry content is escaped before HTML output.
- Draft content remains plain text.
- Invalid and cross-user draft IDs reveal no ownership information.
- Drafts are excluded from mood suggestions, published-entry counts, and admin reflection totals because they live in a separate table.

## 14. Accessibility and Responsive Behavior

- Template cards remain real buttons with accurate `aria-pressed` state.
- Save status uses text and an accessible live region rather than color alone.
- Retry, Continue Writing, Save Draft & Exit, Save Entry, and Delete Draft have distinct accessible names.
- The confirmation screen receives a meaningful heading and keyboard-focus order.
- Draft cards stack to one column at the existing mobile breakpoint.
- Editor actions stack without clipping or horizontal scrolling on narrow screens.
- Visible focus styles remain on templates, buttons, links, and form controls.

## 15. Verification Strategy

### TDD baseline

The current failing Journal JavaScript contract test is retained as the first red test for the missing template and editor behavior.

New tests are written and observed failing before implementation for:

- Partial-draft parsing and validation.
- Meaningful-content detection.
- Owned draft loading.
- Cross-user read, update, publish, and delete rejection.
- First save and later update behavior.
- Publish transaction cleanup.
- Page-specific Journal script inclusion.
- Removal of obsolete browser-local draft and Discard Draft contracts.

### Automated verification

- Run PHP syntax checks over all project PHP files.
- Run `tests/app_config_test.php`.
- Run `tests/journal_helpers_test.php`.
- Run `tests/journal_database_test.php`.
- Run `tests/journal_drafts_database_test.php`.
- Verify the live `journal_drafts` columns and indexes.
- Verify existing published Journal tests continue passing.

### Browser regression verification

Using the live XAMPP application:

- Select each of the five templates and verify prompts and selected state.
- Confirm template replacement protection preserves writing when cancelled.
- Verify word and character counts after template and manual changes.
- Type partial data and observe Saving followed by Draft saved.
- Reload and resume the saved draft from the Journal page.
- Sign in from another browser session and resume the same draft.
- Create and retain more than one draft.
- Use Save Draft & Exit and verify the list card timestamp and preview.
- Publish a resumed draft and verify it disappears from Drafts and appears once in entries.
- Delete a draft through confirmation.
- Simulate a failed save and verify failure text, Retry, retained content, and navigation warning.
- Verify desktop and mobile layouts.
- Verify the browser console contains no Journal errors.

### Regression checks

- Published Journal totals do not include drafts.
- Latest mood does not use draft data.
- Admin Journal totals do not include drafts.
- Existing Journal create, view, edit, delete, search, filter, and sort flows remain functional.
- Exercise, Money, and Habit JavaScript behavior in the shared app script remains unchanged.

## 16. Completion Criteria

The work is complete only when:

- The database migration applies without deleting existing data.
- Templates work in the live editor.
- Drafts save to the database and are recoverable across authenticated sessions.
- Every save state is accurate.
- The ineffective Discard Draft control is absent.
- Delete Draft appears only for an existing database record and performs a confirmed deletion.
- Publishing removes the active draft and creates exactly one completed entry.
- Ownership and CSRF tests pass.
- Published-entry summaries remain accurate.
- All automated tests, PHP syntax checks, and browser regression checks pass with fresh evidence.
