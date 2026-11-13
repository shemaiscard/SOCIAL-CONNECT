<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

final class App
{
    public static function run(): void
    {
        Session::start();
        self::securityHeaders();

        $router = new Router();
        (require ROOT . '/src/routes.php')($router);

        ob_start();
        try {
            if ($id = Auth::id()) {
                User::markSeen($id);
            }
            $router->dispatch(request_method(), request_path());
        } catch (HttpException $e) {
            self::discardOutput();
            self::renderError($e->status, $e->getMessage());
        } catch (\Throwable $e) {
            self::discardOutput();
            error_log((string) $e);
            $detail = Env::bool('APP_DEBUG')
                ? $e::class . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')'
                : '';
            self::renderError(500, $detail);
        }
        ob_end_flush();
    }

    private static function securityHeaders(): void
    {
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; "
            . "script-src 'self'; font-src 'self'; connect-src 'self'; object-src 'none'; "
            . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }

    private static function discardOutput(): void
    {
        while (ob_get_level() > 1) {
            ob_end_clean();
        }
        if (ob_get_level() === 1) {
            ob_clean();
        }
    }

    private static function renderError(int $status, string $detail): void
    {
        $titles = [
            403 => 'You can not do that',
            404 => 'Page not found',
            405 => 'Method not allowed',
            413 => 'Upload too large',
            419 => 'Your session expired',
            500 => 'Something went wrong',
        ];
        $messages = [
            403 => 'You do not have access to this page or action.',
            404 => 'The link may be broken, or the page may have been removed.',
            405 => 'This address does not accept that kind of request.',
            413 => 'Choose a smaller file and try again.',
            419 => 'The form was open too long. Go back, refresh the page and try again.',
            500 => 'The server hit an error. Try again in a moment.',
        ];
        http_response_code($status);
        $message = $detail !== '' ? $detail : ($messages[$status] ?? 'Something went wrong.');
        if (wants_json() || is_fragment()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
            return;
        }
        try {
            View::render('errors/error', [
                'title' => $titles[$status] ?? 'Error',
                'status' => $status,
                'message' => $message,
            ], 'layouts/auth');
        } catch (\Throwable $e) {
            error_log((string) $e);
            header('Content-Type: text/plain; charset=utf-8');
            echo "Error $status. $message";
        }
    }
}
