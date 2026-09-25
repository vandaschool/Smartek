<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function redirect(string $to): never
    {
        if (!preg_match('~^https?://~', $to)) {
            $to = Url::to($to);
        }
        header('Location: ' . $to, true, 303);
        throw new HttpStop('redirect');
    }

    public static function back(string $fallback = '/campaigns'): never
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($ref !== '' && $host !== '' && parse_url($ref, PHP_URL_HOST) === $host) {
            header('Location: ' . $ref, true, 303);
            throw new HttpStop('redirect');
        }
        self::redirect($fallback);
    }

    /** @param array<mixed>|object $data */
    public static function json(array|object $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        throw new HttpStop('json');
    }

    /** @param list<list<scalar|null>> $rows */
    public static function csv(string $filename, array $rows): never
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo "\u{FEFF}";
        $out = fopen('php://output', 'w');
        foreach ($rows as $r) {
            fputcsv($out, array_map(static fn ($x) => $x === null ? '' : (string) $x, $r), ',', '"', '\\');
        }
        fclose($out);
        throw new HttpStop('csv');
    }

    public static function download(string $filename, string $content, string $type): never
    {
        header('Content-Type: ' . $type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');
        echo $content;
        throw new HttpStop('download');
    }

    public static function abort(int $code, string $msg = ''): never
    {
        http_response_code($code);
        if (Request::isAjax()) {
            self::json(['error' => $msg ?: 'error', 'code' => $code], $code);
        }
        View::render('pages/error', ['code' => $code, 'msg' => $msg], Auth::check() ? 'app' : 'public');
        throw new HttpStop('abort');
    }

    public static function securityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; form-action 'self' https://www.zarinpal.com https://sandbox.zarinpal.com; base-uri 'self'");
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }
}
