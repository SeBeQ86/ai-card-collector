<?php declare(strict_types=1);

$config = require file_exists(__DIR__ . '/config/app.php') ? __DIR__ . '/config/app.php' : dirname(__DIR__) . '/config/app.php';
require file_exists(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\Flash;
use App\Database\Connection;
use App\Message\MessageTemplateRepository;
use App\Message\SellerMessageGenerator;
use App\Security\Csrf;

Auth::startSession();
Auth::requireAuth();

$pdo      = Connection::get($config['db']);
$tmplRepo = new MessageTemplateRepository($pdo);
$locales  = SellerMessageGenerator::locales();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf_token'] ?? '');
    if (!Csrf::validate($token)) {
        $errors[] = 'Nieprawidłowe żądanie — spróbuj ponownie.';
    } else {
        foreach (array_keys($locales) as $code) {
            if (isset($_POST['reset'][$code])) {
                $tmplRepo->save($code, '');
                continue;
            }
            $body = trim((string) ($_POST['tpl'][$code] ?? ''));
            $tmplRepo->save($code, $body);
        }
        Flash::set('success', 'Szablony wiadomości zostały zapisane.');
        header('Location: message-templates.php');
        exit;
    }
}

$saved = $tmplRepo->all();

$previewCard = [
    'name'                => 'Charizard',
    'language'            => 'Japanese',
    'country'             => 'Japan',
    'target_price'        => '120.00',
    'current_offer_price' => '95.00',
    'notes'               => 'PSA 8',
];

$appName = htmlspecialchars($config['name'], ENT_QUOTES, 'UTF-8');

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }

?><!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Szablony wiadomości — <?= $appName ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
    <link rel="stylesheet" href="assets/style.css?v=6">
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
        <h2>Szablony wiadomości do sprzedającego</h2>

        <?php if (!empty($errors)): ?>
        <ul class="errors">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <!-- TOKEN REFERENCE -->
        <div class="form-section" style="max-width:760px">
            <div class="form-section-title">Dostępne tokeny</div>
            <p style="font-size:.82rem;color:#6b7280;margin-bottom:.6rem">
                Kliknij token aby zaznaczyć, skopiuj i wklej do szablonu. Puste pole = używa domyślnego szablonu.
            </p>
            <div style="display:flex;flex-wrap:wrap;gap:.35rem .5rem">
                <?php foreach ([
                    '{{name}}'         => 'nazwa karty',
                    '{{notes}}'        => 'notatki / grading',
                    '{{language}}'     => 'wersja językowa',
                    '{{country}}'      => 'kraj / region',
                    '{{target_price}}' => 'budżet w €',
                    '{{offer_price}}'  => 'aktualna oferta w €',
                ] as $token => $desc): ?>
                <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.8rem">
                    <code style="background:#fff;border:1px solid #d1d5db;border-radius:4px;padding:.1rem .45rem;font-size:.78rem;cursor:pointer;user-select:all;color:#2563eb" onclick="this.focus();document.execCommand('selectAll')"><?= e($token) ?></code>
                    <span style="color:#9ca3af"><?= e($desc) ?></span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>

        <form method="post" action="message-templates.php" style="max-width:760px">
            <?= Csrf::field() ?>

            <?php foreach ($locales as $code => $label):
                $isCustom   = isset($saved[$code]) && trim($saved[$code]) !== '';
                $currentVal = $isCustom ? $saved[$code] : '';
                $placeholder = SellerMessageGenerator::generate($previewCard, $code);
            ?>
            <div class="form-section">
                <div class="form-section-title" style="display:flex;align-items:center;justify-content:space-between">
                    <span><?= e($label) ?> <span style="font-weight:400;color:#9ca3af;font-size:.78rem">(<?= e($code) ?>)</span></span>
                    <?php if ($isCustom): ?>
                    <span style="font-size:.7rem;font-weight:600;padding:.15rem .5rem;border-radius:99px;background:#dbeafe;color:#1d4ed8">Własny</span>
                    <?php else: ?>
                    <span style="font-size:.7rem;font-weight:600;padding:.15rem .5rem;border-radius:99px;background:#f3f4f6;color:#6b7280">Domyślny</span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <textarea name="tpl[<?= e($code) ?>]"
                              rows="7"
                              style="font-family:'Courier New',Courier,monospace;font-size:.8rem;line-height:1.6"
                              placeholder="<?= e($placeholder) ?>"
                    ><?= e($currentVal) ?></textarea>
                </div>

                <?php if ($isCustom): ?>
                <div style="padding:.25rem .1rem .5rem;text-align:right">
                    <button type="submit" name="reset[<?= e($code) ?>]" value="1"
                            class="btn-reset"
                            onclick="return confirm('Przywrócić domyślny szablon dla <?= e($label) ?>?')">
                        ↺ Przywróć domyślny
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit">Zapisz szablony</button>
                <a href="index.php" class="btn-cancel">Anuluj</a>
            </div>
        </form>
    </main>

    <?php require '_flash.php'; ?>
</body>
</html>
