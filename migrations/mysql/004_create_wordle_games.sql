-- 004_create_wordle_games.sql
-- Stores one Ash Wordle game per calendar day: the answer, every guess, and how many attempts it took.

CREATE TABLE IF NOT EXISTS wordle_games (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    puzzle_date DATE NOT NULL,
    solution CHAR(5) NOT NULL,
    guesses JSON NOT NULL,
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status ENUM('in_progress', 'won', 'lost') NOT NULL DEFAULT 'in_progress',
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_wordle_games_puzzle_date (puzzle_date),
    KEY idx_wordle_games_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
