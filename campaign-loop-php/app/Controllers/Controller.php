<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Loop;
use App\Services\Nav;

abstract class Controller
{
    protected function ws(): int
    {
        return Auth::wsId();
    }

    /** @param array<string,mixed> $vars */
    protected function page(string $key, string $tpl, array $vars = []): void
    {
        $nav = Nav::build($key);
        View::render($tpl, array_merge($vars, ['nav' => $nav, 'pageKey' => $key]), 'app');
    }

    /** Load a campaign of the current workspace or 404; remembers it as the current campaign. @return array<string,mixed> */
    protected function campaign(string $id): array
    {
        $c = Loop::campaign($this->ws(), (int) $id);
        if (!$c) {
            Response::abort(404, 'این کمپین پیدا نشد.');
        }
        Session::set('cur_campaign', (int) $c['id']);
        return $c;
    }

    protected function flash(string $msg, string $kind = 'ok'): void
    {
        Session::flash($msg, $kind);
    }
}
