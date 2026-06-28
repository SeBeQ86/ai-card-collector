# Change: card-priority-scoring

> **Retrospective / as-built change record.**
> This document was written after implementation to describe what was built and why.
> It did not exist before the code was written.

---

## Change ID

`card-priority-scoring`

## Roadmap link

Slice **S-03** — `card-priority-scoring` in `context/foundation/roadmap.md`
(Also relates to S-01 which introduced the initial `calculate()` call on card save.)

---

## Intent

Collector sees a numeric priority score for each wanted card on the dashboard, and can
expand that score to understand which factors drove it (language rarity, status urgency,
price pressure, age). The score helps the collector focus attention on the cards that are
hardest to acquire and most overdue.

---

## Scope

- Add `CardScorer::explain(array $card): array` — returns the per-component score breakdown
  without changing the existing `calculate()` signature or return value.
- Render the score in `public/index.php` as an expandable `<details>/<summary>` element
  showing the total and the four components.
- Style the score cell and breakdown in `public/assets/style.css`.
- Add scoring correctness as a named risk and test scenario in the testing documentation.

## Non-scope

- No change to how `difficulty_score` is computed or stored (that is `calculate()`, which
  existed since S-01 / `bootstrap-step-3`).
- No change to `CardRepository`, `Auth`, `Csrf`, or any other class.
- No JavaScript added for the score breakdown — the `<details>/<summary>` element is native
  HTML and requires no script.
- No change to database schema.
- No change to GitHub Actions CI.

---

## Touched files

| File | Change type |
|---|---|
| `src/Card/CardScorer.php` | Added `explain(array $card): array` method |
| `public/index.php` | Score cell replaced with `<details>/<summary>` breakdown |
| `public/assets/style.css` | Added `.score-details` styles and responsive adjustments |
| `docs/manual-test-plan.md` | Added §12 — scoring correctness test scenario |
| `docs/testing.md` | Added Risk 2 — scoring correctness with mitigation |

---

## Key implementation decisions

1. **`calculate()` remained backward compatible.** The method signature
   `calculate(string $language, string $status, ?float $targetPrice, ?float $currentOfferPrice, int $ageInDays = 0): int`
   was unchanged. `explain()` was added as a new non-breaking method that accepts a full card
   DB row (including `created_at`) and internally calls `calculate()`.

2. **Terminal statuses always return score 0.** Cards with status `acquired` or `abandoned`
   return `total: 0` from both `calculate()` and `explain()`, regardless of language, price,
   or age. This is enforced inside `CardScorer`, not in the template.

3. **Output must be escaped on the dashboard.** The breakdown values returned by `explain()`
   are integers computed internally, but the card data passed to `explain()` contains
   user-supplied strings (`language`, `status`, etc.). Any value echoed in HTML must use
   `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`. The `<details>/<summary>` template in
   `public/index.php` echoes only the integer component scores, which are cast to `(int)`
   before output, providing implicit safety.

4. **`\DateTimeImmutable` requires FQCN inside the `App\Card` namespace.** Inside
   `namespace App\Card`, global-namespace classes must be referenced with a leading backslash:
   `new \DateTimeImmutable()`. Without it, PHP resolves `DateTimeImmutable` relative to
   the current namespace and throws a fatal error. `public/card-edit.php` has no namespace
   declaration so it uses `new DateTimeImmutable()` directly.

5. **No JavaScript for the breakdown.** The `<details>/<summary>` element is keyboard-
   accessible and supported in all modern browsers. Using `display:flex` on the table cell
   would break table layout; `white-space: nowrap` on `td:last-child` was used instead.
