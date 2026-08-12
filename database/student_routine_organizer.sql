-- UCCD3243 Student Routine Organizer
-- Database export for development and testing
-- Database name: student_routine_organizer

CREATE DATABASE IF NOT EXISTS student_routine_organizer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE student_routine_organizer;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS habit_logs;
DROP TABLE IF EXISTS habits;
DROP TABLE IF EXISTS habit_records;
DROP TABLE IF EXISTS money_transactions;
DROP TABLE IF EXISTS money_savings_contributions;
DROP TABLE IF EXISTS money_savings_goals;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS journal_drafts;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS exercise_blogs;
DROP TABLE IF EXISTS exercise_records;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  user_id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student', 'admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE password_resets (
  reset_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uq_password_reset_token_hash (token_hash),
  INDEX idx_password_reset_user_active (user_id, used_at, expires_at)
);

CREATE TABLE exercise_records (
  exercise_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  activity_type VARCHAR(80) NOT NULL,
  duration_minutes INT NOT NULL,
  calories_burned INT NOT NULL,
  exercise_date DATE NOT NULL,
  notes VARCHAR(255),
  photo_filename VARCHAR(255) NULL,
  photo_mime_type VARCHAR(20) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_exercise_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_exercise_user_date (user_id, exercise_date)
);

CREATE TABLE exercise_blogs (
  blog_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(140) NOT NULL,
  content TEXT NOT NULL,
  blog_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_exercise_blog_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_exercise_blog_user_date (user_id, blog_date)
);

CREATE TABLE journal_entries (
  journal_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(120) NOT NULL,
  content TEXT NOT NULL,
  mood_status VARCHAR(50) NOT NULL,
  entry_date DATE NOT NULL,
  subject VARCHAR(50) NOT NULL DEFAULT 'General',
  weather VARCHAR(50) NOT NULL DEFAULT '☀️ Sunny',
  tags VARCHAR(255) NOT NULL DEFAULT '',
  paper_style VARCHAR(50) NOT NULL DEFAULT 'lined',
  starred TINYINT(1) NOT NULL DEFAULT 0,
  canvas_json MEDIUMTEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_journal_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_journal_user_date (user_id, entry_date),
  INDEX idx_journal_mood (mood_status)
);

CREATE TABLE journal_drafts (
  draft_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(120) NOT NULL DEFAULT '',
  content TEXT NOT NULL,
  mood_status VARCHAR(50) NOT NULL DEFAULT '',
  entry_date DATE NULL,
  template_key VARCHAR(32) NOT NULL DEFAULT 'blank',
  subject VARCHAR(50) NOT NULL DEFAULT 'General',
  weather VARCHAR(50) NOT NULL DEFAULT '☀️ Sunny',
  tags VARCHAR(255) NOT NULL DEFAULT '',
  paper_style VARCHAR(50) NOT NULL DEFAULT 'lined',
  starred TINYINT(1) NOT NULL DEFAULT 0,
  canvas_json MEDIUMTEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_journal_draft_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_journal_draft_user_updated (user_id, updated_at)
);

CREATE TABLE money_transactions (
  transaction_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  category VARCHAR(80) NOT NULL,
  description VARCHAR(255),
  transaction_type ENUM('income', 'expense') NOT NULL,
  transaction_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_money_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_money_user_date (user_id, transaction_date),
  INDEX idx_money_type_category (transaction_type, category)
);

