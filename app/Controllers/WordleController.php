<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Services\WordleRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

final class WordleController
{
    public function show(Request $request): void
    {
        try {
            $date = $this->puzzleDate($request);
            $repository = new WordleRepository();

            Response::json([
                'game' => $repository->findByDate($date),
                'stats' => $repository->stats($date),
                'bonus' => $repository->bonusSummary($date),
            ]);
        } catch (InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Logger::error('Wordle load failed', ['error' => $exception->getMessage()]);
            Response::json(['error' => $exception->getMessage()], 500);
        }
    }

    public function save(Request $request): void
    {
        try {
            $date = $this->puzzleDate($request);
            $guesses = $request->input('guesses', []);
            if (!is_array($guesses)) {
                throw new InvalidArgumentException('Guesses must be a list.');
            }

            $round = filter_var($request->input('round', WordleRepository::DAILY_ROUND), FILTER_VALIDATE_INT);
            if ($round === false || $round < WordleRepository::DAILY_ROUND || $round > 65535) {
                throw new InvalidArgumentException('Round must be a whole number of 1 or higher.');
            }

            $repository = new WordleRepository();
            $result = $repository->saveProgress($date, $round, (string) $request->input('solution', ''), $guesses);
            $payload = [
                'game' => $result['game'],
                'stats' => $repository->stats($date),
                'bonus' => $repository->bonusSummary($date),
            ];

            if ($result['conflict']) {
                $payload['error'] = 'This word already has different progress saved.';
                Response::json($payload, 409);
            }

            Response::json($payload);
        } catch (InvalidArgumentException $exception) {
            Response::json(['error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            Logger::error('Wordle save failed', ['error' => $exception->getMessage()]);
            Response::json(['error' => $exception->getMessage()], 500);
        }
    }

    // The date is the player's local calendar day, so allow one day ahead of the server for time zones.
    private function puzzleDate(Request $request): string
    {
        $raw = (string) $request->route('date', '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $raw);
        if ($date === false || $date->format('Y-m-d') !== $raw) {
            throw new InvalidArgumentException('Date must be formatted as YYYY-MM-DD.');
        }

        if ($date > new DateTimeImmutable('tomorrow')) {
            throw new InvalidArgumentException('That puzzle is not available yet.');
        }

        return $raw;
    }
}
