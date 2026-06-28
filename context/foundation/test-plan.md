# Test Plan — AI Card Collector

_Risk-based QA strategy for the plain PHP MVP. Written as a living document; update when
risks change or new gates are introduced._

---

## Purpose

This plan defines what must be protected first and why — prioritised by the actual risk
profile of a single-user, server-rendered PHP application, not by what is easiest to
automate.

It complements two existing documents:
- `docs/manual-test-plan.md` — the step-by-step manual test checklist (12 sections, 40+
  steps covering all user flows and two named risk scenarios)
- `docs/testing.md` — named risks and mitigations in plain language

Together, these three documents form the complete QA record for the MVP. Future automated
tests should extend this plan, not replace it.

---

## Current test baseline

| Layer | What exists | Status |
|---|---|---|
| Manual tests | `docs/manual-test-plan.md` — 12 sections, login through post-logout | In use |
| Testing notes | `docs/testing.md` — Risk 1 (auth/access) and Risk 2 (scoring) | In use |
| CI syntax check | `.github/workflows/ci.yml` — `php -l` on `public/`, `src/`, `config/` | Active |
| CardScorer CLI guard | `tests/CardScorerTest.php` — 28 assertions, no framework, runs in CI | Active |
| Automated unit tests | PHPUnit / Pest — not installed | Not present |
| Automated E2E tests | Playwright — planned in `tests/e2e/`, not implemented | Not present |

The absence of an automated test runner is intentional for the current MVP. The manual
test plan and CI syntax check are sufficient for a single-user tool at this scope.
Future automated tests should be introduced risk-driven: start with `CardScorer` unit
tests (pure functions, deterministic, no I/O) before adding heavier E2E tooling.

---

## Risk map

**Priority key:** P1 = protect now · P2 = protect before next feature · P3 = future / low urgency

| # | Risk | Impact | Likelihood | Priority | Protected by | Source |
|---|---|---|---|---|---|---|
| R-01 | Unauthenticated access to protected card pages | High | High | P1 | Manual test §1, §10; `Auth::requireAuth()` in every handler; code review | PRD, AGENTS.md, manual test plan |
| R-02 | Cross-user access / IDOR by changing `card_id` in URL or POST | High | High | P1 | Manual test §11; `user_id` bound in every DB query; code review | Implementation review F-03 (adjacent risk), AGENTS.md, lessons.md L-004 |
| R-03 | CSRF bypass on POST actions (add, edit, delete, logout) | High | Medium | P1 | Manual test §11; `Csrf::validate()` on every state-changing handler; code review | AGENTS.md, security rules in CLAUDE.md |
| R-04 | Stored/reflected XSS from card fields on dashboard or message pages | High | Medium | P1 | `htmlspecialchars()` on all output; code review; manual test (payload in card name) | AGENTS.md, CLAUDE.md security rules |
| R-05 | Priority scoring regression — `acquired`/`abandoned` returning non-zero | Medium | Low | P2 | Manual test §12; `CardScorer` terminal-status guard; implementation review | Roadmap S-03, plan.md, impl-review.md |
| R-06 | Score explanation drifting from `calculate()` behaviour | Medium | Low | P2 | Manual test §12; `explain()` calls `calculate()` internally; implementation review F-01 | impl-review.md F-01, plan-brief.md |
| R-07 | Seller message generator accidentally depending on runtime AI / network call | High | Low | P1 | Code review; `SellerMessageGenerator` has no `curl`/`file_get_contents`/`socket` calls; AGENTS.md forbidden actions | CLAUDE.md, AGENTS.md, PRD non-goals |
| R-08 | Demo/seed data containing real password hashes committed to version control | High | Medium | P1 | `seed.sql` placeholder check; CI could add a grep gate; lessons.md L-002 | lessons.md L-002, deployment plan Gate 1 |
| R-09 | Deployment exposing non-public directories (`config/`, `src/`, `database/`, `docs/`, `.claude/`) outside `public/` | High | Medium | P1 | Root `.htaccess` deny; deployment plan web-root check (Gate 4); manual smoke test | CLAUDE.md, deploy-plan.md, infrastructure.md |

### Notes on R-02 (IDOR)

IDOR is the most common class of access-control failure in single-table CRUD apps. Every
`CardRepository` method binds both `id` and `user_id` in WHERE clauses. A future contributor
adding a new query must follow this pattern or R-02 is re-opened. The AGENTS.md mandatory
pattern section documents this constraint.

### Notes on R-08 (seed hash)

`database/seed.sql` currently contains a placeholder string rather than a real hash
(`$2y$12$REPLACE_THIS_WITH_OUTPUT_OF_password_hash_FUNCTION`). The deployment plan Gate 1
includes a `grep` check for this. A future CI step could enforce it automatically.

---

## Security abuse scenarios

These are the concrete attack paths the test plan must cover. Manual test §11 exercises
most of these; they are listed here as a security-lens reference.

### Scenario A — IDOR: user A accesses user B's card

1. Log in as the seeded user.
2. Note a card ID (e.g. `id=3`).
3. Log out, create a second session (or modify `user_id` in the session manually in a
   dev environment).
4. Attempt `GET /card-edit.php?id=3` and `POST /card-delete.php` with `id=3`.
5. **Expected:** `card-edit.php` returns a redirect (card not found for this user);
   `card-delete.php` redirects without deleting.

### Scenario B — CSRF: forged POST on delete or edit

1. Construct a form on a third-party page that POSTs to `card-delete.php` with a valid
   `card_id` but no or forged `csrf_token`.
