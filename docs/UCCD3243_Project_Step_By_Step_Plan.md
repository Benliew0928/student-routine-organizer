# UCCD3243 Student Routine Organizer Step-By-Step Project Plan

Use this file as the working plan for the assignment. Update the tracking tables as the group completes each task.

Source guide: `UCCD3243_Assignment_High_Marks_Guide.md`  
Assignment: `UCCD3243 Server-Side Web Applications Development`  
System: `Student Routine Organizer`  
Deadline: `24 August 2026, Monday, 5:00 PM`

## How To Use This Plan

Work through the phases in order. Do not jump to optional features before the required system is stable.

Recommended status labels:

- `Not Started`
- `In Progress`
- `Blocked`
- `Needs Review`
- `Done`

Update the tracking tables at the end of every work session.

## Progress Tracking Dashboard

### Overall Phase Tracker

| Phase | Goal | Owner | Status | Target Date | Notes |
|---|---|---|---|---|---|
| Phase 1 | Confirm scope, group roles, tools | Solo/User | Done | 2026-06-18 | Tooling completed: XAMPP installed at `C:\xampp`, Apache/MySQL/phpMyAdmin verified; team communication handled separately by user |
| Phase 2 | Set up project folder and database plan | Solo/User | Done | 2026-06-18 | Project skeleton created at `C:\xampp\htdocs\student-routine-organizer`; schema draft added; localhost verified |
| Phase 3 | Build shared database and authentication | Solo/User | Done | 2026-06-18 | Database imported; registration, login, logout, sessions, cookie usage, student/admin redirects, and protected pages verified |
| Phase 4 | Build student and admin dashboards | Solo/User | Done | 2026-06-19 | Student summaries, admin totals, registered users page, and system summaries page implemented and tested |
| Phase 5 | Build Exercise Tracker | Member 1 | Not Started |  |  |
| Phase 6 | Build Diary Journal | Member 2 | Not Started |  |  |
| Phase 7 | Build Money Tracker | Member 3 | Not Started |  |  |
| Phase 8 | Build Habit Tracker | Member 4 | Done | 2026-06-23 | Enhanced CRUD, filters, sorting, quick status updates, progress summaries, best streak, CSV export, CSRF protection, and sample data added |
| Phase 9 | Integrate all modules | Group | Not Started |  |  |
| Phase 10 | Add validation, security, and error handling | Group | Not Started |  |  |
| Phase 11 | Add optional high-mark features | Group | Not Started |  |  |
| Phase 12 | Full testing and debugging | Group | Not Started |  |  |
| Phase 13 | Prepare final source code and database export | Group | Not Started |  |  |
| Phase 14 | Write final report | Group | Not Started |  |  |
| Phase 15 | Final submission check | Group Leader | Not Started |  |  |

### Module Ownership Tracker

| Member | Module | Main Pages | Status | Current Issue | Review By |
|---|---|---|---|---|---|
| Member 1 | Exercise Tracker | Add, View, Edit, Delete | Not Started |  |  |
| Member 2 | Diary Journal | Add, View, Edit, Delete | Not Started |  |  |
| Member 3 | Money Tracker | Add, View, Edit, Delete | Not Started |  |  |
| Member 4 | Habit Tracker | Add, View, Edit, Delete | Done | Ready for group review and browser/database testing |  |

### Current Work Session Tracker

Use this small table whenever you start working.

| Date | Person Working | Task Planned | Task Completed | Next Action |
|---|---|---|---|---|
| 2026-06-18 | Codex | Start Phase 1 and verify local tools | Checked assignment files and searched common XAMPP/PHP/MySQL locations | Collect group member details and resolve XAMPP installation |
| 2026-06-18 | Codex | Install required local development tools | Installed XAMPP 8.2.12 at `C:\xampp`; verified Apache, MySQL, PHP, phpMyAdmin, and `mysqli` | User can complete member/leader communication, then move to Phase 2 |
| 2026-06-18 | Codex | Complete Phase 2 project skeleton | Created project folder, starter PHP files, shared includes, module placeholders, CSS/JS assets, and schema draft; verified 32 PHP files and localhost pages | Move to Phase 3: create database and build authentication |
| 2026-06-18 | Codex | Complete Phase 3 database and authentication | Imported MySQL database, added seed admin/student accounts, implemented registration/login/logout, protected pages, and verified auth redirects | Move to Phase 4: connect student/admin dashboard summaries |
| 2026-06-19 | Codex | Complete Phase 4 dashboards and comprehensive check | Implemented student/admin summaries, users table, system summaries, table/card styling; verified syntax, auth, validation, redirects, and dashboard calculations | Move to Phase 5: build Exercise Tracker CRUD |
| 2026-06-23 | Codex | Complete Member 4 Habit Tracker | Built enhanced habit CRUD, validation, ownership checks, filters, sorting, summaries, streaks, quick status updates, CSV export, CSRF protection, migration SQL, and sample data; PHP syntax check passed | Start XAMPP Apache/MySQL, import `database/habit_tracker_migration.sql` if needed, then capture report screenshots |
|  |  |  |  |  |
|  |  |  |  |  |

