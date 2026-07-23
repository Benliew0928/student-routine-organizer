Student Routine Organizer

Current Phase:
- Phase 5 Exercise Tracker completed for Member 1.
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
- Twilight Conservatory Habit Tracker
- Reusable quest blueprints, daily quest logs, realm progress, Momentum Trail, archive/restore, and CSRF-protected forms
