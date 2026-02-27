-- 003_add_surprise_notified_at.sql
-- Adds notification tracking for cron-delivered reveal emails.

SET @has_notified_at := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'surprises'
      AND COLUMN_NAME = 'notified_at'
);

SET @add_notified_at_sql := IF(
    @has_notified_at = 0,
    'ALTER TABLE surprises ADD COLUMN notified_at DATETIME NULL AFTER reveal_date',
    'SELECT 1'
);

PREPARE add_notified_at_stmt FROM @add_notified_at_sql;
EXECUTE add_notified_at_stmt;
DEALLOCATE PREPARE add_notified_at_stmt;

SET @has_notified_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'surprises'
      AND INDEX_NAME = 'idx_surprises_notified_at'
);

SET @add_notified_idx_sql := IF(
    @has_notified_idx = 0,
    'CREATE INDEX idx_surprises_notified_at ON surprises(notified_at)',
    'SELECT 1'
);

PREPARE add_notified_idx_stmt FROM @add_notified_idx_sql;
EXECUTE add_notified_idx_stmt;
DEALLOCATE PREPARE add_notified_idx_stmt;
