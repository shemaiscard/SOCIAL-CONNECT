<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    /** Flash data written by the previous request. */
    public static array $flash = [];

    public static function start(): void
    {
        session_name('sc_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => base_path() . '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();

        // Only full page loads consume flash data, so a background fetch
        // that lands between a redirect and the next page cannot swallow it.
        if (request_method() === 'GET' && !wants_json() && !is_fragment()) {
            self::$flash = $_SESSION['_flash'] ?? [];
            unset($_SESSION['_flash']);
        }
    }

    /** Release the session lock early for long or frequent read-only requests. */
    public static function release(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }
}
