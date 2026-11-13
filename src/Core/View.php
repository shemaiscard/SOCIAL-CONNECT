<?php

declare(strict_types=1);

namespace App\Core;

/** Plain PHP templates in views/. Every value is escaped with e() inside the template. */
final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): void
    {
        $content = self::capture($view, $data);
        echo $layout === null ? $content : self::capture($layout, ['content' => $content] + $data);
    }

    public static function capture(string $__view, array $__data = []): string
    {
        $__file = ROOT . '/views/' . $__view . '.php';
        if (!is_file($__file)) {
            throw new \RuntimeException("View not found: $__view");
        }
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            require $__file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
