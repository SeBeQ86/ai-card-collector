<?php

declare(strict_types=1);

$config = require file_exists(__DIR__ . '/config/app.php') ? __DIR__ . '/config/app.php' : dirname(__DIR__) . '/config/app.php';
require file_exists(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

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
        'searching'      => 'Szukam',
        'contacted'      => 'Skontaktowano',
        'offer_received' => 'Oferta otrzymana',
        'acquired'       => 'Zakupiono',
        'abandoned'      => 'Porzucono',
        default          => $status,
    };
}

function nextAction(array $card): string
{
    return match ($card['status']) {
        'searching'      => 'Znajdź ofertę i wyślij wiadomość',
        'contacted'      => 'Czekaj / przypomnij się',
        'offer_received' => 'Zaakceptuj lub negocjuj',
        'acquired'       => 'Uzupełnij dane zakupu',
        'abandoned'      => 'Wznów jeśli potrzeba',
        default          => '—',
    };
}

function nextActionUrl(array $card): string
{
    return match ($card['status']) {
        'searching', 'contacted', 'offer_received' => 'card-message.php?id=' . (int) $card['id'],
        'acquired'  => 'card-edit.php?id=' . (int) $card['id'] . '#deal-fields',
        default     => 'card-edit.php?id=' . (int) $card['id'],
    };
}

// Cards needing immediate attention (active statuses, top 3 by score)
$attention = array_slice(
    array_filter($allCards, fn($c) => in_array($c['status'], ['searching', 'contacted', 'offer_received'], true)),
    0,
    3
);

