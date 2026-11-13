<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Comment
{
    private const SELECT = 'SELECT c.id, c.post_id, c.user_id, c.body, c.created_at,
            u.first_name, u.last_name, u.avatar_path, p.user_id AS post_owner_id
       FROM comments c
       JOIN users u ON u.id = c.user_id
       JOIN posts p ON p.id = c.post_id';

    public static function create(int $postId, int $userId, string $body): int
    {
        return DB::insert('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)', [$postId, $userId, $body]);
    }

    public static function find(int $id): ?array
    {
        return DB::one(self::SELECT . ' WHERE c.id = ?', [$id]);
    }

    public static function forPost(int $postId): array
    {
        return DB::all(self::SELECT . ' WHERE c.post_id = ? ORDER BY c.id', [$postId]);
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM comments WHERE id = ?', [$id]);
    }

    public static function countForPost(int $postId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM comments WHERE post_id = ?', [$postId]);
    }
}
