<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Logger;
use Throwable;

final class EmailService
{
    public function send(string $subject, string $text, ?string $overrideEmail = null): array
    {
        $to = trim((string) ($overrideEmail ?: Env::get('TO_EMAIL', '')));
        if ($to === '') {
            $error = 'TO_EMAIL is not configured';
            Logger::error('Email send blocked', ['reason' => $error]);
            return ['error' => $error];
        }

        if (!is_valid_email($to)) {
            $error = sprintf('Invalid TO_EMAIL value: %s', $to);
            Logger::error('Email send blocked', ['reason' => $error]);
            return ['error' => $error];
        }

        $from = trim((string) Env::get('MAIL_FROM', Env::get('MAIL_USERNAME', 'noreply@localhost')));
        if (!is_valid_email($from)) {
            $error = sprintf('Invalid MAIL_FROM value: %s', $from);
            Logger::error('Email send blocked', ['reason' => $error]);
            return ['error' => $error];
        }

        $appUrl = trim((string) Env::get('APP_PUBLIC_URL', ''));
        $message = $text . ($appUrl !== '' ? "\n\n" . $appUrl : '');

        if (Env::bool('MAIL_DRY_RUN', true)) {
            Logger::info('Email dry-run', [
                'to' => $to,
                'subject' => $subject,
            ]);
            return [
                'dryRun' => true,
                'to' => $to,
                'subject' => $subject,
                'text' => $message,
            ];
        }

        $smtpResult = null;
        if ($this->shouldUseSmtp()) {
            $smtpResult = $this->sendViaSmtp($from, $to, $subject, $message);
            if (!isset($smtpResult['error'])) {
                return $smtpResult;
            }

            Logger::error('SMTP send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $smtpResult['error'],
            ]);
        }

        if (!Env::bool('MAIL_FALLBACK_TO_PHP_MAIL', true)) {
            return $smtpResult ?? ['error' => 'MAIL_FALLBACK_TO_PHP_MAIL is disabled'];
        }

        return $this->sendViaPhpMail($from, $to, $subject, $message);
    }

    private function shouldUseSmtp(): bool
    {
        $transport = strtolower(trim((string) Env::get('MAIL_TRANSPORT', '')));
        if ($transport === 'smtp') {
            return true;
        }

        if ($transport === 'mail') {
            return false;
        }

        return trim((string) Env::get('SMTP_HOST', '')) !== '';
    }

    private function sendViaSmtp(string $from, string $to, string $subject, string $message): array
    {
        $host = trim((string) Env::get('SMTP_HOST', ''));
        if ($host === '') {
            return ['error' => 'SMTP_HOST is not configured'];
        }

        $port = Env::int('SMTP_PORT', 587);
        if ($port <= 0) {
            $port = 587;
        }

        $encryption = strtolower(trim((string) Env::get('SMTP_ENCRYPTION', $port === 465 ? 'ssl' : 'tls')));
        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $encryption = 'tls';
        }

        $username = trim((string) Env::get('SMTP_USERNAME', Env::get('MAIL_USERNAME', '')));
        $password = (string) Env::get('SMTP_PASSWORD', Env::get('MAIL_PASSWORD', ''));
        $timeout = max(2, Env::int('SMTP_TIMEOUT', 15));
        $helo = trim((string) Env::get('SMTP_HELO', parse_url((string) Env::get('APP_PUBLIC_URL', ''), PHP_URL_HOST) ?: 'localhost'));

        $client = new SmtpClient();

        try {
            $client->send([
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'password' => $password,
                'timeout' => $timeout,
                'helo' => $helo,
            ], $from, $to, $subject, $message);

            Logger::info('Email sent via SMTP', [
                'to' => $to,
                'subject' => $subject,
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
            ]);

            return [
                'success' => true,
                'transport' => 'smtp',
                'to' => $to,
            ];
        } catch (Throwable $exception) {
            return [
                'error' => 'SMTP failed: ' . $exception->getMessage(),
                'transport' => 'smtp',
                'to' => $to,
            ];
        }
    }

    private function sendViaPhpMail(string $from, string $to, string $subject, string $message): array
    {
        $headers = [
            'From: ' . $from,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $sent = @mail($to, $subject, $message, implode("\r\n", $headers));

        if (!$sent) {
            $error = 'mail() failed';
            Logger::error('Email send failed via mail()', [
                'to' => $to,
                'subject' => $subject,
                'error' => $error,
            ]);
            return ['error' => $error];
        }

        Logger::info('Email sent via mail()', [
            'to' => $to,
            'subject' => $subject,
        ]);

        return [
            'success' => true,
            'transport' => 'mail',
            'to' => $to,
        ];
    }
}
