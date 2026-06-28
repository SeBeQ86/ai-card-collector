# Testing Notes

Minimalny test z perspektywy użytkownika powinien potwierdzać:

1. Użytkownik może otworzyć ekran logowania.
2. Użytkownik może się zalogować.
3. Użytkownik może dodać kartę do listy poszukiwanych.
4. Dodana karta pojawia się na liście.
5. System pokazuje status i priorytet karty.

Docelowo test może zostać wykonany np. w Playwright.

---

## Named risks covered by the manual test plan

### Risk 1 — Unauthorised or wrong-user access

**Risk:** An unauthenticated visitor, or a logged-in user with a crafted request,
can access, modify, or delete another user's wanted cards.

**Mitigations in code:**
- Every page calls `Auth::requireAuth()` before rendering or DB work.
- Every repository method (`findForUser`, `updateForUser`, `deleteForUser`,
  `listForUser`) binds `user_id` in the SQL `WHERE` clause — a guessed card id
  for another user matches zero rows.
- Session is destroyed on logout; cookie is expired.

**Test sections in `docs/manual-test-plan.md`:**
- §1 — Unauthenticated access redirects to `login.php` for every protected page.
- §9 & §10 — Logout destroys the session; subsequent direct URL access redirects to login.
- §7.4 — POST to `card-delete.php` with a foreign card id is silently ignored.
- §11 — Dedicated authorization risk section: unauthenticated redirects, user
  isolation on list/edit/delete, and post-logout protection tested explicitly.

---

### Automated business-logic guard

A no-dependency PHP CLI test guards the core scoring invariants:

```
php tests/CardScorerTest.php
```

This is not a full unit-test framework. It is a plain PHP script with local assertion helpers
that exits 0 on all pass and 1 on any failure. It runs automatically in GitHub Actions as
part of the `syntax` CI job (after `php -l` checks).

It covers 28 assertions across 7 groups:
- Terminal statuses `acquired` / `abandoned` always return score 0
- Active statuses return non-negative scores
- Language rarity ordering (non-EN > EN)
- Status urgency ordering (searching > contacted > offer_received > 0)
- Score ceiling (maximum 100)
- `explain()` structure and terminal flag
- `explain()` total consistency with `calculate()` for the same inputs

Expected values are derived from the documented scoring rules in `CardScorer` and
`context/foundation/roadmap.md` (S-03), not from reading and mirroring the implementation.

---

### Risk 2 — Difficulty scoring produces misleading priority values

**Risk:** The computed `difficulty_score` ranks cards incorrectly, causing the
collector to ignore hard-to-find cards or over-prioritise easy ones.

**Mitigations in code:**
- `CardScorer::calculate()` applies four independent inputs: language rarity,
  status urgency, price pressure, and age.
- Terminal statuses (`acquired`, `abandoned`) short-circuit to 0 — finished cards
  never pollute the top of the list.
- Score is recalculated from real `created_at` on every edit, so age contribution
  reflects elapsed time.

**Test sections in `docs/manual-test-plan.md`:**
- §4.6 — Non-English card scores higher than identical English card at add time.
- §6.4 — Changing status to `acquired` / `abandoned` drops score to 0.
- §12 — Dedicated scoring risk section: non-terminal statuses each produce a
  score > 0; terminal statuses produce 0; non-English language and price
  mismatch each demonstrably increase the score.
