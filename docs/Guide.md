# Student Routine Organizer Run, Test, And Developer Guide

Project: `Student Routine Organizer`  
Course: `UCCD3243 Server-Side Web Applications Development`  
Current development path: `C:\xampp\htdocs\student-routine-organizer`  
Local URL: `http://localhost/student-routine-organizer/`

## 0. First-Time Setup For Teammates With A New Computer

Read this section if you have not installed anything yet. After completing this section, continue with `How To Run The Website Locally`.

### 0.1 What You Need To Install

Minimum required:

| Tool | Purpose | Required? |
|---|---|---|
| XAMPP | Provides Apache, PHP, MySQL/MariaDB, and phpMyAdmin | Yes |
| Visual Studio Code | Code editor for PHP, HTML, CSS, JS, and SQL files | Recommended |
| Git | Version control and easier team file sharing | Optional but recommended |

The assignment specifically mentions XAMPP and MySQL phpMyAdmin, so XAMPP is the most important installation.

### 0.2 Official Download Links

Use official links when possible. If the lecturer or school portal provides a special download link, follow the school link first.

| Resource | Official Link | Notes |
|---|---|---|
| XAMPP | https://www.apachefriends.org/download.html | Download the Windows version. XAMPP includes Apache, MariaDB, PHP, and phpMyAdmin. |
| Visual Studio Code | https://code.visualstudio.com/download | Download the Windows User Installer or System Installer. |
| Git for Windows | https://git-scm.com/download/win | Optional, useful if the team uses GitHub or Git for sharing code. |
| phpMyAdmin import/export guide | https://docs.phpmyadmin.net/en/latest/import_export.html | Useful when importing or exporting the `.sql` database file. |
| PHP manual | https://www.php.net/manual/en/ | Useful when checking PHP functions such as `password_hash()`, `mysqli`, and sessions. |
| MySQL documentation | https://dev.mysql.com/doc/ | Useful for SQL syntax and database concepts. |
| W3Schools PHP tutorial | https://www.w3schools.com/php/ | Beginner-friendly PHP reference. Use it for learning, not as the only source for report references. |
| MDN HTML/CSS/JS docs | https://developer.mozilla.org/ | Reliable reference for front-end syntax. |

### 0.3 Install XAMPP Step By Step

1. Open the XAMPP official download page:

```text
https://www.apachefriends.org/download.html
```

2. Download XAMPP for Windows.

Recommended version for this project:

```text
XAMPP 8.2.12 / PHP 8.2.12
```

The official XAMPP download page lists the Windows package and shows that it includes Apache, MariaDB, PHP, and phpMyAdmin.

3. Run the installer.

4. If Windows asks for permission, allow the installer to run.

5. Install XAMPP to the default path:

```text
C:\xampp
```

Use this default path unless your computer cannot install there. The guide assumes this path.

6. When component selection appears, make sure these are selected:

- Apache
- MySQL or MariaDB
- PHP
- phpMyAdmin

7. Finish the installation.

8. Open XAMPP Control Panel:

```text
C:\xampp\xampp-control.exe
```

9. Click `Start` beside Apache.

10. Click `Start` beside MySQL.

11. Confirm both services are running.

Apache and MySQL should turn green in XAMPP Control Panel.

### 0.4 Verify XAMPP Works

After starting Apache and MySQL, open these links in your browser.

Test XAMPP homepage:

```text
http://localhost/
```

Expected result:

- XAMPP welcome page opens.

Test phpMyAdmin:

```text
http://localhost/phpmyadmin/
```

Expected result:

- phpMyAdmin opens.
- You can see the database management interface.

If either page does not open, check:

- Apache is running.
- MySQL is running.
- No other application is using port `80`.
- XAMPP is installed in `C:\xampp`.

### 0.5 Install Visual Studio Code

1. Open:

```text
https://code.visualstudio.com/download
```

2. Download the Windows installer.

3. Install VS Code.

4. Open VS Code.

5. Recommended extensions:

- PHP Intelephense
- PHP Debug
- MySQL or SQLTools
- HTML CSS Support

These extensions are optional, but they make development easier.

### 0.6 Install Git For Windows

This is optional unless the team wants to use GitHub or Git to share code.

1. Open:

```text
https://git-scm.com/download/win
```

2. Download and install Git for Windows.

3. During installation, the default options are usually fine.

4. After installation, open Command Prompt or PowerShell and test:

```text
git --version
```

Expected result:

- Git version is displayed.

### 0.7 Put The Project Into XAMPP

After XAMPP is installed, the project folder must be placed inside:

```text
C:\xampp\htdocs
```

Final project path should be:

```text
C:\xampp\htdocs\student-routine-organizer
```

If your teammate receives the project as a zip:

1. Extract the zip.
2. Find the folder named `student-routine-organizer`.
3. Copy that folder into `C:\xampp\htdocs`.
4. Confirm this file exists:

```text
C:\xampp\htdocs\student-routine-organizer\index.php
```

### 0.8 Import The Database

The project database file is:

```text
C:\xampp\htdocs\student-routine-organizer\database\student_routine_organizer.sql
```

Import steps:

1. Start Apache and MySQL in XAMPP.

2. Open phpMyAdmin:

```text
http://localhost/phpmyadmin/
```

3. Click `New` on the left sidebar.

4. Create a database named:

```text
student_routine_organizer
```

5. Click the new database.

6. Click the `Import` tab.

7. Choose this SQL file:

```text
C:\xampp\htdocs\student-routine-organizer\database\student_routine_organizer.sql
```

8. Click `Import` or `Go`.

9. Confirm these tables appear:

- `users`
- `exercise_records`
- `journal_entries`
- `money_transactions`
- `habit_records`

The phpMyAdmin documentation also explains that database/table import is done from the `Import` tab.

### 0.9 Final Setup Test

Open:

```text
http://localhost/student-routine-organizer/
```

Expected result:

- Student Routine Organizer home page opens.

Then test login with:

```text
Email: student@example.com
Password: password123
```

If this works, the teammate's environment is ready for development.

### 0.10 Common First-Time Setup Problems

| Problem | Likely Cause | Fix |
|---|---|---|
| `http://localhost/` does not open | Apache is not running | Start Apache in XAMPP |
| phpMyAdmin does not open | Apache or MySQL is not running | Start both Apache and MySQL |
| Website shows database error | Database not imported | Import `student_routine_organizer.sql` |
| Login does not work | Database missing sample users | Re-import the SQL file |
| Project URL gives 404 | Project folder is in wrong location | Put folder in `C:\xampp\htdocs` |
| CSS not loading | Wrong project folder name | Folder must be `student-routine-organizer` |

## 1. What Is The Central Page

There are three important "central" pages depending on user state.

| Situation | Central Page | URL |
|---|---|---|
| Visitor not logged in | Home page | `http://localhost/student-routine-organizer/` |
| Student logged in | Student dashboard | `http://localhost/student-routine-organizer/dashboard.php` |
| Admin logged in | Admin dashboard | `http://localhost/student-routine-organizer/admin/dashboard.php` |

The most important central page for normal users is:

```text
http://localhost/student-routine-organizer/dashboard.php
```

This student dashboard links to all four modules:

- Exercise Tracker
- Diary Journal
- Money Tracker
- Habit Tracker

The admin central page is:

```text
http://localhost/student-routine-organizer/admin/dashboard.php
```

This admin dashboard links to:

- Registered Users
- System Summaries

## 2. How To Run The Website Locally

### Step 1: Open XAMPP

Open:

```text
C:\xampp\xampp-control.exe
```

### Step 2: Start Apache And MySQL

In XAMPP Control Panel:

1. Click `Start` beside Apache.
2. Click `Start` beside MySQL.
3. Make sure both turn green/running.

### Step 3: Open The Website

Open your browser and go to:

```text
http://localhost/student-routine-organizer/
```

You should see the Student Routine Organizer home page.

### Step 4: Open phpMyAdmin

Open:

```text
http://localhost/phpmyadmin/
```

You should see the database manager.

The project database is:

```text
student_routine_organizer
```

## 3. Test Accounts

Use these accounts to test the system.

### Student Account

```text
Email: student@example.com
Password: password123
```

Expected result:

- Redirects to student dashboard.
- Can access module pages.
- Cannot access admin pages.

### Admin Account

```text
Email: admin@example.com
Password: admin123
```

Expected result:

- Redirects to admin dashboard.
- Can view registered users.
- Can view system summaries.

## 4. How To Test The Website Manually

### Test 1: Home Page

1. Open `http://localhost/student-routine-organizer/`.
2. Confirm the page loads.
3. Click `Login`.
4. Click `Register`.

Expected result:

- Home page loads.
- Login and Register links work.

### Test 2: Student Login

1. Open `http://localhost/student-routine-organizer/login.php`.
2. Login with:

```text
student@example.com
password123
```

Expected result:

- You are redirected to `dashboard.php`.
- You see `Welcome, Sample Student`.
- You see summary cards for Exercise, Journal, Money, and Habits.

### Test 3: Student Access Control

While logged in as student, open:

```text
http://localhost/student-routine-organizer/admin/dashboard.php
```

Expected result:

- Student is redirected away from admin page.
- Student should not be able to use admin functions.

### Test 4: Module Page Access

While logged in as student, open each module:

```text
http://localhost/student-routine-organizer/modules/exercise/index.php
http://localhost/student-routine-organizer/modules/journal/index.php
http://localhost/student-routine-organizer/modules/money/index.php
http://localhost/student-routine-organizer/modules/habits/index.php
```

Expected result:

