# Business Rules

## BR-001: Scoring trudności zdobycia karty

System wylicza trudność zdobycia karty na podstawie kilku prostych sygnałów:

- trudny język karty,
- status problemowy,
- przekroczony limit ceny,
- długi czas poszukiwania,
- brak danych sprzedawcy lub źródła.

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
