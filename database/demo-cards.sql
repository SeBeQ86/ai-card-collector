-- AI Card Collector — demo cards for local development
--
-- Prerequisites (run in order before this file):
--   1. mysql -u root ai_card_collector < database/schema.sql
--   2. INSERT the local user account (see docs/deployment.md or README.md).
--
-- Usage:
--   mysql -u root ai_card_collector < database/demo-cards.sql
--
-- This file contains no passwords or password hashes.
-- Safe to commit; do not use on a production database.

SET @user_id = (SELECT id FROM users WHERE email = 'sebastian@example.local' LIMIT 1);

INSERT INTO wanted_cards
    (user_id, name, language, country,
     target_price, current_offer_price,
     status, seller_contact, notes, difficulty_score)
VALUES
    -- Non-English, searching, target set → high score (40+40+5+0 = 85)
    (@user_id, 'Black Lotus', 'Japanese', 'Japan',
     500.00, NULL,
     'searching', NULL, 'Alpha print preferred', 85),

    -- Non-English, contacted, offer exceeds budget → high score (40+30+10+0 = 80)
    (@user_id, 'Mox Ruby', 'Japanese', 'Japan',
     300.00, 350.00,
     'contacted', 'seller@example.com', 'Seller is asking above my limit', 80),

    -- Non-English, offer received, offer within budget → mid score (40+10+0+0 = 50)
    (@user_id, 'Lightning Bolt', 'Portuguese', 'Brazil',
     5.00, 4.00,
     'offer_received', NULL, NULL, 50),

    -- English, searching, target set → lower score (0+40+5+0 = 45)
    (@user_id, 'Ancestral Recall', 'English', NULL,
     1000.00, NULL,
     'searching', NULL, NULL, 45),

    -- Terminal status → score 0
    (@user_id, 'Forest (7th Edition)', 'English', NULL,
     NULL, NULL,
     'acquired', NULL, 'Found it at a local shop', 0);
