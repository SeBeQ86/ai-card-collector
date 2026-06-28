<?php

declare(strict_types=1);

/**
 * TcgDexClient unit tests — no framework, no network required.
 *
 * Run:  php tests/TcgDexClientTest.php
 * Exit: 0 on all pass, 1 on any failure.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Api\ApiCache;
use App\Api\TcgDexClient;

// ── In-memory cache stub ──────────────────────────────────────────────────────

final class TcgDexInMemoryCache extends ApiCache
{
    private array $store = [];

    public function __construct() {}

    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) return null;
        [$data, $exp] = $this->store[$key];
        return time() < $exp ? $data : null;
    }

    public function set(string $key, array $data, int $ttl = 86400): void
    {
        $this->store[$key] = [$data, time() + $ttl];
    }

    public function purgeExpired(): void
    {
        $now = time();
        foreach ($this->store as $k => [, $exp]) {
            if ($now >= $exp) unset($this->store[$k]);
        }
    }
}

// ── HTTP stub ─────────────────────────────────────────────────────────────────

final class StubTcgDexClient extends TcgDexClient
{
    public function __construct(ApiCache $cache, private ?string $response) {
        parent::__construct($cache);
    }

    protected function fetch(string $url): ?string
    {
        return $this->response;
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

$pass = 0;
$fail = 0;

function ok(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) { echo "  PASS  {$label}\n"; $pass++; }
    else         { echo "  FAIL  {$label}\n"; $fail++; }
}

// ── Fixtures ──────────────────────────────────────────────────────────────────

$searchPayload = json_encode([
    ['id' => 'base1-4', 'name' => 'Charizard',       'image' => 'https://assets.tcgdex.net/en/base/base1/4'],
    ['id' => 'ecard1-8','name' => 'Charizard (eCard)','image' => 'https://assets.tcgdex.net/en/ecard1/8'],
]);

$singlePayload = json_encode([
    'id'    => 'base1-4',
    'name'  => 'Charizard',
    'image' => 'https://assets.tcgdex.net/en/base/base1/4',
    'set'   => ['id' => 'base1', 'name' => 'Base Set'],
]);

// ── Tests ─────────────────────────────────────────────────────────────────────

echo "\nTcgDexClientTest\n";
echo str_repeat('-', 42) . "\n";

// 1. searchByName — happy path
$client  = new StubTcgDexClient(new TcgDexInMemoryCache(), $searchPayload);
$results = $client->searchByName('Charizard');
ok('searchByName returns 2 results',      count($results) === 2);
ok('result has id',                       $results[0]['id'] === 'base1-4');
ok('result has name',                     $results[0]['name'] === 'Charizard');
ok('image_small appends /low.webp',       str_ends_with($results[0]['image_small'], '/low.webp'));
ok('image_large appends /high.webp',      str_ends_with($results[0]['image_large'], '/high.webp'));
ok('set is empty string for partial',     $results[0]['set'] === '');

// 2. searchByName — cache hit
$cache2  = new TcgDexInMemoryCache();
$client2 = new StubTcgDexClient($cache2, $searchPayload);
$client2->searchByName('Charizard');
$client3 = new StubTcgDexClient($cache2, null);  // no network
ok('searchByName returns cached result',  count($client3->searchByName('Charizard')) === 2);

// 3. searchByName — API error
$client4 = new StubTcgDexClient(new TcgDexInMemoryCache(), null);
ok('searchByName returns [] on error',    $client4->searchByName('Charizard') === []);

// 4. searchByName — empty query
ok('searchByName returns [] for ""',      $client4->searchByName('') === []);

// 5. findById — happy path
$client5 = new StubTcgDexClient(new TcgDexInMemoryCache(), $singlePayload);
$card    = $client5->findById('base1-4');
ok('findById returns array',              is_array($card));
ok('findById result has id',              ($card['id']   ?? '') === 'base1-4');
ok('findById result has name',            ($card['name'] ?? '') === 'Charizard');
ok('findById result has set name',        ($card['set']  ?? '') === 'Base Set');
ok('findById image_small has /low.webp',  str_ends_with($card['image_small'] ?? '', '/low.webp'));

// 6. findById — cache hit
$cache6  = new TcgDexInMemoryCache();
$client6 = new StubTcgDexClient($cache6, $singlePayload);
$client6->findById('base1-4');
$client7 = new StubTcgDexClient($cache6, null);
ok('findById returns cached result',      $client7->findById('base1-4') !== null);

// 7. findById — error / empty id
$client8 = new StubTcgDexClient(new TcgDexInMemoryCache(), null);
ok('findById returns null on error',      $client8->findById('base1-4') === null);
ok('findById returns null for ""',        $client8->findById('') === null);

// ── Result ────────────────────────────────────────────────────────────────────

echo str_repeat('-', 42) . "\n";
echo "  {$pass} passed, {$fail} failed\n\n";

exit($fail > 0 ? 1 : 0);
