-- Campus Notes (repaired) - disposable local lab database only.
CREATE DATABASE IF NOT EXISTS notes_lab CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE notes_lab;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(254) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,          -- password_hash() output (bcrypt today, room for argon2)
  role VARCHAR(20) NOT NULL DEFAULT 'student',
  CONSTRAINT chk_role CHECK (role IN ('student', 'admin'))
);

CREATE TABLE notes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  body TEXT NOT NULL,
  INDEX idx_notes_user (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Synthetic accounts for local use only. Hashes were produced with PHP password_hash()
-- (PASSWORD_DEFAULT / bcrypt). Plaintext test passwords are documented in README.md.
-- To reset them on an existing database run: php tools/reset_seed_passwords.php
INSERT INTO users (id, email, password_hash, role) VALUES
  (1, 'alex@example.test', '$2y$10$d.Bwj2D9RNpcYGKYyvRByuOjGHb/PBPAAp95sK8ili4xr3duiBOGq', 'student'),
  (2, 'sam@example.test',  '$2y$10$9QqaCqw860CjlZ6L5.TrnO16u/ML/hT6dqT7TjJCtwW9kd5NN0.v.', 'student');

INSERT INTO notes (user_id, title, body) VALUES
  (1, 'Alex: course reminder', 'Review the security reading before class.'),
  (1, 'Alex: project idea', 'Build a small student directory.'),
  (2, 'Sam: study plan', 'Compare authentication and authorization.'),
  (2, 'Sam: meeting', 'Prepare questions for the next group discussion.');