### Blocker Tracker

| Blocker ID | Problem | Affected Phase/Module | Owner | Status | Solution |
|---|---|---|---|---|---|
| B01 | XAMPP/PHP/MySQL were not found at common local paths or on PATH | Phase 1 tools check | Codex | Done | Resolved by installing XAMPP 8.2.12 at `C:\xampp`; Apache, MySQL, localhost, phpMyAdmin, and PHP `mysqli` verified |
| B02 |  |  |  | Not Started |  |
| B03 |  |  |  | Not Started |  |

## Phase 1 - Confirm Scope, Group Roles, And Tools

Goal: Make sure the group understands the exact assignment requirements before coding.

### Tasks

- [ ] Read the assignment PDF once as a group.
- [ ] Read `UCCD3243_Assignment_High_Marks_Guide.md`.
- [ ] Confirm exactly 4 group members.
- [ ] Assign one module to each member:
  - [ ] Exercise Tracker
  - [ ] Diary Journal
  - [ ] Money Tracker
  - [ ] Habit Tracker
- [ ] Select one group leader.
- [ ] Confirm every member has XAMPP installed.
- [ ] Confirm every member can open phpMyAdmin.
- [ ] Decide the project folder name, for example `student-routine-organizer`.
- [ ] Decide the database name, for example `student_routine_organizer`.
- [ ] Create a shared folder for project files.
- [ ] Decide how files will be merged, for example GitHub, OneDrive, Google Drive, or manual merge by group leader.

### Done Criteria

- All members know their module.
- Group leader is confirmed.
- Tools are installed.
- Project and database names are fixed.
- No one starts random coding with different folder or table names.

## Phase 2 - Set Up Project Folder And Database Plan

Goal: Create the project skeleton before building pages.

### Tasks

- [x] Create the main project folder.
- [x] Create this folder structure:

```text
student-routine-organizer/
  index.php
  login.php
  register.php
  logout.php
  dashboard.php
  admin/
  config/
  includes/
  assets/
    css/
    js/
  modules/
    exercise/
    journal/
    money/
    habits/
  database/
```

- [x] Create `config/database.php`.
- [x] Create `includes/header.php`.
- [x] Create `includes/footer.php`.
- [x] Create `includes/navbar.php`.
- [x] Create `includes/auth.php`.
- [x] Create `includes/flash.php`.
- [x] Create `assets/css/style.css`.
- [x] Create empty module pages for each module:
  - [x] `modules/exercise/index.php`
  - [x] `modules/exercise/create.php`
  - [x] `modules/exercise/edit.php`
  - [x] `modules/exercise/delete.php`
  - [x] `modules/journal/index.php`
  - [x] `modules/journal/create.php`
  - [x] `modules/journal/edit.php`
  - [x] `modules/journal/delete.php`
  - [x] `modules/money/index.php`
  - [x] `modules/money/create.php`
  - [x] `modules/money/edit.php`
  - [x] `modules/money/delete.php`
  - [x] `modules/habits/index.php`
  - [x] `modules/habits/create.php`
  - [x] `modules/habits/edit.php`
  - [x] `modules/habits/delete.php`
- [x] Draft the database schema before creating tables.
- [x] Confirm table names and column names under solo-developer assumption.

### Done Criteria

- Project opens in browser using XAMPP.
- Folder structure is clear.
- All members agree on the database schema.
- Every member knows which table their module uses.

## Phase 3 - Build Shared Database And Authentication

Goal: Build the shared foundation used by every module.

Do this before module coding. If authentication is weak or inconsistent, integration marks will suffer.

