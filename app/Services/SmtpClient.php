<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmtpClient
{
    /**
     * @param array{
     *   host:string,
     *   port:int,
     *   encryption:string,
     *   username:string,
     *   password:string,
     *   timeout:int,
     *   helo:string
     * } $config
     */
    public function send(array $config, string $from, string $to, string $subject, string $body): void
    {
        $host = trim($config['host']);
        if ($host === '') {
            throw new RuntimeException('SMTP host is empty');
        }

        $port = max(1, (int) $config['port']);
        $timeout = max(2, (int) $config['timeout']);
        $encryption = strtolower(trim($config['encryption']));
        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $encryption = 'none';
        }

        $username = trim($config['username']);
        $password = (string) $config['password'];
        $helo = trim($config['helo']) !== '' ? trim($config['helo']) : 'localhost';

        $transportHost = $encryption === 'ssl' ? sprintf('ssl://%s', $host) : $host;
        $address = sprintf('%s:%d', $transportHost, $port);

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
        if (!is_resource($socket)) {
            throw new RuntimeException(sprintf('SMTP connect failed: %s (%d)', $errstr, $errno));
        }

        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220], 'greeting');
            $this->command($socket, 'EHLO ' . $helo, [250], 'EHLO');

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220], 'STARTTLS');
                $cryptoOk = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($cryptoOk !== true) {
                    throw new RuntimeException('Unable to establish TLS session');
                }
                $this->command($socket, 'EHLO ' . $helo, [250], 'EHLO (after STARTTLS)');
            }

            if ($username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334], 'AUTH LOGIN');
                $this->command($socket, base64_encode($username), [334], 'SMTP username');
                $this->command($socket, base64_encode($password), [235], 'SMTP password');
            }

            $fromAddress = $this->sanitizeEmailAddress($from);
            $toAddress = $this->sanitizeEmailAddress($to);

            $this->command($socket, sprintf('MAIL FROM:<%s>', $fromAddress), [250], 'MAIL FROM');
            $this->command($socket, sprintf('RCPT TO:<%s>', $toAddress), [250, 251], 'RCPT TO');
            $this->command($socket, 'DATA', [354], 'DATA');

            $this->writeRaw($socket, $this->buildMessage($fromAddress, $toAddress, $subject, $body) . "\r\n.\r\n");
            $this->expect($socket, [250], 'message body');
            $this->command($socket, 'QUIT', [221], 'QUIT');
        } finally {
            fclose($socket);
        }
    }

    private function buildMessage(string $from, string $to, string $subject, string $body): string
    {
        $cleanSubject = $this->sanitizeHeaderValue($subject);
        if (function_exists('mb_encode_mimeheader')) {
            $cleanSubject = mb_encode_mimeheader($cleanSubject, 'UTF-8', 'B', "\r\n");
        }

        $normalizedBody = str_replace(["\r\n", "\r"], "\n", $body);
        $lines = explode("\n", $normalizedBody);
        foreach ($lines as &$line) {
            if (str_starts_with($line, '.')) {
                $line = '.' . $line;
            }
        }
        unset($line);

        $payloadBody = implode("\r\n", $lines);

        $headers = [
            'From: <' . $from . '>',
            'To: <' . $to . '>',
            'Subject: ' . $cleanSubject,
            'Date: ' . gmdate(DATE_RFC2822),
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . $payloadBody;
    }

    private function sanitizeHeaderValue(string $value): string
    {
        return trim(str_replace(["\r", "\n"], '', $value));
    }

    private function sanitizeEmailAddress(string $email): string
    {
        $clean = strtolower(trim(str_replace(["\r", "\n"], '', $email)));
        if (!filter_var($clean, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(sprintf('Invalid email address: %s', $email));
        }

        return $clean;
    }

    /**
     * @param list<int> $expectedCodes
     */
    private function command($socket, string $command, array $expectedCodes, string $context): void
    {
        $this->writeRaw($socket, $command . "\r\n");
        $this->expect($socket, $expectedCodes, $context);
    }

    private function writeRaw($socket, string $payload): void
    {
        $bytes = @fwrite($socket, $payload);
        if ($bytes === false || $bytes < strlen($payload)) {
            throw new RuntimeException('Failed writing to SMTP socket');
        }
    }

    /**
     * @param list<int> $expectedCodes
     */
    private function expect($socket, array $expectedCodes, string $context): void
    {
        [$code, $response] = $this->readResponse($socket);
        if (!in_array($code, $expectedCodes, true)) {
            throw new RuntimeException(sprintf(
                'SMTP %s failed. Expected [%s], got %d (%s)',
                $context,
                implode(', ', $expectedCodes),
                $code,
                $response
            ));
        }
    }

    /**
     * @return array{0:int,1:string}
     */
    private function readResponse($socket): array
    {
        $response = '';

        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;

            if (preg_match('/^(\d{3})([ -])/', $line, $matches) === 1 && $matches[2] === ' ') {
                return [(int) $matches[1], trim($response)];
            }
        }

        $meta = stream_get_meta_data($socket);
        if (!empty($meta['timed_out'])) {
            throw new RuntimeException('SMTP server timed out');
        }

        throw new RuntimeException('SMTP connection closed unexpectedly');
    }
}
