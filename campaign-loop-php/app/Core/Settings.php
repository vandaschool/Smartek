<?php
declare(strict_types=1);

namespace App\Core;

/** Global platform settings (AI, SMTP, payment, connectors) stored in the `settings` table. */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    private const SECRET = ['metis_api_key', 'smtp_pass', 'zarinpal_merchant_id', 'adtrace_api_key', 'intrack_api_key'];

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            foreach (DB::all('SELECT k, v FROM settings') as $r) {
                self::$cache[(string) $r['k']] = (string) $r['v'];
            }
        } catch (\Throwable $e) {
            self::$cache = [];
        }
    }

    public static function get(string $k, string $default = ''): string
    {
        self::load();
        $v = self::$cache[$k] ?? null;
        if ($v === null || $v === '') {
            return $default;
        }
        return in_array($k, self::SECRET, true) ? Crypto::decrypt($v) : $v;
    }

    public static function bool(string $k, bool $default = false): bool
    {
        $v = self::get($k, $default ? '1' : '0');
        return $v === '1' || $v === 'true';
    }

    public static function set(string $k, string $v): void
    {
        self::load();
        $store = in_array($k, self::SECRET, true) && $v !== '' ? Crypto::encrypt($v) : $v;
        DB::q('INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)', [$k, $store]);
        self::$cache[$k] = $store;
    }

    public static function has(string $k): bool
    {
        self::load();
        return isset(self::$cache[$k]) && self::$cache[$k] !== '';
    }

    public static function isSecret(string $k): bool
    {
        return in_array($k, self::SECRET, true);
    }
}