### Database Tasks

- [x] Create database in phpMyAdmin/MySQL.
- [x] Create `users` table.
- [x] Create `exercise_records` table.
- [x] Create `journal_entries` table.
- [x] Create `money_transactions` table.
- [x] Create `habit_records` table.
- [x] Add foreign keys from each module table to `users.user_id`.
- [x] Insert one admin user.
- [x] Insert at least one student test user.
- [x] Save the SQL script in `database/student_routine_organizer.sql`.

### PHP Connection Tasks

- [x] Write database connection code in `config/database.php`.
- [x] Test successful connection.
- [x] Handle connection failure with a clear message.

### Registration Tasks

- [x] Build `register.php` form.
- [x] Validate full name.
- [x] Validate email.
- [x] Validate password.
- [x] Prevent duplicate email.
- [x] Hash password using `password_hash()`.
- [x] Save new user as role `student`.
- [x] Redirect to login after successful registration.
- [x] Show clear validation errors.

### Login Tasks

- [x] Build `login.php` form.
- [x] Validate email and password fields.
- [x] Find user by email.
- [x] Verify password using `password_verify()`.
- [x] Store `user_id`, `full_name`, and `role` in session.
- [x] Redirect student to `dashboard.php`.
- [x] Redirect admin to `admin/dashboard.php`.
- [x] Show error for invalid login.

### Session And Cookie Tasks

- [x] Start session on protected pages.
- [x] Create `requireLogin()` in `includes/auth.php`.
- [x] Create `requireAdmin()` in `includes/auth.php`.
- [x] Create `logout.php` to destroy session.
- [x] Add safe cookie usage, such as remembered email or theme preference.
- [x] Do not store password or role in cookies.

### Done Criteria

- New student can register.
- Student can log in and log out.
- Admin can log in and reach admin dashboard.
- Protected pages cannot be accessed without login.
- Cookie usage exists and is safe.

## Phase 4 - Build Shared Layout, Navigation, And Dashboards

Goal: Make the system feel like one integrated application.

### Shared Layout Tasks

- [x] Build common page header.
- [x] Build common footer.
- [x] Build common navigation bar.
- [x] Link dashboard, all modules, and logout.
- [x] Add CSS for forms, tables, buttons, alerts, and dashboard cards.
- [x] Make page titles consistent.
- [x] Add success and error message styling.

### Student Dashboard Tasks

- [x] Show welcome message with student name.
- [x] Add navigation cards or links to all four modules.
- [x] Show Exercise summary.
- [x] Show Journal summary.
- [x] Show Money summary.
- [x] Show Habit summary.
- [x] Ensure summaries only use logged-in user's records.

### Admin Dashboard Tasks

- [x] Create `admin/dashboard.php`.
- [x] Create `admin/users.php`.
- [x] Show total registered users.
- [x] Show total exercise records.
- [x] Show total journal entries.
- [x] Show total money transactions.
- [x] Show total habit records.
- [x] Show list of registered users.
- [x] Protect admin pages using `requireAdmin()`.

### Done Criteria

- UI is consistent across pages.
- Student and admin see different dashboard functions.
- Navigation does not have broken links.
- Dashboard summaries work.

## Phase 5 - Build Exercise Tracker Module

Owner: Member 1

Goal: Complete full CRUD for exercise records.

### Required Fields

- Activity type
- Duration in minutes
- Calories burned
- Exercise date
- Optional notes

### Build Steps

- [ ] Create Exercise list page.
- [ ] Query only current user's exercise records.
- [ ] Display records in a table.
- [ ] Add empty-state message when no records exist.
- [ ] Create Add Exercise form.
- [ ] Validate activity type is not empty.
- [ ] Validate duration is greater than 0.
- [ ] Validate calories burned is 0 or greater.
- [ ] Validate date is not empty.
- [ ] Insert record with current `user_id`.
- [ ] Create Edit Exercise form.
- [ ] Load record using both `exercise_id` and `user_id`.
- [ ] Update record after validation.
- [ ] Create Delete Exercise function.
- [ ] Confirm record belongs to current user before deleting.
- [ ] Show success/error messages.
- [ ] Add optional filter or sort by activity/date.
- [ ] Add summary such as total duration or total calories.

### Done Criteria

