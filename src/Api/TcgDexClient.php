<?php declare(strict_types=1);

namespace App\Api;

/**
 * Minimal client for the TCGdex API (api.tcgdex.net v2).
 *
 * Free, no API key required.
 * Docs: https://api.tcgdex.net
 *
 * Cache TTL:
 *   - Search results: 6 hours
 *   - Single card:   24 hours
 */
class TcgDexClient
{
    private const BASE_URL   = 'https://api.tcgdex.net/v2/en';
    private const TTL_SEARCH = 21600;  // 6 h
    private const TTL_CARD   = 86400;  // 24 h
    private const TIMEOUT    = 5;

    public function __construct(private ApiCache $cache) {}

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

        $cacheKey = 'tcgdex:search:' . md5(strtolower($query));
        $cached   = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $this->cache->purgeExpired();

        $url = self::BASE_URL . '/cards?name=' . urlencode($query);
        $raw = $this->fetch($url);

        if ($raw === null) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $results = array_slice(
            array_map([$this, 'normalizePartial'], $decoded),
            0,
            20
        );

        $this->cache->set($cacheKey, $results, self::TTL_SEARCH);
        return $results;
    }

    /**
     * Fetch a single card by TCGdex ID (e.g. "base1-4").
     * Returns [id, name, set, image_small, image_large] or null on error.
     */
    public function findById(string $id): ?array
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        $cacheKey = 'tcgdex:card:' . $id;
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
        if (!is_array($decoded) || empty($decoded['id'])) {
            return null;
        }

        $result = $this->normalizeFull($decoded);
        $this->cache->set($cacheKey, $result, self::TTL_CARD);
        return $result;
    }

    // ── private ───────────────────────────────────────────────────────────────

    /** Normalize a partial card object from search results (no set info). */
    private function normalizePartial(array $card): array
    {
        $imageBase = (string) ($card['image'] ?? '');
        return [
            'id'          => (string) ($card['id']   ?? ''),
            'name'        => (string) ($card['name'] ?? ''),
            'set'         => '',
            'image_small' => $imageBase !== '' ? $imageBase . '/low.webp'  : '',
            'image_large' => $imageBase !== '' ? $imageBase . '/high.webp' : '',
        ];
    }

    /** Normalize a full card object from single-card endpoint (includes set). */
    private function normalizeFull(array $card): array
    {
        $imageBase = (string) ($card['image'] ?? '');
        return [
            'id'          => (string) ($card['id']            ?? ''),
            'name'        => (string) ($card['name']          ?? ''),
            'set'         => (string) ($card['set']['name']   ?? ''),
            'image_small' => $imageBase !== '' ? $imageBase . '/low.webp'  : '',
            'image_large' => $imageBase !== '' ? $imageBase . '/high.webp' : '',
        ];
    }

    protected function fetch(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Accept: application/json',
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result !== false ? $result : null;
    }
}
