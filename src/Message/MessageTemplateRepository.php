<?php declare(strict_types=1);

namespace App\Message;

use PDO;

final class MessageTemplateRepository
{
    public function __construct(private PDO $pdo) {}

    /** Returns all templates keyed by locale. */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT locale, body FROM message_templates ORDER BY locale');
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /** Returns body for a single locale, or null if not set. */
    public function get(string $locale): ?string
    {
        $stmt = $this->pdo->prepare('SELECT body FROM message_templates WHERE locale = ? LIMIT 1');
        $stmt->execute([$locale]);
        $row = $stmt->fetchColumn();
        return $row !== false ? (string) $row : null;
    }

    /** Insert or update a template for a locale. */
    public function save(string $locale, string $body): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO message_templates (locale, body)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE body = VALUES(body), updated_at = NOW()'
        );
        $stmt->execute([$locale, $body]);
    }
}
