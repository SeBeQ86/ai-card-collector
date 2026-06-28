<?php declare(strict_types=1);

namespace App\Card;

use PDO;

final class CardRepository
{
    public function __construct(private PDO $pdo) {}

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, api_card_id, language, country,
                    target_price, current_offer_price,
                    purchase_price, purchased_at, source_url, seller_name,
                    status, difficulty_score,
                    seller_contact, notes, image_url,
                    market_price, market_price_at,
                    created_at, updated_at
             FROM   wanted_cards
             WHERE  user_id = ?
             ORDER  BY difficulty_score DESC, created_at ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function listAcquiredForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, api_card_id, language, country,
                    target_price, purchase_price, purchased_at,
                    source_url, seller_name, seller_contact, notes, image_url, created_at
             FROM   wanted_cards
             WHERE  user_id = ? AND status = \'acquired\'
             ORDER  BY purchased_at DESC, created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function findForUser(int $userId, int $cardId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, api_card_id, language, country,
                    target_price, current_offer_price,
                    purchase_price, purchased_at, source_url, seller_name,
                    status, difficulty_score,
                    seller_contact, notes, image_url,
                    market_price, market_price_at,
                    created_at, updated_at
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
                    api_card_id         = ?,
                    language            = ?,
                    country             = ?,
                    target_price        = ?,
                    current_offer_price = ?,
                    purchase_price      = ?,
                    purchased_at        = ?,
                    source_url          = ?,
                    seller_name         = ?,
                    status              = ?,
                    seller_contact      = ?,
                    notes               = ?,
                    image_url           = ?,
                    difficulty_score    = ?
             WHERE  id = ? AND user_id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['api_card_id'],
            $data['language'],
            $data['country'],
            $data['target_price'],
            $data['current_offer_price'],
            $data['purchase_price'],
            $data['purchased_at'],
            $data['source_url'],
            $data['seller_name'],
            $data['status'],
            $data['seller_contact'],
            $data['notes'],
            $data['image_url'] ?? null,
            $data['difficulty_score'],
            $cardId,
            $userId,
        ]);
        return $stmt->rowCount() > 0;
    }

    /** Returns active (non-terminal) cards that have an api_card_id — used for price refresh. */
    public function listActiveWithApiId(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name, api_card_id, language, status,
                    target_price, current_offer_price, difficulty_score, created_at
             FROM   wanted_cards
             WHERE  user_id = ?
               AND  api_card_id IS NOT NULL
               AND  status NOT IN (\'acquired\', \'abandoned\')
             ORDER  BY id ASC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /** Update market_price, market_price_at, difficulty_score and image_url for one card.
     *  COALESCE guards: existing DB value is kept when the API returns null. */
    public function updateMarketPrice(int $userId, int $cardId, ?float $marketPrice, int $score, ?string $imageUrl = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE wanted_cards
             SET    market_price     = COALESCE(?, market_price),
                    market_price_at  = NOW(),
                    difficulty_score = ?,
                    image_url        = COALESCE(?, image_url)
             WHERE  id = ? AND user_id = ?'
        );
        $stmt->execute([$marketPrice, $score, $imageUrl, $cardId, $userId]);
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
                (user_id, name, api_card_id, language, country,
                 target_price, current_offer_price,
                 purchase_price, purchased_at, source_url, seller_name,
                 status, seller_contact, notes, image_url, difficulty_score)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $data['name'],
            $data['api_card_id'] ?? null,
            $data['language'],
            $data['country'],
            $data['target_price'],
            $data['current_offer_price'],
            $data['purchase_price'] ?? null,
            $data['purchased_at'] ?? null,
            $data['source_url'] ?? null,
            $data['seller_name'] ?? null,
            $data['status'],
            $data['seller_contact'],
            $data['notes'],
            $data['image_url'] ?? null,
            $data['difficulty_score'],
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}
