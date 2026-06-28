<?php declare(strict_types=1);

namespace App\Api;

/**
 * Minimal client for the Pokemon TCG API (pokemontcg.io v2).
 *
 * Uses file_get_contents with stream context — no Composer dependency.
 * All responses are cached via ApiCache to stay well within the free-tier
 * limit (20 000 req/day with API key, 1 000/day without).
 *
 * Cache TTL:
 *   - Card search results:  6 hours  (new printings are rare)
 *   - Single card by ID:   24 hours  (card data never changes)
 */
class PokemonTcgClient
{
    private const BASE_URL   = 'https://api.pokemontcg.io/v2';
    private const TTL_SEARCH = 21600;   // 6 h
    private const TTL_CARD   = 86400;   // 24 h
    private const TIMEOUT    = 5;

    public function __construct(
        private ApiCache $cache,
        private string   $apiKey = ''
    ) {}

    /**
     * Search cards by name fragment.
     * Returns up to 20 results: [{id, name, set, image_small, image_large}]
     */
    public function searchByName(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $cacheKey = 'pokemon:search:' . md5(strtolower($query));
        $cached   = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Purge stale entries opportunistically on cache miss
        $this->cache->purgeExpired();

        $url  = self::BASE_URL . '/cards?q=' . urlencode('name:"' . $query . '*"') . '&pageSize=20&select=id,name,set,images';
        $raw  = $this->fetch($url);

        if ($raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['data'])) {
            return [];
        }

        $results = array_map(static function (array $card): array {
            return [
                'id'          => (string) ($card['id']                        ?? ''),
                'name'        => (string) ($card['name']                      ?? ''),
                'set'         => (string) ($card['set']['name']               ?? ''),
                'image_small' => (string) ($card['images']['small']           ?? ''),
                'image_large' => (string) ($card['images']['large']           ?? ''),
            ];
        }, $decoded['data']);

        $this->cache->set($cacheKey, $results, self::TTL_SEARCH);
        return $results;
    }

    /**
     * Fetch a single card by its pokemontcg.io ID.
     * Returns [id, name, set, image_small, image_large] or null on miss/error.
     */
    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $cacheKey = 'pokemon:card:' . $id;
        $cached   = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $url = self::BASE_URL . '/cards/' . urlencode($id);
        $raw = $this->fetch($url);

        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['data'])) {
            return null;
        }

        $card = $decoded['data'];
        $result = [
            'id'          => (string) ($card['id']             ?? ''),
            'name'        => (string) ($card['name']           ?? ''),
            'set'         => (string) ($card['set']['name']    ?? ''),
            'image_small' => (string) ($card['images']['small'] ?? ''),
            'image_large' => (string) ($card['images']['large'] ?? ''),
        ];

        $this->cache->set($cacheKey, $result, self::TTL_CARD);
        return $result;
    }

    // ── private ───────────────────────────────────────────────────────────────

    protected function fetch(string $url): ?string
    {
        $headers = ['Accept: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'X-Api-Key: ' . $this->apiKey;
        }

        $context = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => implode("\r\n", $headers),
                'timeout'         => self::TIMEOUT,
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'     => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result !== false ? $result : null;
    }
}
