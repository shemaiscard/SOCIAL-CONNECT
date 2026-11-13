<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Env;
use App\Core\HttpException;
use App\Core\Session;
use App\Core\View;

/* ---------- Escaping and output ---------- */

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function partial(string $name, array $data = []): string
{
    return View::capture('partials/' . $name, $data);
}

function icon(string $name, string $class = ''): string
{
    return '<svg class="icon' . ($class !== '' ? ' ' . e($class) : '') . '" aria-hidden="true" focusable="false">'
        . '<use href="' . e(asset('icons.svg')) . '#' . e($name) . '"></use></svg>';
}

/* ---------- URLs ---------- */

/** Path prefix when the app runs in a sub-folder, for example "/social-connect". Empty at the web root. */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $configured = Env::get('APP_BASE_PATH');
        if ($configured !== null && $configured !== '') {
            $base = '/' . trim($configured, '/');
        } else {
            $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $dir = preg_replace('#/public$#', '', rtrim($dir, '/'));
            $base = $dir === '/' ? '' : (string) $dir;
        }
    }
    return $base;
}

function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function absolute_url(string $path): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (is_https() ? 'https' : 'http') . '://' . $host . url($path);
}

/** URL of a file in public/assets with a cache-busting version. */
function asset(string $path): string
{
    static $cache = [];
    if (!isset($cache[$path])) {
        $file = ROOT . '/public/assets/' . $path;
        $cache[$path] = url('assets/' . $path) . (is_file($file) ? '?v=' . filemtime($file) : '');
    }
    return $cache[$path];
}

function media_url(string $path): string
{
    return url('uploads/' . $path);
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/* ---------- Request ---------- */

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/** Path without the base folder and without a trailing slash, for example "/posts/12". */
function request_path(): string
{
    $path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $base = base_path();
    if ($base !== '' && str_starts_with($path, $base)) {
        $path = substr($path, strlen($base));
    }
    $path = '/' . trim($path, '/');
    return $path === '/index.php' ? '/' : $path;
}

function request_path_with_query(): string
{
    $query = $_SERVER['QUERY_STRING'] ?? '';
    return request_path() . ($query !== '' ? '?' . $query : '');
}

/** Current path with some query values replaced, for "older posts" links. */
function with_query(array $changes): string
{
    $query = array_filter(array_merge($_GET, $changes), static fn ($v) => $v !== null && $v !== '');
    return url(request_path()) . ($query ? '?' . http_build_query($query) : '');
}

function wants_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** True for requests from app.js that only want an HTML fragment. */
function is_fragment(): bool
{
    return ($_SERVER['HTTP_X_FRAGMENT'] ?? '') === '1';
}

function input(string $key, int $maxLength = 0): string
{
    $value = $_POST[$key] ?? '';
    $value = is_string($value) ? trim(str_replace("\r\n", "\n", $value)) : '';
    return $maxLength > 0 ? mb_substr($value, 0, $maxLength) : $value;
}

function query_int(string $key): ?int
{
    $value = $_GET[$key] ?? null;
    return is_string($value) && ctype_digit($value) && $value !== '0' ? (int) $value : null;
}

/** Only allow local redirect targets such as "/posts/3". */
function safe_next(mixed $next): string
{
    return is_string($next) && preg_match('#^/(?![/\\\\])[^\s]*$#', $next) ? $next : '';
}

/* ---------- Responses ---------- */

function redirect(string $to): never
{
    if (str_starts_with($to, '/')) {
        $to = url($to);
    }
    header('Location: ' . $to, true, 303);
    exit;
}

/** The page the user came from when it is on this site, otherwise the feed. */
function back(string $fallback = '/'): string
{
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        return $referer;
    }
    return url($fallback);
}

function json(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

/** Stop with a user-facing error: JSON for app.js, a toast plus redirect for plain forms. */
function fail(string $message, int $status = 422): never
{
    if (wants_json()) {
        json(['error' => $message], $status);
    }
    flash('toast_error', $message);
    redirect(back());
}

/** Success response: JSON for app.js, a toast plus redirect for plain forms. */
function done(array $data, string $toast = '', ?string $to = null): never
{
    if (wants_json()) {
        json(['ok' => true, 'message' => $toast] + $data);
    }
    if ($toast !== '') {
        flash('toast', $toast);
    }
    redirect($to ?? back());
}

/* ---------- Session helpers ---------- */

function csrf_token(): string
{
    return Csrf::token();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Csrf::token()) . '">';
}

function flash(string $key, mixed $value): void
{
    $_SESSION['_flash'][$key] = $value;
}

function flashed(string $key, mixed $default = null): mixed
{
    return Session::$flash[$key] ?? $default;
}

function old(string $key, string $default = ''): string
{
    return (string) (flashed('_old', [])[$key] ?? $default);
}

function field_error(string $key): ?string
{
    return flashed('_errors', [])[$key] ?? null;
}

function back_with_errors(array $errors, array $old = []): never
{
    flash('_errors', $errors);
    flash('_old', $old);
    redirect(back());
}

/* ---------- Formatting ---------- */

