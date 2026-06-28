<?php

declare(strict_types=1);

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Auth\Flash;
use App\Card\CardRepository;
use App\Database\Connection;
use App\Security\Csrf;

Auth::startSession();
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');

if (!Csrf::validate($token)) {
    header('Location: index.php');
    exit;
}

$user   = Auth::user();
$cardId = isset($_POST['card_id']) ? (int) $_POST['card_id'] : 0;

if ($cardId > 0) {
    $repo = new CardRepository(Connection::get($config['db']));
    $deleted = $repo->deleteForUser($user['id'], $cardId);
    if ($deleted) {
        Flash::set('success', 'Karta została usunięta.');
    }
}

header('Location: index.php');
exit;

