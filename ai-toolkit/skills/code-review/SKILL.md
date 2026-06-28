# Skill: code-review

Review a PHP diff against project security rules and return a structured verdict.

## When to use

Run before merging any change to `public/` or `src/`. Invoke as: `/code-review`

## Steps

1. Read the diff (stdin or `--file <path>`)
2. Check each changed PHP file for:
   - `<?php declare(strict_types=1);` on line 1
   - All SQL via PDO prepared statements (no string concat)
   - All HTML output via `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`
   - POST/DELETE forms include and validate CSRF token
3. Score 1–10 on: implementationCorrectness, idiomaticity, complexity, testRiskCoverage, securitySafety
4. Return verdict: `pass` (all scores ≥ 7, no critical security issues) or `fail`

## Output format

```json
{
  "implementationCorrectness": 8,
  "idiomaticity": 7,
  "complexity": 9,
  "testRiskCoverage": 6,
  "securitySafety": 10,
  "verdict": "pass",
  "summary": "Poprawna zmiana. Prepared statements użyte poprawnie, CSRF obecny."
}
```
