<?php

declare(strict_types=1);

namespace App\Card;

final class CardScorer
{
    /**
     * Difficulty score components and weights:
     *
     * Language rarity  0–40 pts  Non-English editions score 40; English (en/english) scores 0.
     * Status urgency   0–40 pts  searching=40, contacted=30, offer_received=10, acquired/abandoned=0.
     * Price pressure   0–10 pts  Offer > target=10; target set, no offer=5; no pricing data=3; within budget=0.
     * Age urgency      0–10 pts  1 point per full week since card was added, capped at 10.
     *
     * Maximum possible score: 100.
     * Terminal statuses (acquired, abandoned) always return 0.
     */
    /**
     * Return the individual score components for a card row from the DB.
     * Keys: language, status, price, age (int each), total (int), terminal (bool).
     */
    public static function explain(array $card): array
    {
        $language  = (string) ($card['language'] ?? '');
        $status    = (string) ($card['status']   ?? 'searching');
        $target    = isset($card['target_price']) && $card['target_price'] !== null
            ? (float) $card['target_price'] : null;
        $offer     = isset($card['current_offer_price']) && $card['current_offer_price'] !== null
            ? (float) $card['current_offer_price'] : null;

        $createdAt = (string) ($card['created_at'] ?? '');
        $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
        $ageInDays = $createdTs !== false
            ? (int) (((new \DateTimeImmutable())->getTimestamp() - $createdTs) / 86400)
            : 0;

        if (in_array($status, ['acquired', 'abandoned'], true)) {
            return ['language' => 0, 'status' => 0, 'price' => 0, 'age' => 0, 'total' => 0, 'terminal' => true];
        }

        $statusScores  = ['searching' => 40, 'contacted' => 30, 'offer_received' => 10];
        $languageScore = self::languageScore($language);
        $statusScore   = $statusScores[$status] ?? 0;
        $priceScore    = self::priceScore($target, $offer);
        $ageScore      = min((int) ($ageInDays / 7), 10);

        return [
            'language' => $languageScore,
            'status'   => $statusScore,
            'price'    => $priceScore,
            'age'      => $ageScore,
            'total'    => $languageScore + $statusScore + $priceScore + $ageScore,
            'terminal' => false,
        ];
    }

    public static function calculate(
        string $language,
        string $status,
        ?float $targetPrice,
        ?float $currentOfferPrice,
        int $ageInDays = 0
    ): int {
        if (in_array($status, ['acquired', 'abandoned'], true)) {
            return 0;
        }

        $statusScores = [
            'searching'      => 40,
            'contacted'      => 30,
            'offer_received' => 10,
        ];

        $languageScore = self::languageScore($language);
        $statusScore   = $statusScores[$status] ?? 0;
        $priceScore    = self::priceScore($targetPrice, $currentOfferPrice);
        $ageScore      = min((int) ($ageInDays / 7), 10);

        return $languageScore + $statusScore + $priceScore + $ageScore;
    }

    private static function languageScore(string $language): int
    {
        $normalized = strtolower(trim($language));

        if ($normalized === 'english' || $normalized === 'en') {
            return 0;
        }

        return 40;
    }

    private static function priceScore(?float $targetPrice, ?float $currentOfferPrice): int
    {
        if ($currentOfferPrice !== null && $targetPrice !== null) {
            // Known offer exceeds budget: acquisition is blocked on price
            return $currentOfferPrice > $targetPrice ? 10 : 0;
        }

        if ($targetPrice !== null) {
            // Budget defined but no offer seen yet: moderate uncertainty
            return 5;
        }

        // No pricing data at all
        return 3;
    }
}
