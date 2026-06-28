<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\Flash;
use App\Card\CardRepository;
use App\Card\CardScorer;
use App\Database\Connection;
use App\Security\Csrf;

Auth::startSession();
Auth::requireAuth();

$user = Auth::user();

const VALID_STATUSES = ['searching', 'contacted', 'offer_received', 'acquired', 'abandoned'];

$errors = [];

$input = [
    'name'                => '',
    'api_card_id'         => '',
    'language'            => '',
    'country'             => '',
    'target_price'        => '',
    'current_offer_price' => '',
    'status'              => 'searching',
    'seller_contact'      => '',
    'seller_name'         => '',
    'purchase_price'      => '',
    'purchased_at'        => '',
    'source_url'          => '',
    'notes'               => '',
    'image_url'           => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'name'                => trim((string) ($_POST['name']                ?? '')),
        'api_card_id'         => trim((string) ($_POST['api_card_id']         ?? '')),
        'language'            => trim((string) ($_POST['language']            ?? '')),
        'country'             => trim((string) ($_POST['country']             ?? '')),
        'target_price'        => trim((string) ($_POST['target_price']        ?? '')),
        'current_offer_price' => trim((string) ($_POST['current_offer_price'] ?? '')),
        'status'              => (string) ($_POST['status'] ?? ''),
        'seller_contact'      => trim((string) ($_POST['seller_contact']      ?? '')),
        'seller_name'         => trim((string) ($_POST['seller_name']         ?? '')),
        'purchase_price'      => trim((string) ($_POST['purchase_price']      ?? '')),
        'purchased_at'        => trim((string) ($_POST['purchased_at']        ?? '')),
        'source_url'          => trim((string) ($_POST['source_url']          ?? '')),
        'notes'               => trim((string) ($_POST['notes']               ?? '')),
        'image_url'           => trim((string) ($_POST['image_url']           ?? '')),
    ];

    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!Csrf::validate($token)) {
        $errors[] = 'Nieprawidłowe żądanie — spróbuj ponownie.';
    } else {
        if ($input['name'] === '') {
            $errors[] = 'Nazwa karty jest wymagana.';
        } elseif (mb_strlen($input['name']) > 255) {
            $errors[] = 'Nazwa karty jest za długa (maks. 255 znaków).';
        }
        if ($input['language'] === '') {
            $errors[] = 'Wersja językowa jest wymagana.';
        }
        if (!in_array($input['status'], VALID_STATUSES, true)) {
            $errors[] = 'Nieprawidłowy status.';
        }
        if ($input['api_card_id'] !== '' && strlen($input['api_card_id']) > 50) {
            $errors[] = 'ID karty API jest za długie.';
        }
        if ($input['target_price'] !== '' && (!is_numeric($input['target_price']) || (float) $input['target_price'] < 0)) {
            $errors[] = 'Budżet musi być liczbą nieujemną.';
        }
        if ($input['current_offer_price'] !== '' && (!is_numeric($input['current_offer_price']) || (float) $input['current_offer_price'] < 0)) {
            $errors[] = 'Aktualna oferta musi być liczbą nieujemną.';
        }
        if ($input['purchase_price'] !== '' && (!is_numeric($input['purchase_price']) || (float) $input['purchase_price'] < 0)) {
            $errors[] = 'Cena zakupu musi być liczbą nieujemną.';
        }
        if ($input['purchased_at'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['purchased_at'])) {
            $errors[] = 'Data zakupu musi być w formacie RRRR-MM-DD.';
        }
        if ($input['purchased_at'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['purchased_at']) && $input['purchased_at'] > date('Y-m-d')) {
            $errors[] = 'Data zakupu nie może być w przyszłości.';
        }
        if ($input['source_url'] !== '' && !filter_var($input['source_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URL oferty jest nieprawidłowy.';
        }

        if (empty($errors)) {
            $targetPrice = $input['target_price'] !== ''
                ? (float) $input['target_price']
                : null;
            $offerPrice = $input['current_offer_price'] !== ''
                ? (float) $input['current_offer_price']
                : null;

            $score = CardScorer::calculate(
                $input['language'],
                $input['status'],
                $targetPrice,
                $offerPrice,
                0
            );

            $data = [
                'name'                => $input['name'],
                'api_card_id'         => $input['api_card_id'] !== '' ? $input['api_card_id'] : null,
                'language'            => $input['language'],
                'country'             => $input['country'] !== '' ? $input['country'] : null,
                'target_price'        => $targetPrice,
                'current_offer_price' => $offerPrice,
                'status'              => $input['status'],
                'seller_contact'      => $input['seller_contact'] !== '' ? $input['seller_contact'] : null,
                'seller_name'         => $input['seller_name'] !== '' ? $input['seller_name'] : null,
                'purchase_price'      => $input['purchase_price'] !== '' ? (float) $input['purchase_price'] : null,
                'purchased_at'        => $input['purchased_at'] !== '' ? $input['purchased_at'] : null,
                'source_url'          => $input['source_url'] !== '' ? $input['source_url'] : null,
                'notes'               => $input['notes'] !== '' ? $input['notes'] : null,
                'image_url'           => $input['image_url'] !== '' ? $input['image_url'] : null,
                'difficulty_score'    => $score,
            ];

            $repo = new CardRepository(Connection::get($config['db']));
            $repo->createForUser($user['id'], $data);

            Flash::set('success', 'Karta „' . $input['name'] . '" została dodana.');
            header('Location: index.php');
            exit;
        }
    }
}

$appName = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function selectedIf(string $option, string $current): string
{
    return $option === $current ? ' selected' : '';
}

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dodaj kartę — <?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
    <style>
        #add-form { max-width: 760px; }

        /* ---- Autocomplete ---- */
        .autocomplete-wrap { position: relative; }

        .autocomplete-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid #d1d5db;
            border-top: none;
            border-radius: 0 0 6px 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,.1);
            z-index: 100;
            max-height: 260px;
            overflow-y: auto;
        }

        .ac-item {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .45rem .75rem;
            cursor: pointer;
            font-size: .875rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .ac-item:last-child { border-bottom: none; }
        .ac-item:hover, .ac-item.focused { background: #eff6ff; }

        .ac-thumb {
            width: 32px;
            height: 44px;
            object-fit: contain;
            flex-shrink: 0;
            border-radius: 3px;
            background: #f9fafb;
        }

        .ac-thumb-placeholder {
            width: 32px;
            height: 44px;
            flex-shrink: 0;
            background: #f3f4f6;
            border-radius: 3px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .65rem;
            color: #9ca3af;
        }

        .ac-info { flex: 1; min-width: 0; }
        .ac-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ac-set  { font-size: .75rem; color: #6b7280; }

        .ac-spinner {
            padding: .65rem .75rem;
            font-size: .82rem;
            color: #9ca3af;
            font-style: italic;
        }

        /* ---- Card preview bar ---- */
        .card-preview-bar {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .55rem .85rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            margin-top: .4rem;
            font-size: .82rem;
        }

        .card-preview-bar img {
            width: 32px;
            height: 44px;
            object-fit: contain;
            border-radius: 3px;
        }

        .card-preview-bar .cp-clear {
            margin-left: auto;
            font-size: .75rem;
            color: #6b7280;
            cursor: pointer;
            text-decoration: underline;
        }

    </style>
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:inherit;text-decoration:none"><?= $appName ?></a></h1>
        <p>
            <a href="index.php" class="act-btn">&larr; Powrót do listy</a>
            &nbsp;
            <form method="post" action="logout.php" style="display:inline">
                <?= Csrf::field() ?>
                <button type="submit">Wyloguj</button>
            </form>
        </p>
    </header>

    <main>
        <h2>Dodaj poszukiwaną kartę</h2>

        <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <form method="post" action="card-add.php" id="add-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="api_card_id" id="api_card_id" value="<?= e($input['api_card_id']) ?>">
            <input type="hidden" name="image_url"   id="image_url"   value="<?= e($input['image_url'])   ?>">

            <!-- SEKCJA 1: Karta -->
            <div class="form-section">
                <div class="form-section-title">Karta</div>

                <div class="field-row full" style="margin-bottom:.75rem">
                    <div class="field">
                        <label for="name">Nazwa karty <span class="req">*</span></label>
                        <div class="autocomplete-wrap">
                            <input type="text" id="name" name="name" required
                                   value="<?= e($input['name']) ?>"
                                   autocomplete="off"
                                   placeholder="Zacznij pisać, aby wyszukać w TCGdex…">
                            <div class="autocomplete-dropdown" id="ac-dropdown" hidden></div>
                        </div>
                        <div id="card-preview" style="display:none"></div>
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="language">Wersja językowa <span class="req">*</span></label>
                        <select id="language" name="language" required>
                            <option value="">— wybierz —</option>
                            <?php foreach ([
                                'English'              => 'Angielski',
                                'Japanese'             => 'Japoński',
                                'Portuguese'           => 'Portugalski',
                                'French'               => 'Francuski',
                                'German'               => 'Niemiecki',
                                'Spanish'              => 'Hiszpański',
                                'Korean'               => 'Koreański',
                                'Thai'                 => 'Tajski',
                                'Chinese (Traditional)'=> 'Chiński (tradycyjny)',
                                'Chinese (Simplified)' => 'Chiński (uproszczony)',
                                'Indonesian'           => 'Indonezyjski',
                                'Russian'              => 'Rosyjski',
                                'Polish'               => 'Polski',
                            ] as $val => $lbl): ?>
                            <option value="<?= e($val) ?>"<?= $input['language'] === $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="country">Kraj / region</label>
                        <input type="text" id="country" name="country"
                               placeholder="np. Japan, Brazil"
                               value="<?= e($input['country']) ?>">
                    </div>
                </div>
            </div>

            <!-- SEKCJA 2: Status i ceny -->
            <div class="form-section">
                <div class="form-section-title">Status i ceny</div>

                <div class="field-row" style="margin-bottom:.75rem">
                    <div class="field">
                        <label for="status">Status <span class="req">*</span></label>
                        <select id="status" name="status" required>
                            <option value="searching"<?=      selectedIf('searching',      $input['status']) ?>>Szukam</option>
                            <option value="contacted"<?=      selectedIf('contacted',      $input['status']) ?>>Skontaktowano</option>
                            <option value="offer_received"<?= selectedIf('offer_received', $input['status']) ?>>Oferta otrzymana</option>
                            <option value="acquired"<?=       selectedIf('acquired',       $input['status']) ?>>Zakupiono</option>
                            <option value="abandoned"<?=      selectedIf('abandoned',      $input['status']) ?>>Porzucono</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="seller_contact">Kontakt do sprzedającego</label>
                        <input type="text" id="seller_contact" name="seller_contact"
                               placeholder="nick, e-mail, URL…"
                               value="<?= e($input['seller_contact']) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="target_price">Budżet (€)</label>
                        <input type="number" id="target_price" name="target_price"
                               step="any" min="0"
                               placeholder="0.00"
                               value="<?= e($input['target_price']) ?>">
                        <div id="market-price-hint" style="display:none;font-size:.75rem;color:#6b7280;margin-top:.3rem;padding:.3rem .55rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:4px;line-height:1.5"></div>
                    </div>
                    <div class="field">
                        <label for="current_offer_price">Aktualna oferta (€)</label>
                        <input type="number" id="current_offer_price" name="current_offer_price"
                               step="any" min="0"
                               placeholder="0.00"
                               value="<?= e($input['current_offer_price']) ?>">
                    </div>
                </div>
            </div>

            <!-- SEKCJA 3: Notatki -->
            <div class="form-section">
                <div class="form-section-title">Notatki</div>
                <div class="field">
                    <label for="notes">Notatki</label>
                    <textarea id="notes" name="notes" rows="3"
                              placeholder="np. PSA 8, NM, 1st edition — trafi do wygenerowanej wiadomości"><?= e($input['notes']) ?></textarea>
                </div>
            </div>

            <!-- SEKCJA 4: Archiwum dealu -->
            <div class="form-section" id="deal-fields">
                <div class="form-section-title">Archiwum dealu <span style="font-weight:400;font-size:.8rem;color:#9ca3af">(opcjonalnie)</span></div>

                <div class="field-row">
                    <div class="field">
                        <label for="seller_name">Nazwa sprzedającego</label>
                        <input type="text" id="seller_name" name="seller_name"
                               placeholder="np. CardMarket_seller123"
                               value="<?= e($input['seller_name']) ?>">
                    </div>
                    <div class="field">
                        <label for="source_url">Link do oferty</label>
                        <input type="url" id="source_url" name="source_url"
                               placeholder="https://…"
                               value="<?= e($input['source_url']) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="purchase_price">Cena zakupu (€)</label>
                        <input type="number" id="purchase_price" name="purchase_price"
                               step="any" min="0"
                               placeholder="0.00"
                               value="<?= e($input['purchase_price']) ?>">
                    </div>
                    <div class="field">
                        <label for="purchased_at">Data zakupu</label>
                        <input type="date" id="purchased_at" name="purchased_at"
                               value="<?= e($input['purchased_at']) ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Dodaj kartę</button>
                <a href="index.php" class="btn-cancel">Anuluj</a>
            </div>
        </form>
    </main>
    <?php require '_flash.php'; ?>
    <script>
    (function () {
        var nameInput   = document.getElementById('name');
        var dropdown    = document.getElementById('ac-dropdown');
        var cardPreview = document.getElementById('card-preview');
        var hiddenId    = document.getElementById('api_card_id');
        var hiddenImg   = document.getElementById('image_url');

        var debounceTimer = null;
        var currentFocus  = -1;
        var lastQuery     = '';

        nameInput.addEventListener('input', function () {
            var q = nameInput.value.trim();
            if (q === lastQuery) return;
            lastQuery = q;

            clearTimeout(debounceTimer);
            if (q.length < 2) {
                closeDropdown();
                return;
            }

            debounceTimer = setTimeout(function () { fetchSuggestions(q); }, 320);
        });

        nameInput.addEventListener('keydown', function (e) {
            var items = dropdown.querySelectorAll('.ac-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocus = Math.min(currentFocus + 1, items.length - 1);
                setFocus(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocus = Math.max(currentFocus - 1, 0);
                setFocus(items);
            } else if (e.key === 'Enter' && currentFocus >= 0) {
                e.preventDefault();
                if (items[currentFocus]) items[currentFocus].click();
            } else if (e.key === 'Escape') {
                closeDropdown();
            }
        });

        // mousedown fires before blur — prevent default so dropdown stays open long enough for click
        dropdown.addEventListener('mousedown', function (e) {
            e.preventDefault();
        });

        document.addEventListener('click', function (e) {
            if (!nameInput.contains(e.target) && !dropdown.contains(e.target)) {
                closeDropdown();
            }
        });

        function fetchSuggestions(q) {
            dropdown.innerHTML = '<div class="ac-spinner">Szukam…</div>';
            dropdown.hidden = false;

            var apiUrl = '<?= htmlspecialchars(rtrim($config['base_url'], '/'), ENT_QUOTES, 'UTF-8') ?>/api/card-search.php';
            fetch(apiUrl + '?q=' + encodeURIComponent(q))
                .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                .then(function (cards) { renderDropdown(cards); })
                .catch(function () {
                    dropdown.innerHTML = '<div class="ac-spinner" style="color:#dc2626">Błąd połączenia z API — spróbuj wpisać pełną nazwę.</div>';
                });
        }

        function renderDropdown(cards) {
            if (!cards || cards.length === 0) {
                dropdown.innerHTML = '<div class="ac-spinner">Brak wyników.</div>';
                return;
            }

            dropdown.innerHTML = '';
            currentFocus = -1;

            cards.forEach(function (card) {
                var item = document.createElement('div');
                item.className = 'ac-item';

                var thumb = '';
                if (card.image_small) {
                    thumb = '<img class="ac-thumb" src="' + escHtml(card.image_small) + '" alt="" loading="lazy">';
                } else {
                    thumb = '<div class="ac-thumb-placeholder">IMG</div>';
                }

                item.innerHTML = thumb +
                    '<div class="ac-info">' +
                        '<div class="ac-name">' + escHtml(card.name) + '</div>' +
                        '<div class="ac-set">'  + escHtml(card.set)  + '</div>' +
                    '</div>';

                item.addEventListener('click', function () {
                    selectCard(card);
                });

                dropdown.appendChild(item);
            });
        }

        function selectCard(card) {
            nameInput.value = card.name;
            hiddenId.value  = card.id;
            hiddenImg.value = card.image_small || '';
            closeDropdown();
            lastQuery = card.name;

            showPreview(card, null);

            // Fetch full card details (pricing, rarity, set number)
            var detailUrl = '<?= htmlspecialchars(rtrim($config['base_url'], '/'), ENT_QUOTES, 'UTF-8') ?>/api/card-detail.php';
            fetch(detailUrl + '?id=' + encodeURIComponent(card.id))
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (full) {
                    if (!full) return;
                    hiddenImg.value = full.image_large || full.image_small || card.image_small || '';
                    showPreview(full, full);

                    // Auto-fill budget only for English — other editions have different prices
                    var langSelect  = document.getElementById('language');
                    var chosenLang  = langSelect ? langSelect.value : '';
                    var isEnglish   = chosenLang === 'English' || chosenLang === '';
                    var priceField  = document.getElementById('target_price');
                    if (priceField && priceField.value === '' && full.price_avg30 !== null && isEnglish) {
                        priceField.value = full.price_avg30.toFixed(2);
                    }

                    // Show market price hint with clear EN label and language warning
                    var hint = document.getElementById('market-price-hint');
                    if (hint && (full.price_avg30 !== null || full.price_trend !== null)) {
                        var parts = [];
                        if (full.price_avg30 !== null) parts.push('śr. 30 dni: <strong>' + escHtml(full.price_avg30.toFixed(2)) + ' €</strong>');
                        if (full.price_trend !== null) parts.push('trend: <strong>' + escHtml(full.price_trend.toFixed(2)) + ' €</strong>');
                        if (full.price_low   !== null) parts.push('min.: ' + escHtml(full.price_low.toFixed(2)) + ' €');
                        var langNote = isEnglish
                            ? ''
                            : ' &nbsp;<span style="color:#d97706;font-weight:600">&#9888; dotyczy wersji EN — cena ' + escHtml(chosenLang) + ' może być znacząco inna</span>';
                        hint.innerHTML = '&#128200; Cardmarket EN — ' + parts.join(' &nbsp;·&nbsp; ') + langNote;
                        hint.style.display = 'block';
                    }
                })
                .catch(function () { /* detail fetch failure is non-fatal */ });
        }

        function showPreview(card, full) {
            var imgHtml = card.image_small
                ? '<img src="' + escHtml(card.image_small) + '" alt="">'
                : '';

            var metaParts = [];
            if (full) {
                if (full.set)      metaParts.push(escHtml(full.set));
                if (full.local_id) metaParts.push('#' + escHtml(full.local_id));
                if (full.rarity)   metaParts.push(escHtml(full.rarity));
            } else if (card.set) {
                metaParts.push(escHtml(card.set));
            }
            var metaHtml = metaParts.length
                ? '<br><span style="color:#6b7280;font-size:.78rem">' + metaParts.join(' · ') + '</span>'
                : '';

            cardPreview.innerHTML =
                '<div class="card-preview-bar">' +
                    imgHtml +
                    '<div><strong>' + escHtml(card.name) + '</strong>' + metaHtml + '</div>' +
                    '<span class="cp-clear" id="cp-clear">Wyczyść</span>' +
                '</div>';
            cardPreview.style.display = 'block';

            document.getElementById('cp-clear').addEventListener('click', function () {
                hiddenId.value            = '';
                hiddenImg.value           = '';
                cardPreview.style.display = 'none';
                cardPreview.innerHTML     = '';
                var hint = document.getElementById('market-price-hint');
                if (hint) { hint.style.display = 'none'; hint.innerHTML = ''; }
            });
        }

        // Re-evaluate hint when language changes after card was already selected
        var langSelectEl = document.getElementById('language');
        if (langSelectEl) {
            langSelectEl.addEventListener('change', function () {
                var hint = document.getElementById('market-price-hint');
                if (!hint || hint.style.display === 'none') return;
                var isEn  = this.value === 'English' || this.value === '';
                var inner = hint.innerHTML;
                // Remove previous language note if present
                inner = inner.replace(/&nbsp;<span[^>]*>&#9888;.*?<\/span>/, '');
                if (!isEn && this.value !== '') {
                    inner += ' &nbsp;<span style="color:#d97706;font-weight:600">&#9888; dotyczy wersji EN — cena ' + escHtml(this.value) + ' może być znacząco inna</span>';
                }
                hint.innerHTML = inner;
            });
        }

        function closeDropdown() {
            dropdown.hidden = true;
            dropdown.innerHTML = '';
            currentFocus = -1;
        }

        function setFocus(items) {
            items.forEach(function (el, i) {
                el.classList.toggle('focused', i === currentFocus);
            });
            if (items[currentFocus]) {
                items[currentFocus].scrollIntoView({ block: 'nearest' });
            }
        }

        function escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    })();
    </script>
</body>
</html>

