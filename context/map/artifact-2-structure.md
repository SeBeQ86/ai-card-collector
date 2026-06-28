# Artifact 2 — Structure (zależności i powiązania)

Generated: 2026-06-28 | Tool: grep na `use App\` w plikach PHP

## Graf zależności (incoming coupling — kto używa kogo)

| Moduł (src/) | Używany przez | Incoming coupling |
|---|---|---|
| `Auth\Auth` | login, logout, index, card-add, card-edit, card-delete, card-message, card-status | **8** — najwyższy w projekcie |
| `Database\Connection` | login, index, card-add, card-edit, card-delete, card-message, card-status | **7** |
| `Card\CardRepository` | index, card-add, card-edit, card-delete, card-message, card-status | **6** |
| `Security\Csrf` | login, logout, index, card-add, card-edit, card-delete, card-status | **7** |
| `Card\CardScorer` | index, card-add, card-edit, card-status | **4** |
| `Message\SellerMessageGenerator` | card-message | **1** |

## Warstwy systemu

```
public/*.php  (entry points / controllers)
      │
      ├── Auth\Auth          (session, login check)
      ├── Security\Csrf      (token generation/validation)
      ├── Database\Connection (PDO singleton)
      │
      ├── Card\CardRepository  (data access)
      ├── Card\CardScorer      (business logic)
      └── Message\SellerMessageGenerator (templates)
```

Warstwy są respektowane — brak cross-imports między src/ klasami (żadna klasa src/ nie importuje innej klasy src/). Każda klasa jest niezależna.

## Entry pointy

| Plik | Metoda HTTP | Funkcja |
|---|---|---|
| `public/login.php` | GET + POST | Formularz logowania |
| `public/logout.php` | POST | Wylogowanie |
| `public/index.php` | GET | Lista kart + stats + filtr |
| `public/card-add.php` | GET + POST | Dodaj kartę |
| `public/card-edit.php` | GET + POST | Edytuj kartę |
| `public/card-delete.php` | POST | Usuń kartę |
| `public/card-status.php` | POST | Zmień status (quick action) |
| `public/card-message.php` | GET | Generator wiadomości EN/PT |

## Cykle

**Brak cykli** — PHP plain bez frameworka, żadna klasa src/ nie importuje innej. Wszystkie zależności są jednostronne: public/ → src/.

## Centra grafu (load-bearing modules)

1. **Auth\Auth** — Ca=8, każda strona wymaga Auth::requireAuth() i Auth::user()
2. **Security\Csrf** — Ca=7, każdy formularz POST wymaga tokenu
3. **Database\Connection** — Ca=7, każda operacja DB przechodzi przez singleton

## Unknowns

- Graf statyczny nie pokazuje zależności przez `$_SESSION` (runtime coupling między Auth a każdą stroną)
- `config/app.php` jest ładowany przez `require` w każdym public/*.php — nie widoczny w `use App\` ale jest realną zależnością każdego pliku
