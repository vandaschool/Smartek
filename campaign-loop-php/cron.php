<?php
/**
 * Campaign Loop — scheduled jobs.
 *   CLI (recommended, every 15 minutes):  php /path/to/cron.php
 *   Web (if the host has no CLI cron):    https://your-site/cron.php?token=CRON_TOKEN_FROM_CONFIG
 * Options (CLI): --force  run every job now · --job=name  run one job
 */
declare(strict_types=1);

define('APP_ROOT', __DIR__);
require APP_ROOT . '/app/bootstrap.php';

use App\Core\Config;
use App\Services\Cron;

$cli = PHP_SAPI === 'cli';
if (!$cli) {
    $token = (string) Config::get('cron_token', '');
    if ($token === '' || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit('forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
    ignore_user_abort(true);
}
set_time_limit(300);

$lock = fopen(APP_ROOT . '/storage/cache/cron.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit("another cron run is in progress\n");
}

$force = $cli ? in_array('--force', $argv, true) : !empty($_GET['force']);
$only = null;
foreach ($cli ? $argv : [] as $a) {
    if (str_starts_with($a, '--job=')) {
        $only = substr($a, 6);
    }
}
$started = microtime(true);
foreach (Cron::run($force, $only) as $job => $status) {
    echo str_pad($job, 18) . ' ' . $status . "\n";
}
echo 'done in ' . round((microtime(true) - $started) * 1000) . " ms\n";
flock($lock, LOCK_UN);
