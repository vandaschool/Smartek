<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var list<array{0:string,1:string,2:callable|array{0:class-string,1:string},3:array<string,mixed>}> */
    private array $routes = [];

    /** @param callable|array{0:class-string,1:string} $h @param array<string,mixed> $opt */
    public function get(string $p, callable|array $h, array $opt = []): void
    {
        $this->routes[] = ['GET', $p, $h, $opt];
    }

    /** @param callable|array{0:class-string,1:string} $h @param array<string,mixed> $opt */
    public function post(string $p, callable|array $h, array $opt = []): void
    {
        $this->routes[] = ['POST', $p, $h, $opt];
    }

    /** @param callable|array{0:class-string,1:string} $h @param array<string,mixed> $opt */
    public function any(string $p, callable|array $h, array $opt = []): void
    {
        $this->routes[] = ['ANY', $p, $h, $opt];
    }

    public function dispatch(string $method, string $path): void
    {
        $allowed = false;
        foreach ($this->routes as [$m, $pattern, $handler, $opt]) {
            $re = '~^' . preg_replace('~\{(\w+)\}~', '(?P<$1>[^/]+)', $pattern) . '$~u';
            if (!preg_match($re, $path, $mm)) {
                continue;
            }
            if ($m !== 'ANY' && $m !== $method && !($m === 'GET' && $method === 'HEAD')) {
                $allowed = true;
                continue;
            }
            $params = array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY);
            Request::$params = $params;
            $this->guard($method, $opt);
            if (is_array($handler)) {
                $obj = new $handler[0]();
                $obj->{$handler[1]}(...array_values($params));
            } else {
                $handler(...array_values($params));
            }
            return;
        }
        if ($allowed) {
            Response::abort(405, 'روش درخواست مجاز نیست.');
        }
        Response::abort(404, 'این صفحه پیدا نشد یا جابه‌جا شده است.');
    }

    /** @param array<string,mixed> $opt */
    private function guard(string $method, array $opt): void
    {
        if ($method === 'POST' && empty($opt['nocsrf']) && !Csrf::check()) {
            Response::abort(419, 'نشست فرم منقضی شده است. صفحه را دوباره بارگذاری کنید.');
        }
        if (!empty($opt['auth']) && !Auth::check()) {
            if (Request::isAjax()) {
                Response::json(['error' => 'unauthenticated'], 401);
            }
            Session::set('intended', Request::path());
            Response::redirect('/login');
        }
        if (!empty($opt['auth'])) {
            $u = Auth::user();
            if (!empty($u['must_change_password']) && empty($opt['allow_pw'])) {
                Response::redirect('/settings/password');
            }
            if (Auth::ws() === null && empty($opt['nows'])) {
                Response::redirect('/workspace/new');
            }
        }
        if (!empty($opt['admin']) && !Auth::isAdmin()) {
            Response::abort(403, 'این بخش فقط برای مدیر سامانه است.');
        }
        if (!empty($opt['can'])) {
            Auth::require((string) $opt['can']);
        }
        if (!empty($opt['guest']) && Auth::check()) {
            Response::redirect('/campaigns');
        }
    }
}
