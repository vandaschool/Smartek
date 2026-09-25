<?php
declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $dir = APP_ROOT . '/storage/sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0770, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            session_save_path($dir);
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', '1209600');
        session_name('clsid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => Url::basePath() === '' ? '/' : Url::basePath() . '/',
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public static function get(string $k, mixed $d = null): mixed
    {
        return $_SESSION[$k] ?? $d;
    }

    public static function set(string $k, mixed $v): void
    {
        $_SESSION[$k] = $v;
    }

    public static function forget(string $k): void
    {
        unset($_SESSION[$k]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function flash(string $msg, string $kind = 'ok'): void
    {
        $_SESSION['_flash'][] = ['text' => $msg, 'kind' => $kind];
    }

    /** @return list<array{text:string,kind:string}> */
    public static function takeFlash(): array
    {
        $f = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $f;
    }

    /** @param array<string,mixed> $data */
    public static function old(array $data): void
    {
        $_SESSION['_old'] = $data;
    }

    /** @return array<string,mixed> */
    public static function takeOld(): array
    {
        $o = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);
        return is_array($o) ? $o : [];
    }
}
