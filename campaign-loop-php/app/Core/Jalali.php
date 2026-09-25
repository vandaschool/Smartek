<?php
declare(strict_types=1);

namespace App\Core;

final class Jalali
{
    public const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** @return array{0:int,1:int,2:int} */
    public static function fromGregorian(int $gy, int $gm, int $gd): array
    {
        $gdm = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + 365 * $gy + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $gdm[$gm - 1];
        $jy = -1595 + 33 * intdiv($days, 12053);
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function toGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + 365 * $jy + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $days--;
            $gy += 100 * intdiv($days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $sal = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        $gm = 0;
        for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) {
            $gd -= $sal[$gm];
        }
        return [$gy, $gm, $gd];
    }

    public static function monthLength(int $jy, int $jm): int
    {
        if ($jm <= 6) {
            return 31;
        }
        if ($jm <= 11) {
            return 30;
        }
        [$gy, $gm, $gd] = self::toGregorian($jy, 12, 1);
        [$ny, $nm, $nd] = self::toGregorian($jy + 1, 1, 1);
        $a = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $gy, $gm, $gd));
        $b = new \DateTimeImmutable(sprintf('%04d-%02d-%02d', $ny, $nm, $nd));
        return (int) $a->diff($b)->days;
    }

    /** 'YYYY-MM-DD' → '1405/07/01' (Latin digits) */
    public static function fromIso(?string $iso): string
    {
        if (!$iso) {
            return '';
        }
        $t = strtotime(substr($iso, 0, 10));
        if ($t === false) {
            return '';
        }
        [$jy, $jm, $jd] = self::fromGregorian((int) date('Y', $t), (int) date('n', $t), (int) date('j', $t));
        return sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
    }

    /** '1405/07/01' (any digits) → 'YYYY-MM-DD' or null if invalid */
    public static function toIso(string $j): ?string
    {
        $j = Fmt::en(trim($j));
        if (!preg_match('~^(\d{4})/(\d{1,2})/(\d{1,2})$~', $j, $m)) {
            return null;
        }
        [$jy, $jm, $jd] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > self::monthLength($jy, $jm)) {
            return null;
        }
        [$gy, $gm, $gd] = self::toGregorian($jy, $jm, $jd);
        return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
    }

    /** Persian display: ۱۴۰۵/۰۷/۰۱ */
    public static function fa(?string $iso): string
    {
        return Fmt::fa(self::fromIso($iso));
    }

    public static function today(): string
    {
        return self::fromIso(date('Y-m-d'));
    }

    public static function year(?string $iso = null): int
    {
        return (int) substr(self::fromIso($iso ?? date('Y-m-d')), 0, 4);
    }

    public static function monthName(int $jm): string
    {
        return self::MONTHS[max(1, min(12, $jm)) - 1];
    }

    /** Human date-time: ۱۴۰۵/۰۷/۰۳ · ۱۴:۰۵ */
    public static function dt(?string $datetime): string
    {
        if (!$datetime) {
            return '';
        }
        $t = strtotime($datetime);
        if ($t === false) {
            return '';
        }
        return self::fa(date('Y-m-d', $t)) . ' · ' . Fmt::fa(date('H:i', $t));
    }
}