function full_name(array $user): string
{
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function initials(array $user): string
{
    $first = mb_substr((string) ($user['first_name'] ?? ''), 0, 1);
    $last = mb_substr((string) ($user['last_name'] ?? ''), 0, 1);
    return mb_strtoupper($first . $last) ?: '?';
}

/** Photo, or initials on a colour picked from the user id. Sizes: xs sm md lg xl. */
function avatar(array $user, string $size = 'md'): string
{
    $px = ['xs' => 24, 'sm' => 32, 'md' => 40, 'lg' => 56, 'xl' => 136][$size] ?? 40;
    if (!empty($user['avatar_path'])) {
        return '<img class="avatar avatar-' . $size . '" src="' . e(media_url($user['avatar_path'])) . '" alt="" width="'
            . $px . '" height="' . $px . '" loading="lazy" decoding="async">';
    }
    $tone = ((int) ($user['id'] ?? 0)) % 8;
    return '<span class="avatar avatar-' . $size . ' av-' . $tone . '" aria-hidden="true">' . e(initials($user)) . '</span>';
}

/** Turns joined post columns (user_id, first_name...) into an author array for avatar(). */
function author(array $row, string $idKey = 'user_id'): array
{
    return [
        'id' => $row[$idKey] ?? 0,
        'first_name' => $row['first_name'] ?? '',
        'last_name' => $row['last_name'] ?? '',
        'avatar_path' => $row['avatar_path'] ?? null,
    ];
}

function db_time(string $datetime): int
{
    return (int) strtotime($datetime . ' UTC');
}

/** Short relative time: "now", "5m", "3h", "2d", then a date. */
function time_ago(string $datetime): string
{
    $then = db_time($datetime);
    $diff = time() - $then;
    return match (true) {
        $diff < 45 => 'now',
        $diff < 3600 => max(1, intdiv($diff, 60)) . 'm',
        $diff < 86400 => intdiv($diff, 3600) . 'h',
        $diff < 7 * 86400 => intdiv($diff, 86400) . 'd',
        gmdate('Y', $then) === gmdate('Y') => gmdate('M j', $then),
        default => gmdate('M j, Y', $then),
    };
}

/** <time> element; app.js swaps the title for the reader's local date and time. */
function time_tag(string $datetime, string $class = ''): string
{
    $ts = db_time($datetime);
    return '<time' . ($class ? ' class="' . e($class) . '"' : '') . ' datetime="' . gmdate('c', $ts) . '" title="'
        . gmdate('M j, Y \a\t H:i \U\T\C', $ts) . '">' . e(time_ago($datetime)) . '</time>';
}

/** Escaped text with line breaks and clickable http(s) links. */
function format_body(string $text): string
{
    // Split the raw text on URLs first, then escape each piece, so entities never end up inside a link.
    $parts = preg_split('~(\bhttps?://[^\s<>"\']+)~i', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
    $html = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            $html .= e($part);
            continue;
        }
        $trail = '';
        while ($part !== '' && str_contains('.,;:!?)', substr($part, -1))) {
            $trail = substr($part, -1) . $trail;
            $part = substr($part, 0, -1);
        }
        $html .= '<a href="' . e($part) . '" rel="nofollow noopener noreferrer ugc" target="_blank">' . e($part) . '</a>' . e($trail);
    }
    return nl2br($html, false);
}

function plural(int $count, string $one, ?string $many = null): string
{
    return number_format($count) . ' ' . ($count === 1 ? $one : ($many ?? $one . 's'));
}

function excerpt(?string $text, int $length = 80): string
{
    $text = trim(preg_replace('/\s+/', ' ', (string) $text));
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length - 1)) . '…' : $text;
}

/* ---------- View helpers ---------- */

/** True when the user loaded a page in the last five minutes. */
function is_online(?string $lastSeen): bool
{
    return $lastSeen !== null && $lastSeen !== '' && time() - db_time($lastSeen) < 300;
}

/** "Active 5 min ago" style status for chat headers. */
function last_seen_label(?string $lastSeen): string
{
    if (is_online($lastSeen)) {
        return 'Online';
    }
    if (!$lastSeen) {
        return 'Not active yet';
    }
    return 'Active ' . ago($lastSeen);
}

/** aria-invalid and aria-describedby for a field whose server error is flashed under $key. */
function invalid_attrs(string $key, string $id): string
{
    return field_error($key) !== null ? ' aria-invalid="true" aria-describedby="' . e($id) . '-error"' : '';
}

function error_for(string $key, string $id): string
{
    $message = field_error($key);
    return $message === null ? '' : '<p class="field-error" id="' . e($id) . '-error">' . e($message) . '</p>';
}

function current_attr(bool $current): string
{
    return $current ? ' aria-current="page"' : '';
}

/** "5m ago", "just now" or "on Mar 3" for use inside a sentence. */
function ago(string $datetime): string
{
    $short = time_ago($datetime);
    if ($short === 'now') {
        return 'just now';
    }
    return preg_match('/^\d+[mhd]$/', $short) ? "$short ago" : "on $short";
}

/** Escapes $text and wraps case-insensitive matches of $query in <mark>. */
function highlight(string $text, string $query): string
{
    $query = trim($query);
    if ($query === '') {
        return e($text);
    }
    $parts = preg_split('/(' . preg_quote($query, '/') . ')/iu', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($parts === false) {
        return e($text);
    }
    $html = '';
    foreach ($parts as $i => $part) {
        $html .= $i % 2 === 1 ? '<mark>' . e($part) . '</mark>' : e($part);
    }
    return $html;
}
