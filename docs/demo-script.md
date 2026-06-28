# Demo Script

A short walkthrough for demonstrating the MVP end-to-end.
Estimated time: 5–7 minutes.

---

## 1. Open the app

Navigate to `http://localhost/ai-card-collector/public/`.

**Show:** the browser is immediately redirected to `login.php`.
This demonstrates that unauthenticated access is blocked on every page.

---

## 2. Log in

Enter the seeded email and the local password, click **Log in**.

**Show:** redirect to `index.php` with the email address visible in the header.
The wanted cards list is empty on a fresh install ("No cards yet").

---

## 3. Add a wanted card

Click **+ Add card**.

Fill in:
- Card name: `Black Lotus`
- Language edition: `Japanese`
- Status: `Searching`
- Target price: `500`

Click **Add card**.

**Show:** redirect back to the list. The new card appears with a difficulty score
greater than zero (language is non-English → +40, status searching → +40,
target price set with no offer → +5, age = 0 → +0; score = **85**).

Click the score number in the Score column to expand the breakdown:
`Lang +40 · Status +40 · Price +5 · Age +0`.

---

## 4. Add a second card to show scoring contrast

Click **+ Add card** again.

Fill in:
- Card name: `Forest`
- Language edition: `English`
- Status: `Acquired`

Click **Add card**.

**Show:** the second card scores **0** — English edition and terminal status both
contribute 0 points. Black Lotus (85) sorts above it.

---

## 5. Edit a card and change status

Click **Edit** on Black Lotus.

Change:
- Status: `Offer received`
- Current offer price: `620`

Click **Save changes**.

**Show:** score drops from 85 to **55** (status offer_received → +10 instead of +40;
offer > target → +10 instead of +5). The list re-sorts immediately.

---

## 6. Generate seller messages

Click **Message** on Black Lotus.

**Show:**
- Card summary section with name, language, prices.
- English textarea pre-filled with a polite buyer message noting the budget and
  that the listed price exceeds it.
- Portuguese textarea with the equivalent message in Portuguese.
- Both textareas are read-only — no editing needed.
- A **Copy** button sits below each textarea; clicking it copies the full message
  to the clipboard and briefly changes to "Copied!". The textareas remain
  fully usable if JavaScript is disabled.
- No AI API was called; the messages are PHP string templates.

---

## 7. Delete a card

Go back to the list (**← Back to list**).

Click **Delete** on the Forest card.

**Show:** browser confirmation dialog appears ("Delete this card?").
Confirm. Card is removed. Black Lotus remains.

---

## 8. Log out

Click **Log out** in the header.

**Show:** redirect to `login.php`. Pressing the browser back button redirects
back to `login.php` — the session is fully destroyed.

---

## 9. GitHub Actions CI

Open the repository on GitHub and navigate to **Actions**.

**Show:** a passing CI run triggered by the latest push.
The single job `PHP syntax check` runs `php -l` on all files in
`public/`, `src/`, and `config/`. No deployment step is present.
