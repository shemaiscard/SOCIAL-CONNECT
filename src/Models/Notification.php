<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Types: friend_request, friend_accept, post_like, post_comment. */
final class Notification
{
    public static function add(int $userId, int $actorId, string $type, ?int $postId = null): void
    {
        if ($userId === $actorId) {
            return;
        }
        // Liking, unliking and liking again should leave one notification, not three.
        if ($type !== 'post_comment') {
            self::remove($userId, $actorId, $type, $postId);
        }
        DB::run(
            'INSERT INTO notifications (user_id, actor_id, type, post_id) VALUES (?, ?, ?, ?)',
            [$userId, $actorId, $type, $postId]
        );
    }

    public static function remove(int $userId, int $actorId, string $type, ?int $postId = null): void
    {
        DB::run(
            'DELETE FROM notifications WHERE user_id = ? AND actor_id = ? AND type = ? AND post_id <=> ?',
            [$userId, $actorId, $type, $postId]
        );
    }

    public static function forUser(int $me, int $limit = 50): array
    {
        return DB::all(
            'SELECT n.id, n.type, n.post_id, n.created_at, n.read_at, n.actor_id,
                    u.first_name, u.last_name, u.avatar_path, p.body AS post_body
               FROM notifications n
               JOIN users u ON u.id = n.actor_id
               LEFT JOIN posts p ON p.id = n.post_id
              WHERE n.user_id = ?
              ORDER BY n.id DESC
              LIMIT ' . (int) $limit,
            [$me]
        );
    }

    public static function markAllRead(int $me): void
    {
        DB::run('UPDATE notifications SET read_at = UTC_TIMESTAMP() WHERE user_id = ? AND read_at IS NULL', [$me]);
    }

    /** Badge numbers for the navigation. @return array{notifications: int, messages: int, requests: int} */
    public static function counts(int $me): array
    {
        $row = DB::one(
            "SELECT
                (SELECT COUNT(*) FROM notifications WHERE user_id = :me AND read_at IS NULL) AS notifications,
                (SELECT COUNT(*) FROM messages WHERE recipient_id = :me AND read_at IS NULL) AS messages,
                (SELECT COUNT(*) FROM friendships
                  WHERE status = 'pending' AND requester_id <> :me AND (user_a = :me OR user_b = :me)) AS requests",
            ['me' => $me]
        );
        return array_map('intval', $row ?? ['notifications' => 0, 'messages' => 0, 'requests' => 0]);
    }
}
