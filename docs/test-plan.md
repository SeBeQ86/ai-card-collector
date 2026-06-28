# Test Plan — AI Card Collector

## Scope

This document defines the risks addressed by automated tests in this project.
Tests are located in `tests/` and run automatically on every push via GitHub Actions (`.github/workflows/ci.yml`).

## Risk Register

### RISK-01 — Difficulty score ignores terminal statuses

**Risk:** Cards with status `acquired` or `abandoned` could still receive a non-zero difficulty score, causing them to appear at the top of the wanted list and misleading the user about active search priorities.

**Likelihood:** Medium — the scoring algorithm has multiple additive components (language, status, price, age); a bug in any component could leak points into terminal cards.

**Impact:** High — the entire wanted-cards list is sorted by difficulty score. A wrong score disrupts prioritisation and seller outreach decisions.

**Mitigation:** `tests/CardScorerTest.php` — Group 1 "Terminal status invariant" — asserts that `CardScorer::calculate()` returns exactly `0` for both `acquired` and `abandoned` regardless of language, price mismatch, or age.

---

### RISK-02 — Language rarity component misidentifies English editions

**Risk:** The English language rarity bonus (0 points) could incorrectly apply to non-English cards, or the "english" string alias could be treated as a foreign language and score 40 points — inflating scores for easy-to-find EN cards.

**Likelihood:** Low-Medium — the normalisation of the `language` field is a single string comparison; typos or case differences could break it silently.

**Impact:** Medium — inflated scores for English cards would push them above genuinely rare foreign editions, wasting the user's time on easy hunts.

**Mitigation:** `tests/CardScorerTest.php` — Group 3 "Language rarity" — asserts that `JP` scores higher than `EN`, and that the string `"english"` produces the same score as `"EN"`.

---

### RISK-03 — Status urgency ordering is inverted or flat

**Risk:** The urgency contribution of statuses (`searching` > `contacted` > `offer_received`) could be implemented in wrong order, causing `offer_received` cards to outrank `searching` cards.

**Likelihood:** Low — but the ordering is a business rule (not a technical constraint) that the compiler cannot verify.

**Impact:** High — wrong ordering would cause the user to follow up on nearly-closed deals instead of actively searching for new cards.

**Mitigation:** `tests/CardScorerTest.php` — Group 4 "Status urgency ordering" — asserts `searching > contacted > offer_received > 0` for identical language/price/age inputs.

---

### RISK-04 — Score can exceed documented maximum of 100

**Risk:** Adding new scoring components or changing weights could cause the total score to exceed 100, breaking any UI element that treats 100 as a percentage ceiling.

**Likelihood:** Low — but the maximum is a documented invariant referenced in `docs/business-rules.md`.

**Impact:** Low-Medium — scores above 100 would not break the application but would make the difficulty indicator meaningless.

**Mitigation:** `tests/CardScorerTest.php` — Group 5 "Score ceiling" — asserts that maximising all components (JP language + searching + price over target + 70 days age) produces exactly 100 and never more.

---

### RISK-05 — `explain()` breakdown is inconsistent with `calculate()`

**Risk:** The `explain()` method (used for UI score tooltips) could return a breakdown that does not sum to the same value as `calculate()`, causing the user to see a different score in the tooltip than in the table.

**Likelihood:** Medium — `explain()` and `calculate()` are separate code paths; a change to one might not be reflected in the other.

**Impact:** Medium — silent inconsistency between displayed values erodes trust in the application.

**Mitigation:** `tests/CardScorerTest.php` — Group 7 "explain() consistency" — asserts that `explain()['total']` equals `calculate()` for the same inputs at age 0.

---

## Test Execution

```bash
# Local
php tests/CardScorerTest.php

# CI (automatic on every push and pull request)
# See .github/workflows/ci.yml — "Run CardScorer business-logic guard"
```

Expected output: all groups pass, exit code 0.
