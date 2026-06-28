# AI Card Collector - pomysł na MVP

Chcę zbudować projekt kursowy o nazwie AI Card Collector.

To ma być mała aplikacja webowa dla kolekcjonera kart, który szuka konkretnych kart w różnych wersjach językowych, np. portugalskich, japońskich, tajskich albo innych trudniejszych do zdobycia.

Problem polega na tym, że przy większej liczbie poszukiwanych kart łatwo pogubić się w statusach, limitach cenowych, kontaktach ze sprzedawcami i tym, które karty są naprawdę priorytetowe.

## Najmniejszy wartościowy przepływ

- użytkownik loguje się do aplikacji,
- dodaje kartę do listy poszukiwanych,
- podaje nazwę karty, język, kraj/źródło poszukiwania, limit ceny, status i notatkę,
- system wylicza priorytet albo trudność zdobycia karty,
- użytkownik może wygenerować wiadomość do sprzedawcy po angielsku lub portugalsku,
- karta pojawia się na liście poszukiwań z priorytetem i statusem.

## MVP powinno zawierać

- logowanie,
- CRUD kart,
- statusy poszukiwania,
- priorytet,
- prostą logikę biznesową oceny trudności zdobycia,
- generator wiadomości do sprzedawcy,
- dokumenty projektowe,
- test z perspektywy użytkownika,
- pipeline CI/CD.

## Poza zakresem MVP

Nie chcę w MVP:

- integracji z Cardmarket API,
- automatycznego scrapowania marketplace'ów,
- płatności,
- publicznych profili,
- systemu wymiany kart między użytkownikami,
- aplikacji mobilnej,
- dużego katalogu wszystkich kart,
- OCR zdjęć kart,
- zaawansowanych statystyk kolekcji.

Projekt ma być mały, możliwy do ukończenia jako MVP, ale ciekawszy niż zwykła lista rekordów.
