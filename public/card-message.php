<?php declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Card\CardRepository;
use App\Database\Connection;
use App\Message\SellerMessageGenerator;

Auth::startSession();
Auth::requireAuth();

$user   = Auth::user();
$cardId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($cardId <= 0) {
    header('Location: index.php');
    exit;
}

$repo = new CardRepository(Connection::get($config['db']));
$card = $repo->findForUser($user['id'], $cardId);

if ($card === null) {
    header('Location: index.php');
    exit;
}

$locales   = SellerMessageGenerator::locales();
$messages  = [];
foreach ($locales as $locale => $label) {
    $messages[$locale] = SellerMessageGenerator::generate($card, $locale);
}

$appName = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Seller message — <?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .howto {
            background: #f0f7ff;
            border-left: 4px solid #3b82f6;
            border-radius: 0 6px 6px 0;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .howto h3 { margin: 0 0 .5rem; font-size: 1rem; color: #1e40af; }
        .howto ol { margin: 0; padding-left: 1.25rem; }
        .howto li { margin-bottom: .3rem; color: #374151; font-size: .9rem; }
        .howto .tip { margin-top: .75rem; font-size: .85rem; color: #6b7280; }
        .howto .tip strong { color: #374151; }

        .lang-tabs { display: flex; flex-wrap: wrap; gap: .35rem; margin-bottom: 1rem; }
        .lang-tab {
            padding: .4rem .9rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            background: #f9fafb;
            cursor: pointer;
            font-size: .9rem;
            color: #374151;
        }
        .lang-tab:hover { background: #e5e7eb; }
        .lang-tab.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            font-weight: 600;
        }

        .lang-panel { display: none; }
        .lang-panel.active { display: block; }

        .msg-block { position: relative; }
        .msg-block textarea {
            width: 100%;
            box-sizing: border-box;
            font-family: inherit;
            font-size: .9rem;
            line-height: 1.5;
            padding: .75rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            resize: vertical;
            background: #fafafa;
        }
        .msg-block .copy-btn {
            margin-top: .5rem;
        }
        .lang-note { font-size: .8rem; color: #6b7280; margin-top: .4rem; }

        .card-summary {
            display: flex; flex-wrap: wrap; gap: .5rem 1.5rem;
            padding: .75rem 1rem;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            margin-bottom: 1.25rem;
            font-size: .9rem;
        }
        .card-summary span { color: #6b7280; }
        .card-summary strong { color: #111827; }
    </style>
</head>
<body>
    <header>
        <h1><?= $appName ?></h1>
        <p><a href="index.php">&larr; Back to list</a></p>
    </header>

    <main>
        <h2>Seller messages — <?= e($card['name']) ?></h2>

        <!-- HOW-TO TUTORIAL -->
        <div class="howto">
            <h3>📋 How to use these messages</h3>
            <ol>
                <li><strong>Find the card</strong> on a marketplace like <em>Cardmarket</em>, <em>eBay</em> or a Facebook collector group.</li>
                <li><strong>Check the seller's language</strong> — using their language increases your chance of a reply and a better price.</li>
                <li><strong>Pick the tab</strong> below that matches the seller's country or preferred language.</li>
                <li><strong>Copy the message</strong> and paste it into the marketplace contact form or private message.</li>
                <li><strong>Personalise if needed</strong> — add your username or tweak the tone before sending.</li>
            </ol>
            <p class="tip">
                <strong>Tip:</strong> The message is pre-filled with the card name, edition, your target price and current offer price (if set).
                Update those fields on the <a href="card-edit.php?id=<?= $cardId ?>">card edit page</a> before generating messages.
            </p>
        </div>

        <!-- CARD SUMMARY -->
        <div class="card-summary">
            <div><span>Card: </span><strong><?= e($card['name']) ?></strong></div>
            <div><span>Edition: </span><strong><?= e($card['language']) ?><?= $card['country'] ? ' / ' . e($card['country']) : '' ?></strong></div>
            <div><span>Status: </span><strong><?= e(str_replace('_', ' ', $card['status'])) ?></strong></div>
            <?php if ($card['target_price'] !== null): ?>
            <div><span>Budget: </span><strong><?= e(number_format((float) $card['target_price'], 2)) ?> €</strong></div>
            <?php endif; ?>
            <?php if ($card['current_offer_price'] !== null): ?>
            <div><span>Offer seen: </span><strong><?= e(number_format((float) $card['current_offer_price'], 2)) ?> €</strong></div>
            <?php endif; ?>
        </div>

        <!-- LANGUAGE TABS -->
        <div class="lang-tabs" role="tablist">
            <?php foreach ($locales as $locale => $label): ?>
            <button class="lang-tab<?= $locale === 'en' ? ' active' : '' ?>"
                    role="tab"
                    onclick="switchLang('<?= e($locale) ?>', this)"
                    type="button">
                <?= e($label) ?>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- MESSAGE PANELS -->
        <?php foreach ($locales as $locale => $label): ?>
        <div class="lang-panel<?= $locale === 'en' ? ' active' : '' ?>" id="panel-<?= e($locale) ?>">
            <div class="msg-block">
                <textarea id="msg-<?= e($locale) ?>" rows="12" readonly><?= e($messages[$locale]) ?></textarea>
                <button type="button" class="copy-btn" onclick="copyMsg('msg-<?= e($locale) ?>', this)">
                    Copy message
                </button>
                <?php if ($locale === 'ja'): ?>
                <p class="lang-note">Japanese message — useful when contacting sellers on <em>TCGPlayer Japan</em>, Yahoo Auctions JP or direct Japanese collector groups.</p>
                <?php elseif ($locale === 'de'): ?>
                <p class="lang-note">German message — Cardmarket is headquartered in Germany; many high-volume sellers prefer German communication.</p>
                <?php elseif ($locale === 'fr'): ?>
                <p class="lang-note">French message — useful for sellers based in France, Belgium or Switzerland.</p>
                <?php elseif ($locale === 'es'): ?>
                <p class="lang-note">Spanish message — covers Spain and Latin American sellers.</p>
                <?php elseif ($locale === 'pt'): ?>
                <p class="lang-note">Portuguese message — useful for sellers in Portugal and Brazil.</p>
                <?php else: ?>
                <p class="lang-note">English is the universal fallback when you are unsure of the seller's language.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <p style="margin-top:1.5rem"><a href="card-edit.php?id=<?= $cardId ?>">Edit this card</a></p>
    </main>

    <script>
    function switchLang(locale, btn) {
        document.querySelectorAll('.lang-tab').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.lang-panel').forEach(function(p) { p.classList.remove('active'); });
        btn.classList.add('active');
        document.getElementById('panel-' + locale).classList.add('active');
    }

    function copyMsg(id, btn) {
        var el = document.getElementById(id);
        if (!el) return;
        el.select();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(el.value).catch(function() { document.execCommand('copy'); });
        } else {
            document.execCommand('copy');
        }
        btn.textContent = 'Copied!';
        setTimeout(function() { btn.textContent = 'Copy message'; }, 2000);
    }
    </script>
</body>
</html>
