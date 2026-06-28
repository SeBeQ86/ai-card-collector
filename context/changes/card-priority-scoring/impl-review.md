# Implementation Review — card-priority-scoring

> **Retrospective as-built implementation review.**
> Written after implementation to assess how well the built code matches its own change record
> and plan. This was not a pre-merge gate review. No commit SHAs are referenced.

Reviewer: retrospective documentation pass
Change record: `context/changes/card-priority-scoring/change.md`
Plan: `context/changes/card-priority-scoring/plan.md`
Brief: `context/changes/card-priority-scoring/plan-brief.md`
Roadmap: `context/foundation/roadmap.md` (S-03)

---

## Review dimensions

---

### 1. Plan Adherence

**Verdict: PASS**

**Evidence:**
- `plan.md` Phase 1 specified `explain(array $card): array` returning `{language, status, price, age, total, terminal}`. `src/Card/CardScorer.php` implements exactly this signature and return shape.
- `plan.md` Phase 2 specified `<details class="score-details"><summary>` with `(int)` casts and a `<small>` breakdown. `public/index.php` matches this structure.
- `plan.md` Phase 3 specified `.score-details` styles and `white-space: nowrap` on `td:last-child` instead of `display:flex`. `public/assets/style.css` implements this.
- `plan.md` Phase 4 specified §12 in `docs/manual-test-plan.md` and Risk 2 in `docs/testing.md`. Both additions exist.

**Notes:**
All four phases from the plan were executed. No planned artifact is missing. The scope of changes matches the plan exactly — no extra files were touched.

---

### 2. Scope Discipline

**Verdict: PASS**

**Evidence:**
- `change.md` (Non-scope) lists: `CardRepository`, `Auth`, `Csrf`, database schema, CI, JavaScript additions. None of these were modified.
- `change.md` (Touched files) lists five files; the implementation touched exactly those five files.
- `roadmap.md` S-03 prerequisites are F-01 and F-02 (database + auth). Both are marked Done and were not modified.

**Notes:**
The implementation stayed strictly within the declared scope. No accidental expansion into adjacent files was identified. The non-scope list in `change.md` doubles as a regression checklist — login, logout, CRUD, and CSRF were all unaffected.

---

### 3. Safety & Quality

**Verdict: PASS**

**Evidence:**
- `change.md` (Key decision 3) documents the XSS escaping rule: component scores are `(int)` cast before echo in `public/index.php`, providing implicit protection even without `htmlspecialchars()`.
- `change.md` (Key decision 2) documents that terminal statuses `acquired`/`abandoned` return `total: 0` enforced inside `CardScorer`, not in the template — the safe path is the only path.
- `AGENTS.md` (Mandatory code patterns) requires `htmlspecialchars()` on all user-supplied output. The score breakdown values are computed integers, not user-supplied strings. The template echoes only `$ex['language']`, `$ex['status']`, `$ex['price']`, `$ex['age']` which are all `int` keys from `explain()`. Cast to `(int)` before echo.
- `AGENTS.md` (Forbidden actions) prohibits `eval()`, `exec()`, `system()`. None are present in the changed files.

**Notes:**
One nuance worth noting: the `explain()` method accepts a raw card DB row, which contains user-supplied strings (`language`, `status`, `created_at`). These are used in internal computation only and are never echoed. The only values echoed to HTML are the returned integer scores. This distinction is correctly documented in `change.md` but is easy to miss during a future modification to the score template.

---

### 4. Architecture

**Verdict: PASS**

**Evidence:**
- `change.md` (Key decision 1) confirms `calculate()` backward compatibility. Adding `explain()` as a new method on an existing class is the correct non-breaking extension pattern for this codebase.
- `change.md` (Key decision 4) documents the `\DateTimeImmutable` FQCN requirement inside `namespace App\Card`. This is a PHP namespace rule, not a project-specific choice — it is correctly documented so future contributors are not surprised.
- `plan.md` Phase 1 contract specifies that `explain()` calls `calculate()` internally rather than duplicating the scoring logic. This keeps the single source of truth for score computation inside `calculate()`.
- `CLAUDE.md` (Stack) requires plain PHP 8.x, no framework, no build step. The `<details>/<summary>` approach requires no JavaScript, consistent with the no-build-step constraint.

**Notes:**
The age-component derivation from `created_at` inside `explain()` (rather than accepting a pre-computed `ageInDays` like `calculate()` does) is a deliberate convenience for the dashboard render path. It means `explain()` and `calculate()` have slightly different input shapes, which could confuse a future contributor. The `plan.md` contract section for Phase 1 documents this, but a brief comment at the method signature in the source file would also be defensible.

---

### 5. Pattern Consistency

**Verdict: PASS**

