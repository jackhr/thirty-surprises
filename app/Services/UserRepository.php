<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;

final class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function findByName(string $name): ?array
    {
        $statement = $this->db->prepare('SELECT id, name, password FROM users WHERE name = :name LIMIT 1');
        $statement->execute(['name' => $name]);
        $row = $statement->fetch();

        return $row !== false ? $row : null;
    }

    public function create(string $name, string $passwordHash): array
    {
        $statement = $this->db->prepare('INSERT INTO users (name, password, created_at, updated_at) VALUES (:name, :password, :created_at, :updated_at)');
        $now = gmdate('Y-m-d H:i:s');
        $statement->execute([
            'name' => $name,
            'password' => $passwordHash,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => (int) $this->db->lastInsertId(),
            'name' => $name,
        ];
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $statement = $this->db->prepare('UPDATE users SET password = :password, updated_at = :updated_at WHERE id = :id');
        $statement->execute([
            'password' => $passwordHash,
            'updated_at' => gmdate('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }
}
