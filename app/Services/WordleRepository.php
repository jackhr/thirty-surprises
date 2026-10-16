<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Throwable;

final class WordleRepository
{
    public const MAX_ATTEMPTS = 6;
    public const DAILY_ROUND = 1;

    private const WORD_PATTERN = '/^[A-Z]{5}$/';

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByDate(string $date, int $round = self::DAILY_ROUND): ?array
    {
        $row = $this->findRow($date, $round);

        return $row !== null ? $this->mapRow($row) : null;
    }

    /**
     * Saves the full guess list for one round of a day. Round 1 is the daily word and rounds 2+ are
     * bonus words, which open one at a time once the daily word is finished.
     *
     * Guesses can only be appended: a list that is already
     * contained in the stored one is a stale retry and leaves the game as is, and anything that
     * rewrites stored guesses (or a different solution) comes back with conflict = true.
     *
     * @return array{game: array, conflict: bool}
     */
    public function saveProgress(string $date, int $round, string $solution, array $guesses): array
    {
        $solution = $this->normalizeWord($solution, 'Solution');
        $guesses = $this->normalizeGuesses($guesses, $solution);

        $this->db->beginTransaction();

        try {
            $existing = $this->findRow($date, $round);
            if ($existing === null) {
                $this->assertRoundCanStart($date, $round);
            }

            $now = gmdate('Y-m-d H:i:s');
            $status = $this->statusFor($guesses, $solution);
            $completedAt = $status === 'in_progress' ? null : $now;

            if ($existing === null) {
                $statement = $this->db->prepare(
                    'INSERT INTO wordle_games (puzzle_date, round_number, solution, guesses, attempts, status, completed_at, created_at, updated_at)
                     VALUES (:puzzle_date, :round_number, :solution, :guesses, :attempts, :status, :completed_at, :created_at, :updated_at)'
                );
                $statement->execute([
                    'puzzle_date' => $date,
                    'round_number' => $round,
                    'solution' => $solution,
                    'guesses' => json_encode($guesses),
                    'attempts' => count($guesses),
                    'status' => $status,
                    'completed_at' => $completedAt,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->db->commit();

                return ['game' => $this->findByDate($date, $round) ?? [], 'conflict' => false];
            }

            $stored = $this->decodeGuesses($existing['guesses'] ?? '[]');
            $isStaleRetry = array_slice($stored, 0, count($guesses)) === $guesses;
            $extendsStored = array_slice($guesses, 0, count($stored)) === $stored;
            $conflict = $existing['solution'] !== $solution || (!$isStaleRetry && !$extendsStored);

            if (!$conflict && !$isStaleRetry) {
                $statement = $this->db->prepare(
                    'UPDATE wordle_games
                     SET guesses = :guesses,
                         attempts = :attempts,
                         status = :status,
                         completed_at = :completed_at,
                         updated_at = :updated_at
                     WHERE id = :id'
                );
                $statement->execute([
                    'guesses' => json_encode($guesses),
                    'attempts' => count($guesses),
                    'status' => $status,
                    'completed_at' => $completedAt,
                    'updated_at' => $now,
                    'id' => (int) $existing['id'],
                ]);
            }

            $this->db->commit();

            return ['game' => $this->findByDate($date, $round) ?? [], 'conflict' => $conflict];
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    /**
     * Wordle-style statistics over finished daily games up to and including $today. The current
     * streak survives until a day is missed, so it still counts when today's game is unfinished.
     */
    public function stats(string $today): array
    {
        $statement = $this->db->prepare(
            'SELECT puzzle_date, status, attempts FROM wordle_games
             WHERE round_number = :round AND status IN (\'won\', \'lost\') AND puzzle_date <= :today
             ORDER BY puzzle_date ASC'
        );
        $statement->execute(['round' => self::DAILY_ROUND, 'today' => $today]);
        $rows = $statement->fetchAll() ?: [];

        ['wins' => $wins, 'distribution' => $distribution] = $this->tally($rows);
        $streak = 0;
        $maxStreak = 0;
        $previousDate = null;

        foreach ($rows as $row) {
            $date = (string) $row['puzzle_date'];

            if ($row['status'] === 'won') {
                $streak = $previousDate !== null && $this->shiftDate($previousDate, 1) === $date ? $streak + 1 : 1;
                $maxStreak = max($maxStreak, $streak);
            } else {
                $streak = 0;
            }

            $previousDate = $date;
        }

        $played = count($rows);
        $streakIsCurrent = $previousDate !== null && $previousDate >= $this->shiftDate($today, -1);

        return [
            'played' => $played,
            'wins' => $wins,
            'winPercentage' => $played > 0 ? (int) round($wins / $played * 100) : 0,
            'currentStreak' => $streakIsCurrent ? $streak : 0,
            'maxStreak' => $maxStreak,
            'guessDistribution' => $distribution,
        ];
    }

    /**
     * Bonus rounds for $date plus all-time bonus statistics (no streaks). `started` counts every
     * bonus game ever begun, which the client uses to pick the next bonus word.
     */
    public function bonusSummary(string $date): array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM wordle_games WHERE puzzle_date = :puzzle_date AND round_number > :round
             ORDER BY round_number DESC LIMIT 1'
        );
        $statement->execute(['puzzle_date' => $date, 'round' => self::DAILY_ROUND]);
        $latest = $statement->fetch() ?: null;

        $statement = $this->db->prepare('SELECT status, attempts FROM wordle_games WHERE round_number > :round');
        $statement->execute(['round' => self::DAILY_ROUND]);
        $rows = $statement->fetchAll() ?: [];
        $finished = array_values(array_filter($rows, fn (array $row): bool => $row['status'] !== 'in_progress'));
        ['wins' => $wins, 'distribution' => $distribution] = $this->tally($finished);
        $played = count($finished);

        return [
            'lastRound' => $latest !== null ? (int) $latest['round_number'] : self::DAILY_ROUND,
            'game' => $latest !== null ? $this->mapRow($latest) : null,
            'started' => count($rows),
            'stats' => [
                'played' => $played,
                'wins' => $wins,
                'winPercentage' => $played > 0 ? (int) round($wins / $played * 100) : 0,
                'guessDistribution' => $distribution,
            ],
        ];
    }

    /**
     * @return array{wins: int, distribution: int[]}
     */
    private function tally(array $rows): array
    {
        $wins = 0;
        $distribution = array_fill(0, self::MAX_ATTEMPTS, 0);

        foreach ($rows as $row) {
            if ($row['status'] !== 'won') {
                continue;
            }

            $wins++;
            $attempts = (int) $row['attempts'];
            if ($attempts >= 1 && $attempts <= self::MAX_ATTEMPTS) {
                $distribution[$attempts - 1]++;
            }
        }

        return ['wins' => $wins, 'distribution' => $distribution];
    }

    // A bonus round opens only after the daily word and every earlier round that day are finished.
    private function assertRoundCanStart(string $date, int $round): void
    {
        if ($round < self::DAILY_ROUND) {
            throw new InvalidArgumentException('Round must be 1 or higher.');
        }

        if ($round === self::DAILY_ROUND) {
            return;
        }

        $previous = $this->findRow($date, $round - 1);
        if ($previous === null || $previous['status'] === 'in_progress') {
            throw new InvalidArgumentException('Finish the current word before starting the next one.');
        }
    }

    private function findRow(string $date, int $round): ?array
    {
        $statement = $this->db->prepare(
            'SELECT * FROM wordle_games WHERE puzzle_date = :puzzle_date AND round_number = :round_number LIMIT 1'
        );
        $statement->execute(['puzzle_date' => $date, 'round_number' => $round]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    private function normalizeGuesses(array $guesses, string $solution): array
    {
        if ($guesses === []) {
            throw new InvalidArgumentException('At least one guess is required.');
        }

        if (count($guesses) > self::MAX_ATTEMPTS) {
            throw new InvalidArgumentException(sprintf('No more than %d guesses are allowed.', self::MAX_ATTEMPTS));
        }

        $normalized = [];
        foreach (array_values($guesses) as $guess) {
            if (in_array($solution, $normalized, true)) {
                throw new InvalidArgumentException('No guesses are allowed after the word is solved.');
            }

            $normalized[] = $this->normalizeWord($guess, 'Each guess');
        }

        return $normalized;
    }

    private function normalizeWord(mixed $word, string $label): string
    {
        $normalized = is_string($word) ? strtoupper(trim($word)) : '';
        if (preg_match(self::WORD_PATTERN, $normalized) !== 1) {
            throw new InvalidArgumentException(sprintf('%s must be five letters.', $label));
        }

        return $normalized;
    }

    private function statusFor(array $guesses, string $solution): string
    {
        if (end($guesses) === $solution) {
            return 'won';
        }

        return count($guesses) >= self::MAX_ATTEMPTS ? 'lost' : 'in_progress';
    }

    private function decodeGuesses(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    private function shiftDate(string $date, int $days): string
    {
        return (new DateTimeImmutable($date))->modify(sprintf('%+d day', $days))->format('Y-m-d');
    }

    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'date' => (string) $row['puzzle_date'],
            'round' => (int) ($row['round_number'] ?? self::DAILY_ROUND),
            'solution' => (string) $row['solution'],
            'guesses' => $this->decodeGuesses((string) ($row['guesses'] ?? '[]')),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'status' => (string) ($row['status'] ?? 'in_progress'),
            'completedAt' => $this->toIsoDate($row['completed_at'] ?? null),
            'createdAt' => $this->toIsoDate($row['created_at'] ?? null),
            'updatedAt' => $this->toIsoDate($row['updated_at'] ?? null),
        ];
    }

    private function toIsoDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->format('Y-m-d\\TH:i:s\\Z');
        } catch (Throwable) {
            return null;
        }
    }
}
