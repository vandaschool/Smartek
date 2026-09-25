<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}
define('APP_VERSION', '1.0.0');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Campaign Loop requires PHP 8.1 or newer. Current: ' . PHP_VERSION);
}

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'App\\', 4) !== 0) {
        return;
    }
    $path = APP_ROOT . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require APP_ROOT . '/app/helpers.php';

$configFile = APP_ROOT . '/config.php';
if (!is_file($configFile)) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "config.php not found. Copy config.sample.php to config.php first.\n");
        exit(1);
    }
    $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    header('Location: ' . $base . '/install/');
    exit;
}

App\Core\Config::load(require $configFile);
date_default_timezone_set((string) App\Core\Config::get('timezone', 'Asia/Tehran'));
mb_internal_encoding('UTF-8');

$debug = (bool) App\Core\Config::get('debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', APP_ROOT . '/storage/logs/php-error.log');

set_exception_handler(static function (Throwable $e) use ($debug): void {
    App\Core\Log::error('uncaught', ['msg' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, (string) $e . "\n");
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $msg = $debug ? htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') : 'خطای داخلی رخ داد. کد خطا در لاگ سرور ثبت شد.';
    echo '<!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><title>خطا</title>'
        . '<body style="font-family:Tahoma,sans-serif;padding:40px;color:#00142b"><h1 style="font-size:20px">خطای سرور</h1>'
        . '<pre style="white-space:pre-wrap;font-size:13px;direction:ltr;text-align:left">' . $msg . '</pre></body></html>';
});
