<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Sends UTF-8 HTML mail via SMTP (settings: smtp_*) or PHP mail() when SMTP is not configured.
 * Every message is also appended to storage/logs/mail.log (subject + recipient only).
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
        $text = $text !== '' ? $text : trim(html_entity_decode(strip_tags(preg_replace('~<br\s*/?>|</p>|</div>~i', "\n", $html) ?? ''), ENT_QUOTES, 'UTF-8'));
        $fromEmail = Settings::get('mail_from', 'no-reply@' . preg_replace('~^www\.~', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));
        $fromName = Settings::get('mail_from_name', 'Campaign Loop');
        $boundary = 'b' . bin2hex(random_bytes(8));
        $encSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headers = [
            'MIME-Version: 1.0',
            'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>',
            'Reply-To: ' . $fromEmail,
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";

        @file_put_contents(APP_ROOT . '/storage/logs/mail.log', sprintf("[%s] to=%s subject=%s\n", date('c'), $to, $subject), FILE_APPEND);

        $host = Settings::get('smtp_host');
        if ($host === '') {
            if (!function_exists('mail')) {
                return false;
            }
            return @mail($to, $encSubject, $body, implode("\r\n", $headers), '-f' . $fromEmail);
        }
        try {
            return self::smtp($host, (int) Settings::get('smtp_port', '587'), Settings::get('smtp_secure', 'tls'), Settings::get('smtp_user'), Settings::get('smtp_pass'), $fromEmail, $to, 'Subject: ' . $encSubject . "\r\nTo: <" . $to . ">\r\nDate: " . date('r') . "\r\nMessage-ID: <" . bin2hex(random_bytes(8)) . '@' . explode('@', $fromEmail)[1] . ">\r\n" . implode("\r\n", $headers) . "\r\n\r\n" . $body);
        } catch (\Throwable $e) {
            Log::error('smtp failed', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    private static function smtp(string $host, int $port, string $secure, string $user, string $pass, string $from, string $to, string $data): bool
    {
        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$fp) {
            throw new \RuntimeException("connect: $errstr");
        }
        stream_set_timeout($fp, 15);
        $read = static function () use ($fp): string {
            $out = '';
            while (($line = fgets($fp, 515)) !== false) {
                $out .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $out;
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): string {
            fwrite($fp, $c . "\r\n");
            $r = $read();
            if (!in_array((int) substr($r, 0, 3), $ok, true)) {
                throw new \RuntimeException('smtp: ' . trim($r));
            }
            return $r;
        };
        $read();
        $ehloHost = (string) ($_SERVER['SERVER_NAME'] ?? 'localhost');
        $cmd('EHLO ' . $ehloHost, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('starttls failed');
            }
            $cmd('EHLO ' . $ehloHost, [250]);
        }
        if ($user !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($user), [334]);
            $cmd(base64_encode($pass), [235]);
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $data = preg_replace('~^\.~m', '..', $data) ?? $data;
        $cmd($data . "\r\n.", [250]);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return true;
    }
}
