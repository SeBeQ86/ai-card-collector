<?php

declare(strict_types=1);

namespace App\Card;

use PDO;

final class CardRepository
{
    public function __construct(private PDO $pdo) {}

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, language, country,
                    target_price, current_offer_price,
                    status, difficulty_score,
                    seller_contact, notes, created_at
             FROM   wanted_cards
             WHERE  user_id = ?
             ORDER  BY difficulty_score DESC, created_at ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findForUser(int $userId, int $cardId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, language, country,
                    target_price, current_offer_price,
                    status, difficulty_score,
                    seller_contact, notes, created_at
             FROM   wanted_cards
             WHERE  id = ? AND user_id = ?
             LIMIT  1'
        );
        $stmt->execute([$cardId, $userId]);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    public function updateForUser(int $userId, int $cardId, array $data): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE wanted_cards
             SET    name                = ?,
                    language            = ?,
                    country             = ?,
                    target_price        = ?,
                    current_offer_price = ?,
                    status              = ?,
                    seller_contact      = ?,
                    notes               = ?,
                    difficulty_score    = ?
             WHERE  id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['language'],
            $data['country'],
            $data['target_price'],
            $data['current_offer_price'],
            $data['status'],
            $data['seller_contact'],
            $data['notes'],
            $data['difficulty_score'],
            $cardId,
            $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function deleteForUser(int $userId, int $cardId): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM wanted_cards
             WHERE  id = ? AND user_id = ?'
        );
        $stmt->execute([$cardId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function createForUser(int $userId, array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO wanted_cards
                (user_id, name, language, country,
                 target_price, current_offer_price,
                 status, seller_contact, notes, difficulty_score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['name'],
            $data['language'],
            $data['country'],
            $data['target_price'],
            $data['current_offer_price'],
            $data['status'],
            $data['seller_contact'],
            $data['notes'],
            $data['difficulty_score'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
