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
- limitu ceny,
- notatki użytkownika,
- wybranego języka wiadomości.

## BR-004: Fallback bez AI

Jeżeli integracja AI nie jest skonfigurowana, aplikacja powinna móc przygotować prosty szablon wiadomości bez wywołania API.
