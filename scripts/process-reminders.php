<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Logger;
use App\Services\EmailService;

require dirname(__DIR__) . '/config/bootstrap.php';

$db = Database::connection();
$emailService = new EmailService();

$driver = strtolower((string) $db->getAttribute(PDO::ATTR_DRIVER_NAME));
if ($driver === 'mysql') {
    try {
        $columnCheck = $db->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'surprises'
               AND COLUMN_NAME = 'notified_at'"
        );

        if ((int) $columnCheck->fetchColumn() === 0) {
            $db->exec('ALTER TABLE surprises ADD COLUMN notified_at DATETIME NULL AFTER reveal_date');
            $db->exec('CREATE INDEX idx_surprises_notified_at ON surprises(notified_at)');
            Logger::info('Auto-added surprises.notified_at for reminders script');
        }
    } catch (Throwable $exception) {
        Logger::error('Could not ensure reminders schema', ['error' => $exception->getMessage()]);
    }
}

$nowUtc = gmdate('Y-m-d H:i:s');
$query = $db->prepare(
    'SELECT id, title, description, reveal_date
     FROM surprises
     WHERE live = 1
       AND viewed = 0
       AND reveal_date IS NOT NULL
       AND reveal_date <= :now_utc
       AND notified_at IS NULL
     ORDER BY reveal_date ASC, id ASC'
);

$query->execute(['now_utc' => $nowUtc]);
$dueSurprises = $query->fetchAll() ?: [];

$attempted = 0;
$sent = 0;
$failed = 0;

foreach ($dueSurprises as $surprise) {
    $attempted++;

    $surpriseId = (int) ($surprise['id'] ?? 0);
    $title = trim((string) ($surprise['title'] ?? ''));
    $description = trim((string) ($surprise['description'] ?? ''));
    $revealDate = (string) ($surprise['reveal_date'] ?? '');

    $subject = 'Your Surprise is Ready!';
    $body = "Looks like your surprise is ready!\n\n";
    $body .= 'Title: ' . ($title !== '' ? $title : 'Untitled') . "\n";
    if ($description !== '') {
        $body .= 'Description: ' . $description . "\n";
    }
    $body .= 'Reveal Date (UTC): ' . $revealDate;

    $emailResult = $emailService->send($subject, $body);
    if (isset($emailResult['error'])) {
        $failed++;
        Logger::error('Reminder email failed', [
            'surprise_id' => $surpriseId,
            'error' => $emailResult['error'],
        ]);

        echo sprintf("FAILED surprise #%d: %s\n", $surpriseId, (string) $emailResult['error']);
        continue;
    }

    $markNotified = $db->prepare(
        'UPDATE surprises
         SET notified_at = :notified_at, updated_at = :updated_at
         WHERE id = :id AND notified_at IS NULL'
    );
    $markNotified->execute([
        'notified_at' => gmdate('Y-m-d H:i:s'),
        'updated_at' => gmdate('Y-m-d H:i:s'),
        'id' => $surpriseId,
    ]);

    if ($markNotified->rowCount() > 0) {
        $sent++;
        Logger::info('Reminder email sent', [
            'surprise_id' => $surpriseId,
            'title' => $title,
        ]);
        echo sprintf("SENT surprise #%d (%s)\n", $surpriseId, $title !== '' ? $title : 'Untitled');
        continue;
    }

    Logger::warning('Reminder email sent but surprise was already marked as notified', [
        'surprise_id' => $surpriseId,
    ]);
}

echo sprintf("Done. attempted=%d sent=%d failed=%d\n", $attempted, $sent, $failed);

if ($failed > 0) {
    exit(1);
}
