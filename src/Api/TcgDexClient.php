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
     * Search cards by name fragment OR card code (e.g. "PRE 013", "sv3pt5-197").
     * Returns up to 20 results: [{id, name, set, image_small, image_large}]
     */
    public function searchByName(string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        // Detect card-code patterns: "PRE 013", "M1 233", "sv3pt5-197"
        if ($this->looksLikeCardCode($query)) {
            return $this->searchByCode($query);
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
     * Search by card code like "PRE 013", "sv3pt5 197", or "pre-013".
     * Tries: direct ID lookup (set-num), then localId search.
     */
    public function searchByCode(string $query): array
    {
        $cacheKey = 'tcgdex:code:' . md5(strtolower($query));
        $cached   = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $results = [];

        // Normalize: "PRE 013" → "pre-013", "sv3pt5 197" → "sv3pt5-197"
        $normalized = strtolower(preg_replace('/\s+/', '-', trim($query)));

        // Try direct ID lookup first
        $byId = $this->findById($normalized);
        if ($byId !== null) {
            $results[] = $byId;
        }

        // If no hit or the code is just a number, search by localId
        if (empty($results)) {
            $localId = preg_replace('/^[^-\s]+-?/', '', $normalized);
            $localId = ltrim($localId, '-');
            if ($localId !== '' && is_numeric(ltrim($localId, '0') ?: '0')) {
                $url = self::BASE_URL . '/cards?localId=' . urlencode($localId);
                $raw = $this->fetch($url);
                if ($raw !== null) {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded)) {
                        $results = array_slice(
                            array_map([$this, 'normalizePartial'], $decoded),
                            0,
                            20
                        );
                    }
                }
            }
        }

        $this->cache->set($cacheKey, $results, self::TTL_SEARCH);
        return $results;
    }

    /** Returns true if query looks like a card code rather than a card name. */
    private function looksLikeCardCode(string $q): bool
    {
        // Matches: "PRE 013", "M1 233", "sv3pt5-197", "pre-013", "XY 25"
        return (bool) preg_match('/^[A-Za-z0-9]+[\s\-]\d{1,4}$/', $q);
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

    /** Normalize a full card object from single-card endpoint (includes set, rarity, pricing). */
    private function normalizeFull(array $card): array
    {
        $imageBase = (string) ($card['image'] ?? '');
        $cm        = $card['pricing']['cardmarket'] ?? [];
        return [
            'id'          => (string) ($card['id']          ?? ''),
            'name'        => (string) ($card['name']        ?? ''),
            'set'         => (string) ($card['set']['name'] ?? ''),
            'local_id'    => (string) ($card['localId']     ?? ''),
            'rarity'      => (string) ($card['rarity']      ?? ''),
            'image_small' => $imageBase !== '' ? $imageBase . '/low.webp'  : '',
            'image_large' => $imageBase !== '' ? $imageBase . '/high.webp' : '',
            'price_avg30' => isset($cm['avg30']) && $cm['avg30'] !== null ? (float) $cm['avg30'] : null,
            'price_trend' => isset($cm['trend']) && $cm['trend'] !== null ? (float) $cm['trend'] : null,
            'price_low'   => isset($cm['low'])   && $cm['low']   !== null ? (float) $cm['low']   : null,
        ];
    }

    protected function fetch(string $url): ?string
    {
        $isProd  = (getenv('APP_ENV') ?: 'local') === 'production';
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => 'Accept: application/json',
                'timeout'       => self::TIMEOUT,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => $isProd,
                'verify_peer_name' => $isProd,
            ],
        ]);

        $result = @file_get_contents($url, false, $context);
        return $result !== false ? $result : null;
    }
}
