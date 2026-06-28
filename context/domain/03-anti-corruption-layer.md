---
title: "Anti-Corruption Layer — AI Card Collector"
created: "2026-06-28"
type: refactor-plan
---

# 03 — Anti-Corruption Layer: ocena zewnętrznych zależności

## KROK 0 — Kontekst

Stack: PHP 8.x, MySQL, vanilla JS, brak frameworka. Brak Composera — CLAUDE.md: "No Composer packages without explicit task instruction".
Manifest zależności: brak `composer.json` — projekt nie używa żadnych zewnętrznych PHP packages.

**Wnioski wstępne:** AI Card Collector MVP nie ma zewnętrznych zależności domenowych do odseparowania. Brak ORM, brak scoring SDK, brak mail/SMS API, brak external auth provider.

---

## KROK 1 — Identyfikacja zewnętrznych zależności

| Warstwa | "Zależność" | Zasięg przecieku |
|---|---|---|
| Database | PDO (PHP wbudowane) | `src/Database/Connection.php` — singleton owijający PDO | ✅ już izolowane |
| Auth | PHP session (`$_SESSION`) | `src/Auth/Auth.php` — owinięte za metodami statycznymi | ✅ już izolowane |
| CSRF | PHP `random_bytes` | `src/Security/Csrf.php` — owinięte | ✅ już izolowane |
| Scoring | brak zewnętrznego SDK | `CardScorer.php` — własna logika | brak przecieku |
| Seller messages | brak zewnętrznego API | `SellerMessageGenerator.php` — szablony PHP | brak przecieku |

**Wynik:** Projekt nie ma przeciekających zewnętrznych zależności domenowych w MVP. Architektura jest flat i samowystarczalna.

---

## KROK 2 — Ocena i werdykt

**Brak kandydata na ACL w obecnym MVP.**

Powód: projekt świadomie odrzucił zewnętrzne integracje w PRD:
- `prd.md: Non-Goals` — "No Cardmarket or marketplace API integration"
- `prd.md: Non-Goals` — "No AI-generated seller messages [...] no external service dependency"
- `CLAUDE.md` — "No Composer packages without explicit task instruction"

Jedyną "zewnętrzną zależnością" jest PDO — ale jest już owinięte w `Connection.php` singleton z jawnym interfejsem. To funkcjonalny ACL dla warstwy DB.

---

## KROK 3 — Diagnoza: co byłoby ACL-kandydatem po MVP

Jeśli projekt wykroczy poza MVP (zgodnie z prd.md open questions i roadmapą), pojawią się naturalni kandydaci:

| Potencjalna integracja | Ryzyko przecieku | Rekomendacja ACL |
|---|---|---|
| Cardmarket API (ceny, dostępność) | Typy `CardmarketListing`, `PriceOffer` rozlazłyby się po handlerach i CardScorer | Port `PriceDataProvider` + adapter `CardmarketAdapter` |
| Email/SMTP (powiadomienia) | `PHPMailer` lub inny mailer mógłby wejść do handlerów bezpośrednio | Port `NotificationSender` + adapter |
| Zewnętrzny auth (OAuth/SSO) | Token z providera w `$_SESSION` zamiast własnej struktury | Port `IdentityProvider` + adapter |

---

## KROK 4 — Projekt prewencyjny: Connection jako wzorcowy ACL

`Connection.php` to istniejący przykład poprawnego ACL dla PHP PDO.

**Obecny kształt (wzorzec do naśladowania):**
```php
// src/Database/Connection.php
final class Connection {
    private static ?\PDO $pdo = null;

    public static function get(): \PDO { /* singleton */ }
}
```

Reszta kodu zna tylko `Connection::get()`, nie zna szczegółów DSN, credentiali, atrybutów PDO. Wymiana na inne PDO (np. persistent connection, read replica) dotyka tylko `Connection.php`.

**Kryterium sukcesu:** `grep -rn "new \PDO\|PDO::ATTR" src/ public/` → tylko `Connection.php`.

**Weryfikacja:** ✅ Potwierdzone przez analizę `artifact-2-structure.md` — `Connection` ma Ca=7 (7 plików zależy od Connection), ale nikt nie tworzy PDO bezpośrednio.

---

## KROK 5 — Plan i kryterium sukcesu

**Brak wymaganej implementacji ACL w obecnym MVP.**

Rekomendacja na przyszłość:
1. Przy dodaniu Cardmarket API — otwórz nową zmianę z `/10x-new cardmarket-acl` i zastosuj wzorzec z `Connection.php`
2. Utrzymuj zasadę: każda zewnętrzna zależność przechodzi przez `src/{DomainArea}/Port.php` (interfejs) + `src/{DomainArea}/Adapter.php` (implementacja)

---

## Wnioski

**AI Card Collector MVP nie wymaga Anti-Corruption Layer** — projekt jest architektonicznie czysty w zakresie zewnętrznych zależności. Jedyna "zewnętrzna" zależność (PDO) jest już poprawnie odseparowana przez `Connection.php`.

Główne ryzyko architektury leży nie w ACL, ale w:
1. Braku value objects dla kluczowych pojęć domenowych (HuntStatus, DifficultyScore) — opisane w `02-invariant-aggregate-refactor.md`
2. Score jako snapshot (TD-02) — open question domenowe w `docs/business-rules.md`

Ten dokument stanowi baseline na wypadek rozszerzenia MVP o zewnętrzne integracje.
