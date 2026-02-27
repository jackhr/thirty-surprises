<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;

final class EmailService
{
    public function send(string $subject, string $text, ?string $overrideEmail = null): array
    {
        $to = trim((string) ($overrideEmail ?: Env::get('TO_EMAIL', '')));
        if ($to === '') {
            return ['error' => 'TO_EMAIL is not configured'];
        }

        $from = trim((string) Env::get('MAIL_FROM', Env::get('MAIL_USERNAME', 'noreply@localhost')));
        $appUrl = trim((string) Env::get('APP_PUBLIC_URL', ''));
        $message = $text . ($appUrl !== '' ? "\n\n" . $appUrl : '');

        if (Env::bool('MAIL_DRY_RUN', true)) {
            return [
                'dryRun' => true,
                'to' => $to,
                'subject' => $subject,
                'text' => $message,
            ];
        }

        $headers = [
            'From: ' . $from,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = @mail($to, $subject, $message, implode("\r\n", $headers));

        if (!$sent) {
            return ['error' => 'mail() failed'];
        }

        return [
            'success' => true,
            'to' => $to,
        ];
    }
}
