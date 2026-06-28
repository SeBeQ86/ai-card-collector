<?php

declare(strict_types=1);

/**
 * PokemonTcgClient unit tests — no framework, no network required.
 *
 * Run:  php tests/PokemonTcgClientTest.php
 * Exit: 0 on all pass, 1 on any failure.
 *
 * Uses an in-memory SQLite ApiCache and a subclass that stubs the HTTP fetch,
 * so no real network request is ever made.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Api\ApiCache;
use App\Api\PokemonTcgClient;

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * In-memory cache stub — avoids extending the final ApiCache class.
 * Stores entries in a plain PHP array; expiry tracked via timestamps.
 */
final class InMemoryCache extends ApiCache
{
    private array $store = [];

    public function __construct()
    {
        // No PDO needed — override every method
    }

    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) {
            return null;
        }
        [$data, $expiresAt] = $this->store[$key];
        return time() < $expiresAt ? $data : null;
    }

    public function set(string $key, array $data, int $ttlSeconds = 86400): void
    {
        $this->store[$key] = [$data, time() + $ttlSeconds];
    }

    public function purgeExpired(): void
    {
        $now = time();
        foreach ($this->store as $key => [, $exp]) {
            if ($now >= $exp) {
                unset($this->store[$key]);
            }
        }
    }
}

function makeCache(): InMemoryCache
{
    return new InMemoryCache();
}

/** Subclass that replaces HTTP fetch with a fixed stub response. */
final class StubTcgClient extends PokemonTcgClient
{
    private ?string $stubResponse;

    public function __construct(ApiCache $cache, ?string $stubResponse)
    {
        parent::__construct($cache, '');
        $this->stubResponse = $stubResponse;
    }

    protected function fetch(string $url): ?string
    {
        return $this->stubResponse;
    }
}

$pass = 0;
$fail = 0;

function ok(string $label, bool $result): void
{
    global $pass, $fail;
    if ($result) {
        echo "  PASS  {$label}\n";
        $pass++;
    } else {
        echo "  FAIL  {$label}\n";
        $fail++;
    }
}

// ── Fixtures ─────────────────────────────────────────────────────────────────

$searchPayload = json_encode([
    'data' => [
        [
            'id'     => 'base1-4',
            'name'   => 'Charizard',
            'set'    => ['name' => 'Base Set'],
            'images' => ['small' => 'https://images.pokemontcg.io/base1/4.png', 'large' => ''],
        ],
        [
            'id'     => 'base1-5',
            'name'   => 'Charizard (Holo)',
            'set'    => ['name' => 'Base Set'],
            'images' => ['small' => 'https://images.pokemontcg.io/base1/5.png', 'large' => ''],
        ],
    ],
]);

$singlePayload = json_encode([
    'data' => [
        'id'     => 'base1-4',
        'name'   => 'Charizard',
        'set'    => ['name' => 'Base Set'],
        'images' => ['small' => 'https://images.pokemontcg.io/base1/4.png', 'large' => ''],
    ],
]);

// ── Tests ─────────────────────────────────────────────────────────────────────

echo "\nPokemonTcgClientTest\n";
echo str_repeat('-', 42) . "\n";

// 1. searchByName returns normalized results
$client  = new StubTcgClient(makeCache(), $searchPayload);
$results = $client->searchByName('Charizard');
ok('searchByName returns 2 results',           count($results) === 2);
ok('searchByName result has id',               isset($results[0]['id']) && $results[0]['id'] === 'base1-4');
ok('searchByName result has name',             $results[0]['name'] === 'Charizard');
ok('searchByName result has set',              $results[0]['set'] === 'Base Set');
ok('searchByName result has image_small',      str_contains($results[0]['image_small'], 'pokemontcg.io'));

// 2. searchByName caches — second call must not call fetch (stub returns null)
$cache2  = makeCache();
$client2 = new StubTcgClient($cache2, $searchPayload);
$client2->searchByName('Charizard');                        // populates cache
$client3 = new StubTcgClient($cache2, null);                // null = no network
$cached  = $client3->searchByName('Charizard');
ok('searchByName returns cached result on second call',    count($cached) === 2);

// 3. searchByName returns empty array on API error
$client4  = new StubTcgClient(makeCache(), null);
$empty    = $client4->searchByName('Charizard');
ok('searchByName returns [] when fetch fails',             $empty === []);

// 4. searchByName empty/short query returns [] without hitting network
$client5 = new StubTcgClient(makeCache(), $searchPayload);
ok('searchByName returns [] for empty query',  $client5->searchByName('') === []);
// Min-length enforcement is at the HTTP endpoint layer (card-search.php), not the client

// 5. findById returns normalized result
$client6 = new StubTcgClient(makeCache(), $singlePayload);
$card    = $client6->findById('base1-4');
ok('findById returns card array',       is_array($card));
ok('findById result has id',            ($card['id'] ?? '') === 'base1-4');
ok('findById result has name',          ($card['name'] ?? '') === 'Charizard');
ok('findById result has set',           ($card['set'] ?? '') === 'Base Set');

// 6. findById caches
$cache7  = makeCache();
$client7 = new StubTcgClient($cache7, $singlePayload);
$client7->findById('base1-4');
$client8 = new StubTcgClient($cache7, null);
ok('findById returns cached result on second call', $client8->findById('base1-4') !== null);

// 7. findById returns null on error
$client9 = new StubTcgClient(makeCache(), null);
ok('findById returns null when fetch fails', $client9->findById('base1-4') === null);

// 8. findById returns null for empty id
ok('findById returns null for empty id', $client9->findById('') === null);

// ── Result ────────────────────────────────────────────────────────────────────

echo str_repeat('-', 42) . "\n";
echo "  {$pass} passed, {$fail} failed\n\n";

exit($fail > 0 ? 1 : 0);