- Each page loads.
- Each page says its CRUD logic will be implemented in later phases.

### Test 5: Logout

1. Click `Logout`.
2. Try opening `http://localhost/student-routine-organizer/dashboard.php`.

Expected result:

- You are redirected to login.
- Protected pages are not accessible after logout.

### Test 6: Admin Login

1. Open `http://localhost/student-routine-organizer/login.php`.
2. Login with:

```text
admin@example.com
admin123
```

Expected result:

- You are redirected to `admin/dashboard.php`.
- You see system-wide summary cards.
- You can open `Registered Users`.
- You can open `System Summaries`.

### Test 7: Registration

1. Open `http://localhost/student-routine-organizer/register.php`.
2. Register a new student account using a new email.
3. Login with the new account.

Expected result:

- Registration succeeds.
- New user is saved as role `student`.
- New user can login.

## 5. Project Folder Structure

Main folder:

```text
C:\xampp\htdocs\student-routine-organizer
```

Structure:

```text
student-routine-organizer/
  index.php
  login.php
  register.php
  logout.php
  dashboard.php
  README.txt
  admin/
    dashboard.php
    users.php
    summaries.php
  assets/
    css/
      style.css
    js/
      app.js
  config/
    app.php
    database.php
  database/
    schema_draft.sql
    student_routine_organizer.sql
  includes/
    auth.php
    flash.php
    footer.php
    header.php
    navbar.php
    validation.php
  modules/
    exercise/
      index.php
      create.php
      edit.php
      delete.php
    journal/
      index.php
      create.php
      edit.php
      delete.php
    money/
      index.php
      create.php
      edit.php
      delete.php
    habits/
      index.php
      create.php
      edit.php
      delete.php
```

## 6. Important Files Explained

### `config/app.php`

Stores general app constants:

- `APP_NAME`
- `BASE_URL`

Use this when building links.

Example:

```php
<?= BASE_URL; ?>/dashboard.php
```

### `config/database.php`

Stores database connection details.

Database name:

```text
student_routine_organizer
```

Function:

```php
getDatabaseConnection()
```

Use this function whenever you need to query MySQL.

### `includes/auth.php`

Handles:

- Starting sessions.
- Checking whether user is logged in.
- Getting current user role.
- Protecting student pages.
- Protecting admin pages.
- Redirecting users after login.

Important functions:

```php
requireLogin();
requireAdmin();
isLoggedIn();
currentUserRole();
currentUserName();
redirectAfterLogin($role);
```

### `includes/header.php`

Shared top part of every page.

It loads:

- App config.
- Auth helper.
- Flash messages.
- Validation helper.
- CSS file.
- Navbar.

### `includes/footer.php`

Shared bottom part of every page.

It loads:

- Footer text.
- JavaScript file.

### `includes/navbar.php`

Shared navigation bar.

Behavior:

- If user is logged out, show Login/Register.
- If user is logged in, show Dashboard and module links.
- If user is admin, show Admin link.

### `includes/flash.php`

Handles success/error messages.

Example use:

```php
setFlash('success', 'Record added successfully.');
```

### `includes/validation.php`

Contains small helper functions for cleaning and escaping values.

Important:

```php
escapeOutput($value)
```

Use this whenever displaying user input.

## 7. Database Tables

Database:

```text
student_routine_organizer
```

Tables:

| Table | Purpose |
|---|---|
| `users` | Stores registered student/admin accounts |
| `exercise_records` | Stores exercise tracker records |
| `journal_entries` | Stores diary journal records |
| `money_transactions` | Stores income and expense records |
| `habit_records` | Stores habit tracker records |

Important relationship:

```text
users.user_id -> module_table.user_id
```

Every module record must belong to a user.

For student pages, always query using:

```sql
WHERE user_id = ?
```

This prevents students from seeing or editing other students' records.

## 8. How To Develop A Module

Each module should follow the same pattern.

Example module folder:

```text
modules/exercise/
  index.php
  create.php
  edit.php
  delete.php
```

### `index.php`

Purpose:

- Show records.
- Show summary.
- Provide Add/Edit/Delete links.
- Query only current user's records.

Must include:

```php
requireLogin();
```

### `create.php`

Purpose:

- Show add form.
- Validate submitted data.
- Insert record into database.
- Save `user_id` from session.

Must include:

```php
$_SESSION['user_id']
```

when inserting the record.

### `edit.php`

Purpose:

- Load existing record.
- Make sure it belongs to current user.
- Validate submitted data.
- Update the record.

Important query pattern:

```sql
SELECT * FROM module_table
WHERE record_id = ? AND user_id = ?
```

### `delete.php`

Purpose:

- Confirm record belongs to current user.
- Delete the selected record.
- Redirect back to module list.

Important query pattern:

```sql
DELETE FROM module_table
WHERE record_id = ? AND user_id = ?
```
