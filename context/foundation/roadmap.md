# Roadmap — AI Card Collector

_As-built MVP retrospective roadmap. Written after implementation to document what was built,
in what order, and why. This is a record, not a plan._

---

## Vision recap

A personal, single-user tool for a trading-card collector. The collector logs cards they are
searching for, tracks status and seller contacts, watches a computed difficulty score, and
generates polite buyer messages in English and Portuguese — all without marketplace APIs,
runtime AI, or registration flows.

---

## North star

> Collector can log a wanted card, understand its priority, and generate a seller message
> to act on it.

---

## At a glance

| ID | Name | Type | Status |
|---|---|---|---|
| F-01 | minimal-database-and-config | Foundation | Done |
| F-02 | session-auth-and-csrf | Foundation | Done |
| F-03 | ci-and-deployment-docs | Foundation | Done |
| F-04 | agent-onboarding-and-lessons | Foundation | Done |
| F-05 | deployment-planning | Foundation | Done |
| S-01 | wanted-card-create-and-list | Slice | Done |
| S-02 | wanted-card-edit-and-delete | Slice | Done |
| S-03 | card-priority-scoring | Slice | Done |
| S-04 | seller-message-generator | Slice | Done |
| S-05 | showcase-and-demo-flow | Slice | Done |

---

## Baseline

> What the codebase and environment provide before any product work.

