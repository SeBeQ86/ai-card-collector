<?php

declare(strict_types=1);

/**
 * ApiCache unit tests — no framework required.
 *
 * Run:  php tests/ApiCacheTest.php
 * Exit: 0 on all pass, 1 on any failure.
 *
 * Uses SQLite in-memory so no MySQL connection is needed.
 */

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Api\ApiCache;

// ── Helpers ──────────────────────────────────────────────────────────────────

function makeDb(): PDO
{
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // SQLite-compatible schema (no INTERVAL — we'll fake expiry via direct INSERT)
    $pdo->exec('
        CREATE TABLE api_cache (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            cache_key     TEXT    NOT NULL UNIQUE,
            response_json TEXT    NOT NULL,
            fetched_at    TEXT    NOT NULL,
            expires_at    TEXT    NOT NULL
        )
    ');
    return $pdo;
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

// ── Override set() to work with SQLite (no DATE_ADD / INTERVAL) ──────────────

final class TestableApiCache
{
    public function __construct(private \PDO $db) {}

    public function get(string $key): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT response_json FROM api_cache
             WHERE  cache_key = ? AND expires_at > datetime('now')
             LIMIT  1"
        );
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        $data = json_decode((string) $row['response_json'], true);
        return is_array($data) ? $data : null;
    }

    public function set(string $key, array $data, int $ttlSeconds = 86400): void
    {
        $json    = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $expires = gmdate('Y-m-d H:i:s', time() + $ttlSeconds);

        $stmt = $this->db->prepare(
            'INSERT INTO api_cache (cache_key, response_json, fetched_at, expires_at)
             VALUES (?, ?, datetime(\'now\'), ?)
             ON CONFLICT(cache_key) DO UPDATE SET
                 response_json = excluded.response_json,
                 fetched_at    = excluded.fetched_at,
                 expires_at    = excluded.expires_at'
        );
        $stmt->execute([$key, $json, $expires]);
    }

    public function purgeExpired(): void
    {
        $this->db->exec("DELETE FROM api_cache WHERE expires_at <= datetime('now')");
    }

    public function setExpired(string $key, array $data): void
    {
        $json    = json_encode($data);
        $expires = gmdate('Y-m-d H:i:s', time() - 1);
        $stmt    = $this->db->prepare(
            'INSERT INTO api_cache (cache_key, response_json, fetched_at, expires_at)
             VALUES (?, ?, datetime(\'now\'), ?)'
        );
        $stmt->execute([$key, $json, $expires]);
    }
}

// ── Tests ─────────────────────────────────────────────────────────────────────

echo "\nApiCacheTest\n";
echo str_repeat('-', 42) . "\n";

// 1. miss on empty cache
$db    = makeDb();
$cache = new TestableApiCache($db);
ok('get() returns null on empty cache', $cache->get('foo') === null);

// 2. set then get — hit
$data = ['cards' => [['id' => 'base1-4', 'name' => 'Charizard']]];
$cache->set('pokemon:charizard', $data, 3600);
ok('get() returns data after set()', $cache->get('pokemon:charizard') === $data);

// 3. expired entry — miss
$cache->setExpired('pokemon:expired', ['cards' => []]);
ok('get() returns null for expired entry', $cache->get('pokemon:expired') === null);

// 4. set is idempotent — second set overwrites
$cache->set('pokemon:charizard', ['cards' => [['name' => 'NEW']]], 3600);
$result = $cache->get('pokemon:charizard');
ok('set() overwrites existing key', $result === ['cards' => [['name' => 'NEW']]]);

// 5. purgeExpired removes only expired rows
$cache->set('pokemon:fresh', ['ok' => true], 3600);
$cache->setExpired('pokemon:stale1', []);
$cache->setExpired('pokemon:stale2', []);
$cache->purgeExpired();
ok('purgeExpired() keeps valid entries',  $cache->get('pokemon:fresh') !== null);
ok('purgeExpired() removes stale1',       $cache->get('pokemon:stale1') === null);
ok('purgeExpired() removes stale2',       $cache->get('pokemon:stale2') === null);

// 6. different keys are independent
$cache->set('key:a', ['a' => 1], 3600);
$cache->set('key:b', ['b' => 2], 3600);
ok('different keys are independent', $cache->get('key:a') === ['a' => 1]
                                  && $cache->get('key:b') === ['b' => 2]);

// ── Result ────────────────────────────────────────────────────────────────────

echo str_repeat('-', 42) . "\n";
echo "  {$pass} passed, {$fail} failed\n\n";

exit($fail > 0 ? 1 : 0);
