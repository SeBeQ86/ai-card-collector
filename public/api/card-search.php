<?php declare(strict_types=1);

/**
 * AJAX endpoint: card name autocomplete via TCGdex API (api.tcgdex.net/v2).
 *
 * GET /api/card-search.php?q=charizard
 * Auth-gated: returns 401 JSON if session is not authenticated.
 * Returns: JSON array of {id, name, set, image_small, image_large}
 */

$config = require dirname(__DIR__) . '/config/app.php';
require dirname(__DIR__) . '/src/bootstrap.php';

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

$query = trim((string) ($_GET['q'] ?? ''));

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$pdo    = Connection::get($config['db']);
$cache  = new ApiCache($pdo);
$client = new TcgDexClient($cache);

$results = $client->searchByName($query);

echo json_encode($results, JSON_UNESCAPED_UNICODE);
