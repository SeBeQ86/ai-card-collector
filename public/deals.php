<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Card\CardRepository;
use App\Database\Connection;

Auth::startSession();
Auth::requireAuth();

$user  = Auth::user();
$pdo   = Connection::get($config['db']);
$repo  = new CardRepository($pdo);
$deals = $repo->listAcquiredForUser($user['id']);

$totalSpent = array_sum(array_filter(array_column($deals, 'purchase_price')));
$totalCards = count($deals);

$appName   = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');
$userEmail = htmlspecialchars($user['email'],  ENT_QUOTES, 'UTF-8');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fmtPrice(?string $v): string
{
    return $v !== null ? number_format((float) $v, 2) . ' €' : '—';
}

function fmtDate(?string $v): string
{
    if ($v === null || $v === '') {
        return '—';
    }
    $ts = strtotime($v);
    return $ts !== false ? date('j M Y', $ts) : e($v);
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deal archive — <?= $appName ?></title>
    <link rel="stylesheet" href="assets/style.css?v=3">
    <style>
        .deals-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.5rem;
        }
        .summary-chip {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: .6rem 1.1rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.06);
        }
        .summary-chip .sc-num {
            font-size: 1.4rem;
            font-weight: 700;
            color: #111827;
            display: block;
            line-height: 1.1;
        }
        .summary-chip .sc-label {
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #6b7280;
        }
        .summary-chip.chip-total .sc-num { color: #16a34a; }

        .deals-table a.src-link {
            font-size: .78rem;
            padding: .18rem .5rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            color: #6b7280;
            display: inline-block;
            max-width: 140px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            vertical-align: middle;
        }
        .deals-table a.src-link:hover { border-color: #2563eb; color: #2563eb; text-decoration: none; }

        .saving-badge {
            font-size: .72rem;
            color: #16a34a;
            font-weight: 600;
            white-space: nowrap;
        }
        .overpaid-badge {
            font-size: .72rem;
            color: #dc2626;
            font-weight: 600;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <header>
        <h1><?= $appName ?></h1>
        <p>
            <a href="index.php">&larr; Wanted list</a>
            &nbsp;
            <form method="post" action="logout.php" style="display:inline">
                <?= \App\Security\Csrf::field() ?>
                <button type="submit">Log out</button>
            </form>
        </p>
    </header>

    <main>
        <div class="list-header">
            <h2>Deal archive</h2>
        </div>

        <?php if ($totalCards === 0): ?>
            <p>No acquired cards yet. Once you mark a card as <em>Acquired</em> it will appear here.</p>
        <?php else: ?>

        <!-- SUMMARY CHIPS -->
        <div class="deals-summary">
            <div class="summary-chip">
                <span class="sc-num"><?= $totalCards ?></span>
                <span class="sc-label">Cards acquired</span>
            </div>
            <?php if ($totalSpent > 0): ?>
            <div class="summary-chip chip-total">
                <span class="sc-num"><?= number_format($totalSpent, 2) ?> €</span>
                <span class="sc-label">Total spent</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- DEALS TABLE -->
        <div style="overflow-x:auto">
        <table class="card-table deals-table">
            <thead>
                <tr>
                    <th>Card</th>
                    <th>Edition</th>
                    <th>Budget</th>
                    <th>Paid</th>
                    <th>Saved / over</th>
                    <th>Seller</th>
                    <th>Date</th>
                    <th>Source</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($deals as $d): ?>
                <?php
                    $budget  = $d['target_price']   !== null ? (float) $d['target_price']   : null;
                    $paid    = $d['purchase_price']  !== null ? (float) $d['purchase_price']  : null;
                    $diff    = ($budget !== null && $paid !== null) ? $budget - $paid : null;
                ?>
                <tr>
                    <td><?= e($d['name']) ?></td>
                    <td>
                        <?= e($d['language']) ?>
                        <?php if ($d['country']): ?>
                        <span style="color:#9ca3af; font-size:.8rem"> / <?= e($d['country']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="font-variant-numeric:tabular-nums"><?= fmtPrice($d['target_price']) ?></td>
                    <td style="font-variant-numeric:tabular-nums"><?= fmtPrice($d['purchase_price']) ?></td>
                    <td>
                        <?php if ($diff !== null): ?>
                            <?php if ($diff > 0): ?>
                                <span class="saving-badge">−<?= number_format($diff, 2) ?> €</span>
                            <?php elseif ($diff < 0): ?>
                                <span class="overpaid-badge">+<?= number_format(abs($diff), 2) ?> € over</span>
                            <?php else: ?>
                                <span style="color:#6b7280; font-size:.78rem">on budget</span>
                            <?php endif; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?= $d['seller_name'] ? e($d['seller_name']) : ($d['seller_contact'] ? e($d['seller_contact']) : '—') ?></td>
                    <td style="white-space:nowrap"><?= fmtDate($d['purchased_at']) ?></td>
                    <td>
                        <?php if ($d['source_url']): ?>
                            <a class="src-link" href="<?= e($d['source_url']) ?>" target="_blank" rel="noopener" title="<?= e($d['source_url']) ?>">View listing</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><a href="card-edit.php?id=<?= (int) $d['id'] ?>">Edit</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php endif; ?>
    </main>
</body>
</html>
