<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Persian number formatting — byte-for-byte port of the prototype's
 * fa / num / dec / money / pct / signPct helpers.
 */
final class Fmt
{
    private const FA = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];

    /** JS Math.round semantics (half up toward +infinity). */
    public static function jsRound(float $x): float
    {
        return floor($x + 0.5);
    }

    /** JS Number.prototype.toFixed */
    public static function toFixed(float $x, int $d): string
    {
        $s = sprintf('%.' . $d . 'f', $x);
        if ($s === '-0' || preg_match('/^-0\.0*$/', $s)) {
            // JS keeps "-0.0" for small negatives but "0" for -0; mimic "-0.0" only when x<0
            return $x < 0 ? $s : ltrim($s, '-');
        }
        return $s;
    }

    public static function fa(string|int|float $s): string
    {
        $s = (string) $s;
        $s = strtr($s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
        return str_replace('.', '٫', $s);
    }

    /** Persian/Arabic digits → Latin. */
    public static function en(string $s): string
    {
        $map = [];
        foreach (self::FA as $i => $d) {
            $map[$d] = (string) $i;
        }
        foreach (['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'] as $i => $d) {
            $map[$d] = (string) $i;
        }
        return strtr($s, $map);
    }

    public static function num(float|int|null $n): string
    {
        $n = (float) ($n ?? 0);
        $r = self::jsRound($n);
        $neg = $r < 0;
        $s = number_format(abs($r), 0, '.', '٬');
        return self::fa(($neg ? '-' : '') . $s);
    }

    public static function dec(float|int|null $n, int $d): string
    {
        return self::fa(self::toFixed((float) ($n ?? 0), $d));
    }

    public static function money(float|int|null $n): string
    {
        $n = (float) ($n ?? 0);
        $a = abs($n);
        if ($a >= 1e9) {
            return self::dec($n / 1e9, $a >= 1e10 ? 0 : 1) . ' میلیارد';
        }
        if ($a >= 1e7) {
            $m = $n / 1e6;
            return self::dec($m, abs($m - self::jsRound($m)) < 0.05 ? 0 : 1) . ' م';
        }
        return self::num($n);
    }

    public static function pct(float|int|null $x, int $dec = 1): string
    {
        return self::fa(self::toFixed((float) ($x ?? 0) * 100, $dec)) . '٪';
    }

    public static function signPct(float|int|null $x): string
    {
        $x = (float) ($x ?? 0);
        $a = abs($x);
        $s = $x >= 0 ? '+' : '−';
        return $s . self::dec($a * 100, $a < 0.01 ? 2 : ($a < 0.1 ? 1 : 0)) . '٪';
    }

    /** Parse user-entered number: Persian/Latin digits, ٬ , thousands, ٫ decimal. Returns null if invalid. */
    public static function parseNum(mixed $s): ?float
    {
        if (is_int($s) || is_float($s)) {
            return (float) $s;
        }
        $s = trim(self::en((string) $s));
        $s = str_replace(['٬', ',', ' ', "\u{200c}"], '', $s);
        $s = str_replace('٫', '.', $s);
        $s = str_replace('−', '-', $s);
        if ($s === '' || !is_numeric($s)) {
            return null;
        }
        return (float) $s;
    }
}
