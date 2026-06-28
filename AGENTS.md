# AGENTS.md — AI Card Collector

Rules for automated agents making changes to this codebase without interactive oversight.

## Project identity

Plain PHP 8.x · MySQL/MariaDB · Server-rendered HTML · No framework · Single user.

## Writable paths

Agents may create or modify files under:

- `src/` — PHP classes (`App\Auth`, `App\Card`, `App\Database`, `App\Message`, `App\Security`)
- `app/` — empty scaffold stubs only; real code lives in `src/`
- `public/` — page entry points, static assets
- `config/` — PHP config files (not served via web)
- `database/` — schema.sql, seed.sql
- `tests/e2e/` — Playwright tests
- `.github/workflows/` — CI workflow files
- Root config files: `composer.json`, `.gitignore`, `.env.example`

## Read-only paths (context only)

Agents may read but must not modify as part of feature implementation:

- `context/` — 10x chain context (PRD, shape notes, tech-stack hand-off)
- `docs/` — project documentation
- `CLAUDE.md`, `AGENTS.md` — agent context files (update only when explicitly asked)

`context/archive/` is immutable — never write there.

## Forbidden actions

- Introduce any PHP framework (Laravel, Symfony, Slim, Lumen, CodeIgniter, or any MVC framework)
- Add a Composer `require` entry without an explicit task instruction naming the package
- Add npm / Node.js dependencies or a build step
- Write any code that calls an external AI API (Anthropic, OpenAI, Gemini, etc.)
- Write any runtime network call for seller message generation — templates are static PHP strings
- Create a route or `include`/`require` path in `public/index.php` that exposes `config/`, `database/`, `context/`, `docs/`, `.claude/`, or `.env`
- Commit `.env` or any file containing real credentials or API keys
- Remove or bypass CSRF validation on any state-changing form
- Remove or bypass the session auth check from any request handler

## Mandatory code patterns

**Every PHP file — first line after `<?php`:**
```php
declare(strict_types=1);
```

**All DB queries — prepared statements only:**
```php
$stmt = $pdo->prepare('SELECT * FROM wanted_cards WHERE user_id = ? AND id = ?');
$stmt->execute([$userId, $cardId]);
$card = $stmt->fetch(PDO::FETCH_ASSOC);
```

**All user-supplied output — escaped:**
```php
echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
```

**Auth gate — top of every non-login handler:**
```php
Auth::startSession();
Auth::requireAuth(); // redirects to login.php if session is absent
```

**CSRF — every state-changing form:**
```php
// Render in form:  <?= Csrf::field() ?>
// Validate on POST (token is rotated on each call):
if (!Csrf::validate((string) ($_POST['csrf_token'] ?? ''))) {
    header('Location: index.php');
    exit;
}
```

## Domain constraints

**Status values** — use only these literals (lowercase, stored in DB):
`searching` | `contacted` | `offer_received` | `acquired` | `abandoned`

**Difficulty score** — integer, computed on every card save. Do not accept it as user input.

**Seller message locales** — `en` and `pt` only. Templates live in `src/Message/SellerMessageGenerator.php`. No network call, no API.

**No sign-up route** — the single user account is seeded via `database/seed.sql`. Do not add a registration page.

## Page architecture

Each page is its own PHP file directly in `public/` (`index.php`, `card-add.php`, `card-edit.php`,
`card-delete.php`, `card-message.php`, `login.php`, `logout.php`). There is no URL-based router.
`public/index.php` renders the wanted-cards list — it is not a front controller. New pages = new
file in `public/`, following the same auth-gate and CSRF pattern as existing pages.

## Testing

Manual test plan: `docs/manual-test-plan.md` — 12 sections covering all user flows and two named
risk scenarios (access isolation, scoring correctness). This is the current passing bar before
any feature change is considered done. Playwright e2e tests are planned in `tests/e2e/` but
not yet implemented.

Automated business-logic guard (no framework required):

```
php tests/CardScorerTest.php
```

Exits 0 on all pass, 1 on any failure. Runs in CI. Covers terminal-status zero invariant,
language rarity ordering, status urgency ordering, score ceiling (100), and `explain()`
consistency with `calculate()`.

## CI

`.github/workflows/` — GitHub Actions. Every push triggers checks. Do not add steps that require Docker, Node.js packages not already present, or external service credentials.
