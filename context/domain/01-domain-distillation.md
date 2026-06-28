---
title: "Domain Distillation — AI Card Collector"
created: "2026-06-28"
type: domain-distillation
---

# 01 — Destylacja domeny: AI Card Collector

## Kontekst projektu

Stack: plain PHP 8.x, MySQL, vanilla JS. Brak frameworka.
Logika biznesowa: `src/Card/CardScorer.php` (pure static, brak stanu), `src/Card/CardRepository.php` (data access), `src/Message/SellerMessageGenerator.php` (templates).
Warstwy: `public/*.php` (entry points / kontrolery) → `src/` (logika + data access) → MySQL.

---

## KROK 1 — Ubiquitous Language

| Pojęcie domenowe | Definicja (z dokumentu) | Cytat źródłowy | Gdzie w kodzie | Status |
|---|---|---|---|---|
| **Wanted Card** | Karta kolekcjonerska, której kolekcjoner aktywnie poszukuje | `prd.md:20` — "actively hunting" | `wanted_cards` (tabela), brak klasy PHP | ⚠️ BRAK klasy domenowej — byt żyje tylko jako `array` z DB |
| **Hunt Status** | Etap w cyklu poszukiwania karty: searching → contacted → offer_received → acquired / abandoned | `prd.md:91`, `FR-007` | `status` ENUM w `schema.sql:29`, array `VALID_STATUSES` w handlerach | ⚠️ BRAK klasy/enum PHP — tylko stringi |
| **Difficulty Score** | Liczba 0–100 wyrażająca trudność zdobycia karty | `prd.md:114–116`, `FR-008` | `difficulty_score` w DB, `CardScorer::calculate()` `CardScorer.php:59` | ✅ zaimplementowane |
| **Language Edition** | Wersja językowa karty (JP, PT, TH, EN…); determinuje rarity | `prd.md:116` — "non-English editions score as harder" | `language` VARCHAR w DB, `CardScorer::languageScore()` `CardScorer.php:84` | ✅ zaimplementowane |
| **Price Limit** | Maksymalna cena, którą kolekcjoner akceptuje | `prd.md:116` — "very low price cap makes acquisition harder" | `target_price` DECIMAL w DB | ✅ persystowane |
| **Current Offer** | Aktualna propozycja cenowa od sprzedawcy | `business-rules.md:8` | `current_offer_price` DECIMAL w DB | ✅ persystowane |
| **Seller Message** | Wiadomość do sprzedawcy generowana z szablonu (EN/PT) | `prd.md:US-02`, `FR-010`, `FR-011` | `SellerMessageGenerator.php`, `public/card-message.php` | ✅ zaimplementowane |
| **Age Urgency** | Czas od dodania karty przekształcony w punkty pilności | `prd.md:116` — "longer a card sits unresolved, the higher urgency" | `created_at` w DB, obliczane w handlerach | ⚠️ obliczanie niespójne (patrz TD-01 w research.md) |
| **Priority** | Pozycja karty na posortowanej liście; wynika z difficulty_score | `prd.md:FR-009` | `ORDER BY difficulty_score DESC` w `CardRepository.php` | ✅ zaimplementowane |
| **Terminal Status** | Status `acquired` lub `abandoned` — karta opuszcza aktywne poszukiwanie, score = 0 | `CardScorer.php:39` | `CardScorer::calculate()` linia 66, `CardScorer::explain()` linia 39 | ✅ egzekwowane |

### Pojęcia z PRD bez odpowiednika w kodzie

| Pojęcie z PRD | Cytat | BRAK w kodzie |
|---|---|---|
| **Hunt Lifecycle** | `prd.md:91` — "hunt lifecycle" | Brak jawnego cyklu życia — status to luźny string |
| **Collector** | `prd.md:24` — persona | Brak klasy Collector; `user_id` to INT w DB |
| **Acquisition** | `prd.md:20` — finalny cel | Brak zdarzenia domenowego przy przejściu na `acquired` |

---

## KROK 2 — Klasyfikacja subdomen

