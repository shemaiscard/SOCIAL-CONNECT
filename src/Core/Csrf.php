<?php

declare(strict_types=1);

namespace App\Core;

/** Per-session token checked on every POST, sent as a form field or an X-CSRF-Token header. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public static function valid(): bool
    {
        $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        return is_string($sent)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $sent);
    }

    public static function rotate(): void
    {
        unset($_SESSION['_csrf']);
    }
}
