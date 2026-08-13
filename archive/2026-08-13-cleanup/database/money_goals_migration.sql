USE student_routine_organizer;

CREATE TABLE IF NOT EXISTS money_savings_goals (
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

CREATE TABLE IF NOT EXISTS money_savings_contributions (
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
