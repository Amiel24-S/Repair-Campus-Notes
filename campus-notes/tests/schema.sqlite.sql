-- SQLite equivalent of schema.sql, used only by tests/run_checks.sh.
CREATE TABLE users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL DEFAULT 'student' CHECK (role IN ('student', 'admin'))
);
CREATE TABLE notes (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  title TEXT NOT NULL,
  body TEXT NOT NULL
);
INSERT INTO users (id, email, password_hash, role) VALUES
  (1, 'alex@example.test', '$2y$10$d.Bwj2D9RNpcYGKYyvRByuOjGHb/PBPAAp95sK8ili4xr3duiBOGq', 'student'),
  (2, 'sam@example.test',  '$2y$10$9QqaCqw860CjlZ6L5.TrnO16u/ML/hT6dqT7TjJCtwW9kd5NN0.v.', 'student');
INSERT INTO notes (user_id, title, body) VALUES
  (1, 'Alex: course reminder', 'Review the security reading before class.'),
  (1, 'Alex: project idea', 'Build a small student directory.'),
  (2, 'Sam: study plan', 'Compare authentication and authorization.'),
  (2, 'Sam: meeting', 'Prepare questions for the next group discussion.');
