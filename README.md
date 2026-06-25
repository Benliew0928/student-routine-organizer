Student Routine Organizer

Current Phase:
- Phase 4 dashboard summaries completed.
- Phase 8 Habit Tracker completed for Member 4.

Local Requirements:
- XAMPP
- Apache running
- MySQL running

Local URL:
http://localhost/student-routine-organizer/

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
- Enhanced Habit Tracker CRUD
- Habit filters, sorting, quick status updates, progress summaries, best streak, CSV export, and CSRF-protected forms
