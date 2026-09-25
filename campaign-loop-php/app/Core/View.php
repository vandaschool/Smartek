<?php
declare(strict_types=1);

namespace App\Core;

final class View
{
    /** @var array<string,mixed> */
    private static array $shared = [];

    public static function share(string $k, mixed $v): void
    {
        self::$shared[$k] = $v;
    }

    /** @param array<string,mixed> $vars */
    public static function fetch(string $template, array $vars = []): string
    {
        $file = APP_ROOT . '/app/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract(array_merge(self::$shared, $vars), EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    /** @param array<string,mixed> $vars */
    public static function render(string $template, array $vars = [], string $layout = 'app'): void
    {
        $content = self::fetch($template, $vars);
        if ($layout === '') {
            echo $content;
            return;
        }
        echo self::fetch('layouts/' . $layout, array_merge($vars, ['content' => $content]));
    }
}
