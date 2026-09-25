<?php
declare(strict_types=1);

namespace App\AI;

use App\Core\Fmt;

/**
 * Number firewall: every number in model output must match a number present in the input context
 * (±0.5%, with documented unit conversions). Also rejects forbidden vocabulary and unknown source_refs.
 */
final class Firewall
{
    public const FORBIDDEN = ['فاصله‌ی اطمینان', 'فاصله اطمینان', 'سطح اطمینان', 'دقیق است', 'هوشمند است', 'شبیه‌سازی می‌کند', 'تضمین', 'قطعاً', 'بدون شک'];

    /** @return list<float> */
    public static function numbers(string $text): array
    {
        $t = Fmt::en($text);
        $t = str_replace(['٬'], '', $t);
        $t = preg_replace('/(?<=\d),(?=\d{3}\b)/', '', $t) ?? $t;
        $t = str_replace(['٫', '−'], ['.', '-'], $t);
        preg_match_all('/(\d+(?:\.\d+)?)\s*(میلیارد|میلیون|هزار|م(?![\p{L}])|%|٪)?/u', $t, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            $n = (float) $x[1];
            $u = $x[2] ?? '';
            if ($u === 'میلیارد') {
                $out[] = $n * 1e9;
            } elseif ($u === 'میلیون' || $u === 'م') {
                $out[] = $n * 1e6;
            } elseif ($u === 'هزار') {
                $out[] = $n * 1e3;
            } elseif ($u === '%' || $u === '٪') {
                $out[] = $n / 100;
            }
            $out[] = $n;
        }
        return $out;
    }

    /** @param mixed $ctx @return list<float> */
    public static function allowed(mixed $ctx): array
    {
        $acc = [];
        $walk = static function ($v) use (&$walk, &$acc): void {
            if (is_array($v)) {
                foreach ($v as $x) {
                    $walk($x);
                }
            } elseif (is_int($v) || is_float($v)) {
                $acc[] = (float) $v;
                $acc[] = abs((float) $v);
                $acc[] = (float) $v * 100;
                $acc[] = abs((float) $v * 100);
            } elseif (is_string($v)) {
                foreach (self::numbers($v) as $n) {
                    $acc[] = $n;
                }
            }
        };
        $walk($ctx);
        return $acc;
    }

    /**
     * @param mixed $output decoded JSON from the model
     * @param mixed $ctx context sent to the model
     * @return array{ok:bool,reason:string}
     */
    public static function check(mixed $output, mixed $ctx, bool $checkRefs = true): array
    {
        $allowed = self::allowed($ctx);
        $texts = [];
        $collect = static function ($v, $key = '') use (&$collect, &$texts): void {
            if (is_array($v)) {
                foreach ($v as $k => $x) {
                    if ($k === 'source_refs' || $k === 'mapping') {
                        continue;
                    }
                    $collect($x, (string) $k);
                }
            } elseif (is_string($v)) {
                $texts[] = $v;
            }
        };
        $collect($output);
        foreach ($texts as $t) {
            foreach (self::FORBIDDEN as $w) {
                if (mb_strpos($t, $w) !== false) {
                    return ['ok' => false, 'reason' => 'forbidden:' . $w];
                }
            }
            $nums = self::numbers($t);
            // numbers() emits both the unit-scaled and raw value; a number passes if either form matches
            foreach (self::rawNumbers($t) as [$raw, $scaled]) {
                if (!self::matches($raw, $allowed) && ($scaled === null || !self::matches($scaled, $allowed))) {
                    return ['ok' => false, 'reason' => 'number:' . $raw];
                }
            }
            unset($nums);
        }
        if ($checkRefs && is_array($output) && isset($output['source_refs']) && is_array($output['source_refs'])) {
            $refs = [];
            $walk = static function ($v) use (&$walk, &$refs): void {
                if (is_array($v)) {
                    if (isset($v['ref']) && is_string($v['ref'])) {
                        $refs[] = $v['ref'];
                    }
                    foreach ($v as $x) {
                        $walk($x);
                    }
                }
            };
            $walk($ctx);
            foreach ($output['source_refs'] as $r) {
                if (!in_array($r, $refs, true)) {
                    return ['ok' => false, 'reason' => 'ref:' . (is_string($r) ? $r : '?')];
                }
            }
        }
        return ['ok' => true, 'reason' => ''];
    }

    /** @return list<array{0:float,1:?float}> */
    private static function rawNumbers(string $text): array
    {
        $t = Fmt::en($text);
        $t = str_replace('٬', '', $t);
        $t = preg_replace('/(?<=\d),(?=\d{3}\b)/', '', $t) ?? $t;
        $t = str_replace(['٫', '−'], ['.', '-'], $t);
        preg_match_all('/(\d+(?:\.\d+)?)\s*(میلیارد|میلیون|هزار|م(?![\p{L}])|%|٪)?/u', $t, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $x) {
            $n = (float) $x[1];
            $u = $x[2] ?? '';
            $scaled = match ($u) {
                'میلیارد' => $n * 1e9,
                'میلیون', 'م' => $n * 1e6,
                'هزار' => $n * 1e3,
                '%', '٪' => $n / 100,
                default => null,
            };
            $out[] = [$n, $scaled];
        }
        return $out;
    }

    /** @param list<float> $allowed */
    private static function matches(float $n, array $allowed): bool
    {
        foreach ($allowed as $a) {
            if (abs($n - $a) <= 0.005 * max(abs($a), 1)) {
                return true;
            }
        }
        return false;
    }
}
