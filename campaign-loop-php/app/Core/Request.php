<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** @var array<string,string> */
    public static array $params = [];

    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function path(): string
    {
        if (isset($_GET['r']) && is_string($_GET['r'])) {
            $p = $_GET['r'];
        } else {
            $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            $base = Url::basePath();
            if ($base !== '' && str_starts_with($uri, $base)) {
                $uri = substr($uri, strlen($base));
            }
            if (str_starts_with($uri, '/index.php')) {
                $uri = substr($uri, 10);
            }
            $p = $uri;
        }
        $p = '/' . trim(rawurldecode($p), '/');
        return $p;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public static function str(string $key, string $default = '', int $max = 2000): string
    {
        $v = self::input($key, $default);
        if (!is_string($v)) {
            return $default;
        }
        $v = trim(str_replace("\0", '', $v));
        return mb_substr($v, 0, $max);
    }

    public static function num(string $key, ?float $default = null): ?float
    {
        $v = self::input($key);
        if ($v === null || $v === '') {
            return $default;
        }
        $n = Fmt::parseNum($v);
        return $n ?? $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $n = self::num($key);
        return $n === null ? $default : (int) round($n);
    }

    /** @return list<string> */
    public static function arr(string $key): array
    {
        $v = self::input($key, []);
        if (!is_array($v)) {
            return [];
        }
        return array_values(array_filter(array_map(static fn ($x) => is_string($x) ? trim($x) : '', $v), static fn ($x) => $x !== ''));
    }

    /** @return array<string,mixed>|null */
    public static function json(): ?array
    {
        $raw = file_get_contents('php://input');
        if (!$raw) {
            return null;
        }
        $d = json_decode($raw, true);
        return is_array($d) ? $d : null;
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function isAjax(): bool
    {
        return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
            || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
    }

    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }

    public static function device(): string
    {
        $ua = self::userAgent();
        $b = 'مرورگر';
        foreach (['Edg' => 'Edge', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $k => $n) {
            if (str_contains($ua, $k)) {
                $b = $n;
                break;
            }
        }
        $os = '';
        foreach (['iPhone' => 'iPhone', 'iPad' => 'iPad', 'Android' => 'Android', 'Windows' => 'Windows', 'Mac OS' => 'macOS', 'Linux' => 'Linux'] as $k => $n) {
            if (str_contains($ua, $k)) {
                $os = $n;
                break;
            }
        }
        return $b . ($os ? ' · ' . $os : '');
    }
}
