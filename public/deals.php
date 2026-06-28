<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Card\CardRepository;
use App\Database\Connection;
use App\Security\Csrf;

Auth::startSession();
Auth::requireAuth();

$user  = Auth::user();
$pdo   = Connection::get($config['db']);
$repo  = new CardRepository($pdo);
$deals = $repo->listAcquiredForUser($user['id']);

$totalCards = count($deals);
$totalSpent = 0.0;
$totalSaved = 0.0;
$withPrice  = 0;

foreach ($deals as $d) {
    $paid   = $d['purchase_price'] !== null ? (float) $d['purchase_price'] : null;
    $budget = $d['target_price']   !== null ? (float) $d['target_price']   : null;
    if ($paid !== null) {
        $totalSpent += $paid;
        $withPrice++;
    }
    if ($paid !== null && $budget !== null && $budget > $paid) {
        $totalSaved += $budget - $paid;
    }
}

$appName = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');

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
    if ($v === null || $v === '') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', $v);
    return $dt ? $dt->format('j.m.Y') : e($v);
}

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archiwum dealów — <?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
    <style>
        /* ---- Summary strip ---- */
        .deals-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            margin-bottom: 1.75rem;
        }

        .sum-chip {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: .75rem 1.25rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            min-width: 120px;
        }

        .sum-chip .sc-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }

        .sum-chip .sc-lbl {
            font-size: .7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #9ca3af;
            margin-top: .2rem;
        }

        .sum-chip.chip-spent .sc-val { color: #2563eb; }
        .sum-chip.chip-saved .sc-val { color: #16a34a; }

        /* ---- Deal cards grid ---- */
        .deals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 1rem;
        }

        .deal-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
            display: grid;
            grid-template-rows: auto 1fr auto;
            overflow: hidden;
        }

        /* top: image + name */
        .deal-card-top {
            display: flex;
            align-items: center;
            gap: .9rem;
            padding: .9rem 1rem;
            border-bottom: 1px solid #f3f4f6;
        }

        .deal-img {
            width: 46px;
            height: 64px;
            object-fit: contain;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .deal-img-placeholder {
            width: 46px;
            height: 64px;
            flex-shrink: 0;
            border-radius: 4px;
            background-color: #f0f2f5;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 46 64'%3E%3Crect x='1.5' y='1.5' width='43' height='61' rx='4' fill='none' stroke='%23d1d5db' stroke-width='1.5'/%3E%3Crect x='5' y='5' width='36' height='24' rx='2' fill='%23e5e7eb'/%3E%3Ccircle cx='23' cy='46' r='8' fill='none' stroke='%23d1d5db' stroke-width='1.5'/%3E%3Cline x1='19' y1='46' x2='27' y2='46' stroke='%23d1d5db' stroke-width='1.5'/%3E%3Cline x1='23' y1='42' x2='23' y2='50' stroke='%23d1d5db' stroke-width='1.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-size: contain;
        }

        .deal-card-name {
            font-weight: 700;
            font-size: .95rem;
            color: #111827;
            line-height: 1.3;
        }

        .deal-card-edition {
            font-size: .78rem;
            color: #6b7280;
            margin-top: .2rem;
        }

        /* middle: price block */
        .deal-card-price {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: .75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            min-height: 68px;
        }

        .deal-price-main {
            font-size: 1.25rem;
            font-weight: 700;
            font-variant-numeric: tabular-nums;
            color: #111827;
        }

        .deal-price-budget {
            font-size: .78rem;
            color: #9ca3af;
            font-variant-numeric: tabular-nums;
        }

        .deal-diff-badge {
            margin-left: auto;
            font-size: .78rem;
            font-weight: 700;
            padding: .2rem .55rem;
            border-radius: 99px;
            white-space: nowrap;
        }

        .diff-saved   { background: #dcfce7; color: #15803d; }
        .diff-over    { background: #fee2e2; color: #991b1b; }
        .diff-exact   { background: #f3f4f6; color: #6b7280; }
        .diff-nodata  { color: #9ca3af; font-size: .78rem; }

        .deal-price-missing {
            color: #9ca3af;
            font-size: .82rem;
            font-style: italic;
        }

        /* footer: meta row */
        .deal-card-footer {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .4rem .75rem;
            padding: .65rem 1rem;
            font-size: .78rem;
            color: #6b7280;
            background: #fafafa;
        }

        .deal-meta-item { display: flex; align-items: center; gap: .3rem; }

        .deal-footer-actions {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-shrink: 0;
        }

        .deal-src-btn {
            font-size: .75rem;
            padding: .22rem .6rem;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            color: #6b7280;
            text-decoration: none;
            white-space: nowrap;
        }

        .deal-src-btn:hover { border-color: #2563eb; color: #2563eb; text-decoration: none; }

        /* ---- Empty state ---- */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6b7280;
        }

        .empty-state .es-icon { font-size: 2.5rem; margin-bottom: .75rem; }
        .empty-state p { font-size: .95rem; margin: 0 0 1rem; }

        @media (max-width: 600px) {
            .deals-grid { grid-template-columns: 1fr; }
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
        <div class="list-header" style="margin-bottom:1.25rem">
            <h2>Archiwum dealów</h2>
        </div>

        <?php if ($totalCards === 0): ?>
        <div class="empty-state">
            <div class="es-icon">📦</div>
            <p>Brak zakupionych kart.</p>
            <p style="font-size:.85rem">Gdy oznaczysz kartę jako <strong>Zakupiono</strong>, pojawi się tutaj z pełnymi detalami zakupu.</p>
            <a href="index.php" class="btn-add">Przejdź do listy kart</a>
        </div>
        <?php else: ?>

        <!-- SUMMARY STRIP -->
        <div class="deals-summary">
            <div class="sum-chip">
                <div class="sc-val"><?= $totalCards ?></div>
                <div class="sc-lbl">Zakupione karty</div>
            </div>
            <?php if ($totalSpent > 0): ?>
            <div class="sum-chip chip-spent">
                <div class="sc-val"><?= number_format($totalSpent, 2) ?> €</div>
                <div class="sc-lbl">Łącznie wydano</div>
            </div>
            <?php endif; ?>
            <?php if ($withPrice > 0): ?>
            <div class="sum-chip">
                <div class="sc-val"><?= number_format($totalSpent / $withPrice, 2) ?> €</div>
                <div class="sc-lbl">Średnia cena</div>
            </div>
            <?php endif; ?>
            <?php if ($totalSaved > 0): ?>
            <div class="sum-chip chip-saved">
                <div class="sc-val"><?= number_format($totalSaved, 2) ?> €</div>
                <div class="sc-lbl">Łącznie zaoszczędzono</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- DEALS GRID -->
        <div class="deals-grid">
        <?php foreach ($deals as $d):
            $paid   = $d['purchase_price'] !== null ? (float) $d['purchase_price'] : null;
            $budget = $d['target_price']   !== null ? (float) $d['target_price']   : null;
            $diff   = ($paid !== null && $budget !== null) ? $budget - $paid : null;
            $seller = $d['seller_name'] ?: ($d['seller_contact'] ?: null);
        ?>
        <div class="deal-card">

            <!-- Top: image + name -->
            <div class="deal-card-top">
                <?php if (!empty($d['image_url'])): ?>
                <img src="<?= e($d['image_url']) ?>" alt="" class="deal-img">
                <?php else: ?>
                <div class="deal-img-placeholder"></div>
                <?php endif; ?>
                <div>
                    <div class="deal-card-name"><?= e($d['name']) ?></div>
                    <div class="deal-card-edition">
                        <?= e($d['language']) ?>
                        <?php if ($d['country']): ?>
                        <span style="color:#d1d5db">·</span> <?= e($d['country']) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Middle: price -->
            <?php if ($paid !== null): ?>
            <div class="deal-card-price">
                <div>
                    <div class="deal-price-main"><?= number_format($paid, 2) ?> €</div>
                    <div class="deal-price-budget">
                    <?php if ($budget !== null): ?>Budżet: <?= number_format($budget, 2) ?> €<?php else: ?>&nbsp;<?php endif; ?>
                </div>
                </div>
                <?php if ($diff !== null): ?>
                    <?php if ($diff > 0): ?>
                    <span class="deal-diff-badge diff-saved">−<?= number_format($diff, 2) ?> € oszczędność</span>
                    <?php elseif ($diff < 0): ?>
                    <span class="deal-diff-badge diff-over">+<?= number_format(abs($diff), 2) ?> € ponad</span>
                    <?php else: ?>
                    <span class="deal-diff-badge diff-exact">w budżecie</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="deal-card-price deal-price-missing">
                <span>Brak ceny zakupu</span>
                <a href="card-edit.php?id=<?= (int) $d['id'] ?>#deal-fields" class="deal-src-btn" style="margin-left:auto">+ Uzupełnij</a>
            </div>
            <?php endif; ?>

            <!-- Footer: meta + actions -->
            <div class="deal-card-footer">
                <?php if ($seller): ?>
                <span class="deal-meta-item">&#128100; <?= e($seller) ?></span>
                <?php endif; ?>
                <?php if ($d['purchased_at']): ?>
                <span class="deal-meta-item">&#128197; <?= fmtDate($d['purchased_at']) ?></span>
                <?php endif; ?>

                <div class="deal-footer-actions">
                    <?php if ($d['source_url']): ?>
                    <a class="deal-src-btn" href="<?= e($d['source_url']) ?>" target="_blank" rel="noopener" title="<?= e($d['source_url']) ?>">&#128279; Oferta</a>
                    <?php endif; ?>
                    <a class="deal-src-btn" href="card-edit.php?id=<?= (int) $d['id'] ?>">Edytuj</a>
                </div>
            </div>

        </div>
        <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </main>

    <?php require '_flash.php'; ?>
</body>
</html>

