<?php declare(strict_types=1);

namespace App\Message;

final class SellerMessageGenerator
{
    /** @return array<string,string> locale => display label */
    public static function locales(): array
    {
        return [
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'es' => 'Español',
            'pt' => 'Português',
            'ja' => '日本語',
        ];
    }

    /**
     * Generate message for a card in a given locale.
     * If $customBody is provided (from DB), tokens are substituted into it.
     * Otherwise falls back to the built-in PHP template.
     */
    public static function generate(array $card, string $locale, ?string $customBody = null): string
    {
        if ($customBody !== null && trim($customBody) !== '') {
            return self::substituteTokens($customBody, $card);
        }

        return match ($locale) {
            'en' => self::english($card),
            'de' => self::german($card),
            'fr' => self::french($card),
            'es' => self::spanish($card),
            'pt' => self::portuguese($card),
            'ja' => self::japanese($card),
            default => throw new \InvalidArgumentException("Unsupported locale: {$locale}"),
        };
    }

    /**
     * Replace {{token}} placeholders with card data.
     *
     * Available tokens:
     *   {{name}}         — card name
     *   {{notes}}        — notes / grading (e.g. PSA 8), empty string if none
     *   {{language}}     — language edition
     *   {{country}}      — country / region, empty string if none
     *   {{target_price}} — budget formatted to 2 decimal places, or "—" if none
     *   {{offer_price}}  — current offer formatted to 2 decimal places, or "—" if none
     */
    public static function substituteTokens(string $body, array $card): string
    {
        $notes   = isset($card['notes']) && $card['notes'] !== null ? trim((string) $card['notes']) : '';
        $target  = isset($card['target_price'])         && $card['target_price']         !== null
            ? number_format((float) $card['target_price'],         2) : '—';
        $offer   = isset($card['current_offer_price'])  && $card['current_offer_price']  !== null
            ? number_format((float) $card['current_offer_price'],  2) : '—';

        return strtr($body, [
            '{{name}}'         => (string) ($card['name']     ?? ''),
            '{{notes}}'        => $notes,
            '{{language}}'     => (string) ($card['language'] ?? ''),
            '{{country}}'      => (string) ($card['country']  ?? ''),
            '{{target_price}}' => $target,
            '{{offer_price}}'  => $offer,
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private static function extract(array $card): array
    {
        return [
            'name'    => (string) $card['name'],
            'lang'    => (string) $card['language'],
            'country' => isset($card['country']) && $card['country'] !== null && $card['country'] !== ''
                ? (string) $card['country'] : '',
            'target'  => isset($card['target_price']) && $card['target_price'] !== null
                ? (float) $card['target_price'] : null,
            'offer'   => isset($card['current_offer_price']) && $card['current_offer_price'] !== null
                ? (float) $card['current_offer_price'] : null,
            'notes'   => isset($card['notes']) && $card['notes'] !== null && trim((string) $card['notes']) !== ''
                ? trim((string) $card['notes']) : null,
        ];
    }

    // ── built-in templates (fallback when DB has no custom body) ─────────────

    private static function english(array $card): string
    {
        ['name' => $name, 'lang' => $lang, 'country' => $country,
         'target' => $target, 'offer' => $offer, 'notes' => $notes] = self::extract($card);

        $countryStr = $country !== '' ? ", {$country}" : '';
        $notesStr   = $notes !== null ? " ({$notes})" : '';
        $edition    = "{$name}{$notesStr} — {$lang} edition{$countryStr}";

        $lines = ['Hello,', ''];
        if ($target !== null) {
            $lines[] = "I am looking to buy {$edition} and my budget is up to €" . number_format($target, 2) . '.';
        } else {
            $lines[] = "I am looking to buy {$edition}.";
        }
        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'I noticed it is currently listed at €' . number_format($offer, 2)
                . ', which is above my budget — please let me know if there is any flexibility on the price.';
        } elseif ($offer !== null) {
            $lines[] = 'I can see it is listed at €' . number_format($offer, 2) . ' and I am interested.';
        }
        $lines[] = '';
        $lines[] = 'Please let me know if you have this card available and we can arrange the purchase.';
        $lines[] = '';
        $lines[] = 'Thank you,';
        return implode("\n", $lines);
    }

    private static function german(array $card): string
    {
        ['name' => $name, 'lang' => $lang, 'country' => $country,
         'target' => $target, 'offer' => $offer, 'notes' => $notes] = self::extract($card);

        $countryStr = $country !== '' ? ", {$country}" : '';
        $notesStr   = $notes !== null ? " ({$notes})" : '';
        $edition    = "{$name}{$notesStr} — {$lang} Edition{$countryStr}";

        $lines = ['Hallo,', ''];
        if ($target !== null) {
            $lines[] = "Ich suche {$edition} und mein Budget beträgt bis zu €" . number_format($target, 2, ',', '.') . '.';
        } else {
            $lines[] = "Ich suche {$edition}.";
        }
        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'Der aktuelle Preis von €' . number_format($offer, 2, ',', '.') . ' liegt über meinem Budget — gibt es Spielraum beim Preis?';
        } elseif ($offer !== null) {
            $lines[] = 'Ich sehe, dass es für €' . number_format($offer, 2, ',', '.') . ' angeboten wird, und bin interessiert.';
        }
        $lines[] = '';
        $lines[] = 'Bitte teilen Sie mir mit, ob die Karte verfügbar ist.';
        $lines[] = '';
        $lines[] = 'Mit freundlichen Grüßen,';
        return implode("\n", $lines);
    }

    private static function french(array $card): string
    {
        ['name' => $name, 'lang' => $lang, 'country' => $country,
         'target' => $target, 'offer' => $offer, 'notes' => $notes] = self::extract($card);

        $countryStr = $country !== '' ? ", {$country}" : '';
        $notesStr   = $notes !== null ? " ({$notes})" : '';
        $edition    = "{$name}{$notesStr} — édition {$lang}{$countryStr}";

        $lines = ['Bonjour,', ''];
        if ($target !== null) {
            $lines[] = "Je recherche {$edition} avec un budget maximum de €" . number_format($target, 2, ',', ' ') . '.';
        } else {
            $lines[] = "Je recherche {$edition}.";
        }
        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'Le prix actuel de €' . number_format($offer, 2, ',', ' ') . ' dépasse mon budget — y a-t-il une possibilité de négocier ?';
        } elseif ($offer !== null) {
            $lines[] = 'Je vois qu\'il est proposé à €' . number_format($offer, 2, ',', ' ') . ' et je suis intéressé(e).';
        }
        $lines[] = '';
        $lines[] = 'Merci de me contacter si cette carte est disponible.';
        $lines[] = '';
        $lines[] = 'Cordialement,';
        return implode("\n", $lines);
    }