CREATE TABLE money_savings_goals (
  goal_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  goal_name VARCHAR(120) NOT NULL,
  target_amount DECIMAL(10,2) NOT NULL,
  target_date DATE NULL,
  weekly_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  auto_save_enabled TINYINT(1) NOT NULL DEFAULT 0,
  reminders_enabled TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('active', 'paused', 'completed', 'archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_money_goal_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_money_goal_user_status (user_id, status)
);

CREATE TABLE money_savings_contributions (
  contribution_id INT AUTO_INCREMENT PRIMARY KEY,
  goal_id INT NOT NULL,
  user_id INT NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  note VARCHAR(255) NULL,
  contribution_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_money_contribution_goal FOREIGN KEY (goal_id) REFERENCES money_savings_goals(goal_id) ON DELETE CASCADE,
  CONSTRAINT fk_money_contribution_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  INDEX idx_money_contribution_goal_date (goal_id, contribution_date)
);

CREATE TABLE habits (
  habit_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  habit_name VARCHAR(100) NOT NULL,
  realm ENUM('focus', 'energy', 'mind', 'life_admin') NOT NULL DEFAULT 'focus',
  target_frequency ENUM('daily', 'weekdays', 'weekly', 'custom') NOT NULL DEFAULT 'daily',
  scheduled_days VARCHAR(27) NOT NULL,
  preferred_time TIME NULL,
  duration_minutes SMALLINT UNSIGNED NULL,
  motivation VARCHAR(180) NULL,
  priority ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_habit_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_habit_user_active (user_id, is_active),
  INDEX idx_habit_user_realm (user_id, realm)
);

CREATE TABLE habit_logs (
  log_id INT AUTO_INCREMENT PRIMARY KEY,
  habit_id INT NOT NULL,
  user_id INT NOT NULL,
  scheduled_date DATE NOT NULL,
  completion_status ENUM('scheduled', 'completed', 'skipped', 'missed') NOT NULL DEFAULT 'scheduled',
  completed_at DATETIME NULL,
  reflection_note VARCHAR(255) NULL,
  deleted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_habit_log_habit
    FOREIGN KEY (habit_id) REFERENCES habits(habit_id)
    ON DELETE CASCADE,
  CONSTRAINT fk_habit_log_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  UNIQUE KEY uq_habit_log_date (habit_id, scheduled_date),
  INDEX idx_habit_log_user_date (user_id, scheduled_date),
  INDEX idx_habit_log_status_date (completion_status, scheduled_date)
);

INSERT INTO users (full_name, email, password_hash, role) VALUES
('System Admin', 'admin@example.com', '$2y$10$FyJDw1My5DclqVsQQIUlQen.oego9dFKowVG7cF2sDegxSGYHsIhC', 'admin'),
('Sample Student', 'student@example.com', '$2y$10$I1CLDdxlAe1CPh3Si0gBYeszJkDqsiF6OLYeF2cNwBaGzgNXFJpxC', 'student');

INSERT INTO exercise_records (user_id, activity_type, duration_minutes, calories_burned, exercise_date, notes)
SELECT user_id, 'Running', 35, 280, '2026-07-14', 'Morning run around campus.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Cycling', 50, 420, '2026-07-16', 'Evening ride after class.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Gym Session', 60, 510, '2026-07-18', 'Strength training and treadmill cooldown.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Swimming', 40, 330, '2026-07-20', 'Easy laps for recovery.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Walking', 30, 160, '2026-06-08', 'Walked around the campus after class.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Yoga', 25, 120, '2026-06-15', 'Gentle stretching and breathing practice.' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Running', 30, 240, '2026-06-23', 'Short run before breakfast.' FROM users WHERE email = 'student@example.com';

INSERT INTO habits (user_id, habit_name, realm, target_frequency, scheduled_days, preferred_time, duration_minutes, motivation, priority)
SELECT user_id, 'Morning Study Review', 'focus', 'weekdays', 'mon,tue,wed,thu,fri', '08:00:00', 20, 'Arrive at class feeling prepared.', 'high' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Fill Water Bottle', 'energy', 'daily', 'mon,tue,wed,thu,fri,sat,sun', '09:00:00', 5, 'Keep my energy steady through lectures.', 'medium' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Write One Reflection', 'mind', 'custom', 'mon,wed,fri,sun', '21:30:00', 10, 'Make space for what I am feeling.', 'medium' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Plan Tomorrow', 'life_admin', 'weekdays', 'mon,tue,wed,thu,fri', '20:30:00', 10, 'Begin the next day with less stress.', 'high' FROM users WHERE email = 'student@example.com';

INSERT INTO habit_logs (habit_id, user_id, scheduled_date, completion_status, completed_at, reflection_note)
SELECT h.habit_id, h.user_id, '2026-07-20', 'completed', '2026-07-20 08:20:00', 'Finished the database relationship notes.' FROM habits h WHERE h.habit_name = 'Morning Study Review'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-21', 'completed', '2026-07-21 08:18:00', 'Reviewed PHP form validation before class.' FROM habits h WHERE h.habit_name = 'Morning Study Review'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-22', 'scheduled', NULL, NULL FROM habits h WHERE h.habit_name = 'Morning Study Review'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-20', 'completed', '2026-07-20 09:05:00', 'Refilled it before leaving the hostel.' FROM habits h WHERE h.habit_name = 'Fill Water Bottle'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-21', 'completed', '2026-07-21 09:08:00', NULL FROM habits h WHERE h.habit_name = 'Fill Water Bottle'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-22', 'scheduled', NULL, NULL FROM habits h WHERE h.habit_name = 'Fill Water Bottle'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-20', 'completed', '2026-07-20 21:40:00', 'A calm reset before the new week.' FROM habits h WHERE h.habit_name = 'Write One Reflection'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-21', 'completed', '2026-07-21 20:40:00', 'Mapped tomorrow before sleeping.' FROM habits h WHERE h.habit_name = 'Plan Tomorrow'
UNION ALL SELECT h.habit_id, h.user_id, '2026-07-22', 'scheduled', NULL, NULL FROM habits h WHERE h.habit_name = 'Plan Tomorrow'
UNION ALL SELECT h.habit_id, h.user_id, '2026-06-10', 'completed', '2026-06-10 08:15:00', 'Reviewed lecture notes before class.' FROM habits h WHERE h.habit_name = 'Morning Study Review'
UNION ALL SELECT h.habit_id, h.user_id, '2026-06-17', 'completed', '2026-06-17 09:03:00', 'Prepared water before leaving home.' FROM habits h WHERE h.habit_name = 'Fill Water Bottle'
UNION ALL SELECT h.habit_id, h.user_id, '2026-06-24', 'completed', '2026-06-24 20:45:00', 'Listed tomorrow priorities.' FROM habits h WHERE h.habit_name = 'Plan Tomorrow';

INSERT INTO exercise_blogs (user_id, title, content, blog_date)
SELECT user_id, 'A stronger week of movement', 'I kept the routine realistic this week: short runs, a gym session, and recovery swimming. The variety helped me stay consistent without feeling overwhelmed.', '2026-07-20' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'What I learned from cycling after class', 'An evening ride is a useful reset after a long day of lectures. I return home with more energy and a clearer mind for revision.', '2026-07-16' FROM users WHERE email = 'student@example.com';

INSERT INTO journal_entries (user_id, title, content, mood_status, entry_date)
SELECT user_id, 'A focused start', 'I finished my morning review before class and felt more prepared during the lecture. I want to repeat this small routine tomorrow.', 'Motivated', '2026-07-20' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Balancing assignments', 'There is still a lot to complete, but breaking the work into smaller tasks made the day feel manageable.', 'Calm', '2026-07-21' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'A good reset', 'The evening cycle helped me step away from screens and return to my notes with a fresh perspective.', 'Grateful', '2026-07-22' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'June goals', 'I want to make steady progress with revision and leave time for rest each evening.', 'Hopeful', '2026-06-05' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Small wins', 'I completed my exercise plan and reviewed the difficult topic from class.', 'Proud', '2026-06-18' FROM users WHERE email = 'student@example.com';

INSERT INTO journal_drafts (user_id, title, content, mood_status, entry_date, template_key)
SELECT user_id, 'Weekend reflection', 'This draft is ready to be finished after I review the week.', 'Thoughtful', '2026-07-26', 'daily_reflection' FROM users WHERE email = 'student@example.com';

INSERT INTO money_transactions (user_id, amount, category, description, transaction_type, transaction_date)
SELECT user_id, 4000.00, 'Salary', 'Monthly part-time salary.', 'income', '2026-07-01' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 300.00, 'Allowance', 'Monthly study allowance.', 'income', '2026-07-02' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 150.00, 'Freelance', 'Poster design project.', 'income', '2026-07-10' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 38.20, 'Shopping', 'Stationery and study supplies.', 'expense', '2026-07-03' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 15.00, 'Food', 'Lunch near campus.', 'expense', '2026-07-05' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 5.00, 'Transport', 'Campus bus fare.', 'expense', '2026-07-08' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 68.00, 'Bills', 'Mobile data plan.', 'expense', '2026-07-12' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 24.50, 'Entertainment', 'Movie night with friends.', 'expense', '2026-07-18' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 12.00, 'Food', 'Coffee and breakfast.', 'expense', '2026-07-21' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 4000.00, 'Salary', 'Monthly part-time salary.', 'income', '2026-06-01' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 300.00, 'Allowance', 'Monthly study allowance.', 'income', '2026-06-02' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 75.00, 'Freelance', 'Tutoring session payment.', 'income', '2026-06-16' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 26.50, 'Food', 'Lunch and snacks.', 'expense', '2026-06-04' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 18.00, 'Transport', 'Train and bus fares.', 'expense', '2026-06-09' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 45.00, 'Shopping', 'Reference book.', 'expense', '2026-06-13' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 68.00, 'Bills', 'Mobile data plan.', 'expense', '2026-06-20' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 30.00, 'Entertainment', 'Campus event ticket.', 'expense', '2026-06-27' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 850.00, 'Salary', 'Campus assistant payment.', 'income', '2026-08-01' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 250.00, 'Allowance', 'Monthly family allowance.', 'income', '2026-08-02' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 120.00, 'Freelance', 'Tutor two mathematics sessions.', 'income', '2026-08-05' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 35.00, 'Others', 'Sold unused reference book.', 'income', '2026-08-07' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 18.50, 'Food', 'Lunch at campus cafe.', 'expense', '2026-08-02' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 6.00, 'Transport', 'Bus fare to campus.', 'expense', '2026-08-03' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 42.90, 'Shopping', 'Notebook and printer paper.', 'expense', '2026-08-04' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 30.00, 'Education', 'Online course subscription.', 'expense', '2026-08-05' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 55.00, 'Bills', 'Mobile data plan.', 'expense', '2026-08-06' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 14.00, 'Entertainment', 'Movie with classmates.', 'expense', '2026-08-08' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 22.00, 'Healthcare', 'Pharmacy essentials.', 'expense', '2026-08-09' FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 9.50, 'Others', 'Laundry card top-up.', 'expense', '2026-08-10' FROM users WHERE email = 'student@example.com';

INSERT INTO money_savings_goals (user_id, goal_name, target_amount, target_date, weekly_amount, auto_save_enabled, reminders_enabled, status)
SELECT user_id, 'Laptop upgrade', 1200.00, '2026-12-15', 36.11, 1, 1, 'active'
FROM users WHERE email = 'student@example.com'
UNION ALL SELECT user_id, 'Weekend trip', 500.00, '2026-10-30', 0.00, 0, 0, 'paused'
FROM users WHERE email = 'student@example.com';

INSERT INTO money_savings_goals (user_id, goal_name, target_amount, target_date, weekly_amount, auto_save_enabled, reminders_enabled, status, completed_at)
SELECT user_id, 'Textbook fund', 300.00, '2026-07-31', 0.00, 0, 1, 'completed', '2026-07-25 18:00:00'
FROM users WHERE email = 'student@example.com';

INSERT INTO money_savings_contributions (goal_id, user_id, amount, note, contribution_date)
SELECT goal_id, user_id, 250.00, 'Set aside from assistant payment.', '2026-08-01'
FROM money_savings_goals WHERE goal_name = 'Laptop upgrade'
UNION ALL SELECT goal_id, user_id, 300.00, 'Saved after freelance tutoring.', '2026-08-05'
FROM money_savings_goals WHERE goal_name = 'Laptop upgrade'
UNION ALL SELECT goal_id, user_id, 100.00, 'Initial travel fund.', '2026-07-20'
FROM money_savings_goals WHERE goal_name = 'Weekend trip'
UNION ALL SELECT goal_id, user_id, 150.00, 'First savings transfer.', '2026-07-05'
FROM money_savings_goals WHERE goal_name = 'Textbook fund'
UNION ALL SELECT goal_id, user_id, 150.00, 'Fund completed.', '2026-07-25'
FROM money_savings_goals WHERE goal_name = 'Textbook fund';
