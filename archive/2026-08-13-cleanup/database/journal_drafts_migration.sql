USE student_routine_organizer;

CREATE TABLE IF NOT EXISTS journal_drafts (
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
