# Submission Summary — AI Card Collector

## Project name

AI Card Collector

## Description

A personal, single-user web application for tracking wanted trading cards.
The collector logs cards they are searching for, records prices and seller contacts,
watches a computed difficulty score that prioritises the hardest-to-find cards,
and generates polite buyer messages in English and Portuguese to send to sellers.

Built with plain PHP 8.x, MySQL/MariaDB, server-rendered HTML, and minimal vanilla JS.
No framework, no Composer packages, no runtime AI dependency.

---

## 10xBuilder Certification Checklist

### Access control
- [x] Session-based email/password authentication (`src/Auth/Auth.php`)
- [x] Every protected page calls `Auth::requireAuth()` before rendering or DB work
- [x] Login uses `password_verify()` with bcrypt; session stores only `user_id` and `user_email`
- [x] Session regenerated on login (`session_regenerate_id(true)`)
- [x] Session cookie: `httponly`, `samesite=Strict`; `secure` flag enabled when `APP_ENV=production`
- [x] CSRF token on every state-changing form (login, logout, add, edit, delete)
- [x] Logout destroys session and expires the cookie

### CRUD
- [x] **Create** — `public/card-add.php` → `CardRepository::createForUser()`
- [x] **List** — `public/index.php` → `CardRepository::listForUser()`
- [x] **Read** — `public/card-edit.php` (GET) → `CardRepository::findForUser()`
- [x] **Update** — `public/card-edit.php` (POST) → `CardRepository::updateForUser()`
- [x] **Delete** — `public/card-delete.php` (POST) → `CardRepository::deleteForUser()`
- [x] Every query binds `user_id`; cross-user access is structurally impossible

### Business logic
- [x] Difficulty score computed by `CardScorer::calculate()` on every create and update
- [x] Four inputs: language rarity (0–40 pts), status urgency (0–40 pts), price pressure (0–10 pts), age in weeks (0–10 pts); maximum 100
- [x] Terminal statuses `acquired` / `abandoned` always return score 0
- [x] Score stored in DB; list sorted highest score first
- [x] Seller message generator: two PHP string templates, locales `en` and `pt`
- [x] Messages use card name, language edition, country, prices, and notes
- [x] No AI API call; no external network call of any kind

### User-perspective test
- [x] `docs/manual-test-plan.md` — 10 sections, 40+ numbered steps covering every user flow:
  login redirects, failed login, successful login, add card, list cards,
  edit card and status, delete card, generate messages, logout, post-logout access protection

### CI/CD
- [x] `.github/workflows/ci.yml` — triggers on `push` and `pull_request`
- [x] PHP 8.2 via `shivammathur/setup-php`
- [x] `php -l` syntax checks on `public/`, `src/`, `config/`
- [x] No automatic deployment; promotion to production is manual

### Context documents
- [x] `context/foundation/prd.md` — product requirements
- [x] `context/foundation/tech-stack.md` — tech stack hand-off
- [x] `CLAUDE.md` — project conventions and AI agent rules
- [x] `AGENTS.md` — writable paths, forbidden actions, mandatory patterns
- [x] `docs/manual-test-plan.md` — user-flow test plan
- [x] `docs/deployment.md` — shared-hosting deployment notes

---

## Local setup summary

1. Clone the repository into XAMPP `htdocs` (e.g. `D:\xampp\htdocs\ai-card-collector`).
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Create a database (e.g. `ai_card_collector`) in phpMyAdmin.
4. Import `database/schema.sql`.
5. Generate a bcrypt hash for a throwaway local password:
   ```
   D:\xampp\php\php.exe -r "echo password_hash('your-password', PASSWORD_BCRYPT) . PHP_EOL;"
   ```
6. Insert the user:
   ```sql
   INSERT INTO users (email, password_hash) VALUES ('your@email.com', '<hash>');
   ```
7. Open `http://localhost/ai-card-collector/public/` in a browser.

No Composer install. No npm. No build step.

---

## Manual test summary

Full plan: `docs/manual-test-plan.md`

