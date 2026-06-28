<?php

declare(strict_types=1);

namespace App\Message;

final class SellerMessageGenerator
{
    public static function generate(array $card, string $locale): string
    {
        return match ($locale) {
            'en' => self::english($card),
            'pt' => self::portuguese($card),
            default => throw new \InvalidArgumentException("Unsupported locale: {$locale}"),
        };
    }

    private static function english(array $card): string
    {
        $name     = (string) $card['name'];
        $language = (string) $card['language'];
        $country  = isset($card['country']) && $card['country'] !== null && $card['country'] !== ''
            ? ', ' . $card['country']
            : '';
        $target   = isset($card['target_price']) && $card['target_price'] !== null
            ? (float) $card['target_price']
            : null;
        $offer    = isset($card['current_offer_price']) && $card['current_offer_price'] !== null
            ? (float) $card['current_offer_price']
            : null;
        $notes    = isset($card['notes']) && $card['notes'] !== null && trim((string) $card['notes']) !== ''
            ? trim((string) $card['notes'])
            : null;

        $lines   = [];
        $lines[] = 'Hello,';
        $lines[] = '';

        $edition = "{$name} ({$language} edition{$country})";

        if ($target !== null) {
            $lines[] = "I am looking to buy {$edition} and my budget is up to " . number_format($target, 2) . '.';
        } else {
            $lines[] = "I am looking to buy {$edition}.";
        }

        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'I noticed it is currently listed at ' . number_format($offer, 2)
                . ', which is above my budget — please let me know if there is any flexibility on the price.';
        } elseif ($offer !== null) {
            $lines[] = 'I can see it is listed at ' . number_format($offer, 2) . ' and I am interested.';
        }

        if ($notes !== null) {
            $lines[] = '';
            $lines[] = 'Additional details: ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Please let me know if you have this card available and we can arrange the purchase.';
        $lines[] = '';
        $lines[] = 'Thank you,';

        return implode("\n", $lines);
    }

    private static function portuguese(array $card): string
    {
        $name     = (string) $card['name'];
        $language = (string) $card['language'];
        $country  = isset($card['country']) && $card['country'] !== null && $card['country'] !== ''
            ? ', ' . $card['country']
            : '';
        $target   = isset($card['target_price']) && $card['target_price'] !== null
            ? (float) $card['target_price']
            : null;
        $offer    = isset($card['current_offer_price']) && $card['current_offer_price'] !== null
            ? (float) $card['current_offer_price']
            : null;
        $notes    = isset($card['notes']) && $card['notes'] !== null && trim((string) $card['notes']) !== ''
            ? trim((string) $card['notes'])
            : null;

        $lines   = [];
        $lines[] = 'Olá,';
        $lines[] = '';

        $edition = "{$name} (edição em {$language}{$country})";

        if ($target !== null) {
            $lines[] = "Estou à procura de {$edition} e o meu orçamento é de até " . number_format($target, 2, ',', '.') . '.';
        } else {
            $lines[] = "Estou à procura de {$edition}.";
        }

        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'Vi que está listado por ' . number_format($offer, 2, ',', '.')
                . ', o que está acima do meu orçamento — por favor, diga-me se há margem para negociar o preço.';
        } elseif ($offer !== null) {
            $lines[] = 'Vi que está listado por ' . number_format($offer, 2, ',', '.') . ' e tenho interesse.';
        }

        if ($notes !== null) {
            $lines[] = '';
            $lines[] = 'Detalhes adicionais: ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Por favor, entre em contato se tiver este card disponível para que possamos combinar a compra.';
        $lines[] = '';
        $lines[] = 'Obrigado,';

        return implode("\n", $lines);
    }
}
