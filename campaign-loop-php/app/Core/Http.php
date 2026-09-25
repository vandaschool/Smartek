<?php
declare(strict_types=1);

namespace App\Core;

/** Minimal HTTP client on cURL (falls back to streams). */
final class Http
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string,error:string,ms:int}
     */
    public static function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeoutMs = 10000): array
    {
        $start = microtime(true);
        $h = [];
        foreach ($headers as $k => $v) {
            $h[] = $k . ': ' . $v;
        }
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $h,
                CURLOPT_TIMEOUT_MS => $timeoutMs,
                CURLOPT_CONNECTTIMEOUT_MS => min(5000, $timeoutMs),
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
            $resp = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $err = $resp === false ? curl_error($ch) : '';
            curl_close($ch);
            return ['status' => $status, 'body' => $resp === false ? '' : (string) $resp, 'error' => $err, 'ms' => (int) ((microtime(true) - $start) * 1000)];
        }
        $ctx = stream_context_create(['http' => [
            'method' => $method,
            'header' => implode("\r\n", $h),
            'content' => $body ?? '',
            'timeout' => $timeoutMs / 1000,
            'ignore_errors' => true,
        ]]);
        $resp = @file_get_contents($url, false, $ctx);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d+)~', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        return ['status' => $status, 'body' => $resp === false ? '' : $resp, 'error' => $resp === false ? 'request failed' : '', 'ms' => (int) ((microtime(true) - $start) * 1000)];
    }

    /**
     * @param array<string,mixed> $json
     * @param array<string,string> $headers
     * @return array{status:int,body:string,error:string,ms:int,data:mixed}
     */
    public static function postJson(string $url, array $json, array $headers = [], int $timeoutMs = 10000): array
    {
        $r = self::request('POST', $url, array_merge(['Content-Type' => 'application/json', 'Accept' => 'application/json'], $headers), json_encode($json, JSON_UNESCAPED_UNICODE), $timeoutMs);
        $r['data'] = json_decode($r['body'], true);
        return $r;
    }
}
