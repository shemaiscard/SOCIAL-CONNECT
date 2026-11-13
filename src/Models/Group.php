<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

/** Groups are open: anyone can read them and join. Stored as social_groups ("groups" is reserved in MySQL 8). */
final class Group
{
    private const SELECT = 'SELECT g.id, g.owner_id, g.name, g.description, g.created_at,
            (SELECT COUNT(*) FROM group_members c WHERE c.group_id = g.id) AS member_count,
            (SELECT COUNT(*) FROM posts p WHERE p.group_id = g.id) AS post_count,
            (SELECT m.role FROM group_members m WHERE m.group_id = g.id AND m.user_id = :me) AS my_role
       FROM social_groups g';

    public static function create(int $ownerId, string $name, string $description): int
    {
        return DB::transaction(static function () use ($ownerId, $name, $description): int {
            $id = DB::insert(
                'INSERT INTO social_groups (owner_id, name, description) VALUES (?, ?, ?)',
                [$ownerId, $name, $description !== '' ? $description : null]
            );
            DB::run("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, 'owner')", [$id, $ownerId]);
            return $id;
        });
    }

    public static function find(int $id, int $me): ?array
    {
        return DB::one(self::SELECT . ' WHERE g.id = :id', ['me' => $me, 'id' => $id]);
    }

    public static function mine(int $me, int $limit = 0): array
    {
        return DB::all(
            self::SELECT . ' WHERE EXISTS (SELECT 1 FROM group_members x WHERE x.group_id = g.id AND x.user_id = :me)
              ORDER BY g.name' . ($limit > 0 ? ' LIMIT ' . (int) $limit : ''),
            ['me' => $me]
        );
    }

    public static function discover(int $me, int $limit = 12): array
    {
        return DB::all(
            self::SELECT . ' WHERE NOT EXISTS (SELECT 1 FROM group_members x WHERE x.group_id = g.id AND x.user_id = :me)
              ORDER BY member_count DESC, g.id DESC LIMIT ' . (int) $limit,
            ['me' => $me]
        );
    }

    public static function search(string $query, int $me, int $limit = 10): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        return DB::all(
            self::SELECT . ' WHERE g.name LIKE :q OR g.description LIKE :q ORDER BY member_count DESC LIMIT ' . (int) $limit,
            ['me' => $me, 'q' => $like]
        );
    }

    public static function isMember(int $groupId, int $userId): bool
    {
        return (bool) DB::value('SELECT 1 FROM group_members WHERE group_id = ? AND user_id = ?', [$groupId, $userId]);
    }

    public static function join(int $groupId, int $userId): void
    {
        DB::run("INSERT IGNORE INTO group_members (group_id, user_id, role) VALUES (?, ?, 'member')", [$groupId, $userId]);
    }

    public static function leave(int $groupId, int $userId): void
    {
        DB::run("DELETE FROM group_members WHERE group_id = ? AND user_id = ? AND role <> 'owner'", [$groupId, $userId]);
    }

    public static function delete(int $groupId): void
    {
        DB::run('DELETE FROM social_groups WHERE id = ?', [$groupId]);
    }

    public static function members(int $groupId, int $limit = 12): array
    {
        return DB::all(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_path, m.role
               FROM group_members m JOIN users u ON u.id = m.user_id
              WHERE m.group_id = ?
              ORDER BY m.role = 'owner' DESC, m.joined_at
              LIMIT " . (int) $limit,
            [$groupId]
        );
    }
}
