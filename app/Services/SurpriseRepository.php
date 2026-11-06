<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use Throwable;

final class SurpriseRepository
{
    public const TYPE_NORMAL = 'normal';
    public const TYPE_EXPANSION = 'expansion';
    public const MAX_EXTRA_SLOTS = 100;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function getLiveSorted(): array
    {
        $statement = $this->db->query('SELECT * FROM surprises WHERE live = 1 ORDER BY (reveal_date IS NOT NULL), reveal_date ASC, id ASC');
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->mapRow($row), $rows ?: []);
    }

    public function getAllSorted(): array
    {
        $statement = $this->db->query('SELECT * FROM surprises ORDER BY (reveal_date IS NULL), reveal_date ASC, id ASC');
        $rows = $statement->fetchAll();

        return array_map(fn (array $row): array => $this->mapRow($row), $rows ?: []);
    }

    /**
     * Board size: the base slot count plus the slots unlocked by every live expansion surprise that has been opened.
     */
    public function getTotalSlots(): int
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(extra_slots), 0) FROM surprises WHERE type = :type AND live = 1 AND viewed = 1'
        );
        $statement->execute(['type' => self::TYPE_EXPANSION]);
        $extraSlots = (int) $statement->fetchColumn();

        return (new SettingsRepository())->getBaseSurpriseSlots() + $extraSlots;
    }

    public function findById(string|int $id): ?array
    {
        $statement = $this->db->prepare('SELECT * FROM surprises WHERE id = :id LIMIT 1');
        $statement->execute(['id' => (int) $id]);
        $row = $statement->fetch();

        return $row !== false ? $this->mapRow($row) : null;
    }

    public function create(array $data): array
    {
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $magnitude = trim((string) ($data['magnitude'] ?? 'medium'));
        $variety = trim((string) ($data['variety'] ?? 'sweet'));
        $iconClass = trim((string) ($data['iconClass'] ?? 'fa-solid fa-gift'));
        $revealDate = $this->parseDateInput($data['revealDate'] ?? null);
        [$type, $extraSlots] = $this->parseType($data['type'] ?? null, $data['extraSlots'] ?? null);

        $now = gmdate('Y-m-d H:i:s');

        $statement = $this->db->prepare(
            'INSERT INTO surprises (title, description, surprise_number, magnitude, variety, type, extra_slots, icon_class, viewed, live, completed_at, reveal_date, created_at, updated_at)
             VALUES (:title, :description, :surprise_number, :magnitude, :variety, :type, :extra_slots, :icon_class, :viewed, :live, :completed_at, :reveal_date, :created_at, :updated_at)'
        );

        $statement->execute([
            'title' => $title,
            'description' => $description,
            'surprise_number' => null,
            'magnitude' => $magnitude,
            'variety' => $variety,
            'type' => $type,
            'extra_slots' => $extraSlots,
            'icon_class' => $iconClass,
            'viewed' => 0,
            'live' => 0,
            'completed_at' => null,
            'reveal_date' => $revealDate,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $id = (int) $this->db->lastInsertId();

        return $this->findById($id) ?? [];
    }

    public function update(string|int $id, array $data): ?array
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return null;
        }

        $title = trim((string) ($data['title'] ?? $existing['title']));
        $description = trim((string) ($data['description'] ?? $existing['description']));
        $magnitude = trim((string) ($data['magnitude'] ?? $existing['magnitude']));
        $variety = trim((string) ($data['variety'] ?? $existing['variety']));
        $iconClass = trim((string) ($data['iconClass'] ?? $existing['iconClass']));
        [$type, $extraSlots] = $this->parseType(
            $data['type'] ?? $existing['type'],
            $data['extraSlots'] ?? $existing['extraSlots'],
        );

        $live = to_bool_int($data['live'] ?? $existing['live']);
        $viewed = to_bool_int($data['viewed'] ?? $existing['viewed']);

        $completed = is_true($data['completed'] ?? ($existing['completedAt'] !== null));
        if ($completed) {
            $completedAt = $existing['completedAt'] ? $this->toDatabaseDate($existing['completedAt']) : gmdate('Y-m-d H:i:s');
        } else {
            $completedAt = null;
        }

        if (array_key_exists('revealDate', $data)) {
            $revealDate = $this->parseDateInput($data['revealDate']);
        } else {
            $revealDate = $existing['revealDate'] ? $this->toDatabaseDate($existing['revealDate']) : null;
        }

        $statement = $this->db->prepare(
            'UPDATE surprises
             SET title = :title,
                 description = :description,
                 magnitude = :magnitude,
                 variety = :variety,
                 type = :type,
                 extra_slots = :extra_slots,
                 icon_class = :icon_class,
                 viewed = :viewed,
                 live = :live,
                 completed_at = :completed_at,
                 reveal_date = :reveal_date,
                 updated_at = :updated_at
             WHERE id = :id'
        );

        $statement->execute([
            'title' => $title,
            'description' => $description,
            'magnitude' => $magnitude,
            'variety' => $variety,
            'type' => $type,
            'extra_slots' => $extraSlots,
            'icon_class' => $iconClass,
            'viewed' => $viewed,
            'live' => $live,
            'completed_at' => $completedAt,
            'reveal_date' => $revealDate,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => (int) $id,
        ]);

        return $this->findById($id);
    }

    public function markViewed(string|int $id): ?array
    {
        $statement = $this->db->prepare('UPDATE surprises SET viewed = 1, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => (int) $id,
        ]);

        return $this->findById($id);
    }

    public function delete(string|int $id): ?array
    {
        $existing = $this->findById($id);
        if ($existing === null) {
            return null;
        }

        $statement = $this->db->prepare('DELETE FROM surprises WHERE id = :id');
        $statement->execute(['id' => (int) $id]);

        return $existing;
    }

    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'notificationId' => isset($row['notification_id']) ? (int) $row['notification_id'] : null,
            'title' => (string) ($row['title'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'number' => isset($row['surprise_number']) ? (int) $row['surprise_number'] : null,
            'magnitude' => (string) ($row['magnitude'] ?? 'medium'),
            'variety' => (string) ($row['variety'] ?? 'sweet'),
            'type' => (string) ($row['type'] ?? self::TYPE_NORMAL),
            'extraSlots' => (int) ($row['extra_slots'] ?? 0),
            'iconClass' => (string) ($row['icon_class'] ?? ''),
            'viewed' => (int) ($row['viewed'] ?? 0) === 1,
            'live' => (int) ($row['live'] ?? 0) === 1,
            'completedAt' => $this->toIsoDate($row['completed_at'] ?? null),
            'revealDate' => $this->toIsoDate($row['reveal_date'] ?? null),
            'notifiedAt' => $this->toIsoDate($row['notified_at'] ?? null),
            'createdAt' => $this->toIsoDate($row['created_at'] ?? null),
            'updatedAt' => $this->toIsoDate($row['updated_at'] ?? null),
        ];
    }

    /**
     * @return array{0: string, 1: int} The surprise type and how many slots it unlocks (always 0 for normal surprises).
     */
    private function parseType(mixed $type, mixed $extraSlots): array
    {
        if (trim((string) $type) !== self::TYPE_EXPANSION) {
            return [self::TYPE_NORMAL, 0];
        }

        $slots = filter_var($extraSlots, FILTER_VALIDATE_INT);
        if ($slots === false || $slots < 1 || $slots > self::MAX_EXTRA_SLOTS) {
            throw new InvalidArgumentException(sprintf(
                'A "more surprises" surprise needs between 1 and %d extra slots',
                self::MAX_EXTRA_SLOTS
            ));
        }

        return [self::TYPE_EXPANSION, $slots];
    }

    private function toIsoDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\\TH:i:s\\Z');
        } catch (Throwable) {
            return null;
        }
    }

    private function toDatabaseDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function parseDateInput(mixed $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $raw = trim((string) $input);
        if ($raw === '' || strtolower($raw) === 'invalid date') {
            return null;
        }

        $appTimezone = Env::get('APP_TIMEZONE', 'America/New_York') ?? 'America/New_York';

        try {
            if (preg_match('/(Z|[+-]\\d{2}:?\\d{2})$/', $raw) === 1) {
                $date = new DateTimeImmutable($raw);
            } else {
                $date = new DateTimeImmutable($raw, new DateTimeZone($appTimezone));
            }

            return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
