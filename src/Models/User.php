<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database as DB;

final class User
{
    private const COLUMNS = 'id, first_name, last_name, email, bio, location, birth_date, avatar_path, cover_path, created_at, last_seen_at, auth_version';

    public static function find(int $id): ?array
    {
        return DB::one('SELECT ' . self::COLUMNS . ' FROM users WHERE id = ?', [$id]);
    }

    /** Includes password_hash; only for sign-in. */
    public static function findForLogin(string $email): ?array
    {
        return DB::one('SELECT id, email, password_hash FROM users WHERE email = ?', [$email]);
    }

    public static function passwordHash(int $id): ?string
    {
        $hash = DB::value('SELECT password_hash FROM users WHERE id = ?', [$id]);
        return $hash === null ? null : (string) $hash;
    }

    public static function emailTaken(string $email, int $exceptId = 0): bool
    {
        return (bool) DB::value('SELECT 1 FROM users WHERE email = ? AND id <> ?', [$email, $exceptId]);
    }

    public static function create(string $first, string $last, string $email, string $password): int
    {
        return DB::insert(
            'INSERT INTO users (first_name, last_name, email, password_hash) VALUES (?, ?, ?, ?)',
            [$first, $last, $email, password_hash($password, PASSWORD_DEFAULT)]
        );
    }

    public static function updateProfile(int $id, array $fields): void
    {
        DB::run(
            'UPDATE users SET first_name = :first_name, last_name = :last_name, bio = :bio,
                location = :location, birth_date = :birth_date WHERE id = :id',
            [
                'first_name' => $fields['first_name'],
                'last_name' => $fields['last_name'],
                'bio' => $fields['bio'] !== '' ? $fields['bio'] : null,
                'location' => $fields['location'] !== '' ? $fields['location'] : null,
                'birth_date' => $fields['birth_date'] !== '' ? $fields['birth_date'] : null,
                'id' => $id,
            ]
        );
    }

    /** @param 'avatar_path'|'cover_path' $column */
    public static function setPhoto(int $id, string $column, ?string $path): void
    {
        if (!in_array($column, ['avatar_path', 'cover_path'], true)) {
            throw new \InvalidArgumentException($column);
        }
        DB::run("UPDATE users SET $column = ? WHERE id = ?", [$path, $id]);
    }

    public static function setPassword(int $id, string $password): void
    {
        // Raising auth_version signs out every session that was opened with the old password.
        DB::run(
            'UPDATE users SET password_hash = ?, auth_version = auth_version + 1 WHERE id = ?',
            [password_hash($password, PASSWORD_DEFAULT), $id]
        );
    }

    public static function setEmail(int $id, string $email): void
    {
        DB::run('UPDATE users SET email = ? WHERE id = ?', [$email, $id]);
    }

    /** Updates last_seen_at at most once a minute per session. */
    public static function markSeen(int $id): void
    {
        $now = time();
        if (($_SESSION['_seen'] ?? 0) > $now - 60) {
            return;
        }
        $_SESSION['_seen'] = $now;
        DB::run('UPDATE users SET last_seen_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    }

    public static function isOnline(?string $lastSeen): bool
    {
        return $lastSeen !== null && time() - db_time($lastSeen) < 300;
    }

    /** People whose name or email matches, friends first. */
    public static function search(string $query, int $viewer, int $limit = 20): array
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        return DB::all(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_path, u.location,
                    f.status AS friend_status, f.requester_id
               FROM users u
               LEFT JOIN friendships f ON f.user_a = LEAST(u.id, :me) AND f.user_b = GREATEST(u.id, :me)
              WHERE CONCAT(u.first_name, ' ', u.last_name) LIKE :q OR u.email = :email
              ORDER BY (f.status = 'accepted') DESC, u.first_name, u.last_name
              LIMIT " . (int) $limit,
            ['me' => $viewer, 'q' => $like, 'email' => $query]
        );
    }

    /** People the viewer is not connected to, ranked by mutual friends, then newest. */
    public static function suggestions(int $viewer, int $limit = 5): array
    {
        return DB::all(
            "SELECT u.id, u.first_name, u.last_name, u.avatar_path,
                    (SELECT COUNT(*)
                       FROM friendships a
                       JOIN friendships b ON b.status = 'accepted'
                        AND IF(b.user_a = u.id, b.user_b, b.user_a) = IF(a.user_a = :me, a.user_b, a.user_a)
                        AND (b.user_a = u.id OR b.user_b = u.id)
                      WHERE a.status = 'accepted' AND (a.user_a = :me OR a.user_b = :me)) AS mutual
               FROM users u
              WHERE u.id <> :me
                AND NOT EXISTS (SELECT 1 FROM friendships f
                                 WHERE f.user_a = LEAST(u.id, :me) AND f.user_b = GREATEST(u.id, :me))
              ORDER BY mutual DESC, u.id DESC
              LIMIT " . (int) $limit,
            ['me' => $viewer]
        );
    }
}
