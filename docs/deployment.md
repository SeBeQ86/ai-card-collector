# Deployment Notes

Manual process. GitHub Actions only verifies code — it does not deploy.

---

## Web root

Only `public/` must be served by the web server.

On shared hosting the document root for the domain/subdomain must point to `public/`.
If the host gives you `public_html/`, copy the contents of `public/` there and upload all other
project directories (`src/`, `config/`, `database/`, `docs/`, `context/`, `.claude/`) one level
**above** `public_html/` so they are never web-accessible.

Verify by attempting to browse to:
- `https://yourdomain.com/config/app.php` → must return 403 or 404, never PHP source
- `https://yourdomain.com/database/schema.sql` → must return 403 or 404
- `https://yourdomain.com/.env` → must return 403 or 404

---

## Directory layout on the server

```
/home/youruser/          ← or equivalent hosting home
  public_html/           ← web root (maps to public/ in the repo)
    index.php
    login.php
    logout.php
    card-add.php
    card-edit.php
    card-delete.php
    card-message.php
    assets/
  src/
  config/
  database/
  docs/
  context/
  .claude/
```

---

## Database setup

Run in order — do not reverse:

1. **Import schema:**
   ```sql
   -- via phpMyAdmin or CLI
   source database/schema.sql;
   ```

2. **Create the user account:**
   Generate a bcrypt hash locally first (never on the server):
   ```bash
   php -r "echo password_hash('your-chosen-password', PASSWORD_BCRYPT) . PHP_EOL;"
   ```
   Then insert:
   ```sql
   INSERT INTO users (email, password_hash)
   VALUES ('your@email.com', '$2y$12$...<hash from above>...');
   ```
   Do not commit this INSERT to the repository.

---

## Environment variables

Set these in the hosting control panel or `.htaccess` (never commit values):

| Variable | Example value | Notes |
|----------|--------------|-------|
| `APP_ENV` | `production` | Enables `secure` flag on session cookie |
| `APP_URL` | `https://yourdomain.com` | Base URL used in the app config |
| `DB_HOST` | `localhost` | MySQL host |
| `DB_PORT` | `3306` | MySQL port |
| `DB_DATABASE` | `ai_card_collector` | Database name |
| `DB_USERNAME` | `dbuser` | Database user |
| `DB_PASSWORD` | `secret` | Database password |

If your host uses `.htaccess` for env vars:
```apache
SetEnv APP_ENV production
SetEnv DB_DATABASE ai_card_collector
SetEnv DB_USERNAME dbuser
SetEnv DB_PASSWORD secret
```

---

## Deployment steps (manual)

1. Merge to `main` on GitHub.
2. Pull or download the `main` branch on your local machine.
3. Upload changed files to the server via FTP/SFTP (or rsync if available).
4. Do **not** upload `.env`, `database/seed.sql` with real credentials, `.claude/`, or `context/`.
5. Verify the site loads and login works.
6. If the schema changed since the last deploy, apply the migration SQL manually.

---

## No automatic deployment

CI runs PHP syntax checks only. There is no auto-deploy step. Do not add one without
a staging environment and a rollback plan.
