<?php declare(strict_types=1);

namespace App\Api;

use PDO;

final class ApiCache
{
    public function __construct(private PDO $pdo) {}

    /**
     * Returns cached data for $key if it exists and has not expired.
     * Returns null on miss or expiry.
     */
    public function get(string $key): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT response_json FROM api_cache
             WHERE  cache_key = ? AND expires_at > NOW()
             LIMIT  1'
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        $data = json_decode((string) $row['response_json'], true);
        return is_array($data) ? $data : null;
    }

    /**
     * Stores $data under $key with a TTL in seconds.
     * Uses INSERT … ON DUPLICATE KEY UPDATE so repeated calls are idempotent.
     */
    public function set(string $key, array $data, int $ttlSeconds = 86400): void
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        $stmt = $this->pdo->prepare(
            'INSERT INTO api_cache (cache_key, response_json, fetched_at, expires_at)
             VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
                 response_json = VALUES(response_json),
                 fetched_at    = NOW(),
                 expires_at    = VALUES(expires_at)'
        );
        $stmt->execute([$key, $json, $ttlSeconds]);
    }

    /**
     * Removes all rows whose TTL has passed.
     * Call occasionally (e.g. on each cache miss) to keep the table small.
     */
    public function purgeExpired(): void
    {
        $this->pdo->exec('DELETE FROM api_cache WHERE expires_at <= NOW()');
    }
}
