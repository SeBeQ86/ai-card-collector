# AI Card Collector — Team Rules

## Stack

Plain PHP 8.x, MySQL/MariaDB, vanilla JS, no framework, no Composer without explicit instruction.

## Security (non-negotiable)

- Every PHP file: `<?php declare(strict_types=1);`
- Every DB query: PDO prepared statements — no string concatenation into SQL
- Every user value in HTML: `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`
- Every state-changing form: CSRF token validated
- Session: `httponly`, `samesite=Strict`; `secure` in production

## Domain model

Wanted card statuses (lowercase in DB): `searching` | `contacted` | `offer_received` | `acquired` | `abandoned`

## Folder layout

- `public/` — web root only; no other folder is served
- `src/` — PHP classes, namespace `App\`
- Never expose `config/`, `database/`, `context/`, `docs/`, `.claude/`, or `.env` via routes
