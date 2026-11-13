<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function id(): ?int
    {
        return self::user()['id'] ?? null;
    }

    /**
     * The signed-in user, or null. Clears the session if the account no longer exists
     * or its password changed after this session signed in.
     */
    public static function user(): ?array
    {
        if (!self::$loaded) {
            self::$loaded = true;
            $id = isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
            self::$user = $id > 0 ? User::find($id) : null;
            $stale = self::$user !== null && (int) ($_SESSION['av'] ?? 0) !== (int) self::$user['auth_version'];
            if ($stale) {
                self::$user = null;
            }
            if ($id > 0 && self::$user === null) {
                unset($_SESSION['uid'], $_SESSION['av']);
            }
        }
        return self::$user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function login(int $userId): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $userId;
        $_SESSION['av'] = (int) (User::find($userId)['auth_version'] ?? 0);
        Csrf::rotate();
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        Csrf::rotate();
        self::$user = null;
        self::$loaded = true;
    }

    /** Re-read the user row after a profile change. */
    public static function refresh(): void
    {
        self::$loaded = false;
    }
}