function formatPrice(?string $value): string
{
    return $value !== null ? number_format((float) $value, 2) : '—';
}

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:inherit;text-decoration:none"><?= $appName ?></a></h1>
        <p>Zalogowano jako: <strong><?= $userEmail ?></strong>
            &nbsp;
            <a href="deals.php" class="act-btn">Archiwum dealów</a>
            &nbsp;
            <form method="post" action="logout.php" style="display:inline">
                <?= Csrf::field() ?>
                <button type="submit">Wyloguj</button>
            </form>
        </p>
    </header>

    <main>

        <?php if ($cardCount > 0): ?>
        <div class="stats-bar">
            <a href="index.php" class="stat-chip <?= $filterStatus === '' ? 'active' : '' ?>">
                <span class="stat-num"><?= $cardCount ?></span>
                <span class="stat-label">Wszystkie</span>
            </a>
            <a href="?status=searching" class="stat-chip <?= $filterStatus === 'searching' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['searching'] ?></span>
                <span class="stat-label">Szukam</span>
            </a>
            <a href="?status=contacted" class="stat-chip <?= $filterStatus === 'contacted' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['contacted'] ?></span>
                <span class="stat-label">Skontaktowano</span>
            </a>
            <a href="?status=offer_received" class="stat-chip <?= $filterStatus === 'offer_received' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['offer_received'] ?></span>
                <span class="stat-label">Oferta</span>
            </a>
            <a href="?status=acquired" class="stat-chip <?= $filterStatus === 'acquired' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['acquired'] ?></span>
                <span class="stat-label">Zakupiono</span>
            </a>
            <a href="?status=abandoned" class="stat-chip <?= $filterStatus === 'abandoned' ? 'active' : '' ?>">
                <span class="stat-num"><?= $stats['abandoned'] ?></span>
                <span class="stat-label">Porzucono</span>
            </a>
        </div>
        <?php endif; ?>

        <?php if (!empty($attention) && $filterStatus === ''): ?>
        <div class="attention-box">
            <div class="attention-title">&#9888;&#xFE0F; Wymagają uwagi teraz</div>
            <ul class="attention-list">
            <?php foreach ($attention as $ac): ?>
                <li>
                    <span class="attention-status attention-<?= htmlspecialchars($ac['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(formatStatus($ac['status']), ENT_QUOTES, 'UTF-8') ?></span>
                    <strong><?= htmlspecialchars($ac['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span class="attention-sep">→</span>
                    <a href="<?= nextActionUrl($ac) ?>"><?= nextAction($ac) ?></a>
                </li>
            <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <div class="list-header">
            <h2>Poszukiwane karty<?= $filterStatus !== '' ? ' — ' . htmlspecialchars(formatStatus($filterStatus), ENT_QUOTES, 'UTF-8') : '' ?><span id="visible-count"><?= count($cards) > 0 ? ' (' . count($cards) . ')' : '' ?></span></h2>
            <div style="display:flex;align-items:center;gap:.6rem">
                <input type="search" id="list-filter" placeholder="Filtruj po nazwie…"
                       style="padding:.35rem .65rem;border:1px solid #d1d5db;border-radius:6px;font-size:.875rem;width:180px">
                <button type="button" id="btn-refresh-prices" class="act-btn" title="Pobiera aktualne ceny z Cardmarket dla kart z ID API">&#128200; Odśwież ceny</button>
                <a href="card-add.php" class="btn-add">+ Dodaj kartę</a>
            </div>
        </div>

        <?php if ($cardCount === 0): ?>
            <p>Brak kart. <a href="card-add.php">Dodaj pierwszą kartę</a>.</p>
        <?php elseif (count($cards) === 0): ?>
            <p>Brak kart ze statusem „<?= htmlspecialchars(formatStatus($filterStatus), ENT_QUOTES, 'UTF-8') ?>". <a href="index.php">Pokaż wszystkie</a>.</p>
        <?php else: ?>
            <div class="table-scroll">
            <table class="card-table">
                <colgroup>
                    <col style="width:44px">   <!-- Zdjęcie -->
                    <col style="width:48px">   <!-- Wynik -->
                    <col style="width:170px">  <!-- Nazwa -->
                    <col style="width:110px">  <!-- Język/Kraj -->
                    <col style="width:70px">   <!-- Budżet -->
                    <col style="width:70px">   <!-- Oferta -->
                    <col style="width:82px">   <!-- Rynek -->
                    <col style="width:148px">  <!-- Status -->
                    <col style="width:170px">  <!-- Co teraz zrobić -->
                    <col style="width:148px">  <!-- Akcje -->
                </colgroup>
                <thead>
                    <tr>
                        <th></th>
                        <th style="text-align:center"><span class="score-info" aria-label="Priorytet">?<span class="score-tooltip"><strong>Priorytet (0–135)</strong><br><br>🔍 <b>Status</b> — Szukam +40, Skontaktowano +25, Oferta +10<br>🌍 <b>Język</b> — JP/TH/PT/ID +35, FR/DE/ES/KR/PL/RU +20, EN +0<br>💰 <b>Cena</b> — oferta &gt; budżet +25, budżet bez oferty +15, brak danych +8<br>📅 <b>Wiek</b> — +1 za każde 5 dni (maks +15)<br>📈 <b>Rynek</b> — budżet pokrywa ≥100% rynku +0, 85–100% +10, 70–85% +20, 50–70% +30, &lt;50% +40<br><br>Zakupiono / Porzucono → 0</span></span></th>
                        <th>Nazwa</th>
                        <th>Język / Kraj</th>
                        <th>Budżet</th>
                        <th>Oferta</th>
                        <th title="Cena rynkowa wersji angielskiej (Cardmarket avg30). Dla JP/PT/KR może być wyższa.">Rynek EN</th>
                        <th>Status</th>
                        <th>Co teraz zrobić?</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cards as $card): ?>
                    <?php $ex = CardScorer::explain($card); $tier = CardScorer::tier((int)$card['difficulty_score']); ?>
                    <tr class="row-<?= htmlspecialchars($card['status'], ENT_QUOTES, 'UTF-8') ?>">
                        <td class="thumb-cell">
                            <?php if (!empty($card['image_url'])): ?>
                            <img src="<?= htmlspecialchars($card['image_url'], ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8') ?>"
                                 class="card-thumb" loading="lazy">
                            <?php else: ?>
                            <div class="card-thumb-placeholder"></div>
                            <?php endif; ?>
                        </td>
                        <td class="score-cell">
                            <span class="score-badge score-<?= $tier ?>" data-score-id="<?= (int) $card['id'] ?>" title="<?= $ex['terminal'] ? 'Zakupiono / Porzucono — priorytet wyłączony' : 'Język +' . $ex['language'] . ' · Status +' . $ex['status'] . ' · Cena +' . $ex['price'] . ' · Wiek +' . $ex['age'] . ' · Rynek +' . $ex['market'] ?>"><?= (int) $card['difficulty_score'] ?></span>
                        </td>
                        <td><?= htmlspecialchars($card['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <?= htmlspecialchars($card['language'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($card['country'] !== null): ?>
                            <span style="color:#9ca3af;font-size:.8rem"> / <?= htmlspecialchars($card['country'], ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars(formatPrice($card['target_price']),        ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(formatPrice($card['current_offer_price']), ENT_QUOTES, 'UTF-8') ?></td>
                        <td class="market-cell" id="mkt-<?= (int) $card['id'] ?>">
                            <?php if ($card['market_price'] !== null): ?>
                                <?php
                                $mp  = (float) $card['market_price'];
                                $tp  = $card['target_price'] !== null ? (float) $card['target_price'] : null;
                                $pct = ($tp !== null && $tp > 0) ? (int) round(($mp - $tp) / $tp * 100) : null;
                                ?>
                                <?= number_format($mp, 2) ?> €
                                <?php if ($pct !== null && $pct > 10): ?>
                                <span class="market-delta <?= $pct > 30 ? 'delta-critical' : 'delta-warn' ?>">&#8593; +<?= $pct ?>%</span>
                                <?php elseif ($pct !== null && $pct < 0): ?>
                                <span class="market-delta delta-ok">&#8595; <?= $pct ?>%</span>
                                <?php endif; ?>
                            <?php elseif (!empty($card['api_card_id'])): ?>
                                <span style="color:#9ca3af;font-size:.75rem">—</span>
                            <?php else: ?>
                                <span style="color:#d1d5db;font-size:.75rem">n/d</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="card-status.php" class="status-form">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                                <input type="hidden" name="filter_status" value="<?= htmlspecialchars($filterStatus, ENT_QUOTES, 'UTF-8') ?>">
                                <select name="status" class="status-select status-<?= htmlspecialchars($card['status'], ENT_QUOTES, 'UTF-8') ?>" onchange="this.form.submit()">
                                    <option value="searching"      <?= $card['status'] === 'searching'      ? 'selected' : '' ?>>Szukam</option>
                                    <option value="contacted"      <?= $card['status'] === 'contacted'      ? 'selected' : '' ?>>Skontaktowano</option>
                                    <option value="offer_received" <?= $card['status'] === 'offer_received' ? 'selected' : '' ?>>Oferta otrzymana</option>
                                    <option value="acquired"       <?= $card['status'] === 'acquired'       ? 'selected' : '' ?>>Zakupiono</option>
                                    <option value="abandoned"      <?= $card['status'] === 'abandoned'      ? 'selected' : '' ?>>Porzucono</option>
                                </select>
                            </form>
                        </td>
                        <td class="next-step-cell">
                            <a href="<?= nextActionUrl($card) ?>" class="next-step-link"><?= nextAction($card) ?></a>
                        </td>
                        <td>
                            <a href="card-edit.php?id=<?= (int) $card['id'] ?>">Edytuj</a>
                            <a href="card-message.php?id=<?= (int) $card['id'] ?>" title="Wygeneruj wiadomość" style="padding:.22rem .5rem">&#128172;</a>
                            <form method="post" action="card-delete.php"
                                  style="display:inline"
                                  onsubmit="return confirm('Usunąć tę kartę?')">
                                <?= Csrf::field() ?>
                                <input type="hidden" name="card_id" value="<?= (int) $card['id'] ?>">
                                <button type="submit">Usuń</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div><!-- .table-scroll -->
        <?php endif; ?>
    </main>
    <?php require '_flash.php'; ?>
    <script>
    (function () {
        const icon = document.querySelector('.score-info');
        if (icon) {
            const tip = icon.querySelector('.score-tooltip');
            icon.addEventListener('mouseenter', function () {
                const r = icon.getBoundingClientRect();
                tip.style.top  = (r.bottom + 8 + window.scrollY) + 'px';
                tip.style.left = Math.max(8, r.left + r.width / 2 - 140 + window.scrollX) + 'px';
                tip.classList.add('visible');
            });
            icon.addEventListener('mouseleave', function () {
                tip.classList.remove('visible');
            });
        }

        // Price refresh
        var btnRefresh  = document.getElementById('btn-refresh-prices');
        var csrfToken   = '<?= htmlspecialchars(\App\Security\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>';
        var refreshBase = '<?= htmlspecialchars(rtrim($config['base_url'], '/'), ENT_QUOTES, 'UTF-8') ?>';

        if (btnRefresh) {
            btnRefresh.addEventListener('click', function () {
                btnRefresh.disabled    = true;
                btnRefresh.textContent = 'Pobieranie…';

                var fd = new FormData();
                fd.append('csrf_token', csrfToken);

                fetch(refreshBase + '/api/price-refresh.php', { method: 'POST', body: fd })
                    .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
                    .then(function (data) {
                        data.cards.forEach(function (c) {
                            // Update score badge
                            var badge = document.querySelector('[data-score-id="' + c.id + '"]');
                            if (badge) {
                                badge.textContent = c.score;
                                badge.className   = 'score-badge score-' + c.tier;
                            }

                            // Update market cell
                            var cell = document.getElementById('mkt-' + c.id);
                            if (cell) {
                                cell.className = 'market-cell';
                                if (c.market_price === null) {
                                    cell.innerHTML = '<span style="color:#9ca3af;font-size:.75rem">—</span>';
                                    return;
                                }
                                var mp  = parseFloat(c.market_price);
                                var tp  = c.target_price !== null ? parseFloat(c.target_price) : null;
                                var pct = (tp !== null && tp > 0) ? Math.round((mp - tp) / tp * 100) : null;
                                var html = mp.toFixed(2) + ' €';
                                if (pct !== null && pct > 10) {
                                    var cls = pct > 30 ? 'delta-critical' : 'delta-warn';
                                    html += '<span class="market-delta ' + cls + '">↑ +' + pct + '%</span>';
                                } else if (pct !== null && pct < 0) {
                                    html += '<span class="market-delta delta-ok">↓ ' + pct + '%</span>';
                                }
                                cell.innerHTML = html;
                            }
                        });

                        btnRefresh.textContent = '✓ Zaktualizowano (' + data.updated + ')';
                        setTimeout(function () {
                            btnRefresh.disabled   = false;
                            btnRefresh.innerHTML  = '&#128200; Odśwież ceny';
                        }, 3000);
                    })
                    .catch(function () {
                        btnRefresh.textContent = 'Błąd połączenia';
                        btnRefresh.disabled    = false;
                    });
            });
        }

        // Live filter
        var filterInput = document.getElementById('list-filter');
        var countSpan   = document.getElementById('visible-count');
        if (filterInput) {
            filterInput.addEventListener('input', function () {
                var q    = this.value.toLowerCase().trim();
                var rows = document.querySelectorAll('.card-table tbody tr');
                var vis  = 0;
                rows.forEach(function (row) {
                    var name = row.querySelector('td:nth-child(3)');
                    var match = !q || (name && name.textContent.toLowerCase().indexOf(q) !== -1);
                    row.style.display = match ? '' : 'none';
                    if (match) vis++;
                });
                if (countSpan) {
                    countSpan.textContent = rows.length ? ' (' + vis + ')' : '';
                }
            });
        }
    })();
    </script>
</body>
</html>

