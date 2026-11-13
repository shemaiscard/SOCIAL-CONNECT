<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Login throttling, reset request limits and password reset tokens. */
final class Security
{
    private const MAX_FAILURES = 5;
    private const WINDOW_MINUTES = 15;
    private const RESET_MINUTES = 60;
    private const RESETS_PER_EMAIL = 3;
    private const RESETS_PER_IP = 10;

    public static function tooManyFailures(string $email, string $ip): bool
    {
        $count = (int) DB::value(
            'SELECT COUNT(*) FROM login_attempts
              WHERE email = ? AND ip = ? AND attempted_at > UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE',
            [$email, $ip]
        );
        return $count >= self::MAX_FAILURES;
    }

    public static function recordFailure(string $email, string $ip): void
    {
        DB::run('INSERT INTO login_attempts (email, ip) VALUES (?, ?)', [mb_substr($email, 0, 190), $ip]);
        DB::run('DELETE FROM login_attempts WHERE attempted_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    }

    public static function clearFailures(string $email, string $ip): void
    {
        DB::run('DELETE FROM login_attempts WHERE email = ? AND ip = ?', [$email, $ip]);
    }

    /**
     * Reset links per hour: 3 for one email address, 10 from one IP. Counted by the address typed in,
     * whether or not it has an account, so the limit does not reveal which emails are registered.
     */
    public static function tooManyResetRequests(string $email, string $ip): bool
    {
        $recent = 'requested_at > UTC_TIMESTAMP() - INTERVAL 1 HOUR';
        $byEmail = (int) DB::value("SELECT COUNT(*) FROM reset_requests WHERE email = ? AND $recent", [$email]);
        $byIp = (int) DB::value("SELECT COUNT(*) FROM reset_requests WHERE ip = ? AND $recent", [$ip]);
        return $byEmail >= self::RESETS_PER_EMAIL || $byIp >= self::RESETS_PER_IP;
    }

    public static function recordResetRequest(string $email, string $ip): void
    {
        DB::run('INSERT INTO reset_requests (email, ip) VALUES (?, ?)', [mb_substr($email, 0, 190), $ip]);
        DB::run('DELETE FROM reset_requests WHERE requested_at < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    }

    /** Creates a single-use token. Only its SHA-256 hash is stored. */
    public static function createResetToken(int $userId): string
    {
        $token = bin2hex(random_bytes(32));
        DB::run('DELETE FROM password_resets WHERE user_id = ?', [$userId]);
        DB::run(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL ' . self::RESET_MINUTES . ' MINUTE)',
            [$userId, hash('sha256', $token)]
        );
        return $token;
    }

    /** The user id for a valid, unused, unexpired token, or null. */
    public static function resetTokenUser(string $token): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $id = DB::value(
            'SELECT user_id FROM password_resets
              WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()',
            [hash('sha256', $token)]
        );
        return $id === null ? null : (int) $id;
    }

    public static function consumeResetToken(string $token): void
    {
        DB::run('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE token_hash = ?', [hash('sha256', $token)]);
    }
}
