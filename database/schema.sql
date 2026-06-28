-- AI Card Collector — database schema
-- Target: MySQL 8.x / MariaDB 10.4+
-- Usage: mysql -u root ai_card_collector < database/schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS wanted_cards;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    email         VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wanted_cards (
    id                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id             INT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    api_card_id         VARCHAR(50)  DEFAULT NULL,
    language            VARCHAR(100) NOT NULL,
    country             VARCHAR(100) DEFAULT NULL,
    target_price        DECIMAL(10,2) DEFAULT NULL,
    current_offer_price DECIMAL(10,2) DEFAULT NULL,
    purchase_price      DECIMAL(10,2) DEFAULT NULL,
    purchased_at        DATE         DEFAULT NULL,
    source_url          VARCHAR(500) DEFAULT NULL,
    seller_name         VARCHAR(255) DEFAULT NULL,
    status              ENUM(
                            'searching',
                            'contacted',
                            'offer_received',
                            'acquired',
                            'abandoned'
                        ) NOT NULL DEFAULT 'searching',
    difficulty_score    INT UNSIGNED NOT NULL DEFAULT 0,
    seller_contact      VARCHAR(255) DEFAULT NULL,
    notes               TEXT DEFAULT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_wanted_cards_user_id (user_id),
    KEY idx_wanted_cards_status (status),
    CONSTRAINT fk_wanted_cards_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_cache (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    cache_key     VARCHAR(255) NOT NULL,
    response_json MEDIUMTEXT   NOT NULL,
    fetched_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at    DATETIME     NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_cache_key (cache_key),
    KEY idx_api_cache_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
