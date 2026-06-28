# AI Card Collector

AI Card Collector to mała aplikacja webowa przygotowywana jako projekt zaliczeniowy 10xDevs 3.0.

Celem aplikacji jest pomoc kolekcjonerowi kart w zarządzaniu listą brakujących lub poszukiwanych kart, ocenianiu trudności ich zdobycia oraz przygotowywaniu wiadomości do sprzedawców z innych krajów.

## Główny przepływ MVP

1. Użytkownik loguje się do aplikacji.
2. Dodaje kartę do listy poszukiwanych.
3. Podaje nazwę karty, język, kraj/źródło poszukiwania, limit ceny, status i notatkę.
4. System wylicza trudność zdobycia karty oraz priorytet.
5. Użytkownik generuje wiadomość do sprzedawcy po angielsku lub portugalsku.
6. Karta pojawia się na liście poszukiwań z priorytetem i statusem.

## Zakres certyfikacyjny 10xBuilder

Projekt docelowo powinien zawierać:

- mechanizm logowania,
- CRUD kart,
- logikę biznesową oceny trudności zdobycia karty,
- opcjonalną integrację AI do generowania wiadomości,
- dokumenty kontekstowe,
- co najmniej jeden test z perspektywy użytkownika,
- pipeline CI/CD.

## Workflow 10xDevs M1L1

Najpierw uruchom w katalogu projektu:

```bash
npx @przeprogramowani/10x-cli@latest auth
npx @przeprogramowani/10x-cli@latest get m1l1
```

Następnie uruchom Claude Code:

```bash
claude
```

W Claude Code wykonaj:

```text
/10x-init
/10x-shape @context/foundation/project-idea.md
/10x-prd @context/foundation/shape-notes.md
```

Po lekcji M1L1 powinny powstać lub zostać uzupełnione pliki:

```text
context/foundation/shape-notes.md
context/foundation/prd.md
```

## Uwaga

Katalog `.claude/skills/` nie jest dołączony do tej paczki. Skille kursowe powinny zostać pobrane przez `10x-cli`.

---

## Local setup (XAMPP)

1. Clone the repository into `D:\xampp\htdocs\ai-card-collector` (or your XAMPP `htdocs`).
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Open phpMyAdmin and create a database (e.g. `ai_card_collector`).
4. Import `database/schema.sql` into that database.
5. Generate a bcrypt password hash (do **not** commit the result):
   ```
   D:\xampp\php\php.exe -r "echo password_hash('your-password', PASSWORD_BCRYPT) . PHP_EOL;"
   ```
6. Insert your user account:
   ```sql
   INSERT INTO users (email, password_hash)
   VALUES ('your@email.com', '$2y$12$...<hash from step 5>...');
   ```
7. Open `http://localhost/ai-card-collector/public/` in a browser.

No Composer install is needed. No npm. No build step.

### Optional environment variables

The app reads these from the environment (falls back to XAMPP defaults if unset):

```
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=ai_card_collector
DB_USERNAME=root
DB_PASSWORD=
APP_ENV=local
APP_URL=http://localhost/ai-card-collector/public
```

---

## Test login

Never commit a real password. To create or reset the local test account:

```
D:\xampp\php\php.exe -r "echo password_hash('your-password', PASSWORD_BCRYPT) . PHP_EOL;"
```

Copy the output and run in MySQL:

```sql
UPDATE users SET password_hash = '$2y$12$...' WHERE email = 'your@email.com';
```

---

## CI

GitHub Actions runs PHP syntax checks on every push and pull request.
No automatic deployment. See `.github/workflows/ci.yml`.

---

## Docs

- [Manual test plan](docs/manual-test-plan.md) — step-by-step browser test for every user flow
- [Deployment notes](docs/deployment.md) — shared hosting setup and manual deploy process

## Test PR