    private static function spanish(array $card): string
    {
        ['name' => $name, 'lang' => $lang, 'country' => $country,
         'target' => $target, 'offer' => $offer, 'notes' => $notes] = self::extract($card);

        $countryStr = $country !== '' ? ", {$country}" : '';
        $notesStr   = $notes !== null ? " ({$notes})" : '';
        $edition    = "{$name}{$notesStr} — edición {$lang}{$countryStr}";

        $lines = ['Hola,', ''];
        if ($target !== null) {
            $lines[] = "Estoy buscando {$edition} con un presupuesto de hasta €" . number_format($target, 2, ',', '.') . '.';
        } else {
            $lines[] = "Estoy buscando {$edition}.";
        }
        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'El precio actual de €' . number_format($offer, 2, ',', '.') . ' supera mi presupuesto — ¿hay posibilidad de negociar?';
        } elseif ($offer !== null) {
            $lines[] = 'Veo que está listado a €' . number_format($offer, 2, ',', '.') . ' y estoy interesado/a.';
        }
        $lines[] = '';
        $lines[] = 'Por favor, contácteme si tiene esta carta disponible.';
        $lines[] = '';
        $lines[] = 'Saludos,';
        return implode("\n", $lines);
    }

    private static function portuguese(array $card): string
    {
        ['name' => $name, 'lang' => $lang, 'country' => $country,
         'target' => $target, 'offer' => $offer, 'notes' => $notes] = self::extract($card);

        $countryStr = $country !== '' ? ", {$country}" : '';
        $notesStr   = $notes !== null ? " ({$notes})" : '';
        $edition    = "{$name}{$notesStr} — edição {$lang}{$countryStr}";

        $lines = ['Olá,', ''];
        if ($target !== null) {
            $lines[] = "Estou à procura de {$edition} e o meu orçamento é de até €" . number_format($target, 2, ',', '.') . '.';
        } else {
            $lines[] = "Estou à procura de {$edition}.";
        }
        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = 'Vi que está listado por €' . number_format($offer, 2, ',', '.')
                . ', o que está acima do meu orçamento — por favor, diga-me se há margem para negociar o preço.';
        } elseif ($offer !== null) {
            $lines[] = 'Vi que está listado por €' . number_format($offer, 2, ',', '.') . ' e tenho interesse.';
        }
        $lines[] = '';
        $lines[] = 'Por favor, entre em contato se tiver este card disponível para que possamos combinar a compra.';
        $lines[] = '';
        $lines[] = 'Obrigado,';
        return implode("\n", $lines);
    }

    private static function japanese(array $card): string
    {
        ['name' => $name, 'lang' => $lang,
         'target' => $target, 'offer' => $offer, 'notes' => $notes] = self::extract($card);

        $notesStr = $notes !== null ? "（{$notes}）" : '';

        $lines = ['はじめまして。', ''];
        if ($target !== null) {
            $lines[] = "{$name}{$notesStr}（{$lang}版）を探しています。予算は€" . number_format($target, 2) . 'までです。';
        } else {
            $lines[] = "{$name}{$notesStr}（{$lang}版）を探しています。";
        }
        if ($offer !== null && $target !== null && $offer > $target) {
            $lines[] = '現在の価格は予算を超えておりますが、価格交渉は可能でしょうか。';
        } elseif ($offer !== null) {
            $lines[] = '現在の価格を拝見しました。購入に興味があります。';
        }
        $lines[] = '';
        $lines[] = 'ご連絡をお待ちしております。よろしくお願いいたします。';
        return implode("\n", $lines);
    }
}