- Add, view, edit, and delete all work.
- Records are linked to logged-in user.
- One student cannot edit another student's exercise record.
- Validation and messages are clear.
- Module looks consistent with the rest of the system.

## Phase 6 - Build Diary Journal Module

Owner: Member 2

Goal: Complete full CRUD for journal entries.

### Required Fields

- Title
- Content
- Mood status
- Entry date

### Build Steps

- [ ] Create Journal list page.
- [ ] Query only current user's journal entries.
- [ ] Display title, mood, date, and preview.
- [ ] Add empty-state message when no entries exist.
- [ ] Create Add Journal form.
- [ ] Validate title is not empty.
- [ ] Validate content is not empty.
- [ ] Validate mood status is selected.
- [ ] Validate date is not empty.
- [ ] Insert record with current `user_id`.
- [ ] Create Edit Journal form.
- [ ] Load record using both `journal_id` and `user_id`.
- [ ] Update record after validation.
- [ ] Create Delete Journal function.
- [ ] Confirm record belongs to current user before deleting.
- [ ] Show success/error messages.
- [ ] Add optional search by title/content.
- [ ] Add optional filter by mood.

### Done Criteria

- Add, view, edit, and delete all work.
- Journal entries are private to the logged-in student.
- Validation and messages are clear.
- Mood tracking is visible.
- Module looks consistent with the rest of the system.

## Phase 7 - Build Money Tracker Module

Owner: Member 3

Goal: Complete full CRUD for income and expense records.

### Required Fields

- Amount
- Category
- Description
- Transaction type
- Transaction date

### Build Steps

- [ ] Create Money list page.
- [ ] Query only current user's transactions.
- [ ] Display amount, category, description, type, and date.
- [ ] Add empty-state message when no transactions exist.
- [ ] Create Add Transaction form.
- [ ] Validate amount is greater than 0.
- [ ] Validate category is not empty.
- [ ] Validate transaction type is `income` or `expense`.
- [ ] Validate date is not empty.
- [ ] Insert record with current `user_id`.
- [ ] Create Edit Transaction form.
- [ ] Load record using both `transaction_id` and `user_id`.
- [ ] Update record after validation.
- [ ] Create Delete Transaction function.
- [ ] Confirm record belongs to current user before deleting.
- [ ] Show success/error messages.
- [ ] Calculate total income.
- [ ] Calculate total expenses.
- [ ] Calculate balance.
- [ ] Add optional filter by type/category/date.

### Done Criteria

- Add, view, edit, and delete all work.
- Income and expense logic is correct.
- Balance calculation is correct.
- Records are linked to logged-in user.
- Module looks consistent with the rest of the system.

## Phase 8 - Build Habit Tracker Module

Owner: Member 4

Goal: Complete full CRUD for habit records.

### Required Fields

- Habit name
- Target frequency
- Completion status
- Habit date

### Build Steps

- [x] Create Habit list page.
- [x] Query only current user's habits.
- [x] Display habit name, frequency, status, and date.
- [x] Add empty-state message when no habits exist.
- [x] Create Add Habit form.
- [x] Validate habit name is not empty.
- [x] Validate target frequency is not empty.
- [x] Validate completion status is valid.
- [x] Validate date is not empty.
- [x] Insert record with current `user_id`.
- [x] Create Edit Habit form.
- [x] Load record using both `habit_id` and `user_id`.
- [x] Update record after validation.
- [x] Create Delete Habit function.
- [x] Confirm record belongs to current user before deleting.
- [x] Show success/error messages.
- [x] Calculate completion count or completion percentage.
- [x] Add optional filter by completed/pending/missed.

### Done Criteria

- Add, view, edit, and delete all work.
- Completion status works.
- Progress summary works.
- Records are linked to logged-in user.
- Module looks consistent with the rest of the system.

## Phase 9 - Integrate All Modules

Goal: Make the four separate modules behave like one complete system.

### Tasks

- [ ] Merge all module folders into the main project.
- [ ] Confirm all modules use the same `config/database.php`.
- [ ] Confirm all modules use the same `includes/auth.php`.
- [ ] Confirm all modules use the same header, navbar, footer, and CSS.
- [ ] Confirm all modules use session `user_id`.
- [ ] Confirm all module links from dashboard work.
- [ ] Confirm all module links from navbar work.
- [ ] Confirm all edit/delete links use correct record IDs.
- [ ] Standardize success and error messages.
- [ ] Standardize button names:
  - [ ] Add
  - [ ] Edit
  - [ ] Delete
  - [ ] Save
  - [ ] Cancel
