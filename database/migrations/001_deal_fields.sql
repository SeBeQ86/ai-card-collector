-- Migration 001: deal fields for wanted_cards
-- Run once against an existing database to add purchase tracking columns.
-- Safe to run on a fresh install (schema.sql already contains these columns).

ALTER TABLE wanted_cards
    ADD COLUMN IF NOT EXISTS purchase_price DECIMAL(10,2) DEFAULT NULL
        AFTER current_offer_price,
    ADD COLUMN IF NOT EXISTS purchased_at   DATE          DEFAULT NULL
        AFTER purchase_price,
    ADD COLUMN IF NOT EXISTS source_url     VARCHAR(500)  DEFAULT NULL
        AFTER purchased_at,
    ADD COLUMN IF NOT EXISTS seller_name    VARCHAR(255)  DEFAULT NULL
        AFTER source_url;
