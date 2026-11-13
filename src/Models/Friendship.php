<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/**
 * One row per pair of users, stored with user_a < user_b so a pair can only exist once.
 * status is 'pending' (requester_id is waiting for the other person) or 'accepted'.
 */
final class Friendship
{
    public const SELF = 'self';
    public const NONE = 'none';
    public const FRIENDS = 'friends';
    public const OUTGOING = 'outgoing';
    public const INCOMING = 'incoming';

    /** @return array{0: int, 1: int} */
    private static function pair(int $x, int $y): array
    {
        return [min($x, $y), max($x, $y)];
    }

    public static function get(int $x, int $y): ?array
    {
        return DB::one('SELECT * FROM friendships WHERE user_a = ? AND user_b = ?', self::pair($x, $y));
    }

    public static function status(int $viewer, int $other): string
    {
        if ($viewer === $other) {
            return self::SELF;
        }
        return self::statusFromRow(self::get($viewer, $other), $viewer);
    }

    /** Works with a full row or with joined friend_status / requester_id columns. */
    public static function statusFromRow(?array $row, int $viewer): string
    {
        $status = $row['status'] ?? $row['friend_status'] ?? null;
        return match (true) {
            $status === 'accepted' => self::FRIENDS,
            $status === 'pending' && (int) $row['requester_id'] === $viewer => self::OUTGOING,
            $status === 'pending' => self::INCOMING,
            default => self::NONE,
        };
    }

    public static function areFriends(int $x, int $y): bool
    {
        return self::status($x, $y) === self::FRIENDS;
    }

    /** Sends a request. If the other person already asked, this accepts it instead. Returns the new status. */
    public static function send(int $from, int $to): string
    {
        $current = self::status($from, $to);
        if ($current === self::INCOMING) {
            self::accept($from, $to);
            return self::FRIENDS;
        }
        if ($current === self::NONE) {
            [$a, $b] = self::pair($from, $to);
            DB::run(
                "INSERT IGNORE INTO friendships (user_a, user_b, requester_id, status) VALUES (?, ?, ?, 'pending')",
                [$a, $b, $from]
            );
            return self::OUTGOING;
        }
        return $current;
    }

    public static function accept(int $me, int $other): bool
    {
        [$a, $b] = self::pair($me, $other);
        return DB::run(
            "UPDATE friendships SET status = 'accepted', accepted_at = UTC_TIMESTAMP()
              WHERE user_a = ? AND user_b = ? AND status = 'pending' AND requester_id = ?",
            [$a, $b, $other]
        )->rowCount() > 0;
    }

    /** Cancel, decline or unfriend: all three remove the row. */
    public static function remove(int $x, int $y): void
    {
        DB::run('DELETE FROM friendships WHERE user_a = ? AND user_b = ?', self::pair($x, $y));
    }

    public static function friends(int $userId, int $limit = 0): array
    {
        return DB::all(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_path, u.location, f.accepted_at
               FROM friendships f
               JOIN users u ON u.id = IF(f.user_a = :me, f.user_b, f.user_a)
              WHERE f.status = 'accepted' AND (f.user_a = :me OR f.user_b = :me)
              ORDER BY u.first_name, u.last_name"
                . ($limit > 0 ? ' LIMIT ' . (int) $limit : ''),
            ['me' => $userId]
        );
    }

    public static function countFriends(int $userId): int
    {
        return (int) DB::value(
            "SELECT COUNT(*) FROM friendships WHERE status = 'accepted' AND (user_a = :me OR user_b = :me)",
            ['me' => $userId]
        );
    }

    /** Requests other people sent to $me. */
    public static function incoming(int $me, int $limit = 0): array
    {
        return self::pending($me, 'f.requester_id <> :me', $limit);
    }

    /** Requests $me sent that are still waiting. */
    public static function outgoing(int $me, int $limit = 0): array
    {
        return self::pending($me, 'f.requester_id = :me', $limit);
    }

    private static function pending(int $me, string $condition, int $limit): array
    {
        return DB::all(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_path, u.location, f.created_at
               FROM friendships f
               JOIN users u ON u.id = IF(f.user_a = :me, f.user_b, f.user_a)
              WHERE f.status = 'pending' AND (f.user_a = :me OR f.user_b = :me) AND $condition
              ORDER BY f.created_at DESC"
                . ($limit > 0 ? ' LIMIT ' . (int) $limit : ''),
            ['me' => $me]
        );
    }

    public static function mutualCount(int $x, int $y): int
    {
        return (int) DB::value(
            "SELECT COUNT(*)
               FROM friendships a
               JOIN friendships b ON b.status = 'accepted'
                AND IF(b.user_a = :y, b.user_b, b.user_a) = IF(a.user_a = :x, a.user_b, a.user_a)
                AND (b.user_a = :y OR b.user_b = :y)
              WHERE a.status = 'accepted' AND (a.user_a = :x OR a.user_b = :x)",
            ['x' => $x, 'y' => $y]
        );
    }
}
