-- AI Card Collector — demo cards for local development
--
-- Prerequisites (run in order before this file):
--   1. mysql -u root ai_card_collector < database/schema.sql
--   2. INSERT the local user account (see docs/deployment.md).
--   3. Run all migrations in database/migrations/ (001–004).
--
-- Usage:
--   mysql -u root ai_card_collector < database/demo-cards.sql
--
-- api_card_id values are verified against TCGdex API (June 2026).
-- Set ID for SV151 is "sv03.5" with zero-padded local numbers (sv03.5-006, not sv3pt5-6).
-- image_url uses TCGdex CDN /low.webp (30×42 px thumbnail); if a URL 404s the browser
-- falls back to the placeholder SVG — no broken-image icon.
-- "Odśwież ceny" will update market_price + image_url from TCGdex for all 7 active cards.
-- market_price is pre-filled so scoring is visible immediately before first refresh.
--
-- Score formula (max 155, terminal = 0):
--   language (JP/PT/ID=35, FR/DE/ES/KR=20, EN=0)
--   + status  (searching=40, contacted=25, offer_received=10, terminal=0)
--   + price   (offer>budget=25, budget_no_offer=15, no_data=8, within_budget=0)
--   + age     (floor(days/5), capped at 15)
--   + market  (target/market: <50%=40, 50-70%=30, 70-85%=20, 85-100%=10, >=100%=0)
--
-- Safe to commit — no passwords, no hashes, no credentials.

SET @uid = (SELECT id FROM users ORDER BY id LIMIT 1);

INSERT INTO wanted_cards
    (user_id, name, api_card_id, language, country,
     target_price, current_offer_price, purchase_price, purchased_at,
     seller_name, seller_contact, status, difficulty_score,
     market_price, market_price_at, image_url, notes, created_at)
VALUES

-- ── Active cards, sorted by expected score (desc) ────────────────────────────

-- 1. Charizard (SV151) — Japanese, searching, budżet dużo poniżej rynku
--    lang=35 + status=40 + price=15 + age=9 + market(<50%)=40 = 139
(@uid, 'Charizard', 'sv03.5-006', 'Japanese', 'Japan',
 45.00, NULL, NULL, NULL, NULL, NULL, 'searching', 139,
 120.00, NOW(),
 'https://assets.tcgdex.net/en/sv/sv03.5/006/low.webp',
 'Holo z serii 151. Rynek mocno w górę po World Championship.',
 NOW() - INTERVAL 45 DAY),

-- 2. Mew (SV151) — Japanese, searching, cena rynkowa 4× budżet
--    lang=35 + status=40 + price=15 + age=0 + market(<50%)=40 = 130
(@uid, 'Mew', 'sv03.5-151', 'Japanese', 'Japan',
 55.00, NULL, NULL, NULL, NULL, NULL, 'searching', 130,
 200.00, NOW(),
 'https://assets.tcgdex.net/en/sv/sv03.5/151/low.webp',
 'Karta #151 z serii 151. Rzadkość, mało ofert poza JP marketplace.',
 NOW() - INTERVAL 3 DAY),

-- 3. Pikachu (SV151) — Portuguese, searching, 20 dni w systemie
--    lang=35 + status=40 + price=15 + age=4 + market(50-70%)=30 = 124
(@uid, 'Pikachu', 'sv03.5-025', 'Portuguese', 'Brazil',
 25.00, NULL, NULL, NULL, NULL, NULL, 'searching', 124,
 35.00, NOW(),
 'https://assets.tcgdex.net/en/sv/sv03.5/025/low.webp',
 'Wersja brazylijska. Mała podaż na europejskim rynku.',
 NOW() - INTERVAL 20 DAY),

-- 4. Snorlax (SV151) — Korean, searching, 10 dni
--    lang=20 + status=40 + price=15 + age=2 + market(70-85%)=20 = 97
(@uid, 'Snorlax', 'sv03.5-143', 'Korean', 'South Korea',
 18.00, NULL, NULL, NULL, NULL, NULL, 'searching', 97,
 22.00, NOW(),
 'https://assets.tcgdex.net/en/sv/sv03.5/143/low.webp',
 'KR holo, niska podaż na Cardmarket.',
 NOW() - INTERVAL 10 DAY),

-- 5. Mewtwo (Base Set promo) — Japanese, oferta powyżej budżetu
--    lang=35 + status=10 + price=25 + age=6 + market(70-85%)=20 = 96
(@uid, 'Mewtwo', 'basep-3', 'Japanese', 'Japan',
 30.00, 38.00, NULL, NULL, 'tanaka_cards', 'tanaka@example.com', 'offer_received', 96,
 40.00, NOW(),
 'https://assets.tcgdex.net/en/base/basep/3/low.webp',
 'Sprzedawca proponuje 38 EUR. Negocjuję obniżkę.',
 NOW() - INTERVAL 30 DAY),

-- 6. Gengar (Base Set 2) — French, skontaktowany
--    lang=20 + status=25 + price=15 + age=5 + market(85-100%)=10 = 75
(@uid, 'Gengar', 'base3-5', 'French', 'France',
 35.00, NULL, NULL, NULL, 'carte_magic_fr', NULL, 'contacted', 75,
 38.00, NOW(),
 'https://assets.tcgdex.net/en/base/base3/5/low.webp',
 'Kontakt przez Cardmarket. Czekam na odpowiedź sprzedawcy.',
 NOW() - INTERVAL 25 DAY),

-- 7. Eevee (Base Set promo) — English, skontaktowany, budżet bliski rynku
--    lang=0 + status=25 + price=15 + age=3 + market(85-100%)=10 = 53
(@uid, 'Eevee', 'basep-11', 'English', 'UK',
 8.00, NULL, NULL, NULL, 'pokestore_uk', NULL, 'contacted', 53,
 9.00, NOW(),
 'https://assets.tcgdex.net/en/base/basep/11/low.webp',
 'Szukam egzemplarza NM/Mint.',
 NOW() - INTERVAL 15 DAY),

-- ── Terminal cards (score = 0) ────────────────────────────────────────────────

-- 8. Charizard (Platinum Arceus) — Japanese, zakupiona — terminal, score zawsze 0
(@uid, 'Charizard', 'pl4-1', 'Japanese', 'Japan',
 NULL, NULL, 85.00, '2026-06-15',
 'hiro_pokedealer', 'hiro@example.com', 'acquired', 0,
 NULL, NULL,
 'https://assets.tcgdex.net/en/pl/pl4/1/low.webp',
 'Kupione za 85 EUR. Stan NM. Świetna transakcja.',
 NOW() - INTERVAL 60 DAY);
