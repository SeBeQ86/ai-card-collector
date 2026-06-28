# Plan refaktoryzacji: CardScorer — element ④ (Refactor opportunities)

**Zmiana:** `card-scorer-analysis`  
**Etap:** eksploracja → decyzja → **plan** (artefakt 3/4)  
**Prior:** `research.md` tej zmiany (Feature overview ②, Technical debt ③)  
**Wygenerowano:** 2026-06-28  

---

## Klasyfikacja kandydatów

Z raportu ③ (Technical debt) wypisano wszystkie 4 problemy i sklasyfikowano:

| ID | Problem | Klasyfikacja | Uzasadnienie |
|---|---|---|---|
| TD-01 | Niespójne obliczanie `age` w 3 handlerach | **KANDYDAT** | Zmiana struktury wywołania w 3 plikach |
| TD-02 | Score nie przeliczany po upływie czasu | **KANDYDAT domenowy** | Wymaga decyzji architektonicznej przed zmianą kodu |
| TD-03 | Brak CHECK constraint `<= 100` w DB | **nie-kandydat** → wejście do wykonalności | Zmiana schematu, nie struktury kodu |
| TD-04 | `priceScore()` dead case (offer < target = 0) | **nie-kandydat** → decyzja domenowa | Zmiana zachowania biznesowego, nie struktury |

**Lista kandydatów do analizy:**
- **C1** — Niespójne obliczanie `age` (TD-01)
- **C2** — Score stale w bazie (TD-02)

TD-03 i TD-04 zachowane jako wejście do oceny wykonalności (TD-03 → guard w schemacie; TD-04 → wymaga decyzji domenowej poza tym planem).

---

## Analiza kandydatów

### C1 — Niespójne obliczanie `age`

**Obecny kształt** (z dowodami):

| Handler | Obliczenie age | Plik:linia |
|---|---|---|
| `card-add.php` | hardcoded `0` | `card-add.php:81` |
| `card-edit.php` | `(int)(time() - strtotime($card['created_at'])) / 86400` | `card-edit.php:~80` |
| `card-status.php` | `round((time() - strtotime($card['created_at'])) / 86400)` | `card-status.php:47` |

Logika obliczenia nie żyje w `CardScorer` — jest powtórzona w każdym handlerze. `CardScorer::calculate()` przyjmuje gotowy `$ageInDays` (inference: intencja = czysta funkcja bez dostępu do DB; ale konsekwencja = 3 różne implementacje).

**Historia i intencjonalność:**

Projekt ma 2 commity (initial + docs). Brak ADR-ów, brak archeologii git. Verdict: **unknown** — nie ma dowodów świadomej decyzji, ale też nie ma dowodów błędu. Niespójność `round` vs `int cast` wygląda na przypadkową złożoność (dwa handlery pisane niezależnie), ale bez historii to teza, nie fakt.

**Wykonalność migracji:**

- Docelowy kształt: helper prywatny `private static function ageInDays(string $createdAt): int` — albo bezpośrednio w `CardScorer`, albo jako statyczna metoda utility.
- Blast radius: `card-add.php`, `card-edit.php`, `card-status.php` — 3 pliki jednocześnie.
- Istniejące osłony: brak testów integracyjnych dla handlerów. `CardScorerTest.php` testuje `calculate()` z gotowym `ageInDays` — nie łapie niespójności wywołania.
- Prerekwizyt: test charakteryzujący zachowanie `card-status.php` przy zmianie statusu (zapis age), żeby zmiana nie była cicha.
- Odwracalność: każdy handler to osobny commit; rollback możliwy per plik.

---

### C2 — Score stale w bazie (TD-02)

**Obecny kształt:**

Score obliczany tylko przy user action (add/edit/status). Karta z `created_at = 30 dni temu`, `status = searching`, `language = EN`, bez cen ma dziś score=40 (status 40 + age 0 przy dodaniu). Powinna mieć score=45 (status 40 + age 5, bo 30/7=4.28 → 4 pkt). Różnica rośnie z wiekiem.

Evidence: `card-add.php:81` (hardcoded `0`), brak crona ani schedulera w projekcie, brak `CRON_*` w `config/app.php`.

**Historia i intencjonalność:**

Brak ADR-ów. Zachowanie wydaje się świadome (performance — obliczaj raz, trzymaj w DB), ale może być przeoczone. Verdict: **unknown**, ale ryzyko jest domenowe: użytkownik widzi fałszywe priorytety na głównej liście.

**Wykonalność migracji:**

Dwie ścieżki:
1. **Obliczaj score w locie przy wyświetleniu** (`index.php` + `CardRepository`) — zmiana architektury; score nie jest już kolumną DB, tylko wartością obliczaną. Blast radius: `CardRepository.php`, `index.php`, schema (usunięcie kolumny lub zmiana roli). Ryzykowne jako pierwszy krok.
2. **Przelicz score przy każdym GET `index.php`** — UPDATE dla każdej karty przy wyświetleniu. Blast radius: `index.php`, `CardRepository`. Proste, ale cicha zmiana semantyki (score w DB zaczyna być "aktualne do ostatniego odświeżenia").

