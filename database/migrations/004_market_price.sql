-- Migration 001: add market_price columns to wanted_cards
-- Adds Cardmarket price snapshot used by the price-refresh feature.
-- Safe to re-run: uses IF NOT EXISTS guard.

ALTER TABLE wanted_cards
    ADD COLUMN IF NOT EXISTS market_price    DECIMAL(10,2) DEFAULT NULL AFTER image_url,
    ADD COLUMN IF NOT EXISTS market_price_at DATETIME      DEFAULT NULL AFTER market_price;
