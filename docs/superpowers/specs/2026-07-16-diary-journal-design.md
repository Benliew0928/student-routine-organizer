# Diary Journal Design Specification

- **Status:** Approved
- **Date:** 2026-07-16
- **Module owner:** Member 2
**Project:** UCCD3243 Student Routine Organizer

## 1. Goal

Build a complete Diary Journal module that lets each authenticated user add, browse, read, edit, and delete only their own journal entries. The module must integrate with the project's existing authentication, dashboard, database, layout, and procedural PHP conventions.

## 2. Existing Project Context

The project already provides:

- PHP 8.2 under XAMPP with Apache and MariaDB/MySQL.
- A shared `student_routine_organizer` database.
- Central registration, login, logout, sessions, student/admin roles, and a remembered-email cookie.
- Shared helpers for authentication, validation, CSRF tokens, flash messages, escaping, headers, navigation, and footers.
- A `journal_entries` table and Journal page placeholders.
- Dashboard and admin summaries that already read from `journal_entries`.
- A completed Habit Tracker that establishes the project's expected module structure and MySQLi prepared-statement style.

The Journal module will therefore use MySQLi rather than introduce PDO into only one module. It will not rebuild authentication or create a separate application structure.

Before browser testing, the checkout must be served from `C:\xampp\htdocs\student-routine-organizer` so it matches `BASE_URL = '/student-routine-organizer'`. Alternatively, the group must intentionally update `BASE_URL` and all documented local URLs together.

## 3. Scope

### Required features

- Add a journal entry.
- List and browse the authenticated user's entries.
- View one complete journal entry.
- Edit an entry owned by the authenticated user.
- Delete an owned entry after confirmation.
- Store a title, content, free-text mood status, and entry date.
- Display clear validation and database error messages.

### Approved refinements

- Search by title or content.
- Filter by mood and date range.
- Sort newest or oldest first.
- Suggest moods previously used by the current user while accepting new mood text.
- Start an entry using one of five built-in writing templates.
- Show a content preview on entry cards and the complete content on a detail page.
- Show live word and character counts.
- Recover an unfinished create-form draft in the same browser.
- Provide responsive and keyboard-accessible controls.

### Out of scope

- Custom user-created templates.
- Rich-text HTML editing.
- Images, audio, handwriting, or file attachments.
- Sharing entries with other users.
- Replacing the existing authentication system.
- Converting the project from MySQLi to PDO.

## 4. File Structure

### New files

- `modules/journal/journal_helpers.php` - template definitions, request parsing, validation, ownership loading, filtering, sorting, and dynamic parameter binding.
- `modules/journal/view.php` - complete read-only view of one owned journal entry.

### Existing files to modify

- `modules/journal/index.php` - list, search, filters, sorting, mood suggestions, and entry cards.
- `modules/journal/create.php` - template picker and create workflow.
- `modules/journal/edit.php` - owned-entry edit workflow.
- `modules/journal/delete.php` - owned-entry confirmation and deletion workflow.
- `assets/css/style.css` - Journal-specific responsive layout and component styling using the existing design tokens.
- `assets/js/app.js` - template switching, data-loss confirmation, word/character counts, and user-scoped draft recovery.

No authentication, dashboard, admin, or global database file needs to be replaced for this module.

## 5. Three-Tier Mapping

The module follows the assignment's three-tier requirement within the existing project structure:

1. **Presentation tier:** Journal pages, shared header/footer, forms, entry cards, detail view, CSS, and JavaScript.
2. **Application tier:** `journal_helpers.php`, authentication guard, validation rules, CSRF checks, filter parsing, ownership rules, and flash messages.
3. **Data tier:** MySQLi prepared statements operating on `journal_entries` and `users` in MariaDB/MySQL.

## 6. Database Design

The existing `journal_entries` table remains the source of truth:

| Field | Type | Rules and purpose |
|---|---|---|
| `journal_id` | `INT` | Primary key, auto-increment |
| `user_id` | `INT` | Required foreign key to `users.user_id`; identifies the owner |
| `title` | `VARCHAR(120)` | Required entry title |
| `content` | `TEXT` | Required plain-text journal content |
| `mood_status` | `VARCHAR(50)` | Required user-entered mood |
| `entry_date` | `DATE` | Required journal date |
| `created_at` | `TIMESTAMP` | Creation time |
| `updated_at` | `TIMESTAMP` | Automatically updated modification time |

