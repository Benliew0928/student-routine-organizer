-- Upgrade an existing Student Routine Organizer database for the enhanced Habit Tracker.
-- Use this only if you already imported an older database export.

USE student_routine_organizer;

ALTER TABLE habit_records
  ADD COLUMN category VARCHAR(60) NOT NULL DEFAULT 'General' AFTER habit_name,
  ADD COLUMN priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium' AFTER completion_status,
  ADD COLUMN notes VARCHAR(255) NULL AFTER habit_date,
  ADD INDEX idx_habit_user_status_date (user_id, completion_status, habit_date),
  ADD INDEX idx_habit_user_name_date (user_id, habit_name, habit_date),
  ADD UNIQUE KEY uq_habit_user_name_date (user_id, habit_name, habit_date);

INSERT IGNORE INTO habit_records (user_id, habit_name, category, target_frequency, completion_status, priority, habit_date, notes)
SELECT user_id, 'Morning Study Review', 'Study', 'Daily', 'completed', 'high', '2026-06-18', 'Reviewed lecture notes before class.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Morning Study Review', 'Study', 'Daily', 'completed', 'high', '2026-06-19', 'Completed revision for server-side topic.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Morning Study Review', 'Study', 'Daily', 'completed', 'high', '2026-06-20', 'Practiced SQL table relationships.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Drink Water', 'Health', 'Daily', 'completed', 'medium', '2026-06-20', 'Reached the daily hydration target.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Drink Water', 'Health', 'Daily', 'pending', 'medium', '2026-06-21', 'Still tracking today.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Budget Check', 'Finance', 'Weekly', 'completed', 'medium', '2026-06-17', 'Checked spending before the weekend.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Exercise Stretch', 'Fitness', 'Weekdays', 'missed', 'low', '2026-06-19', 'Skipped because of group meeting.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Assignment Planning', 'Study', 'Weekly', 'pending', 'high', '2026-06-22', 'Prepare next task checklist.' FROM users WHERE email = 'student@example.com';
