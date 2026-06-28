# Artifact 3 — Contributors (kontekst kontrybutorów)

Generated: 2026-06-28

## Obserwacja

Projekt ma jednego kontrybutora — **Sebastian Zylkowski** — właściciel i jedyny deweloper. Nie ma rozproszonej wiedzy zespołowej. Cały kontekst domenowy jest skupiony w jednej osobie.

## Profil kontrybutora

| Kontrybutor | Commity | Obszary | Typ wiedzy |
|---|---|---|---|
| Sebastian Zylkowski | 2 | Cały projekt | Właściciel domeny, kolekcjoner kart, decydent architektoniczny |

## Obszary wymagające uwagi przed zmianą

| Obszar | Dlaczego wrażliwy | Kogo zapytać |
|---|---|---|
| Algorytm scoringu (CardScorer) | Reguły punktacji są decyzjami domenowymi (co to znaczy "trudna karta") — nie wynikają z kodu | Sebastian — rozumie kontekst kolekcjonowania |
| Seller message templates | Treść EN/PT to decyzja komunikacyjna, nie techniczna | Sebastian — zna kontekst sprzedaży kart |
| Status lifecycle | 5 statusów tworzy workflow — kolejność i przejścia między nimi mają sens biznesowy | Sebastian — rozumie swój proces poszukiwań |
| Schema bazy | `target_price` vs `current_offer_price` — semantyka tych pól jest domenowa | Sebastian — wie co wpisuje w formularze |

## Wiedza ukryta (nie w kodzie)

- Dlaczego język angielski = 0 pkt a japoński = 40 pkt (rynek wtórny kart)
- Dlaczego `target_price` może być null (nieznana wartość rynkowa)
- Dlaczego PT (portugieski) jako drugi język wiadomości — kontekst geograficzny sprzedawców
- Typowy workflow: searching → contacted → offer_received (lub abandoned) → acquired

## Wniosek

Brak ryzyka "silosowania wiedzy" — jednosobowy projekt. Ryzyko jest odwrotne: jeśli projekt przejmie inny deweloper, całe domain knowledge musi być udokumentowane. Obecne `docs/business-rules.md` i `context/foundation/prd.md` częściowo to adresują.
