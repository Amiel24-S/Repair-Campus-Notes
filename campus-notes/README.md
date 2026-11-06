# Campus Notes: security review project (repaired)

Educational lab application. It started as intentionally vulnerable material and has been
repaired as part of the Security Code Review laboratory. Run it **only** on an isolated machine
you control, with synthetic data and a disposable local database. Do not expose it to the
internet or connect it to real accounts.

## Project layout

```
campus-notes/
├── public/                  <- web root (the only folder served by PHP)
│   ├── index.php            web controller: routes POST actions and renders pages
│   ├── api.php              JSON API (same-origin, session + CSRF protected)
│   └── style.css            styles (external so the CSP can forbid inline code)
├── src/
│   ├── bootstrap.php        error handling, class autoloader, helpers include
│   ├── Config.php           reads LAB_* environment variables (no hard-coded secrets)
│   ├── Database.php         PDO connection factory
│   ├── Session.php          hardened session start, login/logout, idle timeout, flash
│   ├── Csrf.php             CSRF token + Origin / Sec-Fetch-Site check
│   ├── Auth.php             password_verify login, current user lookup
│   ├── Validator.php        input validation (ids, email, password, note fields)
│   ├── UserRepository.php   user queries (prepared statements)
│   ├── NoteRepository.php   owner-scoped note queries (prepared statements)
│   └── helpers.php          h() escaping, redirect, security headers, render
├── templates/
│   ├── home.php             main page; every dynamic value goes through h()
│   └── error.php            generic error page
├── tools/reset_seed_passwords.php   CLI: re-hash the two lab accounts on an old DB
├── tests/
│   ├── run_checks.sh        45 automated security checks (uses SQLite)
│   └── schema.sqlite.sql    SQLite copy of the schema for the tests
├── schema.sql               MySQL schema with password_hash() seed accounts
└── README.md
```

No Composer packages are used; everything is plain PHP 8 + PDO.

## Local setup (MySQL)

1. Install PHP 8.1+ with `pdo_mysql` and `mbstring`, plus a local MySQL 8 server. Check with `php -m`.
2. As a local MySQL administrator, import the schema into a fresh, disposable server:
   `mysql -u root -p < schema.sql`
   (If you already imported the original MD5 starter schema, either drop `notes_lab` and re-import,
   or run `php tools/reset_seed_passwords.php` after step 3.)
3. Create a least-privilege account, for example:
   ```sql
   CREATE USER 'notes_app'@'localhost' IDENTIFIED BY '<choose a local password>';
   GRANT SELECT, INSERT, UPDATE, DELETE ON notes_lab.* TO 'notes_app'@'localhost';
   ```
   Then set the variables in your shell (never commit them):
   - PowerShell: `$env:LAB_DB_USER = 'notes_app'; $env:LAB_DB_PASSWORD = '...'`
   - bash: `export LAB_DB_USER=notes_app LAB_DB_PASSWORD=...`
   The app refuses to start without them (there is no fallback to `root`).
4. From the project folder run: `php -S 127.0.0.1:8000 -t public`
   and open `http://127.0.0.1:8000`. Stop with Ctrl+C.
5. Synthetic accounts (public, throwaway, lab only):
   - `alex@example.test` / `LabOnly-Alex-123`
   - `sam@example.test` / `LabOnly-Sam-456`

### Optional environment variables

| Variable | Default | Purpose |
|---|---|---|
| `LAB_DB_DSN` | `mysql:host=127.0.0.1;port=3306;dbname=notes_lab;charset=utf8mb4` | PDO DSN |
| `LAB_ALLOWED_ORIGINS` | `http://127.0.0.1:8000,http://localhost:8000` | Origins allowed to POST / call the API |
| `LAB_COOKIE_SECURE` | auto (on when HTTPS) | Force the `Secure` cookie flag |

## API

Same-origin only, requires a signed-in session.

- `GET /api.php` returns `{"notes":[...]}` for the signed-in user. `?user_id=` other than your own gives 403.
- `POST /api.php` with `Content-Type: application/json`, header `X-CSRF-Token: <token>`
  (the token is in the page's `<meta name="csrf-token">`), body `{"id":1,"title":"...","body":"..."}`.
  Returns 200, or 400/401/403/404/413/415/422 for errors.

Example from the browser console on the app page:
```js
fetch('/api.php', {method:'POST', headers:{'Content-Type':'application/json',
  'X-CSRF-Token':document.querySelector('meta[name=csrf-token]').content},
  body: JSON.stringify({id:1, title:'Edited', body:'From the API'})}).then(r=>r.json()).then(console.log)
```

## Running the automated checks

`bash tests/run_checks.sh` (Linux/macOS/Git Bash/WSL; needs `php` with `pdo_sqlite` and `curl`).
It creates a throwaway SQLite database in `tests/tmp/`, starts `php -S` on port 8099, runs
45 checks with both synthetic users, and stops the server.

## Production notes (not covered by this lab setup)

HTTPS with HSTS, `Secure` cookies, secrets from a secret manager, a real web server (not `php -S`)
with only `public/` exposed, login rate limiting / lockout, central logging, and dependency/patch management.
