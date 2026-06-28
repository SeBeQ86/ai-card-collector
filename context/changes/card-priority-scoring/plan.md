# Implementation Plan — card-priority-scoring

> **Retrospective as-built implementation plan.**
> Written after the feature was implemented. Reflects what was actually built.

---

## End state

- `CardScorer::explain(array $card): array` exists and returns a map of per-component integer
  scores plus a `terminal` boolean and a `total`.
- `public/index.php` renders each card's score as an expandable `<details>/<summary>` showing
  the four components.
- `public/assets/style.css` styles the `.score-details` element.
- `docs/manual-test-plan.md` includes a scoring-correctness test section (§12).
- `docs/testing.md` names scoring correctness as Risk 2.

---

## Phases

### Phase 1 — Extend `CardScorer`

**Files:** `src/Card/CardScorer.php`

**Intent:** Add a `explain()` method that accepts a full card DB row and returns the
per-component breakdown without breaking the existing `calculate()` call sites.

**Contract:**
- Input: `array $card` — a row from `wanted_cards` including `language`, `status`,
  `target_price`, `current_offer_price`, `created_at`.
- Output: `array` with keys `language` (int), `status` (int), `price` (int), `age` (int),
  `total` (int), `terminal` (bool).
- `terminal` is `true` when status is `acquired` or `abandoned`; in that case all component
  scores and `total` are 0.
- Age in days is derived from `created_at` using `strtotime()` and `\DateTimeImmutable::diff()`.
  FQCN backslash required inside `namespace App\Card`.
- `calculate()` signature and return value are unchanged.

**Success criteria:**
- `explain(['language'=>'JP','status'=>'searching','target_price'=>10,'current_offer_price'=>null,'created_at'=>'2024-01-01'])['language']` returns 40.
- `explain(['status'=>'acquired', ...])['total']` returns 0 and `terminal` is `true`.
- No existing call site of `calculate()` requires changes.

---

### Phase 2 — Update the dashboard

**Files:** `public/index.php`

**Intent:** Replace the plain integer score cell with an expandable breakdown.

**Contract:**
- Score cell uses `<details class="score-details"><summary>` for the total score.
- Inside `<summary>`: total score as `(int)`, then a `<small>` with four component labels:
  `Lang +N · Status +N · Price +N · Age +N`.
- Each component value is `(int)` cast before echo — implicit XSS protection for computed ints.
- `CardScorer::explain($card)` is called for every row in the list.
- No change to auth gate, CSRF, repository calls, redirects, or form logic.

**Success criteria:**
- Clicking the score number expands the breakdown without a page reload.
- Breakdown closes when clicked again.
- Keyboard-accessible (Enter/Space toggles `<details>`).

---

### Phase 3 — Style the score cell

**Files:** `public/assets/style.css`

**Intent:** Make the score breakdown readable without breaking table layout.

**Contract:**
- `.score-details summary`: hides the default `<details>` triangle marker; styles the total
  as a clickable number.
- `.score-details small`: muted colour, smaller font, displayed below or inline with the total.
- No `display:flex` on `td` (would break table cell sizing).
- `td:last-child`: `white-space: nowrap` keeps action buttons on one line.

**Success criteria:**
- Score column is visually distinct and scannable.
- Action buttons do not wrap to a second line on typical screen widths.
- No layout regression on other columns.

---

### Phase 4 — Testing documentation

**Files:** `docs/manual-test-plan.md`, `docs/testing.md`

**Intent:** Name scoring correctness as a testable risk so reviewers know what to verify.

**Contract:**
- `docs/manual-test-plan.md` §12: 8 numbered steps covering terminal status → 0, language
  rarity scoring, age-based urgency, status hierarchy, and score breakdown display.
- `docs/testing.md` Risk 2: names the risk, states mitigation (CardScorer unit-testable
  pure functions), and references §12 of the test plan.

**Success criteria:**
- A reviewer following §12 can confirm scoring without reading source code.
- Risk 2 is clearly linked to the test section.

---

## Success criteria (overall)

- [ ] `CardScorer::explain()` returns correct breakdown for a JP searching card.
- [ ] `CardScorer::explain()` returns total 0 and terminal true for `acquired`/`abandoned`.
- [ ] Dashboard shows expandable score for every card row.
- [ ] Score breakdown values match the raw score stored in `difficulty_score`.
- [ ] Breakdown requires no JavaScript to open/close.
- [ ] No regression on add, edit, delete, login, logout flows.
- [ ] §12 of manual test plan passes end-to-end.

---

## Risks / open questions

| Risk | Mitigation |
|---|---|
| `\DateTimeImmutable` namespace error | Use FQCN `new \DateTimeImmutable()` inside `App\Card` namespace |
| Score drift between stored `difficulty_score` and displayed breakdown | `explain()` calls `calculate()` internally; same logic path |
| `display:flex` breaking table cell | Use `white-space: nowrap` on `td` instead |
| XSS via card data in breakdown | Component scores are cast to `(int)` before echo; no raw string output |
| `<details>` marker styling inconsistency across browsers | `list-style: none` + `appearance: none` on summary covers major browsers |

---

## Progress

All phases completed. No commit SHAs recorded (project does not use git at time of writing).

| Phase | Status |
|---|---|
| Phase 1 — Extend CardScorer | Completed |
| Phase 2 — Update dashboard | Completed |
| Phase 3 — Style score cell | Completed |
| Phase 4 — Testing documentation | Completed |
