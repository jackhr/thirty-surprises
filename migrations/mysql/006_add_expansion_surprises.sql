-- 006_add_expansion_surprises.sql
-- Adds "expansion" surprises: opening one adds extra_slots to the board.
-- The board size is base_surprise_slots (settings) + extra_slots of every live, viewed expansion surprise.
-- Safe to run more than once.

SET @has_type := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'surprises'
      AND COLUMN_NAME = 'type'
);

SET @add_type_sql := IF(
    @has_type = 0,
    'ALTER TABLE surprises ADD COLUMN type ENUM(''normal'', ''expansion'') NOT NULL DEFAULT ''normal'' AFTER variety',
    'SELECT 1'
);

PREPARE add_type_stmt FROM @add_type_sql;
EXECUTE add_type_stmt;
DEALLOCATE PREPARE add_type_stmt;

SET @has_extra_slots := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'surprises'
      AND COLUMN_NAME = 'extra_slots'
);

SET @add_extra_slots_sql := IF(
    @has_extra_slots = 0,
    'ALTER TABLE surprises ADD COLUMN extra_slots INT UNSIGNED NOT NULL DEFAULT 0 AFTER type',
    'SELECT 1'
);

PREPARE add_extra_slots_stmt FROM @add_extra_slots_sql;
EXECUTE add_extra_slots_stmt;
DEALLOCATE PREPARE add_extra_slots_stmt;

CREATE TABLE IF NOT EXISTS settings (
    name VARCHAR(100) NOT NULL,
    value VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (name, value, updated_at)
VALUES ('base_surprise_slots', '30', UTC_TIMESTAMP());
