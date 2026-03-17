-- Blogy Assistant — Queue System Schema
-- Database: Bloggy_Assistant
-- Run this in phpMyAdmin or via: mysql -u root Bloggy_Assistant < schema.sql

CREATE TABLE IF NOT EXISTS `jobs` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `feed_url`    TEXT          NOT NULL,
    `article_url` TEXT          NOT NULL,
    `status`      ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
    `attempts`    INT           NOT NULL DEFAULT 0,
    `last_error`  TEXT          NULL,
    `created_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `articles` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `title`            VARCHAR(255)  NOT NULL,
    `content`          LONGTEXT      NULL,
    `excerpt`          TEXT          NULL,
    `focus_keyword`    VARCHAR(255)  NULL,
    `tags`             JSON          NULL,
    `source_url`       TEXT          NULL,
    `language`         VARCHAR(50)   NULL,
    `image`            VARCHAR(255)  NULL,
    `status`           VARCHAR(20)   NOT NULL DEFAULT 'published',
    `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `internal_links` (
    `id`      INT AUTO_INCREMENT PRIMARY KEY,
    `keyword` VARCHAR(255) NOT NULL,
    `url`     TEXT         NOT NULL,
    UNIQUE KEY `uq_keyword` (`keyword`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
