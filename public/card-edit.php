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

$cardId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($cardId <= 0) {
    header('Location: index.php');
    exit;
}

$pdo  = Connection::get($config['db']);
$repo = new CardRepository($pdo);
$card = $repo->findForUser($user['id'], $cardId);

if ($card === null) {
    header('Location: index.php');
    exit;
}

const VALID_STATUSES = ['searching', 'contacted', 'offer_received', 'acquired', 'abandoned'];

$errors = [];

$input = [
    'name'                => (string) $card['name'],
    'api_card_id'         => (string) ($card['api_card_id'] ?? ''),
    'language'            => (string) $card['language'],
    'country'             => (string) ($card['country'] ?? ''),
    'target_price'        => $card['target_price'] !== null ? (string) $card['target_price'] : '',
    'current_offer_price' => $card['current_offer_price'] !== null ? (string) $card['current_offer_price'] : '',
    'purchase_price'      => $card['purchase_price'] !== null ? (string) $card['purchase_price'] : '',
    'purchased_at'        => (string) ($card['purchased_at'] ?? ''),
    'source_url'          => (string) ($card['source_url'] ?? ''),
    'seller_name'         => (string) ($card['seller_name'] ?? ''),
    'status'              => (string) $card['status'],
    'seller_contact'      => (string) ($card['seller_contact'] ?? ''),
    'notes'               => (string) ($card['notes'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = [
        'name'                => trim((string) ($_POST['name']                ?? '')),
        'api_card_id'         => trim((string) ($_POST['api_card_id']         ?? '')),
        'language'            => trim((string) ($_POST['language']            ?? '')),
        'country'             => trim((string) ($_POST['country']             ?? '')),
        'target_price'        => trim((string) ($_POST['target_price']        ?? '')),
        'current_offer_price' => trim((string) ($_POST['current_offer_price'] ?? '')),
        'purchase_price'      => trim((string) ($_POST['purchase_price']      ?? '')),
        'purchased_at'        => trim((string) ($_POST['purchased_at']        ?? '')),
        'source_url'          => trim((string) ($_POST['source_url']          ?? '')),
        'seller_name'         => trim((string) ($_POST['seller_name']         ?? '')),
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
        if ($input['purchase_price'] !== '' && !is_numeric($input['purchase_price'])) {
            $errors[] = 'Purchase price must be a number.';
        }
        if ($input['purchased_at'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['purchased_at'])) {
            $errors[] = 'Purchase date must be in YYYY-MM-DD format.';
        }
        if ($input['source_url'] !== '' && !filter_var($input['source_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Source URL is not a valid URL.';
        }

        if (empty($errors)) {
            $targetPrice   = $input['target_price'] !== '' ? (float) $input['target_price'] : null;
            $offerPrice    = $input['current_offer_price'] !== '' ? (float) $input['current_offer_price'] : null;
            $purchasePrice = $input['purchase_price'] !== '' ? (float) $input['purchase_price'] : null;

            $createdTs = strtotime((string) $card['created_at']);
            $ageInDays = $createdTs !== false
                ? (int) (((new DateTimeImmutable())->getTimestamp() - $createdTs) / 86400)
                : 0;

            $score = CardScorer::calculate(
                $input['language'],
                $input['status'],
                $targetPrice,
                $offerPrice,
                $ageInDays
            );

            $data = [
                'name'                => $input['name'],
                'api_card_id'         => $input['api_card_id'] !== '' ? $input['api_card_id'] : null,
                'language'            => $input['language'],
                'country'             => $input['country'] !== '' ? $input['country'] : null,
                'target_price'        => $targetPrice,
                'current_offer_price' => $offerPrice,
                'purchase_price'      => $purchasePrice,
                'purchased_at'        => $input['purchased_at'] !== '' ? $input['purchased_at'] : null,
                'source_url'          => $input['source_url'] !== '' ? $input['source_url'] : null,
                'seller_name'         => $input['seller_name'] !== '' ? $input['seller_name'] : null,
                'status'              => $input['status'],
                'seller_contact'      => $input['seller_contact'] !== '' ? $input['seller_contact'] : null,
                'notes'               => $input['notes'] !== '' ? $input['notes'] : null,
                'difficulty_score'    => $score,
            ];

            $repo->updateForUser($user['id'], $cardId, $data);

            header('Location: index.php');
            exit;
        }
    }
}

// Load card image from API if linked
$apiCardData = null;
if ($input['api_card_id'] !== '') {
    $apiConfig = require dirname(__DIR__) . '/config/app.php';
    $apiKey    = (string) (getenv('POKEMON_TCG_API_KEY') ?: '');
    $cache     = new \App\Api\ApiCache($pdo);
    $client    = new \App\Api\PokemonTcgClient($cache, $apiKey);
    $apiCardData = $client->findById($input['api_card_id']);
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

// Funnel: next-step callout per status
function funnelCallout(string $status, int $cardId): string
{
    $msgLink  = '<a href="card-message.php?id=' . $cardId . '">generate a seller message</a>';
    $editLink = '<a href="card-edit.php?id=' . $cardId . '#deal-fields">fill in deal fields</a>';

    return match ($status) {
        'searching'      => '<strong>Next step:</strong> Find a listing, then ' . $msgLink . ' to contact the seller.',
        'contacted'      => '<strong>Next step:</strong> Wait for a reply. If no answer in 7 days, follow up. You can ' . $msgLink . ' again.',
        'offer_received' => '<strong>Next step:</strong> Compare the offer to your budget. Accept, negotiate, or update the offer price and send a counter-message.',
        'acquired'       => '<strong>Next step:</strong> Record the final deal — ' . $editLink . ' below (purchase price, date, source).',
        'abandoned'      => '<strong>Note:</strong> This search is abandoned. You can reopen it by changing the status back to Searching.',
        default          => '',
    };
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit card — <?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .funnel-callout {
            padding: .75rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: 5px;
            font-size: .9rem;
        }
        .funnel-searching      { background: #eff6ff; border-left: 4px solid #2563eb; }
        .funnel-contacted      { background: #fffbeb; border-left: 4px solid #d97706; }
        .funnel-offer_received { background: #f5f3ff; border-left: 4px solid #7c3aed; }
        .funnel-acquired       { background: #f0fdf4; border-left: 4px solid #16a34a; }
        .funnel-abandoned      { background: #f9fafb; border-left: 4px solid #9ca3af; }

        .section-divider {
            margin: 1.75rem 0 1rem;
            font-size: .7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: #9ca3af;
            display: flex;
            align-items: center;
            gap: .75rem;
        }
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #e5e7eb;
        }
    </style>
</head>
<body>
    <header>
        <h1><?= $appName ?></h1>
        <p><a href="index.php">&larr; Back to list</a></p>
    </header>

    <main>
        <h2>Edit wanted card</h2>

        <!-- API CARD PREVIEW -->
        <?php if ($apiCardData !== null && !empty($apiCardData['image_small'])): ?>
        <div style="display:flex;align-items:center;gap:1rem;padding:.75rem 1rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:1.25rem;font-size:.875rem">
            <img src="<?= e($apiCardData['image_small']) ?>" alt="" style="width:48px;height:67px;object-fit:contain;border-radius:4px">
            <div>
                <strong><?= e($apiCardData['name']) ?></strong><br>
                <span style="color:#6b7280;font-size:.8rem"><?= e($apiCardData['set']) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- FUNNEL CALLOUT -->
        <?php $callout = funnelCallout($input['status'], $cardId); ?>
        <?php if ($callout !== ''): ?>
        <div class="funnel-callout funnel-<?= e($input['status']) ?>">
            <?= $callout ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <form method="post" action="card-edit.php?id=<?= $cardId ?>">
            <?= Csrf::field() ?>
            <input type="hidden" name="api_card_id" value="<?= e($input['api_card_id']) ?>">

            <div class="section-divider">Card details</div>

            <p>
                <label for="name">Card name <span aria-label="required">*</span></label><br>
                <input type="text" id="name" name="name" required
                       value="<?= e($input['name']) ?>">
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

            <div class="section-divider">Hunt status &amp; pricing</div>

            <p>
                <label for="status">Status <span aria-label="required">*</span></label><br>
                <select id="status" name="status" required onchange="updateFunnel(this.value)">
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

            <div class="section-divider">Seller</div>

            <p>
                <label for="seller_contact">Seller contact / username</label><br>
                <input type="text" id="seller_contact" name="seller_contact"
                       value="<?= e($input['seller_contact']) ?>">
            </p>

            <p>
                <label for="notes">Notes</label><br>
                <textarea id="notes" name="notes" rows="4"><?= e($input['notes']) ?></textarea>
            </p>

            <!-- DEAL FIELDS — shown always, highlighted when acquired -->
            <div class="section-divider" id="deal-fields">Deal archive</div>

            <p>
                <label for="seller_name">Seller name (for archive)</label><br>
                <input type="text" id="seller_name" name="seller_name"
                       value="<?= e($input['seller_name']) ?>">
            </p>

            <p>
                <label for="purchase_price">Final purchase price (€)</label><br>
                <input type="number" id="purchase_price" name="purchase_price"
                       step="any" min="0"
                       value="<?= e($input['purchase_price']) ?>">
            </p>

            <p>
                <label for="purchased_at">Purchase date</label><br>
                <input type="date" id="purchased_at" name="purchased_at"
                       value="<?= e($input['purchased_at']) ?>">
            </p>

            <p>
                <label for="source_url">Listing URL</label><br>
                <input type="url" id="source_url" name="source_url"
                       placeholder="https://www.cardmarket.com/…"
                       value="<?= e($input['source_url']) ?>">
            </p>

            <p>
                <button type="submit">Save changes</button>
            </p>
        </form>
    </main>
    <script>
    var funnelMessages = {
        searching:      '<strong>Next step:</strong> Find a listing, then <a href="card-message.php?id=<?= $cardId ?>">generate a seller message</a> to contact the seller.',
        contacted:      '<strong>Next step:</strong> Wait for a reply. If no answer in 7 days, follow up. You can <a href="card-message.php?id=<?= $cardId ?>">generate a seller message</a> again.',
        offer_received: '<strong>Next step:</strong> Compare the offer to your budget. Accept, negotiate, or update the offer price and send a counter-message.',
        acquired:       '<strong>Next step:</strong> Record the final deal — fill in the <a href="#deal-fields">deal fields</a> below (purchase price, date, source).',
        abandoned:      '<strong>Note:</strong> This search is abandoned. You can reopen it by changing the status back to Searching.'
    };
    var funnelClasses = {
        searching: 'funnel-searching', contacted: 'funnel-contacted',
        offer_received: 'funnel-offer_received', acquired: 'funnel-acquired',
        abandoned: 'funnel-abandoned'
    };
    function updateFunnel(status) {
        var el = document.querySelector('.funnel-callout');
        if (!el) return;
        el.className = 'funnel-callout ' + (funnelClasses[status] || '');
        el.innerHTML = funnelMessages[status] || '';
    }
    </script>
</body>
</html>