Key flows to verify before submission:

| Flow | Entry point |
|------|-------------|
| Unauthenticated redirect | Open `index.php` without login → redirected to `login.php` |
| Failed login | Wrong password → generic error, no session |
| Successful login | Correct credentials → `index.php` with email in header |
| Add card | Fill form, submit → card in list with difficulty score |
| Edit card / change status | Update status to `acquired` → score drops to 0 |
| Delete card | Confirm dialog → card removed from list |
| Seller messages | Click Message → EN and PT read-only textareas pre-filled |
| Logout | Click Log out → session gone, back button redirects to login |

---

## CI summary

GitHub Actions runs one job (`syntax`) on every push and pull request:

```
actions/checkout@v4
shivammathur/setup-php@v2  (PHP 8.2)
find public/ -name "*.php" | xargs -n1 php -l
find src/    -name "*.php" | xargs -n1 php -l
find config/ -name "*.php" | xargs -n1 php -l
```

No deploy step. No secrets required. Badge can be added to README once the repository is on GitHub.

---

## Showcase improvements (beyond core MVP)

- **Score breakdown** — clicking the score number in the wanted-cards table expands a compact
  breakdown: `Lang +N · Status +N · Price +N · Age +N`. Implemented as a `<details>/<summary>`
  element; no JavaScript required. Powered by `CardScorer::explain(array $card): array`.

- **Copy buttons** — the seller-message page has a **Copy** button below each textarea.
  Clicking it copies the full message to the clipboard (uses `navigator.clipboard` with an
  `execCommand` fallback) and briefly shows "Copied!". Textareas remain fully usable without JS.

- **Demo data** — `database/demo-cards.sql` inserts five sample cards (different languages,
  statuses, prices, and pre-computed scores) for the seeded local user. Useful for populating
  the app before taking screenshots or running a demo.

---

## Change records

One representative as-built change record is stored in `context/changes/card-priority-scoring/`.
A representative implementation review is stored in `context/changes/card-priority-scoring/impl-review.md`.
Risk-based QA strategy is documented in `context/foundation/test-plan.md`.
`CardScorer` has a minimal automated business-logic guard (`tests/CardScorerTest.php`) that runs in CI on every push.

---

## MVP roadmap

The as-built MVP roadmap (foundations, slices, backlog handoff, parked items) is documented in
`context/foundation/roadmap.md`. It covers 5 foundations (F-01–F-05) and 5 user-visible slices
(S-01–S-05), all marked Done.

---

## Deployment planning

Deployment to shared PHP hosting is documented in two read-only context files:

- `context/foundation/infrastructure.md` — platform decision, known risks, anti-bias analysis
  (devil's advocate, pre-mortem, unknown unknowns), and decision signal for when to move to a
  VPS or managed platform.
- `context/deployment/deploy-plan.md` — step-by-step manual deploy checklist with five human
  approval gates (local verification, CI green, DB backup, web root check, smoke test) and
  explicit rollback steps.

---

## Known limitations / non-goals

These are intentional boundaries of the MVP scope, not bugs:

| Area | Status |
|------|--------|
| Cardmarket API integration | Not implemented — out of scope |
| Marketplace scraping | Not implemented — out of scope |
| Runtime AI / LLM dependency | Not implemented — seller messages are PHP templates only |
| Mobile app | Not implemented — responsive HTML only |
| Automatic deployment | Not implemented — manual promotion after merge to `main` |
| Multi-user registration | Not implemented — single account seeded directly |
| OCR / card image recognition | Not implemented — out of scope |
| Payment processing | Not implemented — out of scope |

---

## Suggested screenshots for submission

1. **Login page** — shows the form before authentication.
2. **Wanted cards list** — several cards visible with different difficulty scores, statuses, and the sorted order (highest score first).
3. **Add card form** — filled in with a non-English language and a target price.
4. **Edit card form** — status dropdown open, showing all five status values.
5. **Seller message page** — both EN and PT textareas filled, card summary visible above.
6. **GitHub Actions run** — CI job passing (green check) on a recent push.
