-- Migration 003: api_card_id column on wanted_cards
-- Links a wanted card to an external API card ID (e.g. pokemontcg.io card ID).

ALTER TABLE wanted_cards
    ADD COLUMN IF NOT EXISTS api_card_id VARCHAR(50) DEFAULT NULL
        AFTER name;
