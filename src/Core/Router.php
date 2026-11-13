<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Maps METHOD + path to a controller action. {id} segments match digits and are
 * passed to the action as named int arguments.
 *
 * Access levels: 'auth' (signed in, the default), 'guest' (signed out), 'public' (anyone).
 * Every POST is checked for a valid CSRF token before the action runs.
 */
final class Router
{
    /** @var list<array{method: string, regex: string, handler: array{class-string, string}, access: string}> */
    private array $routes = [];

    public function get(string $path, array $handler, string $access = 'auth'): void
    {
        $this->add('GET', $path, $handler, $access);
    }

    public function post(string $path, array $handler, string $access = 'auth'): void
    {
        $this->add('POST', $path, $handler, $access);
    }

    private function add(string $method, string $path, array $handler, string $access): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>\d{1,10})', $path) . '$#';
        $this->routes[] = compact('method', 'regex', 'handler', 'access');
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $match)) {
                continue;
            }
            if ($route['method'] !== $method && !($method === 'HEAD' && $route['method'] === 'GET')) {
                $allowed[] = $route['method'];
                continue;
            }
            $this->guard($route['access'], $method);
            $params = array_map('intval', array_filter($match, 'is_string', ARRAY_FILTER_USE_KEY));
            [$class, $action] = $route['handler'];
            (new $class())->$action(...$params);
            return;
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405);
        }
        throw new HttpException(404);
    }

    private function guard(string $access, string $method): void
    {
        if ($method === 'POST') {
            // PHP drops the whole body when it exceeds post_max_size.
            if (!$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                throw new HttpException(413, 'That upload is larger than the server accepts.');
            }
            if (!Csrf::valid()) {
                throw new HttpException(419);
            }
        }
        if ($access === 'auth' && !Auth::check()) {
            if (wants_json() || is_fragment()) {
                json(['error' => 'Your session ended. Log in again.'], 401);
            }
            $next = $method === 'GET' ? '?next=' . rawurlencode(request_path_with_query()) : '';
            redirect('/login' . $next);
        }
        if ($access === 'guest' && Auth::check()) {
            redirect('/');
        }
    }
}
