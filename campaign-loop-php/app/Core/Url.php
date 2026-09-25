<?php
declare(strict_types=1);

namespace App\Core;

final class Url
{
    private static ?string $basePath = null;

    /** Path prefix where the app lives, e.g. '' or '/loop'. */
    public static function basePath(): string
    {
        if (self::$basePath === null) {
            $cfg = (string) Config::get('base_url', '');
            if ($cfg !== '') {
                self::$basePath = rtrim((string) parse_url($cfg, PHP_URL_PATH), '/');
            } else {
                $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
                $dir = rtrim(dirname($script), '/');
                if (str_ends_with($dir, '/install')) {
                    $dir = substr($dir, 0, -8);
                }
                self::$basePath = $dir === '.' ? '' : $dir;
            }
        }
        return self::$basePath;
    }

    /** Absolute origin + base, e.g. https://example.ir/loop */
    public static function base(): string
    {
        $cfg = rtrim((string) Config::get('base_url', ''), '/');
        if ($cfg !== '') {
            return $cfg;
        }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
        return (Request::isHttps() ? 'https://' : 'http://') . $host . self::basePath();
    }

    /** @param array<string,scalar|null> $query */
    public static function to(string $path = '/', array $query = [], bool $absolute = false): string
    {
        if (str_contains($path, '?')) {
            [$path, $qsIn] = explode('?', $path, 2);
            parse_str($qsIn, $parsed);
            $query = array_merge($parsed, $query);
        }
        $path = '/' . ltrim($path, '/');
        $pretty = (bool) Config::get('pretty_urls', true);
        $prefix = $absolute ? self::base() : self::basePath();
        $qs = $query ? http_build_query(array_filter($query, static fn ($v) => $v !== null)) : '';
        if ($pretty) {
            return $prefix . ($path === '/' ? '/' : $path) . ($qs !== '' ? '?' . $qs : '');
        }
        return $prefix . '/index.php?r=' . rawurlencode($path) . ($qs !== '' ? '&' . $qs : '');
    }

    public static function asset(string $path): string
    {
        $file = APP_ROOT . '/assets/' . ltrim($path, '/');
        $v = is_file($file) ? substr((string) filemtime($file), -6) : APP_VERSION;
        return self::basePath() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
    }
}
