# Manual Test Plan

Performed by hand in a browser before merging to `main` or deploying.
No automated test runner required for this plan.

---

## Prerequisites

- XAMPP running (Apache + MySQL).
- Schema imported: `database/schema.sql` then the local user insert (see `docs/deployment.md`).
- App reachable at `http://localhost/ai-card-collector/public/`.
- You know the password that matches the hash stored in `users`.

---

## 1. Login redirects (access protection before login)

| Step | Action | Expected result |
|------|--------|-----------------|
| 1.1 | Open `http://localhost/ai-card-collector/public/index.php` without logging in | Redirected to `login.php` |
| 1.2 | Open `public/card-add.php` directly without a session | Redirected to `login.php` |
| 1.3 | Open `public/card-edit.php?id=1` without a session | Redirected to `login.php` |
| 1.4 | Open `public/card-message.php?id=1` without a session | Redirected to `login.php` |

---

## 2. Failed login

| Step | Action | Expected result |
|------|--------|-----------------|
| 2.1 | Open `login.php`, submit with a wrong password | Stay on `login.php`, generic error "Invalid email or password." shown |
| 2.2 | Submit with a wrong email address | Same generic error — no indication which field is wrong |
| 2.3 | Submit empty form | Same generic error |

---

## 3. Successful login

| Step | Action | Expected result |
|------|--------|-----------------|
| 3.1 | Open `login.php`, enter correct email and password | Redirected to `index.php` |
| 3.2 | Logged-in email address shown in the page header | Correct email displayed |
| 3.3 | Reload `index.php` | Still logged in (session persists) |

---

## 4. Add wanted card

| Step | Action | Expected result |
|------|--------|-----------------|
| 4.1 | Click "+ Add card" on `index.php` | `card-add.php` form opens |
| 4.2 | Submit form with name and language fields empty | Validation errors shown, no card created |
| 4.3 | Submit form with an invalid status value | Validation error shown |
| 4.4 | Submit form with a non-numeric target price | Validation error shown |
| 4.5 | Fill in name = "Black Lotus", language = "Japanese", status = "searching", target price = 500 | Redirected to `index.php`, card appears in the list |
| 4.6 | Add a second card with language = "English" | Verify it scores lower than the Japanese card (difficulty score column) |

---

## 5. List wanted cards

| Step | Action | Expected result |
|------|--------|-----------------|
| 5.1 | Open `index.php` after adding cards | Cards listed, sorted highest difficulty score first |
| 5.2 | Card count shown in the heading ("Wanted cards (N)") | Count matches the number of rows |
| 5.3 | Table shows: Score, Name, Language, Country, Target price, Current offer, Status, Added, Actions | All columns present |
| 5.4 | Empty state (no cards yet) | "No cards yet. Add your first card." message shown |

---

## 6. Edit wanted card and status

| Step | Action | Expected result |
|------|--------|-----------------|
| 6.1 | Click "Edit" on a card | `card-edit.php` opens with all current values pre-filled |
| 6.2 | Clear the name field and submit | Validation error shown, no update |
| 6.3 | Change status from "searching" to "offer_received", add a current offer price, save | Redirected to `index.php`, updated values shown |
| 6.4 | Change status to "acquired" or "abandoned" and save | Difficulty score becomes 0 |
| 6.5 | Try opening `card-edit.php?id=999` (non-existent card) | Redirected to `index.php` |

---

## 7. Delete wanted card

| Step | Action | Expected result |
|------|--------|-----------------|
| 7.1 | Click "Delete" on a card | Browser confirmation dialog appears |
| 7.2 | Cancel the confirmation | Card still in the list, no request sent |
| 7.3 | Click "Delete" and confirm | Redirected to `index.php`, card no longer in the list |
| 7.4 | Attempt to send a POST request to `card-delete.php` with a card id belonging to another user (requires a second test account or manual token crafting) | Card not deleted, silently ignored |

---

## 8. Generate seller messages