Existing database behavior is retained:

- `ON DELETE CASCADE` removes a user's entries when that user is deleted.
- `(user_id, entry_date)` supports per-user chronological browsing.
- `mood_status` is indexed for mood filtering.

No template table or `template_key` column is required. A selected template only prefills editable content; the final plain text is stored in `content`.

## 7. Built-In Templates

`journalTemplateOptions()` in `journal_helpers.php` returns these fixed keys, labels, descriptions, and editable content prompts:

- `blank` - empty content.
- `daily_reflection` - highlights, challenges, lessons learned, and tomorrow's focus.
- `gratitude` - things appreciated, a positive moment, and a small win.
- `mood_checkin` - current feeling, possible cause, personal needs, and next action.
- `study_notes` - topic, key ideas, detailed notes, questions, and next steps.

Template keys are allow-listed before use. Selecting a different template after the user has typed content requires confirmation because it replaces the current editor content.

## 8. Form Data and Validation

`journalDefaultFormData()` supplies today's date and empty text fields. `journalDataFromRequest()` trims title, mood, and date while preserving meaningful line breaks in content.

Server-side rules:

- Title is required and must contain at most 120 characters.
- Content is required and must contain at most 10,000 characters.
- Mood status is required and must contain at most 50 characters.
- Entry date is required and must be a real calendar date in `Y-m-d` format.
- Template selection must match one of the five built-in keys when it is submitted.
- A valid CSRF token is required for create, update, and delete requests.

HTML `required` and `maxlength` attributes provide immediate feedback, but server-side validation remains authoritative. Invalid submissions retain the user's entered values and display all relevant errors.

## 9. CRUD and Browsing Flows

### Create

1. `requireLogin()` verifies the session.
2. The GET page displays the template picker and entry form.
3. JavaScript can prefill the content editor from an allow-listed built-in template.
4. On POST, PHP verifies the CSRF token and validates all fields.
5. A prepared statement inserts `user_id` from `$_SESSION['user_id']`; the form never supplies ownership.
6. The app sets a success flash message, clears the browser draft, and redirects to the new entry's detail page or Journal list.

### List, search, filter, and sort

1. The page starts every query with `WHERE user_id = ?`.
2. Optional prepared conditions search `title` and `content`, match `mood_status`, and bound `entry_date` by `date_from` and `date_to`.
3. Sort input is allow-listed and mapped to fixed SQL fragments for newest or oldest ordering.
4. Cards display title, mood, date, updated time when relevant, and an escaped content preview.
5. A distinct-mood query scoped by `user_id` populates mood suggestions and the filter control.
6. An empty state distinguishes between no saved entries and no results matching active filters.

### View

1. The ID is validated as a positive integer.
2. `journalLoadForUser()` loads with `WHERE journal_id = ? AND user_id = ?`.
3. Missing or unowned entries produce the same safe "not found" response.
4. The page renders the complete escaped content with preserved line breaks plus Edit, Delete, and Back actions.

### Edit

1. The entry is loaded by both `journal_id` and `user_id` before rendering.
2. On POST, CSRF and field validation run again.
3. The prepared update includes both identifiers in its `WHERE` clause.
4. Success redirects to the detail page with a flash message.

Template selection is not shown during edit because templates are starting structures, not persistent entry types.

### Delete

1. The GET page loads the entry by both identifiers and displays a confirmation summary.
2. The actual deletion only occurs on a CSRF-protected POST.
3. The prepared delete includes both `journal_id` and `user_id`.
4. Success redirects to the Journal list with a flash message.

## 10. Security and Privacy

- Every Journal page calls `requireLogin()`.
- Every entry query is scoped by the authenticated session user ID.
- All SQL values use MySQLi prepared statements.
- All user content is escaped with `escapeOutput()` before HTML rendering.
- Content remains plain text; line breaks are rendered safely without accepting stored HTML.
- Create, edit, and delete use CSRF tokens.
- Invalid, missing, and unowned IDs receive the same not-found behavior to avoid revealing record ownership.
- Database exception details are not displayed to users; pages show a generic recovery message.
- Draft keys include the authenticated user ID so accounts sharing one browser do not restore each other's draft through the interface.
- Drafts are cleared after successful creation, explicit discard, and logout navigation.

