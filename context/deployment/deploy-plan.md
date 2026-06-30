# Deployment Plan — AI Card Collector

**Read-only reference. Not executed automatically.**
A human must complete every step and confirm each gate before proceeding.

---

## Preconditions

Before starting a deployment:

- [ ] All changes are merged to `main` on GitHub.
- [ ] The latest `main` commit has a passing GitHub Actions run (green `PHP syntax check` job).
- [ ] A database backup of the current production DB exists and is stored locally.
- [ ] You have FTP/SFTP credentials for the hosting account.
- [ ] You know the production MySQL credentials.

---

## Gate 1 — Local verification

Run on your local machine before uploading anything:

```
# Syntax check — must return zero errors
find public/ src/ config/ -name "*.php" -print0 | xargs -0 -n1 php -l

# Confirm seed.sql contains only the placeholder hash, not a real one
grep -c "REPLACE_THIS" database/seed.sql   # must return 1
```

Do not proceed if any syntax check fails or if a real hash is found in seed.sql.

---

## Gate 2 — GitHub Actions verification

Open the repository on GitHub → **Actions** → latest run on `main`.

- [ ] `PHP syntax check` job is green.
- [ ] No deploy step ran (there should be none).

Do not proceed if the CI run is red or missing.

---

## Database setup / import order

**First deploy only:**

1. Create a new MySQL database on the hosting panel.
2. Create a database user; assign all privileges on that database only.
3. Import schema (must come first):
   ```
   mysql -h HOST -u USER -p DBNAME < database/schema.sql
   ```
4. Insert the production user account. Generate the hash locally:
   ```
   php -r "echo password_hash('your-chosen-password', PASSWORD_BCRYPT) . PHP_EOL;"
   ```
   Then in MySQL:
   ```sql
   INSERT INTO users (email, password_hash)
   VALUES ('your@email.com', '$2y$12$...<hash>...');
   ```
   Do **not** upload `database/seed.sql` to the server.

**Schema changes on re-deploy:**

Apply any `ALTER TABLE` statements manually before uploading new PHP files.
Keep a backup immediately before the migration. There is no automated rollback for DB changes.

---

## Manual upload steps

1. Pull or download the `main` branch locally.
2. Connect to the hosting account via FTP/SFTP.
3. Map the project layout to the server:

   | Local path | Server path |
   |---|---|
   | `public/` contents | `public_html/` (or the hosting web root) |
   | `src/` | `public_html/src/` |
   | `config/` | `public_html/config/` |
   | `public/.htaccess` | `public_html/.htaccess` |

   **Note:** `src/` and `config/` go **inside** `public_html/` on shared hosting with
   `open_basedir` restrictions (e.g. MyDevil). The PHP files use `file_exists()` to
   detect which layout is in use and load paths accordingly — the same code works
   both locally (XAMPP, where `src/` and `config/` are one level above `public/`)
   and on shared hosting.

4. Do **not** upload: `.env`, `database/seed.sql`, `.claude/`, `context/`, `docs/`,
   `database/demo-cards.sql`, any file containing credentials.

---

## Web root / public directory requirement

The hosting document root **must** point to `public/` (or `public_html/` after mapping).

Verify immediately after upload:

- `https://yourdomain.com/config/app.php` → must return 403 or 404, never PHP source.
- `https://yourdomain.com/database/schema.sql` → must return 403 or 404.

If either returns PHP source or file content, stop and fix the document root before proceeding.

---

## Environment variables

Set these in the hosting control panel or via `public_html/.htaccess` (never commit values):

```apache
SetEnv APP_ENV      production
SetEnv APP_URL      https://yourdomain.com
SetEnv DB_HOST      localhost
SetEnv DB_PORT      3306
SetEnv DB_DATABASE  your_db_name
SetEnv DB_USERNAME  your_db_user
SetEnv DB_PASSWORD  your_db_password
```

`APP_ENV=production` enables the `Secure` flag on the session cookie. Do not omit it.

---

## Gate 3 — Post-deploy smoke test

Perform these checks in a browser after every deploy:

- [ ] `https://yourdomain.com/` redirects to the login page.
- [ ] Login with production credentials succeeds and shows the wanted-cards list.
- [ ] Add a test card; verify it appears in the list with a non-zero score.
- [ ] Edit the card; verify the update saves correctly.
- [ ] Delete the test card; verify it is removed from the list.
- [ ] Open the seller-message page for any card; verify EN and PT messages render.
- [ ] Log out; verify the session is destroyed and the login page is shown.

Do not mark the deploy as complete until all seven checks pass.

---

## Rollback steps

If the post-deploy smoke test fails:

1. Re-upload the previous version of the PHP files from the prior release.
2. If a schema migration was applied: restore the database from the backup taken in the
   preconditions step.
3. Revert the GitHub merge if the bad code is already on `main`, or open a hotfix PR.

There is no automated rollback. Speed of manual recovery depends on how recently the backup
was taken and how quickly FTP access is available.

---

## Human approval gates summary

| Gate | Who must confirm | Condition to proceed |
|---|---|---|
| Gate 1 — local verification | Developer | Zero syntax errors; placeholder-only seed.sql |
| Gate 2 — CI green | Developer | GitHub Actions passing on `main` |
| Pre-DB backup | Developer | Backup stored locally before any DB change |
| Web root check | Developer | Non-public dirs return 403/404 |
| Gate 3 — smoke test | Developer | All 7 post-deploy checks pass |
