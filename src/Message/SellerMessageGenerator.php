<?php declare(strict_types=1);

namespace App\Message;

final class SellerMessageGenerator
{
    /** @return array<string,string> locale => label */
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

    public static function generate(array $card, string $locale): string
    {
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

    // ── helpers ──────────────────────────────────────────────────────────────

    private static function edition(string $name, string $language, string $country = ''): string
    {
        $suffix = $country !== '' ? ", {$country}" : '';
        return "{$name} ({$language} edition{$suffix})";
    }

    private static function fmt(float $v, string $locale = 'en'): string
    {
        return match ($locale) {
            'de', 'fr', 'es', 'pt' => number_format($v, 2, ',', '.'),
            default                 => number_format($v, 2),
        };
    }

    private static function extract(array $card): array
    {
        return [
            'name'    => (string) $card['name'],
            'lang'    => (string) $card['language'],
            'country' => (string) ($card['country'] ?? ''),
            'target'  => isset($card['target_price']) && $card['target_price'] !== null
                ? (float) $card['target_price'] : null,
            'offer'   => isset($card['current_offer_price']) && $card['current_offer_price'] !== null
                ? (float) $card['current_offer_price'] : null,
            'notes'   => isset($card['notes']) && trim((string) $card['notes']) !== ''
                ? trim((string) $card['notes']) : null,
        ];
    }

    // ── templates ────────────────────────────────────────────────────────────

    private static function english(array $card): string
    {
        ['name' => $n, 'lang' => $l, 'country' => $c,
         'target' => $t, 'offer' => $o, 'notes' => $notes] = self::extract($card);
        $ed = self::edition($n, $l, $c);

        $lines   = ['Hello,', ''];
        $lines[] = $t !== null
            ? "I am looking to buy {$ed} and my budget is up to " . self::fmt($t) . ' €.'
            : "I am looking to buy {$ed}.";

        if ($o !== null && $t !== null && $o > $t) {
            $lines[] = 'I noticed it is currently listed at ' . self::fmt($o)
                . ' €, which is above my budget — please let me know if there is any flexibility on the price.';
        } elseif ($o !== null && $t !== null && $o <= $t) {
            $lines[] = 'The listed price of ' . self::fmt($o) . ' € fits my budget perfectly — I would like to proceed with the purchase.';
        } elseif ($o !== null) {
            $lines[] = 'I can see it is listed at ' . self::fmt($o) . ' € and I am interested.';
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

    private static function german(array $card): string
    {
        ['name' => $n, 'lang' => $l, 'country' => $c,
         'target' => $t, 'offer' => $o, 'notes' => $notes] = self::extract($card);
        $suffix = $c !== '' ? ", {$c}" : '';
        $ed = "{$n} ({$l} Ausgabe{$suffix})";

        $lines   = ['Hallo,', ''];
        $lines[] = $t !== null
            ? "Ich suche {$ed} und mein Budget beträgt bis zu " . self::fmt($t, 'de') . ' €.'
            : "Ich suche {$ed}.";

        if ($o !== null && $t !== null && $o > $t) {
            $lines[] = 'Ich habe gesehen, dass es derzeit für ' . self::fmt($o, 'de')
                . ' € angeboten wird, was über meinem Budget liegt — bitte teilen Sie mir mit, ob es Spielraum beim Preis gibt.';
        } elseif ($o !== null && $t !== null && $o <= $t) {
            $lines[] = 'Der Preis von ' . self::fmt($o, 'de') . ' € passt genau zu meinem Budget — ich würde den Kauf gerne abschließen.';
        } elseif ($o !== null) {
            $lines[] = 'Ich sehe, dass es für ' . self::fmt($o, 'de') . ' € angeboten wird, und ich bin interessiert.';
        }

        if ($notes !== null) {
            $lines[] = '';
            $lines[] = 'Zusätzliche Details: ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Bitte lassen Sie mich wissen, ob Sie diese Karte verfügbar haben, damit wir den Kauf arrangieren können.';
        $lines[] = '';
        $lines[] = 'Mit freundlichen Grüßen,';
        return implode("\n", $lines);
    }

    private static function french(array $card): string
    {
        ['name' => $n, 'lang' => $l, 'country' => $c,
         'target' => $t, 'offer' => $o, 'notes' => $notes] = self::extract($card);
        $suffix = $c !== '' ? ", {$c}" : '';
        $ed = "{$n} (édition {$l}{$suffix})";

        $lines   = ['Bonjour,', ''];
        $lines[] = $t !== null
            ? "Je recherche {$ed} et mon budget est de " . self::fmt($t, 'fr') . ' €.'
            : "Je recherche {$ed}.";

        if ($o !== null && $t !== null && $o > $t) {
            $lines[] = "J'ai remarqué qu'il est actuellement proposé à " . self::fmt($o, 'fr')
                . ' €, ce qui dépasse mon budget — pourriez-vous me faire savoir s\'il y a une possibilité de négocier le prix ?';
        } elseif ($o !== null && $t !== null && $o <= $t) {
            $lines[] = 'Le prix de ' . self::fmt($o, 'fr') . ' € correspond parfaitement à mon budget — je souhaite procéder à l\'achat.';
        } elseif ($o !== null) {
            $lines[] = "Je vois qu'il est proposé à " . self::fmt($o, 'fr') . ' € et je suis intéressé(e).';
        }

        if ($notes !== null) {
            $lines[] = '';
            $lines[] = 'Informations complémentaires : ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Pourriez-vous me faire savoir si vous avez cette carte disponible afin que nous puissions organiser l\'achat ?';
        $lines[] = '';
        $lines[] = 'Cordialement,';
        return implode("\n", $lines);
    }

    private static function spanish(array $card): string
    {
        ['name' => $n, 'lang' => $l, 'country' => $c,
         'target' => $t, 'offer' => $o, 'notes' => $notes] = self::extract($card);
        $suffix = $c !== '' ? ", {$c}" : '';
        $ed = "{$n} (edición en {$l}{$suffix})";

        $lines   = ['Hola,', ''];
        $lines[] = $t !== null
            ? "Estoy buscando {$ed} y mi presupuesto es de hasta " . self::fmt($t, 'es') . ' €.'
            : "Estoy buscando {$ed}.";

        if ($o !== null && $t !== null && $o > $t) {
            $lines[] = 'He visto que actualmente está listado a ' . self::fmt($o, 'es')
                . ' €, lo cual supera mi presupuesto — por favor, hágame saber si hay posibilidad de negociar el precio.';
        } elseif ($o !== null && $t !== null && $o <= $t) {
            $lines[] = 'El precio de ' . self::fmt($o, 'es') . ' € encaja perfectamente con mi presupuesto — me gustaría proceder con la compra.';
        } elseif ($o !== null) {
            $lines[] = 'Veo que está listado a ' . self::fmt($o, 'es') . ' € y estoy interesado/a.';
        }

        if ($notes !== null) {
            $lines[] = '';
            $lines[] = 'Detalles adicionales: ' . $notes;
        }

        $lines[] = '';
        $lines[] = 'Por favor, hágame saber si tiene esta carta disponible para que podamos acordar la compra.';
        $lines[] = '';
        $lines[] = 'Gracias,';
        return implode("\n", $lines);
    }

    private static function portuguese(array $card): string
    {
        ['name' => $n, 'lang' => $l, 'country' => $c,
         'target' => $t, 'offer' => $o, 'notes' => $notes] = self::extract($card);
        $suffix = $c !== '' ? ", {$c}" : '';
        $ed = "{$n} (edição em {$l}{$suffix})";

        $lines   = ['Olá,', ''];
        $lines[] = $t !== null
            ? "Estou à procura de {$ed} e o meu orçamento é de até " . self::fmt($t, 'pt') . ' €.'
            : "Estou à procura de {$ed}.";

        if ($o !== null && $t !== null && $o > $t) {
            $lines[] = 'Vi que está listado por ' . self::fmt($o, 'pt')
                . ' €, o que está acima do meu orçamento — por favor, diga-me se há margem para negociar o preço.';
        } elseif ($o !== null && $t !== null && $o <= $t) {
            $lines[] = 'O preço de ' . self::fmt($o, 'pt') . ' € está dentro do meu orçamento — gostaria de prosseguir com a compra.';
        } elseif ($o !== null) {
            $lines[] = 'Vi que está listado por ' . self::fmt($o, 'pt') . ' € e tenho interesse.';
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

    private static function japanese(array $card): string
    {
        ['name' => $n, 'lang' => $l, 'country' => $c,
         'target' => $t, 'offer' => $o, 'notes' => $notes] = self::extract($card);
        $suffix = $c !== '' ? "・{$c}" : '';
        $ed = "{$n}（{$l}版{$suffix}）";

        $lines   = ['こんにちは、', ''];
        $lines[] = $t !== null
            ? "{$ed}を探しています。予算は" . self::fmt($t) . ' €までです。'
            : "{$ed}を探しています。";

        if ($o !== null && $t !== null && $o > $t) {
            $lines[] = '現在' . self::fmt($o) . ' €で出品されているのを確認しました。予算を超えておりますが、価格交渉は可能でしょうか？';
        } elseif ($o !== null && $t !== null && $o <= $t) {
            $lines[] = '出品価格の' . self::fmt($o) . ' €は予算内です。ぜひ購入させていただきたいと思います。';
        } elseif ($o !== null) {
            $lines[] = '現在' . self::fmt($o) . ' €で出品されているのを確認しました。購入を希望しています。';
        }

        if ($notes !== null) {
            $lines[] = '';
            $lines[] = '備考：' . $notes;
        }

        $lines[] = '';
        $lines[] = 'このカードをお持ちでしたら、ぜひご連絡ください。よろしくお願いいたします。';
        $lines[] = '';
        $lines[] = 'よろしくお願いいたします、';
        return implode("\n", $lines);
    }
}
