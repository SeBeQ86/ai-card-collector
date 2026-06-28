---
title: "Invariant & Aggregate Refactor Plan — AI Card Collector"
created: "2026-06-28"
type: refactor-plan
---

# 02 — Niezmiennik i agregat: WantedCard + HuntStatus

## KROK 0 — Kontekst

Stack: PHP 8.x, MySQL, brak frameworka. Logika biznesowa w `src/Card/`. Brak warstwy domenowej oddzielonej od handlerów.
Źródła: `context/foundation/prd.md`, `docs/business-rules.md`, `context/domain/01-domain-distillation.md`.

---

## KROK 1 — Lista niezmienników

| ID | Reguła | Źródło |
|---|---|---|
| INV-01 | Score terminala = 0: karta `acquired`/`abandoned` ma zawsze score = 0 | `prd.md:116`, `CardScorer.php:39,66` |
| INV-02 | Status pochodzi z dozwolonego zestawu: searching/contacted/offer_received/acquired/abandoned | `schema.sql:29`, `prd.md:FR-007` |
| INV-03 | Score mieści się w 0–100 | `CardScorer.php:12-18` doc comment |
| INV-04 | Karta należy do uwierzytelnionego użytkownika — żadna operacja na karcie nie jest możliwa bez weryfikacji `user_id` | `prd.md:120` — "no card data is ever visible to anyone other than authenticated owner" |
| INV-05 | Nazwa i język są wymagane — karta bez nich nie może być zapisana | `prd.md:US-01 AC` — "Name, language [...] required before save" |

---

## KROK 2 — Wybór niezmiennika #1

### Ocena na 3 osiach

| Niezmiennik | Rdzeniowość | Rozsmarowanie | Egzekwowanie |
|---|---|---|---|
| INV-01 (terminal score=0) | **Wysoka** — to "distinguishes from spreadsheet" | `CardScorer.php` — pilnuje, ale można ominąć przez direct SQL | Deklarowane (PHP), nieegzekwowane na poziomie DB |
| INV-02 (valid status set) | **Wysoka** — status to wejście do score | 3 pliki (`card-add:19`, `card-edit:35`, `card-status:31`) | Częściowe — DB ENUM chroni, PHP VALID_STATUSES są zduplikowane |
| INV-03 (score 0-100) | Średnia — algorytm gwarantuje, nie biznes | `CardScorer.php` (algorytm), brak DB CHECK | Deklarowane, nieegzekwowane w DB |
| INV-04 (ownership) | Wysoka | Każdy handler — `Auth::requireAuth()` + `user_id` w query | Egzekwowane (dobrze) |
| INV-05 (required fields) | Wysoka | Każdy handler waliduje osobno | Częściowe — brak NOT NULL dla language w schema |

**Wybór: INV-02** — valid status set.

**Uzasadnienie:** INV-02 jest jednocześnie:
- Rdzeniowy: status to najważniejszy input scoringu (40 pkt), a workflow searching→contacted→offer_received to serce logiki domenowej
- Rozsmarowany: `VALID_STATUSES` zdefiniowane 3× niezależnie w handlerach jako lokalne tablice; DB ENUM jest single source w SQL, ale PHP jej nie używa
- Najsłabiej egzekwowany na poziomie PHP: literówka w handlerze daje `status = 0` pts bez błędu, bo `$statusScores[$status] ?? 0` cicho fallbackuje na 0 (`CardScorer.php:72`)

---

## KROK 3 — Diagnoza INV-02

**Gdzie dziś żyje reguła (wszystkie warstwy):**

| Warstwa | Plik:linia | Co robi |
|---|---|---|
| DB | `schema.sql:29-35` | ENUM — jedyny hard constraint |
| Handler add | `card-add.php:19` | `$allowed = ['searching', 'contacted', 'offer_received', 'acquired', 'abandoned']` |
| Handler edit | `card-edit.php:35` | identyczna tablica `$validStatuses` |
| Handler status | `card-status.php:31` | `$allowed = [...]` — trzecia kopia |
| Scorer | `CardScorer.php:70-74` | `$statusScores` — tablica z wagami; `?? 0` fallback cicho zwraca 0 dla nieznanego statusu |
| UI | `index.php:160-164` | `<select>` z hardcoded opcjami — czwarte miejsce |

