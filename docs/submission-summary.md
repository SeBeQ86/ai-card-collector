# Submission Summary — AI Card Collector

## Project name

AI Card Collector

## Description

A personal, single-user web application for tracking wanted trading cards.
The collector logs cards they are searching for, records prices and seller contacts,
watches a computed difficulty score that prioritises the hardest-to-find cards,
and generates polite buyer messages in six languages to send to sellers.

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
- [x] Every query binds `user_id`; cross-user access is structurally impossible (IDOR guard tested in `tests/IntegrationTest.php`)

### Business logic
- [x] Difficulty score computed by `CardScorer::calculate()` on every create and update
- [x] Five inputs: language rarity (0–35 pts), status urgency (0–40 pts), price pressure (0–25 pts), age urgency (0–15 pts), market pressure (0–40 pts); maximum 155
- [x] Terminal statuses `acquired` / `abandoned` always return score 0
- [x] Score stored in DB; list sorted highest score first
- [x] Seller message generator: 6 PHP string templates — EN, DE, FR, ES, PT, JA
- [x] Messages use card name, language edition, country, prices, and notes
- [x] Custom templates editable per locale in DB (`message-templates.php`); override built-in fallbacks via `{{token}}` substitution
- [x] No AI API call; no external network call for messages
- [x] Market prices refreshed on demand via TCGdex API (`api/price-refresh.php`)

### User-perspective test
- [x] `tests/e2e/smoke.spec.ts` — 6 Playwright E2E tests covering: unauthenticated redirect, valid login, wrong password error, dashboard table visible, add-form reachable, session clearance → login redirect
- [x] `docs/manual-test-plan.md` — 10 sections, 40+ numbered steps covering every user flow

### CI/CD
- [x] `.github/workflows/ci.yml` — triggers on `push` and `pull_request`
- [x] **Job 1: lint-unit-build** — PHP syntax check (`php -l`), PHPStan level 1, unit tests (`CardScorerTest.php`)
- [x] **Job 2: integration** — MySQL service container, `IntegrationTest.php` (CardRepository CRUD + IDOR guard, 15 assertions)
- [x] **Job 3: e2e** — MySQL service container, PHP built-in server, Playwright Chromium (`smoke.spec.ts`)
- [x] AI Code Review job — Claude Haiku reviews every PR diff and posts a scored comment
- [x] No automatic deployment; promotion to production is manual

### Context documents
- [x] `context/foundation/prd.md` — product requirements (FR-001–FR-013)
- [x] `context/foundation/tech-stack.md` — tech stack hand-off
- [x] `CLAUDE.md` — project conventions and AI agent rules
- [x] `docs/manual-test-plan.md` — user-flow test plan
- [x] `docs/deployment.md` — shared-hosting deployment notes
- [x] `context/deployment/deploy-plan.md` — step-by-step manual deploy checklist with approval gates and rollback

---

## Local setup summary

1. Clone the repository into XAMPP `htdocs` (e.g. `D:\xampp\htdocs\ai-card-collector`).
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Create a database (e.g. `ai_card_collector`) in phpMyAdmin.
4. Import `database/schema.sql`, then run migrations `database/migrations/001` through `004` in order.
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
| Seller messages | Click Message → 6-language grid with copy buttons |
| Logout | Click Log out → session gone, back button redirects to login |

---

## CI summary

GitHub Actions runs 4 jobs on every push and pull request:

| Job | What it checks |
|-----|---------------|
| lint-unit-build | `php -l` syntax, PHPStan level 1, `CardScorerTest` (3 unit tests) |
| integration | CardRepository CRUD + IDOR guard against real MySQL (15 assertions) |
| e2e | 6 Playwright smoke tests against PHP built-in server + MySQL |
| AI Code Review | Claude Haiku scores the diff and posts a comment on the PR |

Screenshot of all 7 checks passing available in PR #4 on GitHub.

---

## Showcase improvements (beyond core MVP)

- **TCGdex autocomplete** — card name field in the add/edit form fetches suggestions from `api.tcgdex.net/v2` as you type; selecting a result auto-fills the card image URL and API ID.
- **Market price refresh** — "Odśwież ceny" button on the wanted list calls `api/price-refresh.php`, which fetches current Cardmarket prices via TCGdex for all active cards with a linked `api_card_id` and recomputes difficulty scores.
- **6-language seller messages** — EN, DE, FR, ES, PT, JA; displayed in a responsive 2-column grid with per-message copy buttons.
- **Editable templates** — `message-templates.php` lets the collector customise any locale's template with `{{token}}` placeholders; stored in DB, override built-in fallbacks at render time.
- **Score breakdown** — clicking the score in the list expands a compact breakdown: `Lang +N · Status +N · Price +N · Age +N · Market +N`.
- **Deal archive** — `deals.php` lists all acquired cards with purchase price, date, and source URL.
- **Integration tests** — `tests/IntegrationTest.php` tests CardRepository CRUD and IDOR guard against a real MySQL database in CI.
- **E2E tests** — `tests/e2e/smoke.spec.ts` (Playwright) runs 6 browser-level smoke tests in CI.

---

## Change records

Representative as-built change records are stored in `context/changes/`:
- `card-priority-scoring/` — difficulty score implementation and review
- `bootstrap-verification/verification.md` — initial project bootstrap check

Risk-based QA strategy: `context/foundation/test-plan.md`.

---

## MVP roadmap

The as-built MVP roadmap is documented in `context/foundation/roadmap.md`.
Five foundations (F-01–F-05) and five user-visible slices (S-01–S-05), all marked Done.

---

## Deployment planning

- `context/foundation/infrastructure.md` — platform decision, known risks, pre-mortem, anti-bias analysis
- `context/deployment/deploy-plan.md` — step-by-step manual deploy checklist with five approval gates and rollback steps

---

## Known limitations / non-goals

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
2. **Wanted cards list** — several cards with different difficulty scores, statuses, sorted highest first.
3. **Add card form** — filled with a non-English language, target price, and TCGdex autocomplete active.
4. **Edit card form** — status dropdown open showing all five values; funnel callout visible.
5. **Seller message page** — 6-language grid with card info bar and copy buttons.
6. **GitHub Actions run** — all 7 checks green (screenshot from PR #4).
