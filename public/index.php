<?php

declare(strict_types=1);

// The PHP built-in server sends every request here; let it serve real static files itself.
if (PHP_SAPI === 'cli-server') {
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    $file = realpath(__DIR__ . $path);
    if ($file !== false && is_file($file) && str_starts_with($file, __DIR__ . DIRECTORY_SEPARATOR)
        && !str_ends_with($file, '.php') && !str_contains($path, '/.')) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

App\Core\App::run();
