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

CREATE TABLE exercise_records (
  exercise_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  activity_type VARCHAR(80) NOT NULL,
  duration_minutes INT NOT NULL,
  calories_burned INT NOT NULL,
  exercise_date DATE NOT NULL,
  notes VARCHAR(255),
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
SELECT user_id, 'Weekend reflection', 'This draft is ready to be finished after I review the week.', 'Thoughtful', '2026-07-26', 'reflection' FROM users WHERE email = 'student@example.com';

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
UNION ALL SELECT user_id, 30.00, 'Entertainment', 'Campus event ticket.', 'expense', '2026-06-27' FROM users WHERE email = 'student@example.com';