- [ ] Standardize date format display.
- [ ] Standardize table layout.
- [ ] Update dashboard summaries using final module table names.

### Done Criteria

- The system feels like one application, not four disconnected mini projects.
- A student can move between modules without confusion.
- Every module uses shared login and shared database.
- Dashboard and navigation are complete.

## Phase 10 - Add Validation, Security, And Error Handling

Goal: Strengthen the system before adding optional features.

### Security Tasks

- [ ] Use prepared statements for all SQL queries.
- [ ] Escape displayed user input using `htmlspecialchars()`.
- [ ] Verify record ownership before view/edit/delete.
- [ ] Protect all module pages with `requireLogin()`.
- [ ] Protect all admin pages with `requireAdmin()`.
- [ ] Prevent direct delete without ownership check.
- [ ] Keep database password only in `config/database.php`.
- [ ] Do not show raw SQL errors to users.

### Validation Tasks

- [ ] Validate every required field.
- [ ] Validate numeric fields.
- [ ] Validate date fields.
- [ ] Validate dropdown values.
- [ ] Validate record IDs from URL.
- [ ] Show clear error messages.
- [ ] Preserve form input after validation failure where possible.

### Error Handling Tasks

- [ ] Add message for empty record lists.
- [ ] Add message for record not found.
- [ ] Add message for unauthorized access.
- [ ] Add success messages after insert/update/delete.
- [ ] Add failure messages if database action fails.
- [ ] Test invalid input in every module.

### Done Criteria

- No obvious security holes for student data.
- Invalid input does not break pages.
- Users receive helpful feedback.
- No raw PHP warnings are visible during normal use.

## Phase 11 - Add Optional High-Mark Features

Goal: Add useful extras only after the required system is stable.

Choose features that are easy to explain and unlikely to break the system.

### Recommended Optional Features

- [ ] Search journal by title/content.
- [ ] Filter money records by income/expense.
- [ ] Filter exercise by activity type.
- [ ] Filter habits by completion status.
- [ ] Sort records by date.
- [ ] Show dashboard summaries.
- [ ] Show admin system summaries.
- [ ] Add simple pagination if many records exist.
- [ ] Add theme preference using cookie.
- [ ] Add CSV export for one or more modules.

### Do Not Add If Short On Time

- Complex charts that may break.
- Complicated calendar view.
- File uploads.
- Email verification.
- Password reset.
- Multi-language support.

### Done Criteria

- Optional features improve browsing or summaries.
- Optional features are tested.
- Optional features are mentioned in the report only if they actually work.

## Phase 12 - Full Testing And Debugging

Goal: Prove that the system works before writing the final report.

### Test Setup

- [ ] Create one admin account.
- [ ] Create at least two student accounts.
- [ ] Add sample records for each student.
- [ ] Add at least three records in every module.

### Core Test Checklist

- [ ] Register new student.
- [ ] Login with correct student account.
- [ ] Login with wrong password.
- [ ] Logout.
- [ ] Try accessing dashboard without login.
- [ ] Try accessing admin page as student.
- [ ] Login as admin.
- [ ] View admin dashboard.
- [ ] View user list.

### Module Test Checklist

- [ ] Exercise add works.
- [ ] Exercise edit works.
- [ ] Exercise delete works.
- [ ] Exercise validation works.
- [ ] Journal add works.
- [ ] Journal edit works.
- [ ] Journal delete works.
- [ ] Journal validation works.
- [ ] Money add works.
- [ ] Money edit works.
- [ ] Money delete works.
- [ ] Money validation works.
- [ ] Money balance calculation works.
- [ ] Habit add works.
- [ ] Habit edit works.
- [ ] Habit delete works.
- [ ] Habit validation works.
- [ ] Habit completion summary works.

### Authorization Test Checklist

- [ ] Student A cannot see Student B exercise records.
- [ ] Student A cannot see Student B journal entries.
- [ ] Student A cannot see Student B money records.
- [ ] Student A cannot see Student B habit records.
- [ ] Student A cannot edit Student B records by changing URL ID.
- [ ] Student A cannot delete Student B records by changing URL ID.

### Testing Table For Report

Fill this during testing so report writing is faster later.

