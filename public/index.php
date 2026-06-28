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

$user     = Auth::user();
$pdo      = Connection::get($config['db']);
$repo     = new CardRepository($pdo);
$allCards = $repo->listForUser($user['id']);

// Stats by status
$stats = ['searching' => 0, 'contacted' => 0, 'offer_received' => 0, 'acquired' => 0, 'abandoned' => 0];
foreach ($allCards as $c) {
    if (array_key_exists($c['status'], $stats)) {
        $stats[$c['status']]++;
    }
}

// Filter
$filterStatus = $_GET['status'] ?? '';
$allowedStatuses = ['', 'searching', 'contacted', 'offer_received', 'acquired', 'abandoned'];
if (!in_array($filterStatus, $allowedStatuses, true)) {
    $filterStatus = '';
}

$cards     = $filterStatus === ''
    ? $allCards
    : array_values(array_filter($allCards, fn($c) => $c['status'] === $filterStatus));
$cardCount = count($allCards);

$appName   = htmlspecialchars($config['name'],  ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'],    ENT_QUOTES, 'UTF-8');

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
    return $value !== null ? number_format((float) $value, 2) : '—';
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css?v=2">
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

        <?php if ($cardCount > 0): ?>
        <div class="stats-bar">
            <a href="index.php" class="stat-chip <?= $filterStatus === '' ? 'active' : '' ?>">
                <span class="stat-num"><?= $cardCount ?></span>
                <span class="stat-label">All</span>
            </a>
            <a href="?status=searching" class="stat-chip <?= $filterStatus === 'searching' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['searching'] ?></span>
                <span class="stat-label">Searching</span>
            </a>
            <a href="?status=contacted" class="stat-chip <?= $filterStatus === 'contacted' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['contacted'] ?></span>
                <span class="stat-label">Contacted</span>
            </a>
            <a href="?status=offer_received" class="stat-chip <?= $filterStatus === 'offer_received' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['offer_received'] ?></span>
                <span class="stat-label">Offer received</span>
            </a>
            <a href="?status=acquired" class="stat-chip <?= $filterStatus === 'acquired' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['acquired'] ?></span>
                <span class="stat-label">Acquired</span>
            </a>
            <a href="?status=abandoned" class="stat-chip <?= $filterStatus === 'abandoned' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['abandoned'] ?></span>
                <span class="stat-label">Abandoned</span>
            </a>
        </div>
        <?php endif; ?>

        <div class="list-header">
            <h2>Wanted cards<?= $filterStatus !== '' ? ' — ' . htmlspecialchars(formatStatus($filterStatus), ENT_QUOTES, 'UTF-8') : '' ?><?= count($cards) > 0 ? ' (' . count($cards) . ')' : '' ?></h2>
            <a href="card-add.php" class="btn-add">+ Add card</a>
        </div>

        <?php if ($cardCount === 0): ?>
            <p>No cards yet. <a href="card-add.php">Add your first card</a>.</p>
        <?php elseif (count($cards) === 0): ?>
            <p>No cards with status "<?= htmlspecialchars(formatStatus($filterStatus), ENT_QUOTES, 'UTF-8') ?>". <a href="index.php">Show all</a>.</p>
        <?php else: ?>
            <table class="card-table">
                <thead>
                    <tr>
                        <th>Score <span class="score-info" aria-label="Scoring rules">?<span class="score-tooltip"><strong>Difficulty score (0–100)</strong><br><br>🌍 <b>Language</b> — non-English edition: +40<br>🔍 <b>Status</b> — Searching +40, Contacted +30, Offer received +10<br>💰 <b>Price</b> — offer over budget +10, budget set +5, no data +3<br>📅 <b>Age</b> — +1 per week unresolved (max +10)<br><br>Acquired / Abandoned → score 0</span></span></th>
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
                        <td>
                            <form method="post" action="card-status.php" class="status-form">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                                <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>">
                                <select name="status" class="status-select status-<?= htmlspecialchars($card['status'], ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                                    <option value="searching"      <?= $card['status'] === 'searching'      ? 'selected' : '' ?>>Searching</option>
                                    <option value="contacted"      <?= $card['status'] === 'contacted'      ? 'selected' : '' ?>>Contacted</option>
                                    <option value="offer_received" <?= $card['status'] === 'offer_received' ? 'selected' : '' ?>>Offer received</option>
                                    <option value="acquired"       <?= $card['status'] === 'acquired'       ? 'selected' : '' ?>>Acquired</option>
                                    <option value="abandoned"      <?= $card['status'] === 'abandoned'      ? 'selected' : '' ?>>Abandoned</option>
                                </select>
                            </form>
                        </td>
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
    <script>
    (function () {
        const icon = document.querySelector('.score-info');
        if (!icon) return;
        const tip = icon.querySelector('.score-tooltip');
        icon.addEventListener('mouseenter', function (e) {
            const r = icon.getBoundingClientRect();
            tip.style.top  = (r.bottom + 8 + window.scrollY) + 'px';
            tip.style.left = Math.max(8, r.left + r.width / 2 - 140 + window.scrollX) + 'px';
            tip.classList.add('visible');
        });
        icon.addEventListener('mouseleave', function () {
            tip.classList.remove('visible');
        });
    })();
    </script>
</body>
</html>
