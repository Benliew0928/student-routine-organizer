Student Routine Organizer

Current Phase:
- Phase 4 dashboard summaries completed.
- Phase 6 Diary Journal completed for Member 2.
- Phase 8 Habit Tracker completed for Member 4.

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
- If you already imported the older SQL file, import database/habit_tracker_migration.sql before testing the enhanced Habit Tracker.

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
- Complete Diary Journal CRUD with per-user ownership
- Journal detail reading view, free-text moods, search, mood/date filters, and sorting
- Blank, Daily Reflection, Gratitude, Mood Check-in, and Study Notes templates
- Journal CSRF protection, validation, safe output, mood suggestions, live counts, and browser draft recovery
- Enhanced Habit Tracker CRUD
- Habit filters, sorting, quick status updates, progress summaries, best streak, CSV export, and CSRF-protected forms

Journal Verification:
- `C:\xampp\php\php.exe tests\app_config_test.php`
- `C:\xampp\php\php.exe tests\journal_helpers_test.php`
- `C:\xampp\php\php.exe tests\journal_database_test.php`