Prerekwizyt: decyzja domenowa — czy stary score to feature (snapshot z momentu akcji) czy bug (nieaktualne priorytety)?

**Uwaga:** TD-02 jest oznaczone jako HIGH risk i **domain decision**. Zmiana kodu bez tej decyzji to zmiana semantyki ukryta przed właścicielem.

---

## Refactor opportunities (ranked)

### Ranking

| Pozycja | Kandydat | Obecny → docelowy kształt | Uzasadnienie rankingu |
|---|---|---|---|
| **#1** | **C1 — niespójne `age`** | 3 lokalne obliczenia → 1 prywatna metoda utility | Przypadkowa złożoność, brak dowodów świadomej decyzji; koszt guarda niski, blast radius średni, droga inkrementalna |
| **#2** | **Guard dla TD-03** | brak constraint → `CHECK (difficulty_score <= 100)` w schema.sql | Tani, odwracalny, samodzielny krok; domyka kontrakt algorytmu na poziomie DB |

**C2 odrzucone jako refaktor w tej zmianie:** wymaga decyzji domenowej (snapshot vs live score). Wpisano do `docs/business-rules.md` jako open question.

**TD-04 odrzucone jako refaktor:** zmiana zachowania biznesowego (offer < target = wyższy priorytet niż brak ceny?). Wymaga decyzji właściciela poza tym planem.

### Kandydaci rozważeni i odrzuceni

| Kandydat | Powód odrzucenia |
|---|---|
| C2 — score staleness | Decyzja domenowa niezapadła; zmiana kodu przed decyzją zmienia semantykę poza wiedzą właściciela |
| TD-04 — priceScore dead case | Zmiana zachowania biznesowego, nie struktury kodu |

---

## Plan implementacji

### Zasady planu

1. **Dodaj test, zanim dotkniesz** — każda faza zaczyna się od testu charakteryzującego obecne zachowanie.
2. **Mechanizm ląduje na zielono, egzekwowanie osobno** — guard w fazie 3 jest wyłączony domyślnie (schema change nie blokuje aplikacji).
3. **Każda faza to osobny, odwracalny commit.**
4. **Jawne "czego NIE robimy"** — bez C2, bez TD-04, bez zmiany algorytmu scoringu.

---

### Faza 1 — Test charakteryzujący niespójność age (prerekwizyt)

**Cel:** Przybić obecne zachowanie przed jakąkolwiek zmianą. Test musi złapać różnicę między `round()` a `int cast` przy granicznym wieku karty.

**Krok 1a:** Dodaj do `tests/CardScorerTest.php` grupę testów weryfikującą `ageInDays` przy granicznych wartościach:

```php
// Karta 3.5 dnia stara:
// - card-edit: (int)(3.5 * 86400 / 86400) = int(3.5) = 3
// - card-status: round(3.5 * 86400 / 86400) = round(3.5) = 4
// Oba prowadzą do tego samego ageScore = min(3/7, 10) lub min(4/7, 10)
// Test charakteryzujący: zapisuje obecne zachowanie, nie żądane
```

**Kryterium weryfikacji:** `php tests/CardScorerTest.php` zielony.

**Commit:** `test: characterize age calculation boundary behavior before refactor`

---

### Faza 2 — Ekstrakcja pomocniczej metody `ageInDays`

**Cel:** Jeden punkt obliczania age zamiast trzech niezależnych.

**Krok 2a:** Dodaj prywatną metodę statyczną do `src/Card/CardScorer.php`:

```php
private static function ageInDays(string $createdAt): int
{
    return (int) round((time() - strtotime($createdAt)) / 86400);
}
```

Decyzja: używamy `round()` (a nie `int cast`) — `round` jest bardziej defensywny przy granicach dnia, a wybranie jednej semantyki jest ważniejsze niż wybór konkretnej. Właściciel może to zmienić przez zmianę jednej metody.

**Krok 2b:** Zaktualizuj `card-status.php:47` — zastąp inline obliczenie wywołaniem `CardScorer::ageInDays()`. (Metoda public na czas migracji.)

**Krok 2c:** Zaktualizuj `card-edit.php` — zastąp `(int)` obliczenie wywołaniem.

**Krok 2d:** `card-add.php` pozostaje z hardcoded `0` — nowa karta nie ma `created_at`, to jest poprawne. Dodaj komentarz inline: `// new card has no history`.

**Krok 2e:** Zmień widoczność metody z `public` na `private` po zakończeniu migracji.

