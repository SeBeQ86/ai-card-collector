<?php declare(strict_types=1);

$config = require __DIR__ . '/config/app.php';
require __DIR__ . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Card\CardRepository;
use App\Database\Connection;
use App\Message\MessageTemplateRepository;
use App\Message\SellerMessageGenerator;
use App\Security\Csrf;

Auth::startSession();
Auth::requireAuth();

$user   = Auth::user();
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

$tmplRepo  = new MessageTemplateRepository($pdo);
$templates = $tmplRepo->all();

$locales  = SellerMessageGenerator::locales();
$messages = [];
foreach ($locales as $code => $label) {
    $custom = $templates[$code] ?? null;
    $messages[$code] = ['label' => $label, 'text' => SellerMessageGenerator::generate($card, $code, $custom)];
}

$appName = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function statusLabel(string $status): string
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

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wiadomość — <?= e($card['name']) ?> — <?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
    <style>
        /* ---- Card info bar ---- */
        .msg-card-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: .9rem 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.06);
        }

        .msg-card-img {
            width: 44px;
            height: 61px;
            object-fit: contain;
            border-radius: 4px;
            flex-shrink: 0;
        }

        .msg-card-img-placeholder {
            width: 44px;
            height: 61px;
            flex-shrink: 0;
            border-radius: 4px;
            background-color: #f0f2f5;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 44 61'%3E%3Crect x='1.5' y='1.5' width='41' height='58' rx='4' fill='none' stroke='%23d1d5db' stroke-width='1.5'/%3E%3Crect x='5' y='5' width='34' height='22' rx='2' fill='%23e5e7eb'/%3E%3Ccircle cx='22' cy='43' r='7' fill='none' stroke='%23d1d5db' stroke-width='1.5'/%3E%3Cline x1='18' y1='43' x2='26' y2='43' stroke='%23d1d5db' stroke-width='1.5'/%3E%3Cline x1='22' y1='39' x2='22' y2='47' stroke='%23d1d5db' stroke-width='1.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-size: contain;
        }

        .msg-card-info { flex: 1; min-width: 0; }

        .msg-card-name {
            font-size: 1rem;
            font-weight: 700;
            color: #111827;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .msg-card-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .25rem .75rem;
            margin-top: .3rem;
        }

        .msg-meta-item {
            font-size: .8rem;
            color: #6b7280;
        }

        .msg-meta-item strong {
            color: #374151;
            font-weight: 600;
        }

        .msg-card-actions {
            display: flex;
            gap: .5rem;
            flex-shrink: 0;
        }

        /* ---- Messages grid ---- */
        .msg-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        @media (max-width: 700px) {
            .msg-grid { grid-template-columns: 1fr; }
        }

        .msg-tile {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem 1.1rem 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.05);
            display: flex;
            flex-direction: column;
            gap: .6rem;
        }

        .msg-tile-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .msg-lang-label {
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #6b7280;
        }

        .msg-tile textarea {
            font-family: "Courier New", Courier, monospace;
            font-size: .8rem;
            line-height: 1.55;
            color: #1f2937;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 5px;
            resize: vertical;
            min-height: 180px;
            padding: .6rem .75rem;
            width: 100%;
        }

        .copy-btn {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .85rem;
            font-size: .8rem;
            font-weight: 600;
            font-family: inherit;
            background: transparent;
            color: #2563eb;
            border: 1px solid #bfdbfe;
            border-radius: 5px;
            cursor: pointer;
            transition: background .12s, color .12s, border-color .12s;
            white-space: nowrap;
        }

        .copy-btn:hover { background: #eff6ff; border-color: #93c5fd; }
        .copy-btn.copied { background: #f0fdf4; color: #15803d; border-color: #86efac; }
    </style>
</head>
<body>
    <header>
        <h1><a href="index.php" style="color:inherit;text-decoration:none"><?= $appName ?></a></h1>
        <p>
            <a href="index.php" class="act-btn">&larr; Powrót do listy</a>
            &nbsp;
            <a href="message-templates.php" class="act-btn">&#9998; Edytuj szablony</a>
            &nbsp;
            <form method="post" action="logout.php" style="display:inline">
                <?= Csrf::field() ?>
                <button type="submit">Wyloguj</button>
            </form>
        </p>
    </header>

    <main>
        <h2>Wiadomość do sprzedającego</h2>

        <!-- CARD INFO BAR -->
        <div class="msg-card-bar">
            <?php if (!empty($card['image_url'])): ?>
            <img src="<?= e($card['image_url']) ?>" alt="" class="msg-card-img">
            <?php else: ?>
            <div class="msg-card-img-placeholder"></div>
            <?php endif; ?>

            <div class="msg-card-info">
                <div class="msg-card-name"><?= e($card['name']) ?></div>
                <div class="msg-card-meta">
                    <span class="msg-meta-item"><strong>Język:</strong> <?= e($card['language']) ?></span>
                    <?php if (!empty($card['country'])): ?>
                    <span class="msg-meta-item"><strong>Kraj:</strong> <?= e($card['country']) ?></span>
                    <?php endif; ?>
                    <span class="msg-meta-item"><strong>Status:</strong> <?= e(statusLabel($card['status'])) ?></span>
                    <?php if ($card['target_price'] !== null): ?>
                    <span class="msg-meta-item"><strong>Budżet:</strong> <?= e(number_format((float)$card['target_price'], 2)) ?> €</span>
                    <?php endif; ?>
                    <?php if ($card['current_offer_price'] !== null): ?>
                    <span class="msg-meta-item"><strong>Oferta:</strong> <?= e(number_format((float)$card['current_offer_price'], 2)) ?> €</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="msg-card-actions">
                <a href="card-edit.php?id=<?= $cardId ?>" class="act-btn">Edytuj kartę</a>
            </div>
        </div>

        <!-- MESSAGES GRID -->
        <div class="msg-grid">
            <?php foreach ($messages as $code => $msg): ?>
            <div class="msg-tile">
                <div class="msg-tile-header">
                    <span class="msg-lang-label"><?= e($msg['label']) ?></span>
                    <button type="button" class="copy-btn" onclick="copyMsg('msg-<?= e($code) ?>', this)">
                        &#128203; Kopiuj
                    </button>
                </div>
                <textarea id="msg-<?= e($code) ?>" readonly><?= e($msg['text']) ?></textarea>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <?php require '_flash.php'; ?>
    <script>
    function copyMsg(id, btn) {
        var el = document.getElementById(id);
        if (!el) return;
        if (navigator.clipboard) {
            navigator.clipboard.writeText(el.value).catch(function () {
                el.select(); document.execCommand('copy');
            });
        } else {
            el.select(); document.execCommand('copy');
        }
        btn.textContent = '✓ Skopiowano';
        btn.classList.add('copied');
        setTimeout(function () {
            btn.innerHTML = '&#128203; Kopiuj';
            btn.classList.remove('copied');
        }, 2000);
    }
    </script>
</body>
</html>