## 11. Draft Recovery and Client-Side Behavior

The create form renders a user-specific `data-draft-key`, such as `journalDraft:2:create`. JavaScript stores title, content, mood, entry date, and selected template after input changes.

When a saved draft exists, the page offers Restore and Discard choices rather than silently replacing server-rendered values. Draft recovery is a convenience only; all saved journal records still require normal server-side validation and insertion.

The same script provides:

- Live word and character counts.
- Auto-expanding content height.
- A confirmation before replacing non-empty content with another template.
- Cleanup of the current user's draft when the save succeeds or the user logs out.

The module must remain usable when JavaScript is unavailable; CRUD, validation, filtering, and ownership controls are server-side features.

## 12. Error Handling

- Invalid form fields produce specific messages beside or above the form.
- Invalid CSRF tokens produce a session-expired message without performing the requested change.
- Invalid IDs redirect to the list with an error flash message.
- Missing and unowned entries use the same not-found message.
- Database failures produce a generic "Journal entries are unavailable right now" message while preserving page structure.
- Empty search results provide Reset Filters and Add Entry actions.
- Destructive actions require a dedicated confirmation page rather than a GET deletion link.

## 13. Visual Design and Accessibility

The Journal module uses the project's existing colors, spacing, buttons, panels, cards, shadows, and responsive breakpoints. Journal-specific components include:

- A calm Journal heading and action bar.
- Template cards with labels and short descriptions.
- Responsive entry cards with mood pills and readable previews.
- A focused reading layout on `view.php`.
- Clearly separated primary, secondary, and destructive actions.

All form fields have visible labels. Interactive controls support keyboard navigation, focus styles, and clear accessible names. Color is not the only signal for mood, status, errors, or actions.

## 14. Verification Strategy

### Syntax and database checks

- Run PHP lint across every PHP file.
- Confirm the live `journal_entries` columns match the exported SQL schema.
- Confirm no schema migration is required for templates.

### Core workflow checks

- Create one entry from Blank and one from each structured template.
- Verify saved content can be viewed completely.
- Edit title, content, mood, and date, then confirm `updated_at` changes.
- Delete an entry through the confirmation form.
- Confirm dashboard Journal count and latest mood update after CRUD changes.

### Validation and error checks

- Submit each required field empty.
- Submit title, mood, and content beyond their limits.
- Submit an invalid date and invalid template key.
- Submit a mutation with a missing or incorrect CSRF token.
- Stop MySQL temporarily and confirm the generic database error state remains usable.

### Authorization and output-safety checks

- Create records for Student A and Student B.
- Confirm each student sees only their own list, mood suggestions, search results, and details.
- Change URL IDs and POST IDs to verify cross-user view, edit, and delete attempts fail.
- Save characters such as `<script>`, quotes, and ampersands and confirm they render as text rather than execute.

### Template, draft, and responsive checks

- Confirm every template inserts the correct prompts.
- Confirm switching templates after typing requests confirmation.
- Confirm refresh recovery, explicit discard, post-save cleanup, and user-scoped draft separation.
- Test the Journal list, detail page, and forms at desktop and mobile widths.
- Complete the full workflow in the live XAMPP application using the shared navigation.

## 15. Implementation Sequence

1. Resolve the local folder and `BASE_URL` mismatch for reliable navigation.
2. Add the module-local helper functions and their validation/query tests.
3. Build the owned-entry list and complete detail view.
4. Build create with CSRF, validation, templates, and redirect behavior.
5. Build edit and delete with ownership enforcement.
6. Add search, mood/date filters, sorting, and mood suggestions.
7. Add Journal styling and accessible responsive layouts.
8. Add template switching, counts, auto-expansion, and user-scoped draft recovery.
9. Run syntax, database, CRUD, validation, authorization, safety, responsive, and dashboard integration checks.
10. Update the project progress documentation and prepare the individual module demonstration.
