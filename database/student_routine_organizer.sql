-- UCCD3243 Student Routine Organizer
-- Database export for development and testing
-- Database name: student_routine_organizer

CREATE DATABASE IF NOT EXISTS student_routine_organizer
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE student_routine_organizer;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS habit_records;
DROP TABLE IF EXISTS money_transactions;
DROP TABLE IF EXISTS journal_entries;
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

CREATE TABLE habit_records (
  habit_id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  habit_name VARCHAR(100) NOT NULL,
  target_frequency VARCHAR(50) NOT NULL,
  completion_status ENUM('pending', 'completed', 'missed') NOT NULL DEFAULT 'pending',
  habit_date DATE NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_habit_user
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE,
  INDEX idx_habit_user_date (user_id, habit_date),
  INDEX idx_habit_status (completion_status)
);

INSERT INTO users (full_name, email, password_hash, role) VALUES
('System Admin', 'admin@example.com', '$2y$10$FyJDw1My5DclqVsQQIUlQen.oego9dFKowVG7cF2sDegxSGYHsIhC', 'admin'),
('Sample Student', 'student@example.com', '$2y$10$I1CLDdxlAe1CPh3Si0gBYeszJkDqsiF6OLYeF2cNwBaGzgNXFJpxC', 'student');

