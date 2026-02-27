<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use PDO;
use Throwable;

final class SchemaService
{
    public function migrate(): void
    {
        $db = Database::connection();
        $driver = strtolower(Env::get('DB_DRIVER', 'mysql') ?? 'mysql');

        if ($driver === 'mysql') {
            $this->migrateMySql($db);
            return;
        }

        $this->migrateSqlite($db);
    }

    private function migrateMySql(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                reveal_date DATETIME NOT NULL,
                executed_date DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_notifications_reveal_date (reveal_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS surprises (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                notification_id BIGINT UNSIGNED NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                surprise_number INT NULL,
                magnitude ENUM("small","medium","large") NOT NULL DEFAULT "medium",
                variety ENUM("cute","romantic","overdue","sweet","special","mystery") NOT NULL DEFAULT "sweet",
                icon_class VARCHAR(255) NOT NULL,
                viewed TINYINT(1) NOT NULL DEFAULT 0,
                live TINYINT(1) NOT NULL DEFAULT 0,
                completed_at DATETIME NULL,
                reveal_date DATETIME NULL,
                notified_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                INDEX idx_surprises_reveal_date (reveal_date),
                INDEX idx_surprises_notified_at (notified_at),
                INDEX idx_surprises_live (live),
                INDEX idx_surprises_viewed (viewed),
                INDEX idx_surprises_notification_id (notification_id),
                CONSTRAINT fk_surprises_notification
                    FOREIGN KEY (notification_id)
                    REFERENCES notifications(id)
                ON UPDATE CASCADE
                ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        if (!$this->mysqlColumnExists($db, 'surprises', 'notified_at')) {
            $db->exec('ALTER TABLE surprises ADD COLUMN notified_at DATETIME NULL AFTER reveal_date');
        }

        if (!$this->mysqlIndexExists($db, 'surprises', 'idx_surprises_notified_at')) {
            $db->exec('CREATE INDEX idx_surprises_notified_at ON surprises(notified_at)');
        }
    }

    private function migrateSqlite(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reveal_date TEXT NOT NULL,
                executed_date TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS surprises (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                notification_id INTEGER NULL,
                title TEXT DEFAULT "",
                description TEXT,
                surprise_number INTEGER NULL,
                magnitude TEXT NOT NULL DEFAULT "medium",
                variety TEXT NOT NULL DEFAULT "sweet",
                icon_class TEXT NOT NULL,
                viewed INTEGER NOT NULL DEFAULT 0,
                live INTEGER NOT NULL DEFAULT 0,
                completed_at TEXT NULL,
                reveal_date TEXT NULL,
                notified_at TEXT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                FOREIGN KEY (notification_id) REFERENCES notifications(id) ON UPDATE CASCADE ON DELETE SET NULL
            )'
        );

        if (!$this->sqliteColumnExists($db, 'surprises', 'notified_at')) {
            $db->exec('ALTER TABLE surprises ADD COLUMN notified_at TEXT NULL');
        }

        $db->exec('CREATE INDEX IF NOT EXISTS idx_notifications_reveal_date ON notifications(reveal_date)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_surprises_reveal_date ON surprises(reveal_date)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_surprises_notified_at ON surprises(notified_at)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_surprises_notification_id ON surprises(notification_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_surprises_live ON surprises(live)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_surprises_viewed ON surprises(viewed)');
    }

    private function mysqlColumnExists(PDO $db, string $table, string $column): bool
    {
        try {
            $statement = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
            );
            $statement->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function mysqlIndexExists(PDO $db, string $table, string $index): bool
    {
        try {
            $statement = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
            );
            $statement->execute([
                'table_name' => $table,
                'index_name' => $index,
            ]);

            return (int) $statement->fetchColumn() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function sqliteColumnExists(PDO $db, string $table, string $column): bool
    {
        try {
            $statement = $db->query(sprintf('PRAGMA table_info(%s)', $table));
            $columns = $statement->fetchAll();

            foreach ($columns as $columnData) {
                if (($columnData['name'] ?? null) === $column) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }
}
