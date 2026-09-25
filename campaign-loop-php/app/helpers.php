<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Fmt;
use App\Core\Url;

function e(mixed $v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string,scalar|null> $q */
function url(string $path = '/', array $q = []): string
{
    return Url::to($path, $q);
}

function asset(string $path): string
{
    return Url::asset($path);
}

function csrf_field(): string
{
    return Csrf::field();
}

function csrf_token(): string
{
    return Csrf::token();
}

function fa(mixed $v): string
{
    return Fmt::fa((string) $v);
}

function num(float|int|null $v): string
{
    return Fmt::num($v);
}

function money(float|int|null $v): string
{
    return Fmt::money($v);
}

function pct(float|int|null $v, int $d = 1): string
{
    return Fmt::pct($v, $d);
}

function spct(float|int|null $v): string
{
    return Fmt::signPct($v);
}

function dec(float|int|null $v, int $d): string
{
    return Fmt::dec($v, $d);
}

function can(string $action): bool
{
    return Auth::can($action);
}

/** Render a disabled attribute + tooltip when the role lacks permission. */
function perm(string $action): string
{
    return Auth::can($action) ? '' : ' disabled title="' . e('نقش «' . Auth::roleLabel() . '» اجازه‌ی این کار را ندارد.') . '"';
}

/** Mono source chip for numbers (source_ref). */
function src(string $ref): string
{
    return '<span class="src" dir="ltr">' . e($ref) . '</span>';
}

/** @param array<mixed> $a */
function jsonAttr(array $a): string
{
    return e(json_encode($a, JSON_UNESCAPED_UNICODE));
}

function jdate(?string $iso): string
{
    return \App\Core\Jalali::fa($iso);
}

function jdt(?string $dt): string
{
    return \App\Core\Jalali::dt($dt);
}
