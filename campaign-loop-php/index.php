<?php
/**
 * Campaign Loop — Smartech
 * Front controller. All requests are routed here (see .htaccess).
 */
declare(strict_types=1);

define('APP_ROOT', __DIR__);
require APP_ROOT . '/app/bootstrap.php';

App\Core\App::run();
