<?php
declare(strict_types=1);

namespace App\Core;

final class Log
{
    /** @param array<string,mixed> $ctx */
    public static function write(string $level, string $msg, array $ctx = []): void
    {
        $dir = APP_ROOT . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        // never log secrets
        foreach (['password', 'pass', 'api_key', 'key', 'token', 'secret', 'authorization'] as $k) {
            if (isset($ctx[$k])) {
                $ctx[$k] = '***';
            }
        }
        $line = sprintf("[%s] %s %s %s\n", date('Y-m-d H:i:s'), strtoupper($level), $msg, $ctx ? json_encode($ctx, JSON_UNESCAPED_UNICODE) : '');
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /** @param array<string,mixed> $ctx */
    public static function error(string $msg, array $ctx = []): void
    {
        self::write('error', $msg, $ctx);
    }

    /** @param array<string,mixed> $ctx */
    public static function info(string $msg, array $ctx = []): void
    {
        self::write('info', $msg, $ctx);
    }
}
