<?php

declare(strict_types=1);

$config = require file_exists(__DIR__ . '/config/app.php') ? __DIR__ . '/config/app.php' : dirname(__DIR__) . '/config/app.php';
require file_exists(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\Flash;
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
    'image_url'           => (string) ($card['image_url'] ?? ''),
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
        'image_url'           => trim((string) ($_POST['image_url']           ?? '')),
    ];

    $token = (string) ($_POST['csrf_token'] ?? '');

    if (!Csrf::validate($token)) {
        $errors[] = 'Nieprawidłowe żądanie — spróbuj ponownie.';
    } else {
        if ($input['name'] === '') {
            $errors[] = 'Nazwa karty jest wymagana.';
        } elseif (mb_strlen($input['name']) > 255) {
            $errors[] = 'Nazwa karty jest za długa (maks. 255 znaków).';
        }
        if ($input['language'] === '') {
            $errors[] = 'Wersja językowa jest wymagana.';
        }
        if (!in_array($input['status'], VALID_STATUSES, true)) {
            $errors[] = 'Nieprawidłowy status.';
        }
        if ($input['api_card_id'] !== '' && strlen($input['api_card_id']) > 50) {
            $errors[] = 'ID karty API jest za długie.';
        }
        if ($input['target_price'] !== '' && (!is_numeric($input['target_price']) || (float) $input['target_price'] < 0)) {
            $errors[] = 'Budżet musi być liczbą nieujemną.';
        }
        if ($input['current_offer_price'] !== '' && (!is_numeric($input['current_offer_price']) || (float) $input['current_offer_price'] < 0)) {
            $errors[] = 'Aktualna oferta musi być liczbą nieujemną.';
        }
        if ($input['purchase_price'] !== '' && (!is_numeric($input['purchase_price']) || (float) $input['purchase_price'] < 0)) {
            $errors[] = 'Cena zakupu musi być liczbą nieujemną.';
        }
        if ($input['purchased_at'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['purchased_at'])) {
            $errors[] = 'Data zakupu musi być w formacie RRRR-MM-DD.';
        }
        if ($input['purchased_at'] !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['purchased_at']) && $input['purchased_at'] > date('Y-m-d')) {
            $errors[] = 'Data zakupu nie może być w przyszłości.';
        }
        if ($input['source_url'] !== '' && !filter_var($input['source_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'URL oferty jest nieprawidłowy.';
        }

        if (empty($errors)) {
            $targetPrice   = $input['target_price'] !== '' ? (float) $input['target_price'] : null;
            $offerPrice    = $input['current_offer_price'] !== '' ? (float) $input['current_offer_price'] : null;
            $purchasePrice = $input['purchase_price'] !== '' ? (float) $input['purchase_price'] : null;

            $createdTs = strtotime((string) $card['created_at']);
            $ageInDays = $createdTs !== false
                ? (int) (((new DateTimeImmutable())->getTimestamp() - $createdTs) / 86400)
                : 0;

            $marketPrice = isset($card['market_price']) && $card['market_price'] !== null
                ? (float) $card['market_price'] : null;

            $score = CardScorer::calculate(
                $input['language'],
                $input['status'],
                $targetPrice,
                $offerPrice,
                $ageInDays,
                $marketPrice
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
                'image_url'           => $input['image_url'] !== '' ? $input['image_url'] : null,
                'difficulty_score'    => $score,
            ];

            $repo->updateForUser($user['id'], $cardId, $data);

            Flash::set('success', 'Zmiany w „' . $input['name'] . '" zostały zapisane.');
            header('Location: index.php');
            exit;
        }
    }
}