| Test ID | Feature | Test Data | Expected Result | Actual Result | Status | Screenshot Name |
|---|---|---|---|---|---|---|
| T01 | Login | Valid student account | Redirect to dashboard |  | Not Started |  |
| T02 | Login | Wrong password | Show error |  | Not Started |  |
| T03 | Register | Duplicate email | Show duplicate error |  | Not Started |  |
| T04 | Exercise Add | Valid workout | Record saved |  | Not Started |  |
| T05 | Journal Add | Empty content | Show validation error |  | Not Started |  |
| T06 | Money Add | Negative amount | Show validation error |  | Not Started |  |
| T07 | Habit Edit | Completed status | Status updated |  | Not Started |  |
| T08 | Authorization | Other user's record ID | Access denied/not found |  | Not Started |  |
| T09 | Admin | View users page | Users displayed |  | Not Started |  |
| T10 | Dashboard | Summary cards | Correct totals shown |  | Not Started |  |

### Done Criteria

- All required features are tested.
- Major bugs are fixed.
- Testing table is ready to reuse in the report.
- Screenshots are saved for important features.

## Phase 13 - Prepare Final Source Code And Database Export

Goal: Make the project ready for submission and fresh installation.

### Clean Source Code

- [ ] Remove unused test files.
- [ ] Remove duplicate old files.
- [ ] Remove temporary debug output.
- [ ] Make sure file names are clear.
- [ ] Make sure folder structure is clean.
- [ ] Make sure all links use correct paths.
- [ ] Make sure no page depends on a local-only absolute path.

### Database Export

- [ ] Open phpMyAdmin.
- [ ] Export the latest database as `.sql`.
- [ ] Save it as `database/student_routine_organizer.sql`.
- [ ] Import the `.sql` into a fresh database to confirm it works.
- [ ] Confirm admin and sample student accounts exist.

### README

- [ ] Create `README.txt`.
- [ ] Include setup instructions.
- [ ] Include database import steps.
- [ ] Include sample admin login.
- [ ] Include sample student login.
- [ ] Include project URL, for example `http://localhost/student-routine-organizer/`.

### Done Criteria

- The project can be installed from the zip on another XAMPP machine.
- The database import works.
- Sample accounts work.
- README is clear.

## Phase 14 - Write The Final Report

Goal: Write the report after the system is mostly complete, so diagrams and explanations match the real application.

Do not write the final report too early. If you write before coding finishes, the report may not match the final system.

### Report Preparation Tasks

- [ ] Collect group member names, IDs, programme, and practical group.
- [ ] Confirm each member's module.
- [ ] Capture screenshots of:
  - [ ] Login page
  - [ ] Student dashboard
  - [ ] Admin dashboard
  - [ ] Exercise module
  - [ ] Journal module
  - [ ] Money module
  - [ ] Habit module
  - [ ] Example validation error
  - [ ] Example successful insert/update/delete
- [ ] Export or redraw database ERD.
- [ ] Draw site hierarchy diagram.
- [ ] Draw system flowchart for Exercise module.
- [ ] Draw system flowchart for Journal module.
- [ ] Draw system flowchart for Money module.
- [ ] Draw system flowchart for Habit module.
- [ ] Prepare testing table from Phase 12.

### Recommended Report Order

Follow this order:

1. Cover Page
2. Marking Scheme
3. Introduction
4. Site Hierarchy and Navigation
5. System Flowcharts
6. Overview of Database Structure
7. Functional Requirements
8. Testing and Debugging
9. Conclusion
10. References
11. Appendix, if needed

### Cover Page Checklist

- [ ] Unit code and title.
- [ ] Course.
- [ ] Assignment title.
- [ ] Practical group.
- [ ] Student IDs.
- [ ] Student names.
- [ ] Programme.
- [ ] Module responsibility for each member.
- [ ] Marking scheme at the front.

### Site Hierarchy Section

- [ ] Include full sitemap.
- [ ] Show login and register.
- [ ] Show student dashboard.
- [ ] Show all four modules.
- [ ] Show CRUD pages.
- [ ] Show admin dashboard.
- [ ] Show user list and summaries.
- [ ] Explain how users navigate through the system.

### System Flowcharts Section

- [ ] Include flowchart for each individual module.
- [ ] Use correct symbols.
- [ ] Explain start, input, validation, database process, output, and end.
- [ ] Make sure each flowchart matches the actual module.