| Obszar | Kategoria | Uzasadnienie |
|---|---|---|
| **Difficulty Scoring** | **Core** | To non-trivial behavior z PRD (FR-008): "auto-scoring is the domain rule that distinguishes this product from a spreadsheet" `prd.md:96` |
| **Wanted Card CRUD** | **Core** | Podstawowy byt produktu; bez niego nie ma nic do scorowania |
| **Hunt Status Workflow** | **Core** | Status jest wejściem do score (40 pkt za `searching`); kolejność przejść ma semantykę biznesową |
| **Seller Message Generator** | **Supporting** | Niesie wartość (PT sellers), ale to szablony bez logiki; wymienialny bez wpływu na domenę |
| **Authentication / Session** | **Generic** | Bramka dostępu; prd.md: "auth exists for data protection, not multi-tenancy" `prd.md:31`; wymienialny |
| **CSRF / Security** | **Generic** | Cross-cutting infrastructure; standardowy wzorzec |

---

## KROK 3 — Kandydaci na agregaty i niezmienniki

### A1 — WantedCard jako agregat

**Niezmiennik:** Score terminala (acquired/abandoned) wynosi zawsze 0. Karta w stanie terminalnym nie powinna rosnąć w rankingu.

- **Cytat z PRD:** `prd.md:116` — "Terminal statuses always return 0"
- **Status egzekwowania:** ✅ Egzekwowane w `CardScorer::calculate():66` i `CardScorer::explain():39`
- **Problem:** Egzekwowanie żyje w `CardScorer`, a nie w samym bycie karty. Zmiana pola `status` na `acquired` przez direct DB INSERT pominęłaby tę regułę.

### A2 — HuntStatus jako value object

**Niezmiennik:** Status może przejść tylko przez dozwolone wartości ENUM. Wartości poza ENUM są niedozwolone.

- **Cytat:** `schema.sql:29` — ENUM definition
- **Status egzekwowania:** ✅ Egzekwowane przez DB ENUM + `VALID_STATUSES` w handlerach
- **Problem:** `VALID_STATUSES` zdefiniowane jako array w 3 handlerach niezależnie (`card-add.php:19`, `card-edit.php:35`, `card-status.php:31`). Brak single source of truth.

### A3 — DifficultyScore jako value object

**Niezmiennik:** Score mieści się w zakresie 0–100. Score terminala = 0.

- **Cytat:** `CardScorer.php:12-18` (doc comment)
- **Status egzekwowania:** ⚠️ Algorytm gwarantuje max 100, ale baza nie wymusza (brak CHECK constraint — patrz TD-03 w plan.md)

---

## KROK 4 — Rozjazdy MODEL vs KOD

| Dokument mówi | Kod robi | Dowód (plik:linia) |
|---|---|---|
| "Hunt lifecycle" — karta ma cykl życia z etapami | Status to luźny string; brak klasy reprezentującej lifecycle | `card-add.php:19` — `$allowed = ['searching', 'contacted', ...]` |
| "Collector" — persona produktu, właściciel danych | Brak klasy Collector; użytkownik to `user_id INT` w DB | `schema.sql:12` — tabela `users` bez metod domenowych |
| "Difficulty score is computed automatically on save" | Score obliczany ręcznie w każdym z 3 handlerów | `card-add.php:75`, `card-edit.php:75`, `card-status.php:49` |
| Score rośnie z wiekiem karty | Score to snapshot z momentu save; nie rośnie bez akcji użytkownika | `research.md` TD-02 — stale score |
| "Language edition" — wymiar trudności | `language` to VARCHAR bez walidacji zestawu wartości | `schema.sql:26` — `VARCHAR(100)` |
| Status ENUM ma business semantics | ENUM w DB, ale PHP nie ma odpowiednika klasy/enum | brak `HuntStatus` klasy w `src/` |

---

## KROK 5 — Ranking refaktoru

| Pozycja | Kandydat | Wartość | Ryzyko bez zmiany |
|---|---|---|---|
| **#1** | VALID_STATUSES — single source of truth | Core domain, 3 rozsmarowania | Nowy status w DB wymaga zmiany w 3 plikach + łatwo o literówkę |
| **#2** | DifficultyScore CHECK constraint | Core invariant | Cicha niespójność przy manual INSERT lub przyszłym błędzie algorytmu |
| **#3** | WantedCard jako wartościowy byt (nie array) | Long-term maintainability | Brak enkapsulacji; każdy handler zna szczegóły DB schema |

---

## Ograniczenia tej analizy

- Projekt jednosobowy bez ADR-ów; rozjazdy oceniane na podstawie kodu, nie historii decyzji
- Brak zewnętrznych zależności domeny (brak ORM, brak SDK scoringu) — ACL nie jest wymagane w MVP
- PRD pisany przed implementacją: kilka pojęć (Hunt Lifecycle, Collector) może być świadomie uproszczone
