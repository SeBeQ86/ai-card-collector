-- AI Card Collector — development seed data
-- Usage: mysql -u root ai_card_collector < database/seed.sql
--
-- Demo login:
--   Email:    demo@example.local
--   Password: demo1234
--
-- Hash generated with: php -r "echo password_hash('demo1234', PASSWORD_BCRYPT, ['cost'=>10]);"
-- This is a DEVELOPMENT-ONLY throwaway credential. Never use in production.
--
-- Covers all 5 statuses and a variety of card types so every UI feature
-- is visible immediately after install.

SET NAMES utf8mb4;

-- ── User ─────────────────────────────────────────────────────────────────────

INSERT INTO users (email, password_hash) VALUES (
    'demo@example.local',
    '$2y$10$4a/Za12T/5HuFYJZ8Ekpwena6by8MVHIydQioje0RxDve8Yo9IJMq'
);

-- ── Wanted cards ─────────────────────────────────────────────────────────────
-- All cards belong to the demo user (id = 1 after the INSERT above).
-- difficulty_score is left at 0 — it will be recalculated on first save.

INSERT INTO wanted_cards
    (user_id, name, language, country, target_price, current_offer_price,
     purchase_price, purchased_at, source_url, seller_name,
     status, difficulty_score, seller_contact, notes)
VALUES

-- 1. Searching — rare Japanese promo, no offers yet, tight budget
(1, 'Charizard (Base Set)', 'Japanese', 'Japan',
 80.00, NULL, NULL, NULL, NULL, NULL,
 'searching', 0, NULL,
 'Looking for PSA 8 or better. Avoid shadowless fakes.'),

-- 2. Searching — no price ceiling set yet
(1, 'Black Lotus (Alpha)', 'Portuguese', 'Brazil',
 NULL, NULL, NULL, NULL, NULL, NULL,
 'searching', 0, NULL,
 NULL),

-- 3. Contacted — seller replied, waiting for photos
(1, 'Pikachu Illustrator', 'Japanese', 'Japan',
 2500.00, NULL, NULL, NULL, NULL, NULL,
 'contacted', 0, 'cardshop_tokyo',
 'Seller claims NM condition. Asked for scan of back.'),

-- 4. Offer received — offer is above budget, tests negotiation message
(1, 'Sprigatito (SV Promo)', 'English', 'Japan',
 10.00, 14.50, NULL, NULL, NULL, NULL,
 'offer_received', 0, 'collector_eu',
 'Price above budget — trying to negotiate.'),

-- 5. Offer received — offer matches budget, tests "fits budget" message
(1, 'Mewtwo ex (151)', 'French', 'France',
 35.00, 35.00, NULL, NULL, NULL, NULL,
 'offer_received', 0, 'paris_tcg',
 NULL),

-- 6. Acquired — completed hunt, price under budget; full deal data
(1, 'Umbreon VMAX (Alt Art)', 'Korean', NULL,
 120.00, 95.00, 95.00, '2026-06-10',
 'https://www.cardmarket.com/en/Pokemon/Products/Singles/Evolving-Skies/Umbreon-VMAX-Alternate-Art',
 'korean_cards_shop',
 'acquired', 0, 'korean_cards',
 'Arrived in perfect condition.'),

-- 7. Acquired — gift, no price data
(1, 'Tropical Wind (Trophy)', 'English', NULL,
 NULL, NULL, NULL, NULL, NULL, NULL,
 'acquired', 0, NULL,
 'Gift from a friend. Not for sale.'),

-- 8. Abandoned — market price never came down
(1, 'Shining Charizard (Neo Destiny)', 'German', 'Germany',
 200.00, 340.00, NULL, NULL, NULL, NULL,
 'abandoned', 0, NULL,
 'Market price too high. Will revisit in 6 months.');
