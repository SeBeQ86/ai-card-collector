# Business Rules

## BR-001: Scoring trudności zdobycia karty

System wylicza trudność zdobycia karty na podstawie pięciu sygnałów (zakres: aktywne 0–155, terminalne zawsze 0):

| Składnik | Zakres | Logika |
|---|---|---|
| Język karty | 0–35 | JP/TH/PT/ID=35; FR/DE/ES/KR/RU/PL/ZH=20; EN=0 |
| Status | 0–40 | searching=40; contacted=25; offer_received=10; acquired/abandoned=0 |
| Presja cenowa | 0–25 | oferta > limit=25; limit bez oferty=15; brak danych=8; w limicie=0 |
| Wiek wpisu | 0–15 | +1 za każde 5 dni bez rozwiązania, max 15 |
| Presja rynkowa | 0–40 | pokrycie = limit/cena_rynkowa: ≥100%=0; 85–100%=+10; 70–85%=+20; 50–70%=+30; <50%=+40 |

Cena rynkowa (`market_price`) pochodzi z TCGdex API (Cardmarket avg30). Presja rynkowa wynosi 0, jeśli cena rynkowa nie jest znana.

## BR-002: Priorytet

Priorytet karty może wynikać z ręcznej decyzji użytkownika oraz scoringu systemowego.

## BR-003: Wiadomość do sprzedawcy

Użytkownik może wygenerować wiadomość do sprzedawcy na podstawie:

- nazwy karty,
- języka karty,
- kraju/źródła,
- limitu ceny (`target_price`),
- aktualnej oferty (`current_offer_price`),
- notatki użytkownika,
- wybranego języka wiadomości.

Obsługiwane języki wiadomości: **English, Deutsch, Français, Español, Português, 日本語** (6 szablonów PHP, brak wywołań sieciowych).

Logika treści wiadomości zależy od relacji cen:

| Sytuacja | Treść |
|---|---|
| Oferta > limit | Wiadomość negocjacyjna — prośba o elastyczność cenową |
| Oferta ≤ limit | Potwierdzenie zainteresowania — cena mieści się w budżecie |
| Brak danych cenowych | Ogólne zapytanie o dostępność karty |

## BR-004: Fallback bez AI

Wiadomości do sprzedawcy są zawsze generowane z szablonów PHP — nie wymagają API AI i działają bez połączenia sieciowego.