**Kryterium weryfikacji:**
- `php tests/CardScorerTest.php` zielony
- Ręcznie: dodaj kartę, zmień status tej samej karty — score powinien być spójny

**Commit:** `refactor: extract ageInDays helper to CardScorer, use round() consistently`

---

### Faza 3 — Guard dla TD-03: CHECK constraint w schemacie

**Cel:** Baza danych egzekwuje kontrakt algorytmu (score ≤ 100).

**Krok 3a:** Dodaj do `database/schema.sql` constraint:

```sql
-- Po kolumnie difficulty_score INT UNSIGNED NOT NULL DEFAULT 0:
CONSTRAINT chk_difficulty_score CHECK (difficulty_score <= 100)
```

**Krok 3b:** Dodaj do `docs/business-rules.md` notatkę: "difficulty_score ma twardy limit 100 egzekwowany przez DB CHECK constraint".

**Uwaga dla deploy:** CHECK constraint wymaga `ALTER TABLE` na istniejącej bazie (lub fresh schema). Nie zepsuje aplikacji — PHP nigdy nie zapisuje > 100.

**Kryterium weryfikacji:**
- `schema.sql` diff review
- Ręcznie na localnym XAMPP: `ALTER TABLE wanted_cards ADD CONSTRAINT chk_difficulty_score CHECK (difficulty_score <= 100);` — powinno przejść (wszystkie obecne wartości ≤ 100)

**Commit:** `guard: add CHECK constraint difficulty_score <= 100 to schema`

---

### Faza 4 — Dokumentacja open questions

**Cel:** Decyzje domenowe z TD-02 i TD-04 nie zginą.

**Krok 4a:** Zaktualizuj `docs/business-rules.md` — dodaj sekcję "Open questions":

```
## Open questions (wymagają decyzji właściciela)

**OQ-01 (TD-02): Czy difficulty_score to snapshot czy live value?**
Obecne zachowanie: score obliczany przy save, trzymany w DB jako snapshot.
Konsekwencja: karta stworzona 10 tygodni temu nie rośnie w rankingu mimo upływu czasu.
Decyzja do podjęcia: snapshot (aktualna implementacja, świadoma) vs live (przeliczaj przy każdym GET index.php).

**OQ-02 (TD-04): Czy karta z ofertą w budżecie powinna mieć wyższy czy niższy priorytet?**
Obecne zachowanie: offer <= target → priceScore = 0 (niski priorytet); brak cen → priceScore = 3 (wyższy).
Pytanie: czy łatwo dostępna karta ma mniejszą pilność (obecna logika) czy wyższy priorytet ("zadziałaj teraz")?
```

**Commit:** `docs: document open domain questions OQ-01 (score staleness) and OQ-02 (price semantics)`

---

## Czego NIE robimy w tej zmianie

- **Nie zmieniamy C2** — decyzja domenowa niezapadła
- **Nie naprawiamy TD-04** — wymaga rozmowy o semantyce `priceScore()`
- **Nie zmieniamy algorytmu scoringu** — żadnych nowych wag ani komponentów
- **Nie dodajemy cron/scheduler** do przeliczania score
- **Nie zmieniamy architektury** (score live vs snapshot) — to osobna zmiana po OQ-01

---

## Kolejność faz (od liścia do korzenia)

```
Faza 1 (test charakteryzujący) → Faza 2 (ekstrakcja metody) → Faza 3 (guard schema) → Faza 4 (dokumentacja)
    ↑ prerekwizyt dla Fazy 2         ↑ samodzielna                ↑ samodzielna
```

Fazy 3 i 4 są niezależne od siebie i od Fazy 2. Można je dostarczyć osobno.

---

## Weryfikacja twierdzeń strukturalnych

| Twierdzenie | Metoda weryfikacji | Wynik |
|---|---|---|
| `CardScorer::calculate` wywołany w 3 handlerach | `grep -rn "CardScorer::calculate" public/` | ✅ card-add:75, card-edit:75, card-status:49 |
| Age obliczane w `card-status.php` przez `round()` | `grep -n "round" public/card-status.php` | ✅ linia 47 |
| Age obliczane w `card-edit.php` przez `int cast` | `grep -n "int.*strtotime\|strtotime.*int" public/card-edit.php` | ✅ potwierdzone |
| `card-add.php` używa hardcoded `0` dla age | `grep -n "ageInDays\|age.*0\|0.*age" public/card-add.php` | ✅ linia 81 |
| Brak CHECK constraint w schema.sql | `grep -n "CHECK" database/schema.sql` | ✅ brak (0 wyników) |

---

## Ograniczenia tego planu

- Analiza statyczna, brak testów integracyjnych handlerów
- TD-02 wymaga decyzji domenowej, której ten plan celowo nie podejmuje
- Historia projektu = 2 commity → werdykty intencjonalności opierają się na analizie kodu, nie archeologii git
