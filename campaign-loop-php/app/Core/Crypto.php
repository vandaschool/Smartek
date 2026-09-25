<?php
declare(strict_types=1);

namespace App\Core;

final class Crypto
{
    private static function key(): string
    {
        $k = (string) Config::get('app_key', '');
        if ($k === '' || str_starts_with($k, 'CHANGE_ME')) {
            throw new \RuntimeException('app_key in config.php is not set. Run /install or put 64 random hex characters in app_key.');
        }
        if (strlen($k) === 64 && ctype_xdigit($k)) {
            return (string) hex2bin($k);
        }
        return hash('sha256', $k, true);
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) {
            throw new \RuntimeException('encryption failed');
        }
        return 'v1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(?string $enc): string
    {
        if (!$enc || !str_starts_with($enc, 'v1:')) {
            return '';
        }
        $raw = base64_decode(substr($enc, 3), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ct = substr($raw, 28);
        $p = openssl_decrypt($ct, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $p === false ? '' : $p;
    }

    public static function token(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function hashPassword(string $pass): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return password_hash($pass, PASSWORD_ARGON2ID);
        }
        return password_hash($pass, PASSWORD_BCRYPT, ['cost' => 11]);
    }

    public static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, self::key());
    }
}
