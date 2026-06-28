# CLAUDE.md — AI Card Collector

Projekt: AI Card Collector — osobiste narzędzie do zarządzania poszukiwaniami kart kolekcjonerskich.

Pracuj etapowo i nie rozbudowuj MVP poza zakres opisany w `context/foundation/prd.md`.

## Najważniejsza zasada

Najpierw zaproponuj plan. Modyfikuj tylko pliki potrzebne do danego kroku. Zachowuj proste rozwiązania.

## Granice MVP

Nie implementuj:

- integracji z Cardmarket API ani scrapowania marketplace'ów (dozwolone: odczyt cen referencyjnych przez TCGdex API jako agregator danych kart)
- płatności
- publicznych profili lub wymiany kart między użytkownikami
- aplikacji mobilnej
- dużego katalogu kart ani OCR zdjęć
- wywołań AI API do generowania wiadomości (szablony PHP, bez sieci)
- rejestracji użytkowników (jedno konto seedowane bezpośrednio)

---

## Stack

Plain PHP 8.x, MySQL/MariaDB, HTML renderowany po stronie serwera, minimalny vanilla JS.

- Every PHP file: `<?php declare(strict_types=1);` — no exceptions.
- No PHP framework. Not Laravel, not Symfony, not Slim.
- No Composer packages without an explicit task instruction that names the package.
- No runtime AI API calls. Seller messages are PHP string templates only.
- Vanilla JS only — no build step, no npm, no bundler.

## Folder layout

`public/` is the **only** web root. Apache/XAMPP serves nothing outside it.

```
public/          web root — one PHP file per page + static assets
  index.php      wanted-cards list
  assets/        CSS, JS
src/             PHP classes, namespace App\
  Auth/          session management, login/logout handlers
  Card/          wanted-card CRUD, difficulty scoring
  Database/      PDO connection wrapper
  Message/       seller message templates (no network, no AI runtime)
  Security/      CSRF token management
  bootstrap.php  PSR-4 autoloader
app/             empty scaffold stubs (not used)
config/          PHP config files — loaded by app/, never served
database/        schema.sql, seed.sql — never served
tests/e2e/       Playwright end-to-end tests
context/         10x chain context — never served
docs/            project docs — never served
```

**Hard rule:** never create a route or `require` path in `public/index.php` that exposes or proxies `config/`, `database/`, `context/`, `docs/`, `.claude/`, or `.env`.

## Security rules

These are non-negotiable on every change:

1. **Auth gate** — every request handler checks the session before rendering. Unauthenticated → redirect to `login.php`. No exceptions for "just a GET".
2. **Prepared statements** — every DB query uses PDO prepared statements. No string concatenation into SQL ever.
3. **Output escaping** — every user-supplied value echoed in HTML uses `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
4. **CSRF** — every state-changing form (POST/DELETE) includes and validates a CSRF token.
5. **Session cookie** — `httponly`, `samesite=Strict`; add `secure` flag when `APP_ENV=production`.
6. **Secrets** — `.env` is gitignored. Credentials and API keys never appear in committed files.
7. **No dangerous functions** — no `eval()`, `exec()`, `system()`, `shell_exec()`, `passthru()`.

## Domain model

**Wanted card status** (stored lowercase in DB, strict enum in PHP):
`searching` | `contacted` | `offer_received` | `acquired` | `abandoned`

**Difficulty score** — computed on save, stored in `difficulty_score` (INT). Range: active cards **0–155**, terminal (acquired/abandoned) always **0**. Inputs (FR-008 + market extension):
1. Language rarity (0–35) — JP/TH/PT/ID=35, FR/DE/ES/KR/RU/PL/ZH=20, EN=0
2. Status urgency (0–40) — `searching`=40, `contacted`=25, `offer_received`=10, terminal=0
3. Price pressure (0–25) — offer > budget=25, budget set but no offer=15, no data=8, within budget=0
4. Age urgency (0–15) — +1 per 5 days unresolved, capped at 15
5. Market pressure (0–40) — budget/market coverage: ≥100%=0, 85–100%=+10, 70–85%=+20, 50–70%=+30, <50%=+40; requires `market_price` to be set

`market_price` and `market_price_at` are refreshed via "Odśwież ceny" button (POST `api/price-refresh.php`) which calls TCGdex API for all active cards with a linked `api_card_id`. Score is recomputed on each refresh.

**Seller message templates** (FR-010, FR-011):
- Two PHP string templates, one per locale: `en`, `pt`
- Substitution fields: card name, language edition, target price
- Generated at request time — no DB storage required, no network call

## CI / deployment

- `.github/workflows/` — GitHub Actions runs checks on every push to any branch
- Promotion to production is manual: merge to `main`, deploy by hand to shared PHP hosting
- Local runtime: XAMPP, `D:\xampp\htdocs\ai-card-collector`, PHP at `D:\xampp\php\php.exe`
- Production: shared PHP hosting — no Docker, no container runtime, no Composer install step unless hosting supports it

## 10x chain — reference files

- PRD: `context/foundation/prd.md`
- Tech stack hand-off: `context/foundation/tech-stack.md`
- Bootstrap verification: `context/changes/bootstrap-verification/verification.md`
- Business rules: `docs/business-rules.md`
- DB schema: `database/schema.sql`
- Migrations: `database/migrations/` (001–004; run in order on a fresh install)

Skills must not write to `context/archive/`. Archived changes are immutable. If a target path starts with `context/archive/`, abort: "This change is archived. Open a new change with `/10x-new` instead."
