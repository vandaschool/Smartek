<?php
declare(strict_types=1);

namespace App\Core;

final class RateLimit
{
    /** Returns true when allowed; false when the limit is exceeded. */
    public static function hit(string $key, int $max, int $windowSec = 60): bool
    {
        $k = substr(hash('sha256', $key), 0, 64);
        $now = time();
        $row = DB::one('SELECT hits, window_start FROM rate_limits WHERE k = ?', [$k]);
        if (!$row || (int) $row['window_start'] < $now - $windowSec) {
            DB::q('INSERT INTO rate_limits (k, hits, window_start) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE hits = 1, window_start = VALUES(window_start)', [$k, $now]);
            return true;
        }
        if ((int) $row['hits'] >= $max) {
            return false;
        }
        DB::q('UPDATE rate_limits SET hits = hits + 1 WHERE k = ?', [$k]);
        return true;
    }

    public static function enforce(string $key, int $max, int $windowSec = 60): void
    {
        if (!self::hit($key, $max, $windowSec)) {
            Response::abort(429, 'تعداد درخواست‌ها بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');
        }
    }
}
