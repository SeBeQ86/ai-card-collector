<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
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
    'notes'               => '',
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
        'notes'               => trim((string) ($_POST['notes']               ?? '')),
    ];

    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!Csrf::validate($token)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        if ($input['name'] === '') {
            $errors[] = 'Name is required.';
        }
        if ($input['language'] === '') {
            $errors[] = 'Language is required.';
        }
        if (!in_array($input['status'], VALID_STATUSES, true)) {
            $errors[] = 'Status is invalid.';
        }
        if ($input['api_card_id'] !== '' && strlen($input['api_card_id']) > 50) {
            $errors[] = 'API card ID is too long.';
        }
        if ($input['target_price'] !== '' && !is_numeric($input['target_price'])) {
            $errors[] = 'Target price must be a number.';
        }
        if ($input['current_offer_price'] !== '' && !is_numeric($input['current_offer_price'])) {
            $errors[] = 'Current offer price must be a number.';
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
                'notes'               => $input['notes'] !== '' ? $input['notes'] : null,
                'difficulty_score'    => $score,
            ];

            $repo = new CardRepository(Connection::get($config['db']));
            $repo->createForUser($user['id'], $data);

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add card — <?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
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

        .card-preview-bar {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .6rem .9rem;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            margin-top: .4rem;
            font-size: .82rem;
        }

        .card-preview-bar img {
            width: 36px;
            height: 50px;
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
        <h1><?= $appName ?></h1>
        <p><a href="index.php">&larr; Back to list</a></p>
    </header>

    <main>
        <h2>Add wanted card</h2>

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

            <p>
                <label for="name">Card name <span aria-label="required">*</span></label><br>
                <div class="autocomplete-wrap">
                    <input type="text" id="name" name="name" required
                           value="<?= e($input['name']) ?>"
                           autocomplete="off"
                           placeholder="Start typing to search…">
                    <div class="autocomplete-dropdown" id="ac-dropdown" hidden></div>
                </div>
                <div id="card-preview" style="display:none"></div>
            </p>

            <p>
                <label for="language">Language edition <span aria-label="required">*</span></label><br>
                <input type="text" id="language" name="language" required
                       placeholder="e.g. Japanese, Portuguese, Thai, English"
                       value="<?= e($input['language']) ?>">
            </p>

            <p>
                <label for="country">Country / region</label><br>
                <input type="text" id="country" name="country"
                       placeholder="e.g. Japan, Brazil"
                       value="<?= e($input['country']) ?>">
            </p>

            <p>
                <label for="status">Status <span aria-label="required">*</span></label><br>
                <select id="status" name="status" required>
                    <option value="searching"<?=      selectedIf('searching',      $input['status']) ?>>Searching</option>
                    <option value="contacted"<?=      selectedIf('contacted',      $input['status']) ?>>Contacted</option>
                    <option value="offer_received"<?= selectedIf('offer_received', $input['status']) ?>>Offer received</option>
                    <option value="acquired"<?=       selectedIf('acquired',       $input['status']) ?>>Acquired</option>
                    <option value="abandoned"<?=      selectedIf('abandoned',      $input['status']) ?>>Abandoned</option>
                </select>
            </p>

            <p>
                <label for="target_price">Target price (€)</label><br>
                <input type="number" id="target_price" name="target_price"
                       step="any" min="0"
                       value="<?= e($input['target_price']) ?>">
            </p>

            <p>
                <label for="current_offer_price">Current offer price (€)</label><br>
                <input type="number" id="current_offer_price" name="current_offer_price"
                       step="any" min="0"
                       value="<?= e($input['current_offer_price']) ?>">
            </p>

            <p>
                <label for="seller_contact">Seller contact</label><br>
                <input type="text" id="seller_contact" name="seller_contact"
                       value="<?= e($input['seller_contact']) ?>">
            </p>

            <p>
                <label for="notes">Notes</label><br>
                <textarea id="notes" name="notes" rows="4"><?= e($input['notes']) ?></textarea>
            </p>

            <p>
                <button type="submit">Add card</button>
            </p>
        </form>
    </main>
    <script>
    (function () {
        var nameInput   = document.getElementById('name');
        var dropdown    = document.getElementById('ac-dropdown');
        var cardPreview = document.getElementById('card-preview');
        var hiddenId    = document.getElementById('api_card_id');

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

        document.addEventListener('click', function (e) {
            if (!nameInput.contains(e.target) && !dropdown.contains(e.target)) {
                closeDropdown();
            }
        });

        function fetchSuggestions(q) {
            dropdown.innerHTML = '<div class="ac-spinner">Searching…</div>';
            dropdown.hidden = false;

            fetch('api/card-search.php?q=' + encodeURIComponent(q))
                .then(function (r) { return r.ok ? r.json() : []; })
                .then(function (cards) { renderDropdown(cards); })
                .catch(function () { closeDropdown(); });
        }

        function renderDropdown(cards) {
            if (!cards || cards.length === 0) {
                dropdown.innerHTML = '<div class="ac-spinner">No results found.</div>';
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
            nameInput.value  = card.name;
            hiddenId.value   = card.id;
            closeDropdown();
            lastQuery = card.name;

            // Show preview bar
            var imgHtml = card.image_small
                ? '<img src="' + escHtml(card.image_small) + '" alt="">'
                : '';
            cardPreview.innerHTML =
                '<div class="card-preview-bar">' +
                    imgHtml +
                    '<div><strong>' + escHtml(card.name) + '</strong><br>' +
                        '<span style="color:#6b7280;font-size:.78rem">' + escHtml(card.set) + '</span>' +
                    '</div>' +
                    '<span class="cp-clear" id="cp-clear">Clear selection</span>' +
                '</div>';
            cardPreview.style.display = 'block';

            document.getElementById('cp-clear').addEventListener('click', function () {
                hiddenId.value          = '';
                cardPreview.style.display = 'none';
                cardPreview.innerHTML   = '';
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