- XAMPP on Windows (`D:\xampp\htdocs\ai-card-collector`)
- PHP 8.x at `D:\xampp\php\php.exe`
- MySQL / MariaDB via XAMPP
- `public/` as sole web root; Apache serves nothing outside it
- `.htaccess` root-deny + `public/.htaccess` grants access and suppresses directory listing
- No framework, no Composer packages, no npm, no build step
- Manual PSR-4 autoloader in `src/bootstrap.php` mapping `App\` → `src/`

---

## Foundations

Foundations are cross-cutting infrastructure that must exist before slices can be built or
deployed. None is user-visible on its own.

---

### F-01 — minimal-database-and-config

**Change ID:** bootstrap-step-1

**Outcome:** Database schema, PHP config, and PDO connection exist.

| Artifact | Path |
|---|---|
| Schema | `database/schema.sql` |
| Seed (placeholder hash) | `database/seed.sql` |
| Config | `config/app.php` |
| PDO singleton | `src/Database/Connection.php` |
| Autoloader | `src/bootstrap.php` |

Key decisions:
- `wanted_cards.status` is an ENUM: `searching` | `contacted` | `offer_received` | `acquired` | `abandoned`
- FK `fk_wanted_cards_user` with `ON DELETE CASCADE`
- PDO: `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`, `charset=utf8mb4`
- Config reads from `getenv()` with XAMPP fallbacks; never committed secrets

**Unlocks:** S-01 (cards can be stored and retrieved)

---

### F-02 — session-auth-and-csrf

**Change ID:** bootstrap-step-2

**Outcome:** User can log in and out; all protected pages require an authenticated session;
all state-changing forms carry and validate a CSRF token.

| Artifact | Path |
|---|---|
| Auth class | `src/Auth/Auth.php` |
| CSRF class | `src/Security/Csrf.php` |
| Login page | `public/login.php` |
| Logout handler | `public/logout.php` |

Key decisions:
- `Auth::startSession()` sets `httponly`, `samesite=Strict`; `secure` flag only when `APP_ENV=production`
- `Auth::requireAuth()` redirects to `login.php` for any unauthenticated request — no exceptions
- `password_verify()` + bcrypt; session stores only `user_id` and `user_email`, never the hash
- `session_regenerate_id(true)` on login
- CSRF token rotated on every validation call (unset before `hash_equals`, even on failure)

**Unlocks:** S-01, S-02, S-03, S-04 (every slice requires an authenticated session)

---

### F-03 — ci-and-deployment-docs

**Change ID:** bootstrap-step-7

**Outcome:** GitHub Actions syntax checks run on every push; deployment and testing
documentation exist for human reviewers.

| Artifact | Path |
|---|---|
| CI workflow | `.github/workflows/ci.yml` |
| Manual test plan | `docs/manual-test-plan.md` |
| Deployment notes | `docs/deployment.md` |
| Testing overview | `docs/testing.md` |

Key decisions:
- `php -l` on `public/`, `src/`, `config/` — no deploy step, no Docker, no external credentials
- Manual test plan has 12 sections including two named risk scenarios:
  Risk 1 — unauthenticated / wrong-user access
  Risk 2 — scoring correctness
- No automated deployment; promotion to production is a human gate

**Unlocks:** submission readiness (project cannot be submitted without a passing CI run and test plan)

---

### F-04 — agent-onboarding-and-lessons

**Change ID:** agent-onboarding (post-certification)

**Outcome:** `CLAUDE.md`, `AGENTS.md`, and `context/foundation/lessons.md` accurately reflect
the real codebase and guide future agent or human work without misleading anyone.

| Artifact | Path |
|---|---|
| Project conventions | `CLAUDE.md` |
| Agent rules | `AGENTS.md` |
| Lessons learned | `context/foundation/lessons.md` |

Key decisions:
- Both rule files corrected to match actual implementation (folder layout, auth/CSRF patterns,
  page architecture, message template location)
- `lessons.md` is append-only; six lessons recorded covering SQL truncation, seed hash safety,
  Composer constraints, web root discipline, post-audit caution, and rule-file accuracy

**Unlocks:** safer future iterations (agents and developers start with accurate constraints)

---

### F-05 — deployment-planning

**Change ID:** m1l5-deployment (post-certification)

**Outcome:** A human can deploy the app to shared PHP hosting by following a documented plan
with defined approval gates and rollback steps.

| Artifact | Path |
|---|---|
| Platform decision | `context/foundation/infrastructure.md` |
| Deploy checklist | `context/deployment/deploy-plan.md` |

Key decisions:
- Shared PHP hosting (cPanel / DirectAdmin) — no Docker, no managed cloud
- Five human approval gates: local syntax check, CI green, pre-deploy DB backup, web root
  verification, post-deploy smoke test
- Anti-bias section in `infrastructure.md`: devil's advocate weaknesses, pre-mortem,
  unknown unknowns (opcache, session storage, case-sensitivity on Linux)

**Unlocks:** future public deployment (without this plan, the first deploy is undocumented)

---

## Slices

Slices are vertical, user-visible increments. Each delivers a complete end-to-end behaviour.

---

### S-01 — wanted-card-create-and-list

**Change ID:** bootstrap-step-3

**Outcome:** Logged-in collector can add a wanted card and see it on the dashboard, sorted by
difficulty score (highest first).

**Prerequisites:** F-01, F-02

| Artifact | Path |
|---|---|
| Card repository | `src/Card/CardRepository.php` (`createForUser`, `listForUser`) |
| Difficulty scorer | `src/Card/CardScorer.php` (`calculate`) |
| Wanted-cards list | `public/index.php` |
| Add-card form | `public/card-add.php` |

User-visible outcome:
- Dashboard shows card name, language, status, target price, and difficulty score
- Form validates required fields; score is computed on save and stored
- All queries bind `user_id`; cross-user access is structurally impossible

---

### S-02 — wanted-card-edit-and-delete

**Change IDs:** bootstrap-step-4 (edit), bootstrap-step-5 (delete)

**Outcome:** Collector can update card details or status (including marking a card
`acquired` or `abandoned`), and can delete obsolete cards.

**Prerequisites:** S-01

| Artifact | Path |
|---|---|
| Repository methods | `src/Card/CardRepository.php` (`findForUser`, `updateForUser`, `deleteForUser`) |
| Edit page | `public/card-edit.php` |
| Delete handler | `public/card-delete.php` |

User-visible outcome:
- Edit form pre-populated from DB; score recomputed on every save
- Delete is POST-only with CSRF; always redirects to `index.php`
- Status change to `acquired`/`abandoned` immediately drops score to 0

---

### S-03 — card-priority-scoring

**Change IDs:** bootstrap-step-3 (initial scoring), bootstrap-step-10 (score explanation)

**Outcome:** Collector sees a priority score and an expandable score breakdown for each
card on the dashboard.

**Prerequisites:** S-01

| Artifact | Path |
|---|---|
| Scorer | `src/Card/CardScorer.php` (`calculate`, `explain`) |
| Dashboard score cell | `public/index.php` (`<details>/<summary>` breakdown) |

Score model (active: 0–155, terminal: always 0):
- Language rarity: 0–35 pts (JP/TH/PT/ID=35; FR/DE/ES/KR/RU/PL/ZH=20; EN=0)
- Status urgency: 0–40 pts (`searching`=40, `contacted`=25, `offer_received`=10, terminal=0)
- Price pressure: 0–25 pts (offer > budget=25; budget set no offer=15; no data=8; within budget=0)
- Age urgency: 0–15 pts (+1 per 5 days unresolved, capped at 15)
- Market pressure: 0–40 pts (budget/market coverage: ≥100%=0, 85–100%=+10, 70–85%=+20, 50–70%=+30, <50%=+40)

User-visible outcome:
- Score badge with tooltip: `Język +N · Status +N · Cena +N · Wiek +N · Rynek +N`
- Market price refreshed via "Odśwież ceny" button (TCGdex API, Cardmarket avg30 EN)

---

### S-04 — seller-message-generator

**Change ID:** bootstrap-step-6

**Outcome:** Collector can view a card's page and copy a ready-to-send EN or PT seller
message without any manual text composition.

**Prerequisites:** S-01

| Artifact | Path |
|---|---|
| Message generator | `src/Message/SellerMessageGenerator.php` |
| Message page | `public/card-message.php` |

Key decisions:
- Two pure PHP string templates; no network call, no AI API, no runtime dependency
- Locales: `en` and `pt`; price formatted as `1,234.56` (EN) and `1.234,56` (PT)
- Copy buttons use `navigator.clipboard` with `execCommand` fallback; textareas remain
  usable without JS

---

### S-05 — showcase-and-demo-flow

**Change IDs:** bootstrap-step-9 (UI polish), bootstrap-step-10 (showcase extras),
agent-onboarding (docs), m1l5-deployment (submission docs)

**Outcome:** A reviewer, demo audience, or future maintainer can quickly understand and
evaluate the project.

**Prerequisites:** S-01, S-02, S-03, S-04

| Artifact | Path |
|---|---|
| Full CSS | `public/assets/style.css` |
| Demo card data | `database/demo-cards.sql` |
| Demo script | `docs/demo-script.md` |
| Submission summary | `docs/submission-summary.md` |

User-visible outcome:
- CSS variables, responsive table, pill-style action links, score breakdown styling
- Five demo cards (varied languages, statuses, prices, scores) ready to import
- Submission summary covers all 10xBuilder certification checks

---

## Backlog handoff

All slices above are **Done**. Future changes to this project should be opened as new change
contexts using `/10x-plan` with a new change ID. Do not modify existing change IDs.

Suggested next change IDs (if needed):
- `m2-playwright-e2e` — implement `tests/e2e/` Playwright tests (currently planned, not built)
- `m2-multi-language-ui` — add more seller-message locales
- `m2-export` — CSV or JSON export of the wanted-cards list

---

## Open roadmap questions

These were intentionally deferred and have no planned answer:

- Should difficulty scoring weights be user-configurable (stored in DB) or remain fixed?
- Should the seller-message page support a custom greeting/signature per locale?
- Is a second user account (read-only) needed for demo purposes without sharing credentials?
- Should `demo-cards.sql` be safe to re-import (idempotent) or does it always insert new rows?

---

## Parked

The following were explicitly ruled out of MVP scope. Revisit only if the project's purpose
changes substantially.

| Item | Reason parked |
|---|---|
| Cardmarket API integration | Requires marketplace credentials and adds external dependency |
| Marketplace scraping | Fragile, legally ambiguous, out of scope |
| Runtime AI / LLM API calls | Seller messages are PHP templates; no runtime AI needed |
| Payment processing | Out of scope for a personal collector tool |
| Public profiles / card exchange | Single-user tool; no social features |
| User registration | Single account seeded directly; no sign-up route |
| Laravel / React / Vue migration | Stack is intentionally plain PHP; no framework |
| Automated deployment | Manual promotion after CI; no CD pipeline |
| Mobile app | Responsive HTML only |
| OCR / card image recognition | Out of scope |

---

## Done

| ID | Name | Change ID |
|---|---|---|
| F-01 | minimal-database-and-config | bootstrap-step-1 |
| F-02 | session-auth-and-csrf | bootstrap-step-2 |
| F-03 | ci-and-deployment-docs | bootstrap-step-7 |
| F-04 | agent-onboarding-and-lessons | agent-onboarding |
| F-05 | deployment-planning | m1l5-deployment |
| S-01 | wanted-card-create-and-list | bootstrap-step-3 |
| S-02 | wanted-card-edit-and-delete | bootstrap-step-4, bootstrap-step-5 |
| S-03 | card-priority-scoring | bootstrap-step-3, bootstrap-step-10 |
| S-04 | seller-message-generator | bootstrap-step-6 |
| S-05 | showcase-and-demo-flow | bootstrap-step-9, bootstrap-step-10, agent-onboarding, m1l5-deployment |
