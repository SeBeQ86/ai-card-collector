<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\Flash;
use App\Card\CardScorer;
use App\Database\Connection;
use App\Security\Csrf;

Auth::startSession();
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
    header('Location: index.php');
    exit;
}

$user   = Auth::user();
$pdo    = Connection::get($config['db']);
$cardId = (int) ($_POST['card_id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowed = ['searching', 'contacted', 'offer_received', 'acquired', 'abandoned'];

if ($cardId <= 0 || !in_array($status, $allowed, true)) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM wanted_cards WHERE id = ? AND user_id = ?');
$stmt->execute([$cardId, $user['id']]);
$card = $stmt->fetch();

if (!$card) {
    header('Location: index.php');
    exit;
}

$ageInDays = (int) round((time() - strtotime((string) $card['created_at'])) / 86400);

$score = CardScorer::calculate(
    (string) $card['language'],
    $status,
    $card['target_price']         !== null ? (float) $card['target_price']         : null,
    $card['current_offer_price']  !== null ? (float) $card['current_offer_price']  : null,
    $ageInDays,
    $card['market_price']         !== null ? (float) $card['market_price']         : null
);

$stmt = $pdo->prepare(
    'UPDATE wanted_cards SET status = ?, difficulty_score = ? WHERE id = ? AND user_id = ?'
);
$stmt->execute([$status, $score, $cardId, $user['id']]);

$statusLabels = [
    'searching' => 'Szukam', 'contacted' => 'Skontaktowano',
    'offer_received' => 'Oferta otrzymana', 'acquired' => 'Zakupiono', 'abandoned' => 'Porzucono',
];
Flash::set('success', 'Status zmieniony na: ' . ($statusLabels[$status] ?? $status) . '.');

header('Location: index.php' . (isset($_POST['filter_status']) && $_POST['filter_status'] !== ''
    ? '?status=' . urlencode($_POST['filter_status'])
    : ''));
exit;

