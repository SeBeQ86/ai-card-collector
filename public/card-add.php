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

        <form method="post" action="card-add.php">
            <?= Csrf::field() ?>

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
                <label for="target_price">Target price</label><br>
                <input type="number" id="target_price" name="target_price"
                       step="any" min="0"
                       value="<?= e($input['target_price']) ?>">
            </p>

            <p>
                <label for="current_offer_price">Current offer price</label><br>
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
</body>
</html>