// Use stored image_url (no API call needed on edit page)
$cardImageUrl = $input['image_url'];

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
    $msgLink  = '<a href="card-message.php?id=' . $cardId . '">wygeneruj wiadomość do sprzedającego</a>';
    $editLink = '<a href="card-edit.php?id=' . $cardId . '#deal-fields">uzupełnij dane dealu</a>';

    return match ($status) {
        'searching'      => '<strong>Następny krok:</strong> Znajdź ofertę, a następnie ' . $msgLink . ', aby skontaktować się ze sprzedającym.',
        'contacted'      => '<strong>Następny krok:</strong> Czekaj na odpowiedź. Jeśli brak odpowiedzi przez 7 dni — przypomnij się. Możesz ' . $msgLink . ' ponownie.',
        'offer_received' => '<strong>Następny krok:</strong> Porównaj ofertę ze swoim budżetem. Zaakceptuj, negocjuj lub zaktualizuj cenę i wyślij kontrpropozycję.',
        'acquired'       => '<strong>Następny krok:</strong> Zapisz szczegóły zakupu — ' . $editLink . ' poniżej (cena, data, źródło).',
        'abandoned'      => '<strong>Uwaga:</strong> Poszukiwanie porzucone. Możesz je wznowić zmieniając status z powrotem na Szukam.',
        default          => '',
    };
}

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edytuj kartę — <?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
    <style>
        .funnel-callout {
            padding: .7rem 1rem;
            border-radius: 6px;
            font-size: .875rem;
            margin-top: .75rem;
        }
        .funnel-searching      { background: #eff6ff; border-left: 4px solid #2563eb; }
        .funnel-contacted      { background: #fffbeb; border-left: 4px solid #d97706; }
        .funnel-offer_received { background: #f5f3ff; border-left: 4px solid #7c3aed; }
        .funnel-acquired       { background: #f0fdf4; border-left: 4px solid #16a34a; }
        .funnel-abandoned      { background: #f9fafb; border-left: 4px solid #9ca3af; }
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
        <h2>Edytuj poszukiwaną kartę</h2>
        <?php
        $changedAt = $card['updated_at'] ?? $card['created_at'] ?? null;
        if ($changedAt): $ts = strtotime((string)$changedAt); $daysAgo = $ts ? (int)(((new DateTimeImmutable())->getTimestamp() - $ts) / 86400) : null;
        ?>
        <p style="font-size:.8rem;color:#9ca3af;margin:-.25rem 0 1rem">
            Dodano: <?= date('j.m.Y', strtotime((string)$card['created_at'])) ?>
            <?php if ($card['updated_at']): ?>
            &nbsp;·&nbsp; Ostatnia zmiana:
            <?= $daysAgo === 0 ? 'dziś' : ($daysAgo === 1 ? 'wczoraj' : $daysAgo . ' dni temu') ?>
            <?php endif; ?>
        </p>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <form method="post" action="card-edit.php?id=<?= $cardId ?>" class="edit-form">
            <?= Csrf::field() ?>
            <input type="hidden" name="api_card_id" value="<?= e($input['api_card_id']) ?>">
            <input type="hidden" name="image_url"   value="<?= e($input['image_url'])   ?>">

            <!-- SEKCJA 1: Karta -->
            <div class="form-section">
                <div class="form-section-title">Karta</div>

                <?php if ($cardImageUrl !== ''): ?>
                <div style="display:flex;align-items:center;gap:.75rem;padding:.55rem .75rem;background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;margin-bottom:.9rem;font-size:.85rem">
                    <img src="<?= e($cardImageUrl) ?>" alt="" style="width:36px;height:50px;object-fit:contain;border-radius:3px">
                    <strong><?= e($input['name']) ?></strong>
                </div>
                <?php endif; ?>

                <div class="field-row full" style="margin-bottom:.75rem">
                    <div class="field">
                        <label for="name">Nazwa karty <span class="req">*</span></label>
                        <input type="text" id="name" name="name" required value="<?= e($input['name']) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="language">Wersja językowa <span class="req">*</span></label>
                        <select id="language" name="language" required>
                            <option value="">— wybierz —</option>
                            <?php foreach ([
                                'English'              => 'Angielski',
                                'Japanese'             => 'Japoński',
                                'Portuguese'           => 'Portugalski',
                                'French'               => 'Francuski',
                                'German'               => 'Niemiecki',
                                'Spanish'              => 'Hiszpański',
                                'Korean'               => 'Koreański',
                                'Thai'                 => 'Tajski',
                                'Chinese (Traditional)'=> 'Chiński (tradycyjny)',
                                'Chinese (Simplified)' => 'Chiński (uproszczony)',
                                'Indonesian'           => 'Indonezyjski',
                                'Russian'              => 'Rosyjski',
                                'Polish'               => 'Polski',
                            ] as $val => $lbl): ?>
                            <option value="<?= e($val) ?>"<?= $input['language'] === $val ? ' selected' : '' ?>><?= e($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="country">Kraj / region</label>
                        <input type="text" id="country" name="country"
                               placeholder="np. Japan, Brazil"
                               value="<?= e($input['country']) ?>">
                    </div>
                </div>
            </div>

            <!-- SEKCJA 2: Status i ceny -->
            <div class="form-section">
                <div class="form-section-title">Status i ceny</div>

                <div class="field-row" style="margin-bottom:.75rem">
                    <div class="field">
                        <label for="status">Status <span class="req">*</span></label>
                        <select id="status" name="status" required onchange="updateFunnel(this.value)">
                            <option value="searching"<?=      selectedIf('searching',      $input['status']) ?>>Szukam</option>
                            <option value="contacted"<?=      selectedIf('contacted',      $input['status']) ?>>Skontaktowano</option>
                            <option value="offer_received"<?= selectedIf('offer_received', $input['status']) ?>>Oferta otrzymana</option>
                            <option value="acquired"<?=       selectedIf('acquired',       $input['status']) ?>>Zakupiono</option>
                            <option value="abandoned"<?=      selectedIf('abandoned',      $input['status']) ?>>Porzucono</option>
                        </select>
                        <div class="funnel-callout funnel-<?= e($input['status']) ?>" id="funnel-box">
                            <?= funnelCallout($input['status'], $cardId) ?>
                        </div>
                    </div>
                    <div class="field">
                        <label for="seller_contact">Kontakt do sprzedającego</label>
                        <input type="text" id="seller_contact" name="seller_contact"
                               placeholder="nick, e-mail, URL…"
                               value="<?= e($input['seller_contact']) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="target_price">Budżet (€)</label>
                        <input type="number" id="target_price" name="target_price"
                               step="any" min="0" placeholder="0.00"
                               value="<?= e($input['target_price']) ?>">
                    </div>
                    <div class="field">
                        <label for="current_offer_price">Aktualna oferta (€)</label>
                        <input type="number" id="current_offer_price" name="current_offer_price"
                               step="any" min="0" placeholder="0.00"
                               value="<?= e($input['current_offer_price']) ?>">
                    </div>
                </div>
            </div>

            <!-- SEKCJA 3: Notatki -->
            <div class="form-section">
                <div class="form-section-title">Notatki</div>
                <div class="field">
                    <label for="notes">Notatki</label>
                    <textarea id="notes" name="notes" rows="3"
                              placeholder="np. PSA 8, NM, 1st edition — trafi do wygenerowanej wiadomości"><?= e($input['notes']) ?></textarea>
                </div>
            </div>

            <!-- SEKCJA 4: Archiwum dealu -->
            <div class="form-section" id="deal-fields">
                <div class="form-section-title">Archiwum dealu</div>

                <div class="field-row" style="margin-bottom:.75rem">
                    <div class="field">
                        <label for="seller_name">Nazwa sprzedającego</label>
                        <input type="text" id="seller_name" name="seller_name"
                               value="<?= e($input['seller_name']) ?>">
                    </div>
                    <div class="field">
                        <label for="purchase_price">Ostateczna cena zakupu (€)</label>
                        <input type="number" id="purchase_price" name="purchase_price"
                               step="any" min="0" placeholder="0.00"
                               value="<?= e($input['purchase_price']) ?>">
                    </div>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="purchased_at">Data zakupu</label>
                        <input type="date" id="purchased_at" name="purchased_at"
                               value="<?= e($input['purchased_at']) ?>">
                    </div>
                    <div class="field">
                        <label for="source_url">URL oferty</label>
                        <input type="url" id="source_url" name="source_url"
                               placeholder="https://www.cardmarket.com/…"
                               value="<?= e($input['source_url']) ?>">
                    </div>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit">Zapisz zmiany</button>
                <a href="index.php" class="btn-cancel">Anuluj</a>
                <a href="card-message.php?id=<?= $cardId ?>" class="btn-add" style="margin-left:auto">&#128172; Wygeneruj wiadomość</a>
            </div>
        </form>
    </main>
    <?php require '_flash.php'; ?>
    <script>
    var funnelMessages = {
        searching:      '<strong>Następny krok:</strong> Znajdź ofertę, a następnie <a href="card-message.php?id=<?= $cardId ?>">wygeneruj wiadomość do sprzedającego</a>.',
        contacted:      '<strong>Następny krok:</strong> Czekaj na odpowiedź. Jeśli brak odpowiedzi przez 7 dni — przypomnij się. Możesz <a href="card-message.php?id=<?= $cardId ?>">wygenerować wiadomość</a> ponownie.',
        offer_received: '<strong>Następny krok:</strong> Porównaj ofertę z budżetem. Zaakceptuj, negocjuj lub zaktualizuj cenę i wyślij kontrpropozycję.',
        acquired:       '<strong>Następny krok:</strong> Zapisz szczegóły zakupu — <a href="#deal-fields">uzupełnij dane dealu</a> poniżej (cena, data, źródło).',
        abandoned:      '<strong>Uwaga:</strong> Poszukiwanie porzucone. Możesz wznowić zmieniając status na Szukam.'
    };
    var funnelClasses = {
        searching: 'funnel-searching', contacted: 'funnel-contacted',
        offer_received: 'funnel-offer_received', acquired: 'funnel-acquired',
        abandoned: 'funnel-abandoned'
    };
    function updateFunnel(status) {
        var el = document.getElementById('funnel-box');
        if (!el) return;
        el.className = 'funnel-callout ' + (funnelClasses[status] || '');
        el.innerHTML = funnelMessages[status] || '';
    }
    </script>
</body>
</html>