### Database Structure Section

- [ ] Include ERD or schema diagram.
- [ ] Show all tables.
- [ ] Show primary keys.
- [ ] Show foreign keys.
- [ ] Explain `users.user_id` relationship to all module tables.
- [ ] Explain how student-specific data is retrieved.

### Functional Requirements Section

- [ ] List overall system requirements.
- [ ] List Exercise module requirements.
- [ ] List Journal module requirements.
- [ ] List Money module requirements.
- [ ] List Habit module requirements.
- [ ] Explain how each feature supports the module purpose.

### Testing And Debugging Section

- [ ] Include testing table.
- [ ] Include failed tests that were fixed, if any.
- [ ] Include validation tests.
- [ ] Include role/access control tests.
- [ ] Include screenshots where useful.

### References

- [ ] Use IEEE referencing style.
- [ ] Reference any external code, templates, tutorials, documentation, or libraries used.
- [ ] Do not leave unreferenced copied content.

### Report Final Checks

- [ ] Report does not exceed 30 pages excluding cover page and references.
- [ ] Diagrams are readable.
- [ ] Screenshots are clear.
- [ ] Page numbering is consistent.
- [ ] Formatting is consistent.
- [ ] No section contradicts the final application.
- [ ] All member names and IDs are correct.

### Done Criteria

- Report matches the actual final system.
- Required sections are in the required order.
- Marking scheme is at the front.
- IEEE references are included.
- The report is ready to export as PDF.

## Phase 15 - Final Submission Check

Goal: Submit a complete package before the deadline.

### Final Folder Structure

Prepare the zip like this:

```text
StudentRoutineOrganizer_GroupXX.zip
  report/
    UCCD3243_GroupXX_Report.pdf
  source_code/
    student-routine-organizer/
      index.php
      login.php
      register.php
      logout.php
      dashboard.php
      admin/
      config/
      includes/
      assets/
      modules/
      database/
  database/
    student_routine_organizer.sql
  README.txt
```

### Final Checks

- [ ] Open the zip and confirm files are inside.
- [ ] Confirm report PDF opens correctly.
- [ ] Confirm source code folder is included.
- [ ] Confirm database `.sql` file is included.
- [ ] Confirm README is included.
- [ ] Upload zip to Google Drive.
- [ ] Set Google Drive link to publicly accessible/downloadable.
- [ ] Open the public link in an incognito/private browser to verify access.
- [ ] Group leader submits the Google Form.
- [ ] Save proof of submission.

### Done Criteria

- One complete submission is made by the group leader.
- Google Drive link works.
- Submission is completed before `24 August 2026, Monday, 5:00 PM`.

## Quick Daily Workflow

Use this routine to avoid wasting time:

1. Open this plan.
2. Check the Progress Tracking Dashboard.
3. Pick the next unchecked task from the current phase.
4. Work only on that task.
5. Test immediately after coding.
6. Update the tracker.
7. Record any blocker.
8. Commit/share the updated files.
9. Stop only after writing the next action.

## Priority Rules

When time is limited, follow this priority:

1. Authentication and database must work.
2. All four modules must have CRUD.
3. Records must be linked to users.
4. Admin role and summaries must work.
5. Validation and error handling must work.
6. Integration and navigation must be clean.
7. Testing evidence must be collected.
8. Report must match the actual system.
9. Optional features come last.

## Final Completion Tracker

Only mark the assignment as ready when every item below is done.

| Item | Status | Notes |
|---|---|---|
| Four members confirmed | Not Started |  |
| Four modules assigned | Not Started |  |
| Database completed | Not Started |  |
| Authentication completed | Not Started |  |
| Student dashboard completed | Not Started |  |
| Admin dashboard completed | Not Started |  |
| Exercise module completed | Not Started |  |
| Journal module completed | Not Started |  |
| Money module completed | Not Started |  |
| Habit module completed | Not Started |  |
| Module integration completed | Not Started |  |
| Validation completed | Not Started |  |
| Error handling completed | Not Started |  |
| Testing completed | Not Started |  |
| SQL export completed | Not Started |  |
| README completed | Not Started |  |
| Report completed | Not Started |  |
| Zip package completed | Not Started |  |
| Google Drive link tested | Not Started |  |
| Google Form submitted | Not Started |  |
