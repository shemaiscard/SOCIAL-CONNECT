<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sends email through the Resend API when RESEND_API_KEY is set.
 * Without a key (local development), each email is written to storage/outbox as a text file.
 */
final class Mailer
{
    /** Overridden by tests to point at a local mock server. */
    public static string $endpoint = 'https://api.resend.com/emails';

    /** Returns false when Resend refuses the message or cannot be reached; the reason goes to the error log. */
    public static function send(string $to, string $subject, string $body): bool
    {
        $key = (string) Env::get('RESEND_API_KEY', '');
        if ($key === '') {
            return self::toOutbox($to, $subject, $body);
        }

        $payload = json_encode([
            'from' => (string) Env::get('MAIL_FROM', 'Social Connect <onboarding@resend.dev>'),
            'to' => [$to],
            'subject' => $subject,
            'text' => $body,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $ch = curl_init(self::$endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false || $status < 200 || $status >= 300) {
            error_log(sprintf('Resend email to %s failed: HTTP %d %s %s', $to, $status, $error, substr((string) $response, 0, 300)));
            return false;
        }
        return true;
    }

    private static function toOutbox(string $to, string $subject, string $body): bool
    {
        $dir = ROOT . '/storage/outbox';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $file = sprintf('%s/%s-%s.txt', $dir, gmdate('Ymd-His'), bin2hex(random_bytes(3)));
        return file_put_contents($file, "To: $to\nSubject: $subject\nDate: " . gmdate('r') . "\n\n$body\n") !== false;
    }
}
