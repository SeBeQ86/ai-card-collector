# Plan Brief — card-priority-scoring

_Short human-readable summary for reviewers and future contributors._

---

## Why this slice matters

A wanted-card list without priority is just a list. The difficulty score turns it into a
decision tool: the collector immediately sees which cards are hardest to find, most overdue,
or most price-constrained — and can focus on those first.

The score breakdown (Lang / Status / Price / Age) makes the number legible. Without it,
a score of 85 is opaque; with the breakdown, the collector knows it is 85 because the card
is in Japanese (40) and has been searched for 8+ weeks (10).

---

## Files involved

| File | Role |
|---|---|
| `src/Card/CardScorer.php` | Added `explain()` — returns per-component breakdown; `calculate()` unchanged |
| `public/index.php` | Score cell replaced with expandable `<details>/<summary>` breakdown |
| `public/assets/style.css` | `.score-details` styles for the expandable score cell |
| `docs/manual-test-plan.md` | §12 — 8 manual test steps for scoring correctness |
| `docs/testing.md` | Risk 2 — scoring correctness named as a testable risk |

---

## Verification steps

1. Import `database/demo-cards.sql` on a local XAMPP instance.
2. Log in and open the wanted-cards list.
3. Confirm Black Lotus JP (searching) shows score 85; click it and verify breakdown
   shows `Lang +40 · Status +40 · Price +5 · Age +0` (or similar for the seeded age).
4. Confirm Forest EN (acquired) shows score 0; verify breakdown shows all zeros.
5. Edit a JP card and change status to `acquired`; confirm score drops to 0 immediately.
6. Follow §12 of `docs/manual-test-plan.md` for the full scoring test sequence.

---

## Known risks

- **Score drift** — `difficulty_score` stored in the DB is computed at save time;
  `explain()` recomputes at render time. If the scoring logic ever changes, stored scores
  and displayed breakdowns can diverge until cards are re-saved. Current mitigation: both
  paths call the same `CardScorer::calculate()` implementation.

- **Age-based drift** — the age component increases over time without a card save. A card
  can show a higher breakdown total than its stored `difficulty_score` if significant time
  has passed since the last edit. This is intentional (reflects current urgency) but should
  be documented for future contributors.

- **No automated tests** — `CardScorer` methods are pure functions and are unit-testable,
  but no PHPUnit tests exist yet. The only verification is the manual test plan §12.
