-- Migration 002: api_cache table
-- Stores cached responses from external card APIs with TTL expiry.

CREATE TABLE IF NOT EXISTS api_cache (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key     VARCHAR(255) NOT NULL,
    response_json MEDIUMTEXT   NOT NULL,
    fetched_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_cache_key (cache_key),
    KEY idx_api_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
