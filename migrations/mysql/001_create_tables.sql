-- 001_create_tables.sql
-- MySQL 8+ schema using standard SQL identifiers and relationships.

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reveal_date DATETIME NOT NULL,
    executed_date DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_notifications_reveal_date (reveal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS surprises (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    surprise_number INT NULL,
    magnitude ENUM('small', 'medium', 'large') NOT NULL DEFAULT 'medium',
    variety ENUM('cute', 'romantic', 'overdue', 'sweet', 'special', 'mystery') NOT NULL DEFAULT 'sweet',
    icon_class VARCHAR(255) NOT NULL,
    viewed TINYINT(1) NOT NULL DEFAULT 0,
    live TINYINT(1) NOT NULL DEFAULT 0,
    completed_at DATETIME NULL,
    reveal_date DATETIME NULL,
    notified_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_surprises_reveal_date (reveal_date),
    KEY idx_surprises_notified_at (notified_at),
    KEY idx_surprises_live (live),
    KEY idx_surprises_viewed (viewed),
    KEY idx_surprises_notification_id (notification_id),
    CONSTRAINT fk_surprises_notification
        FOREIGN KEY (notification_id)
        REFERENCES notifications(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