| Step | Action | Expected result |
|------|--------|-----------------|
| 8.1 | Click "Message" on a card | `card-message.php` opens |
| 8.2 | Card summary section shows correct name, language, status, prices | Values match what was saved |
| 8.3 | English textarea is pre-filled with a polite buyer message | Message mentions card name, language, target price if set |
| 8.4 | Portuguese textarea is pre-filled with the same information in Portuguese | Message mentions card name, language, target price if set |
| 8.5 | Both textareas are read-only (no accidental edit) | Cannot type in the textareas |
| 8.6 | Card with no price set — check both messages | Price lines omitted, message still coherent |
| 8.7 | Card where offer > target — check both messages | Messages note that offer exceeds budget |

---

## 9. Logout

| Step | Action | Expected result |
|------|--------|-----------------|
| 9.1 | Click "Log out" | Redirected to `login.php` |
| 9.2 | Browser back button after logout | Redirected back to `login.php` (session destroyed, auth gate fires) |

---

## 10. Access protection after logout

| Step | Action | Expected result |
|------|--------|-----------------|
| 10.1 | After logout, open `index.php` directly in the address bar | Redirected to `login.php` |
| 10.2 | After logout, open `card-add.php` | Redirected to `login.php` |
| 10.3 | After logout, attempt a POST to `card-delete.php` with a valid `card_id` | Redirected to `login.php` before any DB work |

---

## 11. Risk — Unauthenticated or wrong-user access

**Risk: an unauthenticated visitor, or a logged-in user with a crafted request,
can access, modify, or delete another user's wanted cards.**

_Prerequisites: complete sections 3–5 so at least two cards exist for the test
account. A second browser profile or incognito window is useful for the
unauthenticated steps._

| Step | Action | Expected result |
|------|--------|-----------------|
| 11.1 | Without any session, open `index.php` | Redirected to `login.php` — no card data visible |
| 11.2 | Without any session, open `card-edit.php?id=1` | Redirected to `login.php` — no form rendered |
| 11.3 | Without any session, send a POST to `card-delete.php` with `card_id=1` | Redirected to `login.php` — no DB write performed |
| 11.4 | Log in and open `index.php` | Only cards belonging to the logged-in user are listed; no other user's cards appear |
| 11.5 | While logged in, open `card-edit.php?id=N` where N is a card id that does not belong to this user (or does not exist) | Redirected to `index.php` — no form rendered, no data leaked |
| 11.6 | While logged in, POST to `card-delete.php` with a `card_id` that belongs to another user | Redirected to `index.php` — card not deleted, no error exposed to the attacker |
| 11.7 | Log out (§9), then open `index.php` | Redirected to `login.php` — session is gone, back-button also redirects |

---

## 12. Risk — Difficulty scoring produces misleading priority values

**Risk: the business scoring rule produces incorrect priority values, causing
hard-to-find cards to be ranked below easy ones.**

_Add the cards below in sequence; note the score shown in the Score column after
each redirect to `index.php`._

| Step | Action | Expected result |
|------|--------|-----------------|
| 12.1 | Add card: language = `Japanese`, status = `searching`, no prices | Score > 0 (non-terminal status; non-English language both contribute) |
| 12.2 | Add card: language = `English`, status = `contacted`, no prices | Score > 0 (non-terminal status contributes even for English) |
| 12.3 | Add card: language = `English`, status = `offer_received`, no prices | Score > 0 (offer_received is still non-terminal) |
| 12.4 | Edit the card from 12.1, change status to `acquired`, save | Score becomes **0** — terminal status short-circuits all other inputs |
| 12.5 | Edit the card from 12.2, change status to `abandoned`, save | Score becomes **0** — abandoned is also terminal |
| 12.6 | Add two otherwise identical cards: one with language = `Japanese`, one with language = `English`, same status (`searching`), no prices | Japanese card scores higher than the English card — non-English language adds points |
| 12.7 | Add card: language = `Japanese`, status = `searching`, target price = `100`, current offer = `150` (offer exceeds budget) | Score is higher than the same card with no prices — price mismatch adds points |
| 12.8 | Edit the card from 12.7, set current offer = `80` (offer within budget), save | Score drops compared to 12.7 — offer within budget contributes 0 price points |