**Warstwy, które NIE egzekwują:**
- `CardRepository.php` — przyjmuje `status` jako string, brak walidacji przed INSERT/UPDATE
- `CardScorer::calculate()` i `CardScorer::explain()` — fallback `?? 0` połyka nieznany status zamiast rzucać błąd

**Błąd połykany zamiast zatrzymywać operację:**
```php
// CardScorer.php:72 — silent fallback
$statusScore = $statusScores[$status] ?? 0;
// Nieznany status → score = 0 bez informacji dla klienta
```

---

## KROK 4 — Projekt: HuntStatus jako single source of truth

### Value object HuntStatus

```php
// src/Card/HuntStatus.php
final class HuntStatus
{
    private const VALID = [
        'searching', 'contacted', 'offer_received', 'acquired', 'abandoned'
    ];

    private const TERMINAL = ['acquired', 'abandoned'];

    private function __construct(private readonly string $value) {}

    public static function from(string $value): self
    {
        if (!in_array($value, self::VALID, true)) {
            throw new \DomainException("Invalid hunt status: {$value}");
        }
        return new self($value);
    }

    public function value(): string { return $this->value; }

    public function isTerminal(): bool
    {
        return in_array($this->value, self::TERMINAL, true);
    }

    public static function validValues(): array { return self::VALID; }
}
```

### Zmiany w CardScorer (before/after)

**Before:**
```php
// CardScorer.php:70-74
$statusScores = ['searching' => 40, 'contacted' => 30, 'offer_received' => 10];
$statusScore  = $statusScores[$status] ?? 0;  // ← cichy fallback
```

**After:**
```php
// CardScorer.php
$huntStatus  = HuntStatus::from($status);  // ← rzuca DomainException dla nieznanych
if ($huntStatus->isTerminal()) { return 0; }
$statusScores = ['searching' => 40, 'contacted' => 30, 'offer_received' => 10];
$statusScore  = $statusScores[$huntStatus->value()];  // ← brak fallback, znane wartości
```

### Uproszczenie handlerów (before/after)

**Before (card-add.php:19):**
```php
$allowed = ['searching', 'contacted', 'offer_received', 'acquired', 'abandoned'];
if (!in_array($status, $allowed, true)) { /* error */ }
```

**After:**
```php
// Walidacja przez HuntStatus::from() — rzuca DomainException → handler mapuje na błąd formularza
$huntStatus = HuntStatus::from($_POST['status'] ?? '');
```

### Kryteria sukcesu

Po refaktorze:
- `grep -rn "'searching'\|'contacted'\|'offer_received'" public/ src/` → tylko w `HuntStatus.php` i `schema.sql`
- Każdy handler używa `HuntStatus::from()` — jedna linia zamiast tablicy
- Nieznany status rzuca wyjątek domenowy, nie cicho fallbackuje

---

## KROK 5 — Plan faz (guard-first)

| Faza | Cel | Commit |
|---|---|---|
| 1 | Test charakteryzujący silent fallback w `CardScorer` | `test: characterize unknown-status silent fallback` |
| 2 | Stwórz `HuntStatus` value object z testem jednostkowym | `feat: add HuntStatus value object with DomainException on invalid` |
| 3 | Zaktualizuj `CardScorer::calculate()` i `CardScorer::explain()` | `refactor: use HuntStatus in CardScorer, replace silent fallback with DomainException` |
| 4 | Zaktualizuj 3 handlery POST | `refactor: replace VALID_STATUSES arrays with HuntStatus::from()` |

**Przypadki testowe dla INV-02:**

| Operacja | Oczekiwany wynik |
|---|---|
| `HuntStatus::from('searching')` | ok |
| `HuntStatus::from('acquired')` | ok, `isTerminal()=true` |
| `HuntStatus::from('unknown')` | `DomainException` |
| `HuntStatus::from('')` | `DomainException` |
| `CardScorer::calculate('JP', 'searching', ...)` | score > 0 |
| `CardScorer::calculate('JP', 'unknown_status', ...)` | `DomainException` (po refaktorze) |

---

## Ograniczenia

- INV-02 jest najgorzej egzekwowanym niezmennikiem w PHP; INV-04 (ownership) jest już dobrze chroniony
- HuntStatus nie blokuje direct SQL INSERT z nieprawidłowym statusem — DB ENUM pozostaje jedynym hardem na poziomie bazy
- Ta zmiana nie adresuje TD-02 (stale score) — to osobna decyzja domenowa
