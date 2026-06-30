<?php declare(strict_types=1);

/**
 * AJAX endpoint: refresh Cardmarket prices for all active cards with api_card_id.
 *
 * POST /api/price-refresh.php  (CSRF: non-consuming check — token stays valid for page forms)
 * Auth-gated. Returns JSON:
 *   { updated: int, cards: [{id, market_price, target_price, score, tier}] }
 */

$config = require file_exists(dirname(__DIR__) . '/config/app.php') ? dirname(__DIR__) . '/config/app.php' : dirname(__DIR__, 2) . '/config/app.php';
require file_exists(dirname(__DIR__) . '/src/bootstrap.php') ? dirname(__DIR__) . '/src/bootstrap.php' : dirname(__DIR__, 2) . '/src/bootstrap.php';

use App\Auth\Auth;
use App\Api\ApiCache;
use App\Api\TcgDexClient;
use App\Card\CardRepository;
use App\Card\CardScorer;
use App\Database\Connection;
use App\Security\Csrf;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

Auth::startSession();

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$token = (string) ($_POST['csrf_token'] ?? '');
if (!Csrf::check($token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$user   = Auth::user();
$pdo    = Connection::get($config['db']);
$repo   = new CardRepository($pdo);
$cache  = new ApiCache($pdo);
$client = new TcgDexClient($cache);

$cards   = $repo->listActiveWithApiId($user['id']);
$results = [];

foreach ($cards as $card) {
    $full = $client->findById((string) $card['api_card_id']);

    // Skip update entirely if API returned nothing — don't wipe pre-existing data
    if ($full === null) {
        continue;
    }

    $marketPrice = isset($full['price_avg30']) ? $full['price_avg30'] : null;
    $imageUrl    = !empty($full['image_small']) ? $full['image_small'] : null;

    $createdTs = strtotime((string) ($card['created_at'] ?? ''));
    $ageInDays = $createdTs !== false ? (int) ((time() - $createdTs) / 86400) : 0;

    $target = isset($card['target_price']) && $card['target_price'] !== null
        ? (float) $card['target_price'] : null;
    $offer  = isset($card['current_offer_price']) && $card['current_offer_price'] !== null
        ? (float) $card['current_offer_price'] : null;

    $newScore = CardScorer::calculate(
        (string) $card['language'],
        (string) $card['status'],
        $target,
        $offer,
        $ageInDays,
        $marketPrice
    );

    $repo->updateMarketPrice($user['id'], (int) $card['id'], $marketPrice, $newScore, $imageUrl);

    $results[] = [
        'id'           => (int) $card['id'],
        'market_price' => $marketPrice,
        'target_price' => $target,
        'score'        => $newScore,
        'tier'         => CardScorer::tier($newScore),
    ];
}

echo json_encode(['updated' => count($results), 'cards' => $results], JSON_UNESCAPED_UNICODE);
