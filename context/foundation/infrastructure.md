# Infrastructure Decision — AI Card Collector

_Context document. Read-only after creation; append to update._

---

## Decision summary

| Dimension | Choice |
|---|---|
| Platform | Shared PHP hosting (cPanel / DirectAdmin style) |
| Runtime | PHP 8.x (matching XAMPP local dev) |
| Database | MySQL / MariaDB |
| Web root | `public/` only — document root points here |
| Deployment style | Manual upload / promotion after CI passes |
| CI | GitHub Actions — `php -l` syntax checks only |
| Secrets | Hosting control panel env vars or `.htaccess SetEnv`; never committed |
| Rollback | Restore previous uploaded files + restore DB backup |
| Permissions | Production upload and DB import require a human action |

---

## Deployment constraints

- No Docker, no container runtime, no Composer install step unless hosting explicitly supports it.
- No automatic deploy pipeline. GitHub Actions verifies code; a human decides when to promote.
- `.htaccess` is relied on for directory-listing suppression and the root-deny safety net. Some
  hosts disable `AllowOverride` — verify this on first deploy.
- Environment variables are set via the hosting panel or `.htaccess SetEnv`. A `.env` file is
  gitignored and must never be committed.

---

## Known risks

- **Local/production parity gap** — XAMPP on Windows ≠ shared Linux hosting. PHP version, MySQL
  version, file-path case sensitivity, and session handling may differ. The first manual deploy
  should be treated as an exploratory test.
- **`.htaccess` support** — some shared hosts restrict `AllowOverride`. If `Require all denied`
  or `Options -Indexes` are ignored, the fallback protection evaporates silently.
- **No automated rollback** — rolling back requires manually uploading the previous file set and
  restoring a DB snapshot. There is no one-command undo.
- **No public URL yet** — the app has no live URL until manually deployed. CI passes on code
  quality only; it does not verify the app runs in a real hosting environment.
- **Single-server database** — no replica, no point-in-time recovery. A bad schema migration has
  no automated safety net. Apply migrations manually and keep a backup before any `ALTER TABLE`.

---

## Anti-bias analysis

### Devil's advocate — weaknesses of shared hosting for this app

- Shared hosts offer no resource isolation; a noisy neighbour can degrade PHP process latency.
- PHP version upgrades are at the host's discretion; you may be stuck on 8.0 while needing 8.2.
- SSH access and `rsync` are not guaranteed; FTP is error-prone and leaves no deploy log.
- Outgoing HTTP from PHP (if ever needed) may be blocked by the host's firewall.
- Cron jobs, background workers, and WebSockets are typically unavailable.

### Pre-mortem — why we might regret this choice in 3 months

- The app grows to multiple users. Shared hosting's single-database-user model becomes a
  security and isolation problem.
- The host upgrades MySQL in a way that breaks the current schema or charset assumptions.
- A manual deploy process causes a production outage when two people deploy at the same time
  or when a deploy is rushed and a missing file goes unnoticed.
- The host suspends the account for resource overuse, with no automated failover.

### Unknown unknowns — risks not covered by the initial decision

- Shared hosting session storage: PHP sessions are stored on disk by default. A host that
  uses a shared `/tmp` or aggressively cleans sessions could silently log users out.
- Opcache behaviour: shared hosts sometimes run PHP with opcache and aggressive TTLs.
  A deployed file may not be picked up until opcache expires.
- Charset/collation on the host's MySQL may default differently from `utf8mb4_unicode_ci`.
  Always set `NAMES utf8mb4` at connection time (the DSN already does this via `charset=utf8mb4`).
- File-system case sensitivity: `require 'Auth.php'` works on Windows/XAMPP but fails on a
  case-sensitive Linux host if the actual file is `auth.php`. PHP autoloading must match
  the exact case of every file name.

---

## Decision signal — when to revisit

Move to a VPS or managed PHP platform (e.g. DigitalOcean, Ploi, Forge + Linode) if:
- The app acquires more than one user.
- Deployment frequency exceeds once per week and manual FTP becomes a friction point.
- A post-deploy incident occurs that a staging environment would have caught.
- The host's PHP version falls more than one minor behind the latest stable.
