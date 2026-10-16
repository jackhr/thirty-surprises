-- 005_add_wordle_bonus_rounds.sql
-- Lets one day hold several games: round 1 is the daily word, rounds 2+ are "Another?" bonus words.
-- Safe to run more than once.

SET @has_round_number := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordle_games'
      AND COLUMN_NAME = 'round_number'
);

SET @add_round_number_sql := IF(
    @has_round_number = 0,
    'ALTER TABLE wordle_games ADD COLUMN round_number SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER puzzle_date',
    'SELECT 1'
);

PREPARE add_round_number_stmt FROM @add_round_number_sql;
EXECUTE add_round_number_stmt;
DEALLOCATE PREPARE add_round_number_stmt;

SET @has_date_round_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordle_games'
      AND INDEX_NAME = 'uq_wordle_games_date_round'
);

SET @add_date_round_idx_sql := IF(
    @has_date_round_idx = 0,
    'ALTER TABLE wordle_games ADD UNIQUE KEY uq_wordle_games_date_round (puzzle_date, round_number)',
    'SELECT 1'
);

PREPARE add_date_round_idx_stmt FROM @add_date_round_idx_sql;
EXECUTE add_date_round_idx_stmt;
DEALLOCATE PREPARE add_date_round_idx_stmt;

-- The old one-game-per-day key is named uq_wordle_games_puzzle_date when 004 created the table,
-- or puzzle_date when scripts/init-db.php did.
SET @has_daily_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordle_games'
      AND INDEX_NAME = 'uq_wordle_games_puzzle_date'
);

SET @drop_daily_idx_sql := IF(
    @has_daily_idx > 0,
    'ALTER TABLE wordle_games DROP INDEX uq_wordle_games_puzzle_date',
    'SELECT 1'
);

PREPARE drop_daily_idx_stmt FROM @drop_daily_idx_sql;
EXECUTE drop_daily_idx_stmt;
DEALLOCATE PREPARE drop_daily_idx_stmt;

SET @has_inline_daily_idx := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'wordle_games'
      AND INDEX_NAME = 'puzzle_date'
);

SET @drop_inline_daily_idx_sql := IF(
    @has_inline_daily_idx > 0,
    'ALTER TABLE wordle_games DROP INDEX puzzle_date',
    'SELECT 1'
);

PREPARE drop_inline_daily_idx_stmt FROM @drop_inline_daily_idx_sql;
EXECUTE drop_inline_daily_idx_stmt;
DEALLOCATE PREPARE drop_inline_daily_idx_stmt;
