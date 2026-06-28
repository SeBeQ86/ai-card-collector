<?php

declare(strict_types=1);

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

$messageEn = SellerMessageGenerator::generate($card, 'en');
$messagePt = SellerMessageGenerator::generate($card, 'pt');

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
</head>
<body>
    <header>
        <h1><?= $appName ?></h1>
        <p><a href="index.php">&larr; Back to list</a></p>
    </header>

    <main>
        <h2>Seller message</h2>

        <section>
            <h3>Card details</h3>
            <dl>
                <dt>Name</dt>
                <dd><?= e($card['name']) ?></dd>

                <dt>Language</dt>
                <dd><?= e($card['language']) ?></dd>

                <?php if ($card['country'] !== null && $card['country'] !== ''): ?>
                <dt>Country</dt>
                <dd><?= e($card['country']) ?></dd>
                <?php endif; ?>

                <dt>Status</dt>
                <dd><?= e($card['status']) ?></dd>

                <?php if ($card['target_price'] !== null): ?>
                <dt>Target price</dt>
                <dd><?= e(number_format((float) $card['target_price'], 2)) ?></dd>
                <?php endif; ?>

                <?php if ($card['current_offer_price'] !== null): ?>
                <dt>Current offer</dt>
                <dd><?= e(number_format((float) $card['current_offer_price'], 2)) ?></dd>
                <?php endif; ?>
            </dl>
        </section>

        <section>
            <h3>English</h3>
            <textarea id="msg-en" rows="12" cols="70" readonly><?= e($messageEn) ?></textarea>
            <button type="button" class="copy-btn" onclick="copyMsg('msg-en', this)">Copy</button>
        </section>

        <section>
            <h3>Portuguese</h3>
            <textarea id="msg-pt" rows="12" cols="70" readonly><?= e($messagePt) ?></textarea>
            <button type="button" class="copy-btn" onclick="copyMsg('msg-pt', this)">Copy</button>
        </section>

        <p><a href="card-edit.php?id=<?= $cardId ?>">Edit this card</a></p>
    </main>

    <script>
    function copyMsg(id, btn) {
        var el = document.getElementById(id);
        if (!el) return;
        el.select();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(el.value).catch(function () {
                document.execCommand('copy');
            });
        } else {
            document.execCommand('copy');
        }
        btn.textContent = 'Copied!';
        setTimeout(function () { btn.textContent = 'Copy'; }, 2000);
    }
    </script>
</body>
</html>