2. Trick the logged-in user into submitting it.
3. **Expected:** `Csrf::validate()` fails; redirect without deletion.

### Scenario C — HTML/JS payload in card name or notes

1. Add a card with name `<script>alert(1)</script>` or notes containing `"><img src=x>`.
2. Load `index.php` (dashboard) and `card-message.php`.
3. **Expected:** The literal string is displayed, not executed. `htmlspecialchars()` renders
   `&lt;script&gt;` in the source.

### Scenario D — Direct browser access to non-public directories

1. Request `http://localhost/ai-card-collector/config/app.php` directly.
2. Request `http://localhost/ai-card-collector/database/schema.sql`.
3. Request `http://localhost/ai-card-collector/.claude/` or `src/`.
4. **Expected:** 403 Forbidden (root `.htaccess` `Require all denied`) or 404.

---

## Quality gates

### Current gates (active)

| Gate | Trigger | Checked by |
|---|---|---|
| PHP syntax check | Every push / PR via GitHub Actions | `.github/workflows/ci.yml` |
| Manual smoke test | Before submission / before deploy | `docs/manual-test-plan.md` §1–§10 |
| Auth / CRUD / scoring / message checks | Before submission | `docs/manual-test-plan.md` §11–§12 |
| Rule-file review | When `AGENTS.md` or `CLAUDE.md` changes | Code review against actual codebase |
| Secrets / hash check | Before deploy | `deploy-plan.md` Gate 1 (`grep "REPLACE_THIS"`) |
| Non-public directory check | After every deploy | `deploy-plan.md` Gate 4 (web root verification) |

### Future optional gates (not currently implemented)

| Gate | What it would cover | When to introduce |
|---|---|---|
| Full PHPUnit suite for `CardScorer` | Exhaustive `calculate()` combos; property-based age tests | Before any scoring-weight change (minimal guard already active) |
| Integration-style PHP checks for repository user scoping | Confirm `findForUser`, `updateForUser`, `deleteForUser` reject wrong `user_id` | Before adding a second user or a multi-user feature |
| Browser smoke test (login → add → score → message → delete) | Full happy-path regression via Playwright in `tests/e2e/` | Before adding a second public deployment |
| CI grep for seed hash placeholder | Verify `seed.sql` never contains a real `$2y$` hash | Low effort; add to `ci.yml` when convenient |

Do not treat future gates as currently active. Mark them "planned" or "parked" until the
relevant test runner is intentionally introduced.

---

## Test exclusions — not worth testing now

| Area | Reason |
|---|---|
| Cosmetic CSS details (colours, spacing, font sizes) | Subjective; no business rule to verify |
| Browser matrix (Safari, Firefox, Edge compatibility) | Single-user personal tool; Chrome/Chromium sufficient for now |
| External marketplace / API behaviour | Not implemented; no integration exists |
| Runtime AI behaviour | The app does not use runtime AI; `SellerMessageGenerator` is pure PHP templates |
| Payment flows | Non-goal; not in MVP scope |
| Scraping behaviour | Non-goal; not in MVP scope |
| Public profiles / card exchange | Non-goal; not in MVP scope |
| User registration | Non-goal; single account seeded directly |

These exclusions match the "Parked" section in `context/foundation/roadmap.md`. If a parked
item is ever un-parked, its test coverage should be designed before implementation starts.

---

## Cookbook patterns

Lightweight guidance for anyone writing new tests or extending this plan:

**Prefer user-visible behaviour over implementation detail.**
A test that asserts `CardScorer::calculate('JP', 'searching', null, null, 0) === 40` is
testing the language-rarity rule, not the implementation. A test that asserts the return
equals `$scorer->languageScore('JP')` (mirroring the private helper) is not a test — it
just copies the code.

**Expected values must come from documented rules, not from reading `calculate()` and
mirroring it.**
`docs/business-rules.md` and `src/Card/CardScorer.php` define the scoring model:
language rarity 0–35, status urgency 0–40, price pressure 0–25, age urgency 0–15,
market pressure 0–40, max 155, terminal statuses always 0. These are the ground truth.
If a test fails because `calculate()` was changed and the expected value was derived from
the old code, the test has correctly caught a regression.

**For auth and security, always include negative paths.**
A test that only verifies "logged-in user can view card" does not cover R-01 or R-02. Every
auth/access test should have a paired negative: "unauthenticated user is redirected",
"user B cannot view user A's card".

**Keep manual tests in `docs/manual-test-plan.md` unless/until an automated runner is
intentionally introduced.**
Do not convert manual test steps into ad-hoc PHP scripts or inline `var_dump` checks
scattered across the codebase. The manual plan is the current passing bar; maintain it
as a first-class document.

**Do not mark a gate as active until its tooling is installed and the gate is enforced.**
The risk map and quality-gates table should reflect the real current state, not aspirations.
A future gate listed under "future optional gates" must not appear in the "current gates"
table until it passes on CI or is validated by a human in every release.

---

## Rollout status

| Phase | Description | Status |
|---|---|---|
| Phase 1 | Risk map defined; manual test plan covers R-01–R-09; CI syntax check active | Completed |
| Phase 2 | Minimal automated CardScorer guard (`tests/CardScorerTest.php`, 28 assertions, runs in CI) | Completed |
| Phase 2b | Full PHPUnit / Pest suite for `CardScorer` with exhaustive combos and property-based age tests | Parked / future |
| Phase 3 | Automated browser smoke test for full user flow (Playwright in `tests/e2e/`) | Parked / future |
