<?php declare(strict_types=1);

/**
 * AJAX endpoint: full card details by TCGdex ID.
 *
 * GET /api/card-detail.php?id=swsh1-1
 * Auth-gated: returns 401 JSON if session is not authenticated.
 * Returns: JSON object {id, name, set, local_id, rarity, image_small, image_large,
 *                       price_avg30, price_trend, price_low} or null if not found.
 */

$config = require file_exists(dirname(__DIR__) . '/config/app.php') ? dirname(__DIR__) . '/config/app.php' : dirname(__DIR__, 2) . '/config/app.php';
require file_exists(dirname(__DIR__) . '/src/bootstrap.php') ? dirname(__DIR__) . '/src/bootstrap.php' : dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Api\ApiCache;
use App\Api\TcgDexClient;
use App\Database\Connection;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

Auth::startSession();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$id = trim((string) ($_GET['id'] ?? ''));

if ($id === '' || strlen($id) > 80) {
    echo json_encode(null);
    exit;
}

$pdo    = Connection::get($config['db']);
$cache  = new ApiCache($pdo);
$client = new TcgDexClient($cache);

$card = $client->findById($id);
echo json_encode($card, JSON_UNESCAPED_UNICODE);
