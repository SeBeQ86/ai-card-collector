# Raport architektoniczny — Moduł 4 (10xArchitect)

**Autor:** Sebastian Zylkowski  
**Data:** 2026-06-28  
**Projekt:** AI Card Collector (jedno repozytorium, wszystkie artefakty)

---

## 1. Opisany projekt

| Atrybut | Wartość |
|---|---|
| Repozytorium | `ai-card-collector` (D:\xampp\htdocs\ai-card-collector) |
| Stack | PHP 8.x, MySQL/MariaDB, vanilla JS, brak frameworka, brak Composera |
| Skala | 1 użytkownik, 8 entry pointów, 6 klas PHP, ~15 plików źródłowych |
| Cel produktu | Zarządzanie poszukiwaniami kart kolekcjonerskich z algorytmem trudności |
| Artefakty | L2 + L3 + L4 + L5 — wszystkie z tego samego repo |

---

## 2. Mapa projektu (z L2 — `context/map/repo-map.md`)

**Kluczowe wnioski:**

1. **Centra grafu:** `Auth\Auth` (Ca=8), `Security\Csrf` (Ca=7), `Database\Connection` (Ca=7) — błąd w którymkolwiek z nich łamie całą aplikację.

2. **Strefy ryzyka:** CardScorer jest oznaczony jako Core/High sensitivity — zmiana algorytmu dotyka 3 handlerów (`card-add`, `card-edit`, `card-status`) jednocześnie i invaliduje wszystkie zapisane wartości score.

3. **Brak cykli:** Żadna klasa `src/` nie importuje innej klasy `src/` — architektura flat MVC bez cross-dependencies.

4. **Najważniejszy unknown:** `difficulty_score` w DB to snapshot z momentu save — nie rośnie z wiekiem karty bez akcji użytkownika. Czy to świadoma decyzja produktowa, czy przeoczenie — to open question domenowe.

5. **First-day reading list:** `schema.sql` → `Auth.php` → `CardScorer.php` → `index.php` → `CardRepository.php` — cały system rozumiany po 5 plikach.

---

## 3. Analiza ficzera (z L3 — `context/changes/card-scorer-analysis/research.md`)

**Badany przepływ:** obliczanie i zapis `difficulty_score` — od POST formularza do wiersza w MySQL.

**Feature overview:** Użytkownik submituje formularz (name, language, status, prices) → handler waliduje → wywołuje `CardScorer::calculate(language, status, target, offer, ageInDays)` → wynik INT 0-100 trafia do `$data['difficulty_score']` → `CardRepository` zapisuje przez PDO prepared statement → redirect na `index.php`.

**Technical debt (2 najważniejsze):**

- **TD-01 (MEDIUM):** Age obliczany w 3 miejscach niezależnie — `card-add.php` hardcoded `0`, `card-edit.php` int cast, `card-status.php` `round()`. Różnica w zaokrągleniu powoduje cicho inny score przy granicznym wieku karty. Potwierdzone przez grep: `card-status.php:47` vs `card-edit.php:~80`.

- **TD-02 (HIGH, domain):** Score to snapshot z momentu save. Karta stworzona 10 tygodni temu wyświetla score z dnia dodania, nie score aktualny. Lista priorytetów może dawać mylące rankingi dla starych kart. Wymaga decyzji domenowej przed zmianą kodu.

---

## 4. Plan refaktoryzacji (z L4 — `context/changes/card-scorer-analysis/plan.md`)

**Wybrana opcja:** C1 — ekstrakcja metody `ageInDays()` do `CardScorer` + guard TD-03 (CHECK constraint).

**Czego NIE robimy:** brak zmiany algorytmu scoringu, brak score live vs snapshot, brak zmiany TD-04 (priceScore semantics).

**Fazy planu:**

| Faza | Cel | Weryfikacja |
|---|---|---|
| 1 | Test charakteryzujący niespójność `age` | `php tests/CardScorerTest.php` zielony |
| 2 | Ekstrakcja `ageInDays()` do `CardScorer`, unifikacja przez `round()` | testy + ręczny test add→status |
| 3 | CHECK constraint `difficulty_score <= 100` w `schema.sql` | ALTER TABLE na local DB przechodzi |
| 4 | Dokumentacja open questions (OQ-01, OQ-02) w `business-rules.md` | review dokumentu |

---

## 5. Domena wg DDD (z L5 — `context/domain/`)

**Ubiquitous Language — 3 kluczowe pojęcia i rozjazdy:**

| Pojęcie z PRD | Rozjazd w kodzie |
|---|---|
| **Hunt Status** ("hunt lifecycle") | Status to luźny string; `VALID_STATUSES` zdefiniowane 3× w handlerach jako lokalne tablice; brak PHP enum/klasy |
| **Wanted Card** | Byt istnieje tylko jako `array` z DB — brak klasy domenowej `WantedCard` |
| **Difficulty Score** | Algorytm poprawny, ale score to snapshot w DB; kontrakt `<= 100` nie egzekwowany przez bazę |

**Niezmiennik #1:** INV-02 — status pochodzi z dozwolonego zestawu 5 wartości. Wybrany jako najgorzej egzekwowany: DB ENUM chroni, ale PHP fallbackuje cicho (`$statusScores[$status] ?? 0` w `CardScorer.php:72`) zamiast rzucać błąd.

**Agregat-strażnik:** `HuntStatus` jako value object — `HuntStatus::from(string): self` rzuca `DomainException` dla nieznanych wartości. Zastępuje 3 kopie `VALID_STATUSES` w handlerach.

**Anti-Corruption Layer:** Nie wymagany w MVP — projekt nie ma zewnętrznych zależności domenowych (brak Composera, brak marketplace API). `Connection.php` (PDO singleton) to istniejący wzorcowy ACL dla warstwy DB.

---

## 6. Decyzje, które należą do mnie

1. **TD-02 (stale score) pozostaje open question** — AI zaproponowało dwie ścieżki (obliczaj w locie vs cron update), ale decyzja wymaga odpowiedzi na pytanie domenowe: czy score to "migawka intencji przy save" czy "aktualny priorytet"? Celowo nie zamknąłem tego w planie, bo to zmiana semantyki produktu, nie refaktor.

2. **Wybrałem `round()` nad `int cast`** dla unifikacji age calculation (Faza 2) — obydwie semantyki są obronne, ale jedna musi wygrać. `round()` jest mniej zaskakujący przy granicach dnia.

3. **INV-02 nad INV-01** jako priorytet DDD — AI zidentyfikowało 5 niezmienników; wybrałem INV-02 (valid status), bo rozsmarowanie 3× w handlerach + cichy fallback w CardScorer to realne ryzyko regresji, a INV-01 (terminal score=0) jest dobrze egzekwowany.

4. **Brak WantedCard klasy w planie** — AI w `01-domain-distillation.md` wskazało brak klasy domenowej jako rozjazd. Nie wciągnąłem go do planu L4, bo koszt refaktoru (zmiana wszystkich handlerów + CardRepository) przekracza wartość w MVP z 1 użytkownikiem. To kandydat na "post-MVP second cycle".

5. **ACL nie wymagany** — AI potwierdziło analizę: brak zewnętrznych zależności domenowych to zasługa świadomych decyzji w PRD (non-goals). Dodanie ACL teraz byłoby YAGNI.
