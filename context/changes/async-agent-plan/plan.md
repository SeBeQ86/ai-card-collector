# Async Agent Plan — M5L5

## Zadanie 1 — Wybrane zadanie

**Cel:** Zaimplementuj fazę 1 z `context/changes/card-scorer-analysis/plan.md` — dodaj test charakteryzujący dla `CardScorer::ageInDays()` i wyekstrahuj pomocnik `ageInDays(int $createdAt): int` z aktualnej logiki w `CardScorer.php`.

**Dlaczego to dobry kandydat:**
- Jasny zakres: dwa pliki (`src/Card/CardScorer.php`, `tests/Unit/CardScorerTest.php`)
- Nie wymaga decyzji produktowych (OQ-01 nie jest blokujący dla tej fazy)
- Nie wymaga sekretów ani dostępu do produkcji
- Warunek sukcesu jest weryfikowalny: testy zielone, `ageInDays` wyekstrahowane

---

## Zadanie 2 — Wybrany tryb kontroli

**Tryb: Sandbox w chmurze (Tryb 2)**

Uzasadnienie: Zadanie jest ograniczone do refaktoru bez decyzji live. Nie musi blokować laptopa. Chcę wrócić do gotowego PR po sesji, a nie pilnować każdego kroku.

Narzędzie: Claude Code on the web (gdy dostępne) lub headless `claude -p` z `/10x-goal-implement`.

---

## Zadanie 3 — Granice przed startem

### Polecenie dla Agenta

```text
/goal Zaimplementuj fazę 1 z context/changes/card-scorer-analysis/plan.md.

Zakres: modyfikuj tylko src/Card/CardScorer.php i tests/Unit/CardScorerTest.php.
Nie zmieniaj innych plików src/, nie zmieniaj public/, nie commituj do main.
Nie przenoś algorytmu scoringu — tylko wyekstrahuj ageInDays.

Setup: PHP 8.x dostępne; uruchom `vendor/bin/phpunit tests/Unit/` po każdej zmianie.
Sieć: nie potrzeba; zależności są w vendor/ w repo.
MCP: tylko .mcp.json z repo (jeśli istnieje); bez lokalnego profilu.
Sekrety: brak — zadanie nie wymaga żadnych tokenów ani kluczy.

Warunek stopu: testy zielone + ageInDays wyekstrahowane + commit na branchu `refactor/age-in-days`.
Limit: maks. 15 tur; jeśli blokujesz się na setupie lub testach przez 3 kolejne tury — STOP z raportem.

Nie merguj. Zakończ PR draftem z krótkim opisem co zostało zrobione.
```

### Konfiguracja sandboxa

| Wymiar | Decyzja |
|---|---|
| Setup | `php --version` + `vendor/bin/phpunit --version`; brak `composer install` (vendor w repo) |
| Sieć (setup) | Nie potrzeba |
| Sieć (praca) | Wyłączona |
| MCP | Tylko `.mcp.json` z repo, jeśli istnieje |
| Sekrety | Brak |
| Zakres plików | `src/Card/CardScorer.php`, `tests/Unit/CardScorerTest.php` |
| Warunek stopu | Zielone testy + commit na branchu |
| Limit kosztu | 15 tur |

---

## Zadanie 4 — Dry run (plan awaryjny)

Poniżej sekwencja kroków, gdyby dostęp do chmurowego sandboxa był niedostępny:

| Krok | Status |
|---|---|
| `git checkout -b refactor/age-in-days` | ✅ do wykonania lokalnie |
| Napisz test charakteryzujący dla ageInDays w `tests/Unit/CardScorerTest.php` | ✅ do wykonania lokalnie |
| Uruchom testy — upewnij się, że test jest czerwony (deliberate-break check) | ✅ do wykonania lokalnie |
| Wyekstrahuj `ageInDays(string $createdAt): int` z `CardScorer.php` | ✅ do wykonania lokalnie |
| Uruchom testy — powinny być zielone | ✅ do wykonania lokalnie |
| Commit: `refactor: extract ageInDays helper (INV-02 prerequisite)` | ✅ do wykonania lokalnie |
| Otwórz PR draft | ✅ do wykonania lokalnie |

---

## Zadanie 5 — Kontrola w trakcie (monitoring)

Checkpointy do sprawdzenia z telefonu (Remote Control lub status sandboxa):

1. **Setup OK?** — PHP dostępne, phpunit działa, repo sklonowane
2. **Test charakteryzujący napisany?** — czerwony przed ekstraktą
3. **Zakres respektowany?** — diff tylko w dwóch dozwolonych plikach
4. **Agent nie rozszerza zakresu?** — jeśli próbuje edytować `CardScorer::calculate()` lub inne pliki → STOP

---

## Zadanie 6 — Checklist review

**Zielony przebieg NIE jest sukcesem, jeśli:**

- [ ] Diff dotyka plików spoza zakresu (`public/`, `src/Auth/`, itp.)
- [ ] Test charakteryzujący nie istnieje (deliberate-break check pominięty)
- [ ] `ageInDays` nie rzuca wyjątku / nie zwraca 0 na nieprawidłowym wejściu
- [ ] Algorytm scoringu zmienił się (nie tylko ekstrakta)
- [ ] Agent poprosił o sekrety lub dostęp spoza zakresu

**Decyzja: co musiałoby się zmienić dla bezpiecznego użycia w zespole:**

Jedyna realna bariera to brak PHPUnit w bazowym obrazie sandboxa — wymaga jawnego kroku setupu. Poza tym zadanie jest naturalnie izolowane: brak sekretów, brak sieci, ograniczony zakres plików. Ten typ refaktoru (ekstrakta pomocnika z testem) jest bezpiecznym kandydatem do delegacji asynchronicznej bez nadzoru w czasie rzeczywistym.