**Evidence:**
- `AGENTS.md` (Page architecture): each page is its own PHP file in `public/`, no front controller. `public/index.php` continues this pattern — no routing changes introduced.
- `AGENTS.md` (Auth gate pattern): `Auth::startSession()` + `Auth::requireAuth()` at the top of every protected page. `public/index.php` retains this gate unchanged.
- `AGENTS.md` (DB queries): all queries use prepared statements. No new DB queries were introduced by this slice — `CardScorer::explain()` is pure computation with no DB access.
- Output escaping convention (`htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`) is the project standard. The score breakdown outputs only integers; explicit `htmlspecialchars` is not needed but the convention is not violated.
- `public/assets/style.css` uses CSS custom properties (`--text-muted`, `--border`, `--radius`, etc.) consistent with the existing variable set introduced in the UI polish pass.

**Notes:**
All patterns consistent with the codebase conventions documented in `AGENTS.md` and `CLAUDE.md`. No new patterns were introduced.

---

### 6. Success Criteria

**Verdict: PASS**

**Evidence (from `plan.md` success criteria checklist):**

| Criterion | Status |
|---|---|
| `explain()` returns correct breakdown for JP searching card | Met — language score for non-EN = 40 |
| `explain()` returns total 0 and `terminal: true` for `acquired`/`abandoned` | Met — terminal check enforced in `CardScorer` |
| Dashboard shows expandable score for every card row | Met — `<details>/<summary>` in `index.php` |
| Breakdown values match stored `difficulty_score` at save time | Met at save time; see Finding F-01 for drift |
| Breakdown requires no JavaScript | Met — native HTML element |
| No regression on add/edit/delete/login/logout | Met — non-scope files untouched |
| §12 of manual test plan passes | Verified manually against demo data |

**Notes:**
All seven success criteria from `plan.md` are met. Finding F-01 below captures the one
nuance (age-based drift) that is expected behaviour rather than a defect.

---

## Findings / Triage

---

### F-01 — Age-based score drift between stored value and live breakdown

**Severity:** Observation
**Impact:** Low

**Description:**
`difficulty_score` is stored in the database at card-save time. `explain()` recomputes the
score at render time, deriving age from `created_at` relative to the current date. As days
pass, the age component in the breakdown will increase, making the live breakdown total
higher than the stored `difficulty_score` for cards that have not been recently edited.

`database/demo-cards.sql` seeds pre-computed scores (e.g. Black Lotus JP = 85). After
deployment, the age component will increase and the `<details>` breakdown will show a total
that differs from 85 — the collector may notice the discrepancy if they look closely.

**Decision:** Accept risk — document as known risk
**Rationale:**
The drift is intentional: the breakdown reflects current urgency, which grows over time.
The stored score is a snapshot from the last save. This is documented in `plan-brief.md`
(Known risks, paragraph 2). No code change is needed; the behaviour is correct by design.
The risk to note is that if the two values are ever presented side by side in a future UI,
they should be labelled differently ("score at last save" vs. "current score").

---

### F-02 — Scoring constants and demo-cards.sql must stay aligned

**Severity:** Observation
**Impact:** Low

**Description:**
`database/demo-cards.sql` seeds `difficulty_score` values (85, 80, 50, 45, 0) that were
computed by hand to match `CardScorer::calculate()` at a specific point in time and with
specific age assumptions. If the scoring weights inside `CardScorer` are ever changed (e.g.
language rarity ceiling raised from 40 to 50), the seeded scores will be wrong and the demo
data will no longer illustrate the intended priority order.

**Decision:** Record as known risk / observation
**Rationale:**
The demo data is for local setup and screenshot purposes only — it has no impact on
production data. The risk is low. If scoring weights change in a future slice, `demo-cards.sql`
should be regenerated. Adding a comment to that file noting the scoring version it was
generated for would reduce future confusion.

---

### F-03 — Score template output escaping is correct but fragile under future edits

**Severity:** Warning
**Impact:** Low

**Description:**
The dashboard template echoes `$ex['language']`, `$ex['status']`, `$ex['price']`, `$ex['age']`
as `(int)` casts. These are returned as integers from `CardScorer::explain()`, so the cast is
safe. However, a future developer modifying the score cell to also display a human-readable
label (e.g. `echo $ex['language_label']` for "Japanese") might add a string value without
applying `htmlspecialchars()`, introducing an XSS vector.

`change.md` (Key decision 3) documents the current safety rationale, but the template itself
carries no inline comment drawing attention to the escaping constraint.

**Decision:** Verified; no code change needed
**Rationale:**
The current implementation is safe. The escaping contract is documented in `change.md`. The
risk is a future maintenance footgun, not a present defect. Per project convention, comments
are only added when the WHY is non-obvious (`CLAUDE.md`); the change record is the appropriate
place for this documentation, not the template source.

---

## Final verdict

**Approve**

All six review dimensions pass. Three findings are raised: two observations (accepted risk,
documented) and one warning with no code change required. The implementation is consistent
with the change record, plan, roadmap slice S-03, and the project conventions in `AGENTS.md`
and `CLAUDE.md`. No code changes are needed before this slice can be considered complete.
