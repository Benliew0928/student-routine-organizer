ALTER TABLE money_savings_goals
  ADD COLUMN weekly_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER target_date,
  ADD COLUMN auto_save_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER weekly_amount,
  ADD COLUMN reminders_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER auto_save_enabled;
