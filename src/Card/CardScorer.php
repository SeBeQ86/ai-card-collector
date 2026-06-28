<?php

declare(strict_types=1);

namespace App\Card;

final class CardScorer
{
    /**
     * Priority score components (active: 0–155, terminal: always 0):
     *
     * Status urgency   0–40  searching=40, contacted=25, offer_received=10, terminal=0
     * Language rarity  0–35  JP/TH/PT/ID=35, FR/DE/ES/KR/RU/PL/ZH=20, EN=0
     * Price pressure   0–25  offer>budget=25, budget set no offer=15, no data=8, within budget=0
     * Age urgency      0–15  +1 per 5 days unresolved, capped at 15
     * Market pressure  0–40  budget/market: ≥100%=0, 85–100%=+10, 70–85%=+20, 50–70%=+30, <50%=+40
     *
     * Terminal statuses (acquired, abandoned) always return 0.
     */
    public static function explain(array $card): array
    {
        $language    = (string) ($card['language'] ?? '');
        $status      = (string) ($card['status']   ?? 'searching');
        $target      = isset($card['target_price']) && $card['target_price'] !== null
            ? (float) $card['target_price'] : null;
        $offer       = isset($card['current_offer_price']) && $card['current_offer_price'] !== null
            ? (float) $card['current_offer_price'] : null;
        $marketPrice = isset($card['market_price']) && $card['market_price'] !== null
            ? (float) $card['market_price'] : null;

        $createdAt = (string) ($card['created_at'] ?? '');
        $createdTs = $createdAt !== '' ? strtotime($createdAt) : false;
        $ageInDays = $createdTs !== false
            ? (int) (((new \DateTimeImmutable())->getTimestamp() - $createdTs) / 86400)
            : 0;

        if (in_array($status, ['acquired', 'abandoned'], true)) {
            return ['language' => 0, 'status' => 0, 'price' => 0, 'age' => 0, 'market' => 0, 'total' => 0, 'terminal' => true];
        }

        $statusScore   = self::statusScore($status);
        $languageScore = self::languageScore($language);
        $priceScore    = self::priceScore($target, $offer);
        $ageScore      = min((int) ($ageInDays / 5), 15);
        $marketScore   = self::marketScore($target, $marketPrice);

        return [
            'language' => $languageScore,
            'status'   => $statusScore,
            'price'    => $priceScore,
            'age'      => $ageScore,
            'market'   => $marketScore,
            'total'    => $languageScore + $statusScore + $priceScore + $ageScore + $marketScore,
            'terminal' => false,
        ];
    }

    public static function calculate(
        string $language,
        string $status,
        ?float $targetPrice,
        ?float $currentOfferPrice,
        int $ageInDays = 0,
        ?float $marketPrice = null
    ): int {
        if (in_array($status, ['acquired', 'abandoned'], true)) {
            return 0;
        }

        $ageScore = min((int) ($ageInDays / 5), 15);

        return self::languageScore($language)
            + self::statusScore($status)
            + self::priceScore($targetPrice, $currentOfferPrice)
            + $ageScore
            + self::marketScore($targetPrice, $marketPrice);
    }

    /** Colored tier label for a score. */
    public static function tier(int $score): string
    {
        return match (true) {
            $score === 0       => 'none',
            $score <= 25       => 'low',
            $score <= 50       => 'medium',
            $score <= 75       => 'high',
            default            => 'critical',
        };
    }

    // ── private ───────────────────────────────────────────────────────────────

    private static function statusScore(string $status): int
    {
        return match ($status) {
            'searching'      => 40,
            'contacted'      => 25,
            'offer_received' => 10,
            default          => 0,
        };
    }

    private static function languageScore(string $language): int
    {
        return match (strtolower(trim($language))) {
            'japanese', 'thai', 'portuguese', 'indonesian' => 35,
            'french', 'german', 'spanish', 'korean',
            'russian', 'polish',
            'chinese (traditional)', 'chinese (simplified)' => 20,
            default => 0,   // English and unknown → no rarity penalty
        };
    }

    private static function priceScore(?float $targetPrice, ?float $currentOfferPrice): int
    {
        if ($currentOfferPrice !== null && $targetPrice !== null) {
            return $currentOfferPrice > $targetPrice ? 25 : 0;
        }
        if ($targetPrice !== null) {
            return 15;
        }
        return 8;
    }

    private static function marketScore(?float $targetPrice, ?float $marketPrice): int
    {
        if ($marketPrice === null || $targetPrice === null || $targetPrice <= 0 || $marketPrice <= 0) {
            return 0;
        }
        // Coverage: how much of the market price the budget covers (1.0 = at market, 0.5 = half market)
        $coverage = $targetPrice / $marketPrice;
        if ($coverage >= 1.00) return 0;   // budget at or above market — achievable
        if ($coverage >= 0.85) return 10;  // budget 85–100% of market — slightly tight
        if ($coverage >= 0.70) return 20;  // budget 70–85% — noticeably below market
        if ($coverage >= 0.50) return 30;  // budget 50–70% — hard to find at this price
        return 40;                         // budget below 50% of market — very hard
    }
}
