<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Message
{
    public static function send(int $from, int $to, string $body): int
    {
        return DB::insert('INSERT INTO messages (sender_id, recipient_id, body) VALUES (?, ?, ?)', [$from, $to, $body]);
    }

    public static function find(int $id): ?array
    {
        return DB::one('SELECT * FROM messages WHERE id = ?', [$id]);
    }

    /** The latest $limit messages between two people (oldest first), or only those after $afterId. */
    public static function thread(int $me, int $other, int $afterId = 0, int $limit = 60): array
    {
        return DB::all(
            'SELECT * FROM (
                SELECT id, sender_id, recipient_id, body, created_at, read_at
                  FROM messages
                 WHERE ((sender_id = :me AND recipient_id = :other) OR (sender_id = :other AND recipient_id = :me))
                   AND id > :after
                 ORDER BY id DESC
                 LIMIT ' . (int) $limit . '
             ) t ORDER BY id',
            ['me' => $me, 'other' => $other, 'after' => $afterId]
        );
    }

    public static function hasThread(int $me, int $other): bool
    {
        return (bool) DB::value(
            'SELECT 1 FROM messages WHERE (sender_id = :me AND recipient_id = :other)
                OR (sender_id = :other AND recipient_id = :me) LIMIT 1',
            ['me' => $me, 'other' => $other]
        );
    }

    public static function markRead(int $me, int $other): void
    {
        DB::run(
            'UPDATE messages SET read_at = UTC_TIMESTAMP() WHERE recipient_id = ? AND sender_id = ? AND read_at IS NULL',
            [$me, $other]
        );
    }

    /** One row per person I have talked to, newest conversation first. */
    public static function conversations(int $me): array
    {
        return DB::all(
            'SELECT u.id, u.first_name, u.last_name, u.avatar_path, u.last_seen_at,
                    m.body, m.created_at, m.sender_id,
                    (SELECT COUNT(*) FROM messages x
                      WHERE x.sender_id = u.id AND x.recipient_id = :me AND x.read_at IS NULL) AS unread
               FROM (SELECT IF(sender_id = :me, recipient_id, sender_id) AS other_id, MAX(id) AS last_id
                       FROM messages
                      WHERE sender_id = :me OR recipient_id = :me
                      GROUP BY other_id) c
               JOIN messages m ON m.id = c.last_id
               JOIN users u ON u.id = c.other_id
              ORDER BY m.id DESC',
            ['me' => $me]
        );
    }
}
