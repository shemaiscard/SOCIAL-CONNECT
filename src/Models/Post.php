<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class Post
{
    public const PER_PAGE = 10;

    /** Post columns plus author, group name, counts and the viewer's like/save state. Needs :me. */
    private const SELECT = "SELECT p.id, p.user_id, p.group_id, p.body, p.image_path, p.image_w, p.image_h,
            p.visibility, p.created_at, u.first_name, u.last_name, u.avatar_path, g.name AS group_name,
            (SELECT COUNT(*) FROM post_likes l WHERE l.post_id = p.id) AS like_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id) AS comment_count,
            EXISTS (SELECT 1 FROM post_likes ml WHERE ml.post_id = p.id AND ml.user_id = :me) AS liked,
            EXISTS (SELECT 1 FROM saved_posts ms WHERE ms.post_id = p.id AND ms.user_id = :me) AS saved
       FROM posts p
       JOIN users u ON u.id = p.user_id
       LEFT JOIN social_groups g ON g.id = p.group_id";

    /** Who may see a post: its author, anyone for public and group posts, friends for friends-only posts. */
    private const VISIBLE = "(p.user_id = :me OR p.group_id IS NOT NULL OR p.visibility = 'public'
       OR EXISTS (SELECT 1 FROM friendships fv WHERE fv.status = 'accepted'
                   AND fv.user_a = LEAST(p.user_id, :me) AND fv.user_b = GREATEST(p.user_id, :me)))";

    /** Feed: my posts, my friends' posts and posts in groups I belong to. */
    public static function feed(int $me, ?int $before): array
    {
        return self::page(
            "(p.group_id IS NULL AND (p.user_id = :me OR EXISTS (
                  SELECT 1 FROM friendships ff WHERE ff.status = 'accepted'
                     AND ff.user_a = LEAST(p.user_id, :me) AND ff.user_b = GREATEST(p.user_id, :me))))
             OR p.group_id IN (SELECT gm.group_id FROM group_members gm WHERE gm.user_id = :me)",
            ['me' => $me],
            $before
        );
    }

    public static function byUser(int $owner, int $me, ?int $before): array
    {
        return self::page('p.user_id = :owner AND p.group_id IS NULL AND ' . self::VISIBLE, ['me' => $me, 'owner' => $owner], $before);
    }

    public static function byGroup(int $groupId, int $me, ?int $before): array
    {
        return self::page('p.group_id = :gid', ['me' => $me, 'gid' => $groupId], $before);
    }

    public static function saved(int $me, ?int $before): array
    {
        return self::page(
            'EXISTS (SELECT 1 FROM saved_posts sv WHERE sv.post_id = p.id AND sv.user_id = :me) AND ' . self::VISIBLE,
            ['me' => $me],
            $before
        );
    }

    public static function search(string $query, int $me, int $limit = 10): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $rows = DB::all(
            self::SELECT . ' WHERE p.body LIKE :q AND ' . self::VISIBLE . ' ORDER BY p.id DESC LIMIT ' . (int) $limit,
            ['me' => $me, 'q' => $like]
        );
        return self::withComments($rows);
    }

    /** A single post if the viewer may see it. */
    public static function find(int $id, int $me): ?array
    {
        return DB::one(self::SELECT . ' WHERE p.id = :id AND ' . self::VISIBLE, ['me' => $me, 'id' => $id]);
    }

    /** @return array{0: list<array>, 1: ?int} posts and the id to pass as ?before= for the next page */
    private static function page(string $where, array $params, ?int $before): array
    {
        $sql = self::SELECT . " WHERE ($where)";
        if ($before !== null) {
            $sql .= ' AND p.id < :before';
            $params['before'] = $before;
        }
        $sql .= ' ORDER BY p.id DESC LIMIT ' . (self::PER_PAGE + 1);
        $rows = DB::all($sql, $params);
        $next = null;
        if (count($rows) > self::PER_PAGE) {
            array_pop($rows);
            $next = (int) $rows[array_key_last($rows)]['id'];
        }
        return [self::withComments($rows), $next];
    }

    /** Adds the two newest comments (oldest first) to each post as $post['comments']. */
    private static function withComments(array $posts, int $perPost = 2): array
    {
        if (!$posts) {
            return [];
        }
        $ids = array_column($posts, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $rows = DB::all(
            "SELECT * FROM (
                SELECT c.id, c.post_id, c.user_id, c.body, c.created_at, u.first_name, u.last_name, u.avatar_path,
                       ROW_NUMBER() OVER (PARTITION BY c.post_id ORDER BY c.id DESC) AS rn
                  FROM comments c JOIN users u ON u.id = c.user_id
                 WHERE c.post_id IN ($marks)
             ) t WHERE rn <= " . (int) $perPost . ' ORDER BY id',
            $ids
        );
        $byPost = [];
        foreach ($rows as $row) {
            $byPost[$row['post_id']][] = $row;
        }
        foreach ($posts as &$post) {
            $post['comments'] = $byPost[$post['id']] ?? [];
        }
        return $posts;
    }

    public static function create(int $userId, ?int $groupId, string $body, ?array $image, string $visibility): int
    {
        return DB::insert(
            'INSERT INTO posts (user_id, group_id, body, image_path, image_w, image_h, visibility)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $groupId,
                $body !== '' ? $body : null,
                $image['path'] ?? null,
                $image['width'] ?? null,
                $image['height'] ?? null,
                $visibility,
            ]
        );
    }

    public static function delete(int $id): void
    {
        DB::run('DELETE FROM posts WHERE id = ?', [$id]);
    }

    /** @return array{liked: bool, count: int} */
    public static function toggleLike(int $postId, int $me): array
    {
        $removed = DB::run('DELETE FROM post_likes WHERE post_id = ? AND user_id = ?', [$postId, $me])->rowCount() > 0;
        if (!$removed) {
            DB::run('INSERT IGNORE INTO post_likes (post_id, user_id) VALUES (?, ?)', [$postId, $me]);
        }
        $count = (int) DB::value('SELECT COUNT(*) FROM post_likes WHERE post_id = ?', [$postId]);
        return ['liked' => !$removed, 'count' => $count];
    }

    public static function toggleSave(int $postId, int $me): bool
    {
        $removed = DB::run('DELETE FROM saved_posts WHERE post_id = ? AND user_id = ?', [$postId, $me])->rowCount() > 0;
        if (!$removed) {
            DB::run('INSERT IGNORE INTO saved_posts (user_id, post_id) VALUES (?, ?)', [$me, $postId]);
        }
        return !$removed;
    }

    public static function report(int $postId, int $me, string $reason): void
    {
        DB::run(
            'INSERT INTO reports (post_id, reporter_id, reason) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason), created_at = UTC_TIMESTAMP()',
            [$postId, $me, $reason]
        );
    }

    public static function countByUser(int $userId): int
    {
        return (int) DB::value('SELECT COUNT(*) FROM posts WHERE user_id = ? AND group_id IS NULL', [$userId]);
    }
}
