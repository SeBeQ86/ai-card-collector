# Artifact 1 — Territory (historia zmian)

Generated: 2026-06-28 | Window: all commits (project is ~4 days old)

## Obserwacja wstępna

Projekt ma **2 commity** — Initial commit + docs commit. Wszystkie pliki zostały dodane w jednym kroku, więc ranking aktywności jest płaski: każdy plik ma dokładnie 1 zmianę. Klasyczna analiza kwartalna nie ma zastosowania.

Zamiast historii zmian, terytorium opisujemy przez **funkcjonalne obszary** wynikające ze struktury projektu.

## Obszary funkcjonalne (zamiast rankingu git)

| Obszar | Pliki | Rola | Aktywność |
|---|---|---|---|
| **Auth** | `public/login.php`, `public/logout.php`, `src/Auth/Auth.php` | Bramka dostępu, sesja | Core — każdy request przechodzi przez Auth |
| **Card CRUD** | `public/card-add.php`, `card-edit.php`, `card-delete.php`, `card-status.php` | Zarządzanie kartami | Core — główna wartość produktu |
| **Dashboard** | `public/index.php` | Lista kart, stats, filtr | Core — główny widok użytkownika |
| **Scoring** | `src/Card/CardScorer.php` | Algorytm trudności (0–100) | Core logic — obliczenia przy każdym save |
| **Repository** | `src/Card/CardRepository.php` | Warstwa danych (PDO) | Supporting — pośredniczy między UI a DB |
| **Messages** | `public/card-message.php`, `src/Message/SellerMessageGenerator.php` | Szablony EN/PT | Supporting — pomocnicza funkcja |
| **Security** | `src/Security/Csrf.php` | CSRF tokens | Supporting — cross-cutting |
| **Database** | `src/Database/Connection.php` | Singleton PDO | Supporting — infrastruktura |
| **Assets** | `public/assets/style.css` | Styling | Peripheral |
| **Docs/Context** | `docs/`, `context/`, `database/` | Dokumentacja, schema | Peripheral (nie serwowane) |

## Współzmiany (co zmienia się razem)

Z analizy kodu (nie historii — za mało commitów):

- `card-add.php` + `card-edit.php` — oba używają CardScorer + CardRepository + Csrf; zmiana w scoring wpływa na oba
- `CardScorer.php` + `CardRepository.php` — score jest obliczany i zapisywany razem
- `Auth.php` + każda strona public/ — Auth::requireAuth() jest w każdym handler

## Strefy wrażliwe

- **Auth gate** — `src/Auth/Auth.php` jest zależnością każdej strony; błąd tu = brak dostępu do całości
- **CardScorer** — używany w 3 miejscach (add, edit, status); zmiana algorytmu = rekalkukacja wszystkich kart
- **Connection.php** — singleton PDO; problemy konfiguracyjne wyłączają całą aplikację
