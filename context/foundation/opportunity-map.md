---
title: "Opportunity Map — AI Card Collector"
created: "2026-06-28"
type: opportunity-map
---

# Mapa okazji — AI Card Collector (M5L1)

Projekt solowy, więc "tarcie zespołu" rozumiemy jako: powtarzalny koszt koordynacji między **rolami** (developer / reviewer / właściciel domeny), między **narzędziami** (GitHub / lokalny XAMPP / formularz certyfikacyjny) i między **momentami** (push → review → certyfikacja).

---

## Sygnały tarcia (3 wybrane)

### Sygnał 1 — Review bez kryteriów domenowych

**Opis:** Każdy PR jest sprawdzany przez automatyczne CI (syntax check + testy), ale logika domenowa (CardScorer weights, status transitions, CSRF) nie jest oceniana automatycznie. Reviewer musi znać `docs/business-rules.md` z pamięci.

| Pole | Wartość |
|---|---|
| **Sygnał tarcia** | PR zmienia CardScorer i reviewer nie wie, czy zmiana jest zgodna z regułami z prd.md |
| **SaaS / domyślna odpowiedź** | GitHub PR status checks — częściowe; brakuje kontekstu domenowego |
| **Cienki helper** | AI code review czytający diff + `docs/business-rules.md` i `context/foundation/prd.md` → komentarz z oceną zgodności domenowej |
| **Pierwsza użyteczna wersja** | ✅ **Już zbudowane** — `.github/workflows/code-review.yml` używa Claude Haiku + `gh pr diff` |

**Status:** ✅ Zrealizowane w aktualnym projekcie.

---

### Sygnał 2 — Stale score jako niemowe ryzyko

**Opis:** Kolekcjoner nie wie, że score karty "stareje się" w DB i nie rośnie bez akcji. Lista priorytetów może dawać mylące rankingi po tygodniach bez edycji. Brak widocznego ostrzeżenia.

| Pole | Wartość |
|---|---|
| **Sygnał tarcia** | Użytkownik widzi kartę z score=40 dodaną 8 tygodni temu, a faktyczny score powinien być 45-50 |
| **SaaS / domyślna odpowiedź** | Brak — żaden SaaS nie rozwiązuje tej domeny; to specyfika TD-02 projektu |
| **Cienki helper** | Badge "⚠️ Score może być nieaktualny" przy kartach starszych niż 7 dni bez edycji — obliczany in-line przy wyświetleniu przez `CardScorer::explain()` bez zapisu do DB |
| **Pierwsza użyteczna wersja** | Dodanie warunku w `index.php` — jeśli `updated_at` > 7 dni temu i status != terminal, pokaż ikonę z tooltip "Score obliczony przy ostatniej edycji" |

**Status:** ❌ Nie zbudowane. Wymaga decyzji domenowej (OQ-01 z plan.md).

---

### Sygnał 3 — Certyfikacja bez automatycznego checklist

**Opis:** Weryfikacja wymagań na 10xBuilder / 10xArchitect / 10xChampion wymaga ręcznego sprawdzenia listy kryteriów z formularza. Przy trzech ścieżkach jednocześnie łatwo przeoczyć brakujący artefakt.

| Pole | Wartość |
|---|---|
| **Sygnał tarcia** | "Czy mam już wszystkie artefakty do formularza Architect?" — sprawdzane ręcznie |
| **SaaS / domyślna odpowiedź** | Brak (platforma kursu nie ma API) |
| **Cienki helper** | Statyczny checklist Markdown z linkami do artefaktów + status ✅/❌ |
| **Pierwsza użyteczna wersja** | Plik `context/cert-checklist.md` aktualizowany ręcznie po każdej lekcji |

**Status:** ⚡ Częściowo — poniżej.

---

## Wybrany helper do realizacji: Cert Checklist

```text
Helper:
Certification Checklist — AI Card Collector

Czyta:
Ścieżki plików w context/ i docs/ (lista artefaktów certyfikacyjnych)

Zwraca:
Checklist z linkami do artefaktów, status ✅/❌, info o brakujących elementach

Nie robi:
Nie sprawdza zawartości plików (nie waliduje jakości), nie wysyła formularza

Ryzyko danych:
Brak — tylko lokalne pliki projektowe, nic wrażliwego
```

---

## Status certyfikacji (aktualny — 2026-06-28)

### 10xBuilder ✅

| Kryterium | Artefakt | Status |
|---|---|---|
| Działający projekt na GitHub | `github.com/...` | ✅ |
| CI/CD pipeline | `.github/workflows/ci.yml` | ✅ |
| AI code review | `.github/workflows/code-review.yml` | ✅ |
| Test plan z risk register | `docs/test-plan.md` | ✅ |
| Testy jednostkowe | `tests/CardScorerTest.php` | ✅ |

### 10xArchitect ✅

| Kryterium | Artefakt | Status |
|---|---|---|
| Mapa repozytorium (L2) | `context/map/repo-map.md` | ✅ |
| Research ficzera (L3) | `context/changes/card-scorer-analysis/research.md` | ✅ |
| Plan refaktoryzacji (L4) | `context/changes/card-scorer-analysis/plan.md` | ✅ |
| DDD domain notes (L5) | `context/domain/01,02,03-*.md` | ✅ |
| Raport architektoniczny | `context/architect-report.md` | ✅ |

### 10xChampion ⚡ (w trakcie)

**Ścieżka A: Pipeline CI/CD z AI review (M5L2 + M5L3)**

| Kryterium | Artefakt | Status |
|---|---|---|
| Widok pipeline'u + job | `.github/workflows/` | ✅ |
| Logi z pipeline / joba | GitHub Actions logs (screenshot) | ❌ potrzebny screenshot |
| Działający AI review na PR | `.github/workflows/code-review.yml` | ✅ |
| Screenshot AI review na PR | GitHub PR conversation (screenshot) | ❌ potrzebny screenshot |

**Ścieżka B: Rejestr artefaktów AI (M5L4)**

| Kryterium | Artefakt | Status |
|---|---|---|
| Repozytorium z rejestrem | nie zbudowane | ❌ |
| Definicja paczki | nie zbudowane | ❌ |
| Lista wydanych wersji | nie zbudowane | ❌ |

**Rekomendacja:** Ścieżka A jest prawie gotowa — pipeline działa, potrzebne są tylko 2 screenshoty z GitHuba.
