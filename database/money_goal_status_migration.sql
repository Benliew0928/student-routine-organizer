ALTER TABLE money_savings_goals
  MODIFY COLUMN status ENUM('active', 'paused', 'completed', 'archived') NOT NULL DEFAULT 'active';
