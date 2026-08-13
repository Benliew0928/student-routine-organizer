-- UCCD3243 Student Routine Organizer
-- Current schema-only reference. Import student_routine_organizer.sql for sample data.
-- Database name: student_routine_organizer

CREATE DATABASE IF NOT EXISTS student_routine_organizer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE student_routine_organizer;

DROP TABLE IF EXISTS habit_logs;
DROP TABLE IF EXISTS habits;
DROP TABLE IF EXISTS habit_records;
DROP TABLE IF EXISTS money_savings_contributions;
DROP TABLE IF EXISTS money_savings_goals;
DROP TABLE IF EXISTS money_transactions;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS journal_drafts;
DROP TABLE IF EXISTS journal_entries;
DROP TABLE IF EXISTS exercise_blogs;
DROP TABLE IF EXISTS exercise_records;
DROP TABLE IF EXISTS users;

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
  CONSTRAINT fk_exercise_blog_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
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
  CONSTRAINT fk_habit_log_habit FOREIGN KEY (habit_id) REFERENCES habits(habit_id) ON DELETE CASCADE,
  CONSTRAINT fk_habit_log_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
  UNIQUE KEY uq_habit_log_date (habit_id, scheduled_date),
  INDEX idx_habit_log_user_date (user_id, scheduled_date),
  INDEX idx_habit_log_status_date (completion_status, scheduled_date)
);

-- Sample users for later testing.
-- Replace these password hashes in Phase 3 if needed.
-- Plain text reference for coursework testing only:
-- admin@example.com / admin123
-- student@example.com / password123
