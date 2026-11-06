<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class SettingsRepository
{
    public const DEFAULT_BASE_SURPRISE_SLOTS = 30;

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $statement = $this->db->prepare('SELECT value FROM settings WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);
        $value = $statement->fetchColumn();

        return $value === false ? $default : (string) $value;
    }

    /** Slots on the board before any expansion surprise has been opened. */
    public function getBaseSurpriseSlots(): int
    {
        $value = (int) $this->get('base_surprise_slots', (string) self::DEFAULT_BASE_SURPRISE_SLOTS);

        return $value > 0 ? $value : self::DEFAULT_BASE_SURPRISE_SLOTS;
    }
}
