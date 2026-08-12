Student Routine Organizer

Current modules:
- Exercise Tracker, Diary Journal, Money Tracker, and Habit Tracker are implemented.

Local Requirements:
- XAMPP
- Apache running
- MySQL running

Local URL:
http://localhost/<checkout-folder>/

Examples:
- http://localhost/student-routine-organizer/
- http://localhost/Server-Side/

The application detects the checkout folder automatically.

Database:
student_routine_organizer

Database Import:
1. Open phpMyAdmin at http://localhost/phpmyadmin/.
2. Create or select the database named student_routine_organizer.
3. Import database/student_routine_organizer.sql.

Existing Database Upgrade:
- To retain existing local records while adding the Journal editor and Money savings-goal tables, run `C:\xampp\php\php.exe database\run_migration.php` from the project folder. This script is CLI-only and cannot be run through Apache.

Habit Tracker Database:
- The Twilight Conservatory redesign uses a fresh `habits` + `habit_logs` model.
- Re-import `database/student_routine_organizer.sql` to use the new tracker. The old single-record habit table is intentionally not migrated.

Sample Student Account:
Email: student@example.com
Password: password123

Sample Admin Account:
Email: admin@example.com
Password: admin123

Implemented So Far:
- Project skeleton and folder structure
- Database schema
- Sample admin and student users
- Registration
- Login
- Logout
- Session-based access control
- Student/admin role redirects
- Safe remembered-email cookie
- Student dashboard summaries
- Admin dashboard totals
- Admin registered users listing
- Admin system summaries
- Exercise Tracker CRUD
- Exercise summaries, filters, sorting, CSV export, and CSRF-protected forms
- Database-backed Journal drafts with autosave, cross-device resume, and safe publication
- Money Tracker CRUD, summaries, filters, CSV export, and manual savings goals
- Twilight Conservatory Habit Tracker
- Reusable quest blueprints, daily quest logs, realm progress, Momentum Trail, archive/restore, and CSRF-protected forms
