<?php

declare(strict_types=1);

/*
 * Shared setup for web requests (public/index.php) and CLI scripts (bin/).
 * Registers the autoloader, reads .env and turns PHP warnings into exceptions.
 */

define('ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

require ROOT . '/src/helpers.php';

App\Core\Env::load(ROOT . '/.env');

error_reporting(E_ALL);
ini_set('display_errors', PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
if (is_dir(ROOT . '/storage/logs') && is_writable(ROOT . '/storage/logs')) {
    ini_set('error_log', ROOT . '/storage/logs/app.log');
}
date_default_timezone_set('UTC');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
