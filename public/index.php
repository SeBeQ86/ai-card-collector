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

$user  = Auth::user();
$pdo   = Connection::get($config['db']);
$repo  = new CardRepository($pdo);
$cards = $repo->listForUser($user['id']);

$appName   = htmlspecialchars($config['name'],  ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'],    ENT_QUOTES, 'UTF-8');
$cardCount = count($cards);

function formatStatus(string $status): string
{
    return match ($status) {
        'searching'      => 'Searching',
        'contacted'      => 'Contacted',
        'offer_received' => 'Offer received',
        'acquired'       => 'Acquired',
        'abandoned'      => 'Abandoned',
        default          => $status,
    };
}

function formatPrice(?string $value): string
{
    return $value !== null
        ? number_format((float) $value, 2)
        : '—';
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header>
        <h1><?= $appName ?></h1>
        <p>Logged in as: <strong><?= $userEmail ?></strong>
            &nbsp;
            <form method="post" action="logout.php" style="display:inline">
                <?= Csrf::field() ?>
                <button type="submit">Log out</button>
            </form>
        </p>
    </header>

    <main>
        <h2>Wanted cards<?= $cardCount > 0 ? ' (' . $cardCount . ')' : '' ?></h2>
        <p><a href="card-add.php">+ Add card</a></p>

        <?php if ($cardCount === 0): ?>
            <p>No cards yet. <a href="card-add.php">Add your first card</a>.</p>
        <?php else: ?>
            <table class="card-table">
                <thead>
                    <tr>
                        <th>Score</th>
                        <th>Name</th>
                        <th>Language</th>
                        <th>Country</th>
                        <th>Target price</th>
                        <th>Current offer</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                    <?php $ex = CardScorer::explain($card); ?>
                    <tr>
                        <td>
                            <details class="score-details">
                                <summary><?= (int) $card['difficulty_score'] ?></summary>
                                <small>Lang +<?= $ex['language'] ?> &middot; Status +<?= $ex['status'] ?> &middot; Price +<?= $ex['price'] ?> &middot; Age +<?= $ex['age'] ?></small>
                            </details>
                        </td>
                        <td><?= htmlspecialchars($card['name'],     ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($card['language'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= $card['country'] !== null
                                ? htmlspecialchars($card['country'], ENT_QUOTES, 'UTF-8')
                                : '—' ?></td>
                        <td><?= htmlspecialchars(formatPrice($card['target_price']),        ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(formatPrice($card['current_offer_price']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(formatStatus($card['status']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(substr((string) $card['created_at'], 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a href="card-edit.php?id=<?= (int) $card['id'] ?>">Edit</a>
                            <a href="card-message.php?id=<?= (int) $card['id'] ?>">Message</a>
                            <form method="post" action="card-delete.php"
                                  style="display:inline"
                                  onsubmit="return confirm('Delete this card?')">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                                <button type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>
</html>
