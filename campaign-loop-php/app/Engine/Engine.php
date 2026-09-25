<?php
declare(strict_types=1);

namespace App\Engine;

use App\Core\Fmt;

/**
 * Layer 1 — deterministic engine.
 * Faithful PHP port of the prototype `class Component` (engine/engine.js).
 * Deviations from the prototype are marked with "FIX Bn" and documented in PRD v6 §18.
 *
 * Rate row shape: ['id'?, 'ch','seg','type','cpi','cvr','aov','variance','n','ceiling','lift','d7','d30','fraud','age','source'?]
 */
final class Engine
{
    public const CHANNELS = ['گوگل', 'تپسل', 'یکتانت', 'اینستاگرام', 'پوش', 'پیامک'];
    public const SEGMENTS = ['کاربر جدید', 'فعال', 'در معرض ریزش', 'پرارزش', 'بازگشتی'];
    public const OWNED = ['پوش', 'پیامک'];
    public const GOALS = ['رشد', 'سودآوری', 'نگهداشت', 'سهم بازار'];
    public const GOAL_TYPES = ['نصب', 'خرید', 'درآمد', 'نگهداشت'];
    public const RISKS = ['محافظه‌کار', 'متعادل', 'تهاجمی'];
    public const EXT = ['عادی' => 1.0, 'فصل اوج' => 1.15, 'رکود' => 0.85, 'رقابت شدید' => 0.92];
    public const BAND = ['باریک' => 0.7, 'معمول' => 1.0, 'محتاط' => 1.4];
    public const SOURCES = ['مکتوب و عددی', 'عددی ولی نامکتوب', 'تجربی و ذهنی'];
    public const CAUSES = ['ناسازگاری داده', 'انحراف اجرا', 'مقیاس، نه کیفیت', 'خطای برآورد', 'در دامنه‌ی انتظار'];
    public const CAUSE_IDS = ['ناسازگاری داده' => 'data_mismatch', 'انحراف اجرا' => 'execution', 'مقیاس، نه کیفیت' => 'scale', 'خطای برآورد' => 'estimation', 'در دامنه‌ی انتظار' => 'within'];
    public const DEFAULT_RULES = ['execTh' => 0.15, 'estTh' => 0.25, 'scaleLo' => 0.8, 'scaleHi' => 1.2, 'inflation' => 0.035, 'attrWindow' => 7, 'fraudTh' => 0.08];

    /** @var array<string,float|int> */
    public array $cfg;
    /** @var array<string,mixed> */
    public array $profile;

    /**
     * @param array<string,float|int> $cfg
     * @param array<string,mixed> $profile keys: budget, margin, targetCac, ltv, goal, season, blocked
     */
    public function __construct(array $cfg = [], array $profile = [])
    {
        $this->cfg = array_merge(self::DEFAULT_RULES, $cfg);
        $this->profile = array_merge(['budget' => 0, 'margin' => 0.0, 'targetCac' => 0, 'ltv' => 0, 'goal' => '', 'season' => '', 'blocked' => ''], $profile);
    }

    /** @return list<array{id:string,name:string,obj:string}> */
    public static function perspectives(): array
    {
        return [
            ['id' => 'cfo', 'name' => 'مدیر مالی', 'obj' => 'max_unit_profit'],
            ['id' => 'ceo', 'name' => 'مدیرعامل', 'obj' => 'max_volume'],
            ['id' => 'cmo', 'name' => 'مدیر بازاریابی', 'obj' => 'max_composite_rank'],
            ['id' => 'analyst', 'name' => 'تحلیل‌گر داده', 'obj' => 'min_variance'],
            ['id' => 'ops', 'name' => 'مسئول کمپین', 'obj' => 'max_sample_n'],
            ['id' => 'value', 'name' => 'سگمنت پرارزش', 'obj' => 'max_aov_x_cvr'],
            ['id' => 'churn', 'name' => 'سگمنت در معرض ریزش', 'obj' => 'min_reacquisition_cost'],
            ['id' => 'season', 'name' => 'فصلی و مناسبتی', 'obj' => 'max_seasonal_lift'],
            ['id' => 'compete', 'name' => 'رقابتی', 'obj' => 'min_sample_n_acceptable'],
            ['id' => 'safe', 'name' => 'محافظه‌کار', 'obj' => 'top_objective_x_0.2'],
        ];
    }

    /** @return list<array{k:string,lift:float,cpi:float}> */
    public static function defaultOccasions(): array
    {
        return [
            ['k' => 'بدون مناسبت', 'lift' => 0.0, 'cpi' => 0.0],
            ['k' => 'یلدا (آذر)', 'lift' => 0.12, 'cpi' => 0.08],
            ['k' => 'بلک‌فرایدی (آبان)', 'lift' => 0.35, 'cpi' => 0.25],
            ['k' => 'نوروز (اسفند–فروردین)', 'lift' => 0.28, 'cpi' => 0.18],
            ['k' => 'ماه رمضان', 'lift' => -0.08, 'cpi' => -0.05],
            ['k' => 'بازگشایی مدارس (شهریور)', 'lift' => 0.10, 'cpi' => 0.06],
        ];
    }

    public static function channelType(string $ch): string
    {
        return in_array($ch, self::OWNED, true) ? 'owned' : 'paid';
    }

    private static function jr(float $x): float
    {
        return Fmt::jsRound($x);
    }

    private static function M(float $n): string
    {
        return Fmt::money($n) . ' تومان';
    }

    // ------------------------------------------------------------------ core formulas

    /** @param array<string,mixed> $r */
    public function infl(array $r): float
    {
        return (1 + (float) ($this->cfg['inflation'] ?? 0)) ** (float) ($r['age'] ?? 0);
    }

    /** @param array<string,mixed> $r */
    public function cpiAdj(array $r): float
    {
        return self::jr((float) $r['cpi'] * $this->infl($r));
    }

    /** @param array<string,mixed> $r */
    public function cac(array $r): float
    {
        $cvr = (float) $r['cvr'];
        return $cvr > 0 ? $this->cpiAdj($r) / $cvr : INF;
    }

    /** installs = B/cpi * 1/(1 + 0.5*B/(ceiling*cpi)) @param array<string,mixed> $a */
    public function installsAt(array $a, float $budget): float
    {
        $cpi = $this->cpiAdj($a);
        if ($cpi <= 0) {
            return 0.0;
        }
        $k = $budget / max((float) $a['ceiling'] * $cpi, 1);
        return $budget / $cpi / (1 + 0.5 * $k);
    }

    public function poas(float $rev, float $spend): float
    {
        return $spend > 0 ? ($rev * (float) $this->profile['margin'] - $spend) / $spend : 0.0;
    }

    /** @param array<string,mixed> $r */
    public function cap(array $r): float
    {
        return (float) $this->profile['margin'] * (float) $r['aov'];
    }

    // ------------------------------------------------------------------ designer

    /**
     * @param list<array<string,mixed>> $rates
     * @param list<string> $channels
     * @param list<string> $segments
     * @return list<array<string,mixed>>
     */
    public function eligibleRows(array $rates, array $channels, array $segments): array
    {
        return array_values(array_filter($rates, static fn ($r) => in_array($r['ch'], $channels, true) && in_array($r['seg'], $segments, true)));
    }

    /** @param array<string,mixed> $r @param list<array<string,mixed>> $rows */
    public function score(string $id, array $r, array $rows): float
    {
        $cac = $this->cac($r);
        $cap = $this->cap($r);
        switch ($id) {
            case 'cfo':
                return ($cac <= $cap ? 1000 : 0) + ($cac > 0 ? $cap / $cac : 0);
            case 'ceo':
                return (float) $r['cpi'] > 0 ? 1 / (float) $r['cpi'] * 1e6 : 0;
            case 'cmo':
                $vol = 0;
                $eff = 0;
                foreach ($rows as $x) {
                    if (1 / (float) $x['cpi'] > 1 / (float) $r['cpi']) {
                        $vol++;
                    }
                    if ($this->cac($x) < $cac) {
                        $eff++;
                    }
                }
                return -($vol + $eff);
            case 'analyst':
                return -(float) $r['variance'];
            case 'ops':
                return (float) $r['n'];
            case 'value':
                return (float) $r['aov'] * (float) $r['cvr'] / 1e5;
            case 'churn':
                return ($r['seg'] === 'در معرض ریزش' ? 1000 : 0) + ($cac > 0 ? 1e6 / $cac : 0);
            case 'season':
                return (float) ($r['lift'] ?? 0);
            case 'compete':
                return ($cac <= $cap ? 100 : 30) / max((float) $r['n'], 1);
        }
        return 0.0;
    }

    /**
     * Greedy allocation with per-row capacity.
     * FIX B1: copies every rate field (incl. `lift`, `id`, `source`) so narration never sees undefined.
     * @param list<array<string,mixed>> $sorted
     * @return list<array<string,mixed>>
     */
    public function allocate(array $sorted, float $budget): array
    {
        $out = [];
        $rem = $budget;
        foreach ($sorted as $r) {
            if ($rem <= $budget * 0.005) {
                break;
            }
            $capB = (float) $r['ceiling'] * $this->cpiAdj($r);
            $b = min($rem, $capB);
            $row = $r;
            $row['budget'] = $b;
            $out[] = $row;
            $rem -= $b;
        }
        if (!$out) {
            return $out;
        }
        if ($rem > 0) {
            $out[0]['budget'] += $rem;
        }
        $tot = array_sum(array_column($out, 'budget'));
        foreach ($out as &$x) {
            $x['share'] = $tot > 0 ? $x['budget'] / $tot : 0;
        }
        unset($x);
        return $out;
    }

    /** @param list<array<string,mixed>> $alloc @return array<string,float> */
    public function expected(array $alloc): array
    {
        $inst = $conv = $rev = $budget = 0.0;
        foreach ($alloc as $a) {
            $i = $this->installsAt($a, (float) $a['budget']);
            $c = $i * (float) $a['cvr'];
            $inst += $i;
            $conv += $c;
            $rev += $c * (float) $a['aov'];
            $budget += (float) $a['budget'];
        }
        return [
            'installs' => $inst, 'conv' => $conv, 'revenue' => $rev, 'budget' => $budget,
            'cac' => $conv > 0 ? $budget / $conv : 0.0,
            'roas' => $budget > 0 ? $rev / $budget : 0.0,
            'poas' => $this->poas($rev, $budget),
        ];
    }

    public function fitScore(string $pid, string $goal, string $risk): int
    {
        $byGoal = [
            'رشد' => ['ceo' => 3, 'cmo' => 2, 'compete' => 2, 'season' => 1],
            'سودآوری' => ['cfo' => 3, 'value' => 2, 'churn' => 2, 'analyst' => 1],
            'نگهداشت' => ['churn' => 3, 'value' => 2, 'ops' => 2, 'cfo' => 1],
            'سهم بازار' => ['ceo' => 3, 'compete' => 3, 'cmo' => 2, 'season' => 1],
        ][$goal] ?? [];
        $byRisk = [
            'محافظه‌کار' => ['safe' => 3, 'analyst' => 2, 'ops' => 2],
            'متعادل' => ['cmo' => 2, 'cfo' => 1, 'value' => 1],
            'تهاجمی' => ['ceo' => 2, 'compete' => 2, 'season' => 1],
        ][$risk] ?? [];
        return ($byGoal[$pid] ?? 0) + ($byRisk[$pid] ?? 0);
    }

    /** Stable sort helper (PHP 8 usort is stable). @param list<mixed> $a */
    private static function sortBy(array $a, callable $cmp): array
    {
        usort($a, $cmp);
        return $a;
    }

    /**
     * @param list<array<string,mixed>> $rates
     * @param array<string,mixed> $di keys: budget, channels, segments, risk
     * @return list<array<string,mixed>>
     */
    public function buildInsights(array $rates, array $di): array
    {
        $rows = $this->eligibleRows($rates, $di['channels'], $di['segments']);
        if (!$rows) {
            return [];
        }
        $budget = (float) $di['budget'];
        $goal = (string) $this->profile['goal'];
        $risk = (string) $di['risk'];
        $persp = self::perspectives();
        $ranked = self::sortBy(array_values(array_filter($persp, static fn ($p) => $p['id'] !== 'safe')), fn ($a, $b) => $this->fitScore($b['id'], $goal, $risk) <=> $this->fitScore($a['id'], $goal, $risk));
        $topId = $ranked[0]['id'];

        $out = [];
        foreach ($persp as $p) {
            $useId = $p['id'] === 'safe' ? $topId : $p['id'];
            $sorted = self::sortBy($rows, fn ($a, $b) => $this->score($useId, $b, $rows) <=> $this->score($useId, $a, $rows));
            $alloc = $this->allocate($sorted, $p['id'] === 'safe' ? $budget * 0.2 : $budget);
            $e = $this->expected($alloc);
            $w = $alloc[0] ?? $sorted[0];
            $t = $this->narrate($p, $w, $alloc, $e, $rows, $topId, $budget);
            $out[] = [
                'id' => $p['id'], 'perspective' => $p['name'], 'objective' => $p['obj'],
                'claim' => $t['claim'], 'proposal' => $t['proposal'], 'evidence' => $t['evidence'],
                'risk' => $t['risk'], 'successMetric' => $t['success'],
                'alloc' => $alloc, 'exp' => $e,
                'topRow' => $w['ch'] . '|' . $w['seg'],
                'fit' => $this->fitScore($p['id'], $goal, $risk) + ($p['id'] === 'safe' ? $this->fitScore('safe', $goal, $risk) : 0),
            ];
        }
        return self::sortBy($out, static fn ($a, $b) => $b['fit'] <=> $a['fit']);
    }

    /**
     * @param array{id:string,name:string,obj:string} $p
     * @param array<string,mixed> $w
     * @param list<array<string,mixed>> $alloc
     * @param array<string,float> $e
     * @param list<array<string,mixed>> $rows
     * @return array{claim:string,proposal:string,evidence:string,risk:string,success:string}
     */
    public function narrate(array $p, array $w, array $alloc, array $e, array $rows, string $topId, float $totalBudget): array
    {
        $M = static fn (float $n) => self::M($n);
        $wCac = $this->cac($w);
        $wCap = $this->cap($w);
        $share = static fn (array $a) => Fmt::pct($a['share'] ?? 1, 0);
        $second = $alloc[1] ?? null;
        $evi = static fn (array $r, string $extra = '') => 'ردیف rates: ' . $r['ch'] . '|' . $r['seg'] . ' · ' . Fmt::fa((string) $r['n']) . ' کمپین پشت این ردیف' . ($extra !== '' ? ' · ' . $extra : '');
        $prop = 'بودجه بین ' . Fmt::fa((string) count($alloc)) . ' ردیف: ' . implode(' · ', array_map(static fn ($a) => $a['ch'] . '/' . $a['seg'] . ' ' . $share($a), $alloc));
        $base = ['proposal' => $prop, 'evidence' => $evi($w, 'CAC ' . $M($wCac)), 'risk' => '—', 'success' => '—'];
        $f1 = static fn (float $x) => Fmt::fa(Fmt::toFixed($x, 1));

        switch ($p['id']) {
            case 'cfo':
                return array_merge($base, [
                    'claim' => 'از دید سود: روی ' . $w['ch'] . ' و سگمنت «' . $w['seg'] . '» تمرکز کن. CAC آنجا ' . $M($wCac) . ' است در برابر سقف حاشیه‌ی ' . $M($wCap) . ' — یعنی ' . Fmt::pct(1 - $wCac / $wCap, 0) . ' فاصله‌ی امن زیر سقف. CAC کل این تخصیص ' . $M($e['cac']) . ' می‌شود.',
                    'risk' => 'سقف حجم این ردیف ' . Fmt::num((float) $w['ceiling']) . ' نصب است؛ بیش از آن بودجه به ردیف‌های گران‌تر سرریز می‌شود.',
                    'success' => 'اگر CAC واقعی زیر ' . $M($wCap) . ' ماند، این انتخاب درست بوده.',
                ]);
            case 'ceo':
                return array_merge($base, [
                    'claim' => 'از دید رشد: ' . $w['ch'] . ' بیشترین حجم را به ازای بودجه می‌دهد — کل این تخصیص ' . Fmt::num($e['installs']) . ' نصب و ' . Fmt::num($e['conv']) . ' خرید. CAC بالاتر است (' . $M($e['cac']) . ') ولی اگر هدف سهم بازار است، این گزینه است.',
                    'evidence' => $evi($w, 'CPI ' . $M((float) $w['cpi'])),
                    'risk' => 'کارایی فدای حجم می‌شود؛ اگر حاشیه‌ی سود کم شود، این تخصیص در سطح واحد ضررده است.',
                    'success' => 'اگر نصب واقعی بالای ' . Fmt::num($e['installs'] * 0.85) . ' ماند، حجم محقق شده.',
                ]);
            case 'cmo':
                return array_merge($base, [
                    'claim' => 'از دید تعادل: تقسیم بودجه بین ' . $w['ch'] . ' (' . $share($w) . ') و ' . ($second ? $second['ch'] . ' (' . $share($second) . ')' : 'ردیف بعدی') . ' هم حجم می‌دهد (' . Fmt::num($e['installs']) . ' نصب) هم CAC کل را روی ' . $M($e['cac']) . ' نگه می‌دارد.',
                    'risk' => 'مدیریت دو کانال هم‌زمان بار عملیاتی و اندازه‌گیری بیشتری دارد.',
                    'success' => 'اگر هم نصب بالای ' . Fmt::num($e['installs'] * 0.85) . ' و هم CAC زیر ' . $M($e['cac'] * 1.15) . ' بماند.',
                ]);
            case 'analyst':
                return array_merge($base, [
                    'claim' => 'از دید قابلیت پیش‌بینی: ' . $w['ch'] . ' روی سگمنت «' . $w['seg'] . '» کمترین نوسان تاریخی را دارد (±' . Fmt::pct((float) $w['variance'], 0) . '). اگر تصمیم بعدی به این نتیجه وابسته است، اینجا امن‌تر است.',
                    'evidence' => $evi($w, 'نوسان ±' . Fmt::pct((float) $w['variance'], 0)),
                    'risk' => 'کم‌نوسان بودن به معنی بهینه بودن نیست؛ ممکن است CAC از گزینه‌های پرنوسان بدتر باشد.',
                    'success' => 'اگر نتیجه‌ی واقعی داخل بازه‌ی ±' . Fmt::pct((float) $w['variance'], 0) . ' افتاد، مدل نرخ قابل اتکاست.',
                ]);
            case 'ops':
                return array_merge($base, [
                    'claim' => 'از دید اجرا: ' . $w['ch'] . ' را ' . Fmt::fa((string) $w['n']) . ' بار اجرا کرده‌اید — سریع‌ترین راه‌اندازی و کمترین ریسک عملیاتی. CAC کل ' . $M($e['cac']) . '.',
                    'evidence' => $evi($w, 'بیشترین sample_n میان ردیف‌های مجاز'),
                    'risk' => 'آشنایی، سوگیری است: کانال‌های کم‌آزموده هیچ‌وقت شانس نمی‌گیرند.',
                    'success' => 'اگر کمپین در کمتر از یک هفته و بدون انحراف اجرا راه افتاد.',
                ]);
            case 'value':
                return array_merge($base, [
                    'claim' => 'از دید ارزش مشتری: سگمنت «' . $w['seg'] . '» ارزش سفارش ' . $M((float) $w['aov']) . ' دارد. با همین بودجه درآمد مورد انتظار ' . $M($e['revenue']) . ' و ROAS ' . $f1($e['roas']) . ' برابر می‌شود — بودجه‌ی کمتر، بازگشت بیشتر.',
                    'evidence' => $evi($w, 'AOV ' . $M((float) $w['aov']) . ' · CVR ' . Fmt::pct((float) $w['cvr'])),
                    'risk' => 'اندازه‌ی این سگمنت کوچک است (سقف ' . Fmt::num((float) $w['ceiling']) . ' نصب)؛ مقیاس‌پذیر نیست.',
                    'success' => 'اگر ROAS واقعی بالای ' . $f1($e['roas'] * 0.8) . ' ماند.',
                ]);
            case 'churn':
                return array_merge($base, [
                    'claim' => 'از دید نگهداشت: به‌جای جذب جدید، سگمنت در معرض ریزش را هدف بگیر — CAC جذب مجدد ' . $M($wCac) . ' در برابر ' . $M($this->newUserCac($rows)) . ' برای کاربر جدید.',
                    'evidence' => $evi($w, 'CAC جذب مجدد ' . $M($wCac)),
                    'risk' => 'سقف این سگمنت محدود است و اثر آن روی رشد مطلق صفر است.',
                    'success' => 'اگر نرخ بازگشت این سگمنت بالای CVR تاریخی ' . Fmt::pct((float) $w['cvr']) . ' ماند.',
                ]);
            case 'season':
                $lift = (float) ($w['lift'] ?? 0);
                return array_merge($base, [
                    'claim' => 'از دید زمان‌بندی: ' . $w['ch'] . ' در دوره‌های اوج تاریخی ' . Fmt::pct($lift, 0) . ' بهتر عمل کرده. ' . $this->profile['season'] . ' — جابه‌جایی بازه‌ی کمپین را بررسی کن.',
                    'evidence' => $evi($w, 'ضریب اوج تاریخی +' . Fmt::pct($lift, 0)),
                    'risk' => 'رقابت و CPI هم در اوج بالا می‌رود؛ این ضریب تضمین‌شده نیست.',
                    'success' => 'اگر CVR واقعی در بازه‌ی اوج بالای ' . Fmt::pct((float) $w['cvr'] * (1 + $lift)) . ' بود.',
                ]);
            case 'compete':
                return array_merge($base, [
                    'claim' => 'از دید تمایز: ' . $w['ch'] . ' روی «' . $w['seg'] . '» کمتر استفاده شده (فقط ' . Fmt::fa((string) $w['n']) . ' کمپین) ولی نرخش قابل‌قبول است — CAC ' . $M($wCac) . '. فرصت کم‌رقابت‌تر.',
                    'evidence' => $evi($w, 'کمترین sample_n با CAC قابل‌قبول'),
                    'risk' => 'داده‌ی کم یعنی بازه‌ی عدم‌قطعیت عریض؛ پیش‌بینی این ردیف ضعیف‌تر است.',
                    'success' => 'اگر CAC واقعی زیر ' . $M($wCac * 1.3) . ' ماند، ردیف ارزش سرمایه‌گذاری بیشتر دارد.',
                ]);
        }
        return array_merge($base, [
            'claim' => 'از دید ریسک: پیش از تعهد کامل، ۲۰٪ بودجه (' . $M($e['budget']) . ') را روی ' . $w['ch'] . ' تست کن. هزینه‌ی یادگیری ' . $M($e['budget']) . ' است در برابر ریسک ' . $M($totalBudget) . ' بودجه‌ی کل.',
            'evidence' => $evi($w, 'همان تابع هدف دیدگاه برتر (' . $topId . ') با قید بودجه × ۰٫۲'),
            'risk' => 'نمونه‌ی کوچک ممکن است به آستانه‌ی یادگیری کانال نرسد و نتیجه‌اش بی‌معنا شود.',
            'success' => 'اگر CAC تست زیر ' . $M($wCap) . ' درآمد، بودجه‌ی کامل آزاد شود.',
        ]);
    }

    /** @param list<array<string,mixed>> $rows */
    public function newUserCac(array $rows): float
    {
        $ns = array_filter($rows, static fn ($r) => $r['seg'] === 'کاربر جدید');
        if (!$ns) {
            return 0.0;
        }
        return min(array_map(fn ($r) => $this->cac($r), $ns));
    }

    /** Evidence validity gate (acceptance #3): must reference rates + sample_n and contain no NaN/INF. @param array<string,mixed> $ins */
    public static function insightValid(array $ins): bool
    {
        foreach (['claim', 'proposal', 'evidence', 'risk', 'successMetric'] as $k) {
            $v = (string) ($ins[$k] ?? '');
            if ($v === '' || str_contains($v, 'NaN') || str_contains($v, 'INF') || str_contains($v, 'Infinity')) {
                return false;
            }
        }
        return str_contains((string) $ins['evidence'], 'rates:') && str_contains((string) $ins['evidence'], 'کمپین پشت این ردیف');
    }

    /**
     * Combine primary + secondary insight allocations.
     * @param list<array<string,mixed>> $insights
     * @return list<array<string,mixed>>|null
     */
    public function mergedAlloc(array $insights, ?string $sel, ?string $sec, int $mix, float $totalBudget): ?array
    {
        $A = null;
        $B = null;
        foreach ($insights as $i) {
            if ($i['id'] === $sel) {
                $A = $i;
            }
            if ($sec !== null && $i['id'] === $sec) {
                $B = $i;
            }
        }
        if (!$A) {
            return null;
        }
        if (!$B) {
            return $A['alloc'];
        }
        $w = $mix / 100;
        $map = [];
        $add = static function (array $alloc, float $factor) use (&$map): void {
            foreach ($alloc as $a) {
                $k = $a['ch'] . '|' . $a['seg'];
                if (!isset($map[$k])) {
                    $map[$k] = $a;
                    $map[$k]['budget'] = 0.0;
                }
                $map[$k]['budget'] += (float) $a['budget'] * $factor;
            }
        };
        $sA = $A['exp']['budget'] ? $totalBudget * $w / $A['exp']['budget'] : 0;
        $sB = $B['exp']['budget'] ? $totalBudget * (1 - $w) / $B['exp']['budget'] : 0;
        $add($A['alloc'], $sA);
        $add($B['alloc'], $sB);
        $out = array_values($map);
        $spill = 0.0;
        foreach ($out as &$x) {
            $capB = (float) $x['ceiling'] * $this->cpiAdj($x) * 1.5;
            if ($x['budget'] > $capB) {
                $spill += $x['budget'] - $capB;
                $x['budget'] = $capB;
                $x['capped'] = true;
            }
        }
        unset($x);
        if ($spill > 0) {
            $free = array_keys(array_filter($out, static fn ($x) => empty($x['capped'])));
            if ($free) {
                foreach ($free as $idx) {
                    $out[$idx]['budget'] += $spill / count($free);
                }
            } else {
                $out[0]['budget'] += $spill;
            }
        }
        $tot = array_sum(array_column($out, 'budget'));
        foreach ($out as &$x) {
            $x['share'] = $tot > 0 ? $x['budget'] / $tot : 0;
        }
        unset($x);
        return self::sortBy($out, static fn ($a, $b) => $b['budget'] <=> $a['budget']);
    }

    // ------------------------------------------------------------------ simulator

    /**
     * @param list<array<string,mixed>> $alloc
     * @param array{band?:string,ext?:string,occasion?:array{lift:float,cpi:float}} $opts
     * @return array<string,mixed>
     */
    public function simulate(array $alloc, string $goalType, float $goalValue, array $opts = []): array
    {
        $occ = $opts['occasion'] ?? ['lift' => 0.0, 'cpi' => 0.0];
        $f = (self::EXT[$opts['ext'] ?? 'عادی'] ?? 1.0) * (1 + (float) $occ['lift']) / (1 + (float) $occ['cpi']);
        $k = self::BAND[$opts['band'] ?? 'معمول'] ?? 1.0;
        $P = $this->profile;
        $inst = $iLow = $iHigh = $conv = $cLow = $cHigh = $rev = $rLow = $rHigh = $budget = $paid = $owned = $fraudInst = $ret30 = 0.0;
        $rows = [];
        foreach ($alloc as $a) {
            $v = min((float) $a['variance'] * $k, 0.6);
            $i = $this->installsAt($a, (float) $a['budget']) * $f;
            $fr = ($a['type'] ?? self::channelType($a['ch'])) === 'paid' ? (float) ($a['fraud'] ?? 0) : 0.0;
            $iClean = $i * (1 - $fr);
            $c = $iClean * (float) $a['cvr'];
            $spread = $v * 0.85;
            $inst += $iClean;
            $iLow += $iClean * (1 - $v * 0.7);
            $iHigh += $iClean * (1 + $v * 0.7);
            $conv += $c;
            $cLow += $c * (1 - $spread);
            $cHigh += $c * (1 + $spread);
            $rev += $c * (float) $a['aov'];
            $rLow += $c * (1 - $spread) * (float) $a['aov'];
            $rHigh += $c * (1 + $spread) * (float) $a['aov'];
            $budget += (float) $a['budget'];
            $fraudInst += $i * $fr;
            $ret30 += $iClean * (float) ($a['d30'] ?? 0);
            if (($a['type'] ?? '') === 'owned') {
                $owned += (float) $a['budget'];
            } else {
                $paid += (float) $a['budget'];
            }
            $rows[] = [
                'ch' => $a['ch'], 'seg' => $a['seg'], 'label' => $a['ch'] . ' · ' . $a['seg'],
                'kind' => ($a['type'] ?? '') === 'owned' ? 'کانال خودی' : 'جذب پولی',
                'budget' => (float) $a['budget'], 'share' => (float) ($a['share'] ?? 0),
                'installs' => $iClean, 'iLow' => $iClean * (1 - $v * 0.7), 'iHigh' => $iClean * (1 + $v * 0.7),
                'conv' => $c, 'cLow' => $c * (1 - $spread), 'cHigh' => $c * (1 + $spread),
                'revenue' => $c * (float) $a['aov'], 'aov' => (float) $a['aov'],
            ];
        }
        $cac = $conv ? $budget / $conv : 0.0;
        $aovW = $conv ? $rev / $conv : 0.0;
        $marginCap = (float) $P['margin'] * $aovW;
        $poas = $this->poas($rev, $budget);
        $payback = $marginCap > 0 ? $cac / ($marginCap * 1.2) : 0.0;
        $ltvCac = $cac > 0 && $P['ltv'] ? (float) $P['ltv'] / $cac : 0.0;
        $target = $goalType === 'نصب' ? $inst : ($goalType === 'درآمد' ? $rev : $conv);
        $tLow = $goalType === 'نصب' ? $iLow : ($goalType === 'درآمد' ? $rLow : $cLow);
        $gv = $goalValue;
        if ($gv <= 0) {
            $risk = ['kind' => 'حکم ریسک', 'text' => 'عدد هدفی ثبت نشده؛ پوشش هدف قابل ارزیابی نیست.', 'level' => 'warn'];
        } elseif ($tLow >= $gv) {
            $risk = ['kind' => 'حکم ریسک', 'text' => 'هدف پوشش داده می‌شود — حتی کران پایین بازه‌ی تجربی از هدف بالاتر است.', 'level' => 'ok'];
        } elseif ($target >= $gv) {
            $risk = ['kind' => 'حکم ریسک', 'text' => 'هدف در دامنه است ولی تضمین‌شده نیست: مورد انتظار ' . Fmt::num($target) . ' در برابر هدف ' . Fmt::num($gv) . '، کران پایین ' . Fmt::num($tLow) . '.', 'level' => 'warn'];
        } else {
            $risk = ['kind' => 'حکم ریسک', 'text' => 'با این بودجه هدف محقق نمی‌شود — کسری ' . Fmt::num($gv - $target) . ' ' . $goalType . '. با بازده نزولی، افزایش بودجه کسری را خطی جبران نمی‌کند.', 'level' => 'bad'];
        }
        $prof = $poas < 0
            ? ['kind' => 'حکم سودآوری (POAS)', 'text' => 'سود ناخالص پیش‌بینی‌شده از هزینه کمتر است (POAS ' . Fmt::signPct($poas) . '). CAC ' . Fmt::money($cac) . ' در برابر حاشیه‌ی هر سفارش ' . Fmt::money($marginCap) . ' تومان — بازگشت فقط با خرید تکراری ممکن است (دوره‌ی بازگشت ' . Fmt::dec($payback, 1) . ' ماه).', 'level' => 'bad']
            : ['kind' => 'حکم سودآوری (POAS)', 'text' => 'سود ناخالص از هزینه بیشتر است (POAS ' . Fmt::signPct($poas) . '). CAC ' . Fmt::money($cac) . ' زیر حاشیه‌ی هر سفارش ' . Fmt::money($marginCap) . ' تومان.', 'level' => 'ok'];
        return [
            'installs' => $inst, 'iLow' => $iLow, 'iHigh' => $iHigh, 'conv' => $conv, 'cLow' => $cLow, 'cHigh' => $cHigh,
            'rev' => $rev, 'rLow' => $rLow, 'rHigh' => $rHigh, 'cac' => $cac, 'roas' => $budget ? $rev / $budget : 0.0, 'poas' => $poas,
            'payback' => $payback, 'ltvCac' => $ltvCac, 'ret30' => $ret30, 'fraudInst' => $fraudInst, 'paid' => $paid, 'owned' => $owned,
            'budget' => $budget, 'aovW' => $aovW, 'marginCap' => $marginCap, 'rows' => $rows, 'risk' => $risk, 'prof' => $prof,
            'coverage' => $gv ? $target / $gv : 0.0, 'target' => $target, 'tLow' => $tLow, 'goalType' => $goalType, 'goalValue' => $gv,
            'factor' => $f, 'band' => $opts['band'] ?? 'معمول', 'ext' => $opts['ext'] ?? 'عادی',
        ];
    }

    /** @param list<array<string,mixed>> $alloc @return list<array<string,mixed>> */
    public function scaleAlloc(array $alloc, float $factor): array
    {
        return array_map(static function ($a) use ($factor) {
            $a['budget'] = (float) $a['budget'] * $factor;
            return $a;
        }, $alloc);
    }

    /** Move pct of row 1's budget (or of `$movable` when given) to row 2. @param list<array<string,mixed>> $alloc @return list<array<string,mixed>> */
    public function shiftAlloc(array $alloc, float $pct, ?float $movable = null): array
    {
        if (count($alloc) < 2) {
            return $alloc;
        }
        $out = $alloc;
        $mv = ($movable ?? (float) $out[0]['budget']) * $pct;
        $out[0]['budget'] -= $mv;
        $out[1]['budget'] += $mv;
        $tot = array_sum(array_column($out, 'budget'));
        foreach ($out as &$x) {
            $x['share'] = $tot > 0 ? $x['budget'] / $tot : 0;
        }
        unset($x);
        return $out;
    }

    /**
     * What-if scenario table (sim page).
     * @param list<array<string,mixed>> $alloc
     * @return array<string,mixed>
     */
    public function whatIf(array $alloc, int $budgetPct, int $shiftPct): array
    {
        $base = $this->expected($alloc);
        $altAlloc = $this->shiftAlloc($this->scaleAlloc($alloc, 1 + $budgetPct / 100), $shiftPct / 100);
        $alt = $this->expected($altAlloc);
        $d = static fn (float $a, float $b) => $b ? Fmt::signPct($a / $b - 1) : '—';
        return [
            'alloc' => $altAlloc,
            'budgetLbl' => Fmt::signPct($budgetPct / 100),
            'shiftLbl' => Fmt::fa((string) $shiftPct) . '٪',
            'rows' => [
                ['k' => 'بودجه', 'a' => Fmt::money($base['budget']), 'b' => Fmt::money($alt['budget']), 'd' => $d($alt['budget'], $base['budget'])],
                ['k' => 'نصب', 'a' => Fmt::num($base['installs']), 'b' => Fmt::num($alt['installs']), 'd' => $d($alt['installs'], $base['installs'])],
                ['k' => 'خرید', 'a' => Fmt::num($base['conv']), 'b' => Fmt::num($alt['conv']), 'd' => $d($alt['conv'], $base['conv'])],
                ['k' => 'CAC', 'a' => Fmt::money($base['cac']), 'b' => Fmt::money($alt['cac']), 'd' => $d($alt['cac'], $base['cac'])],
                ['k' => 'POAS', 'a' => Fmt::signPct($base['poas']), 'b' => Fmt::signPct($alt['poas']), 'd' => ''],
            ],
            'note' => $budgetPct > 0 && $base['conv'] > 0 && $alt['conv'] / $base['conv'] - 1 < $budgetPct / 100
                ? 'بازده نزولی: ' . Fmt::signPct($budgetPct / 100) . ' بودجه فقط ' . Fmt::signPct($alt['conv'] / $base['conv'] - 1) . ' خرید بیشتر می‌دهد.' : '',
        ];
    }

    // ------------------------------------------------------------------ pacing

    /**
     * FIX B7: campaign length `$days` instead of fixed 30. FIX B10: realloc moves 30% of the *remaining* row-1 budget.
     * @param array<string,mixed> $sim
     * @param array{day:int|float,spend:int|float,installs:int|float,conv:int|float} $pace
     * @param list<array<string,mixed>> $alloc
     * @return array<string,mixed>
     */
    public function pace(array $sim, array $pace, int $days, array $alloc): array
    {
        $days = max(1, $days);
        $day = (float) $pace['day'];
        $frac = min(max($day ?: 0, 1), $days) / $days;
        $defs = [
            ['k' => 'هزینه', 'exp' => $sim['budget'] * $frac, 'act' => (float) $pace['spend'], 'money' => true],
            ['k' => 'نصب', 'exp' => $sim['installs'] * $frac, 'act' => (float) $pace['installs'], 'money' => false],
            ['k' => 'خرید', 'exp' => $sim['conv'] * $frac, 'act' => (float) $pace['conv'], 'money' => false],
        ];
        $rows = [];
        foreach ($defs as $x) {
            $d = $x['exp'] > 0 ? $x['act'] / $x['exp'] - 1 : 0.0;
            $rows[] = [
                'k' => $x['k'], 'expRaw' => $x['exp'], 'actRaw' => $x['act'],
                'exp' => $x['money'] ? Fmt::money($x['exp']) : Fmt::num($x['exp']),
                'act' => $x['money'] ? Fmt::money($x['act']) : Fmt::num($x['act']),
                'dev' => Fmt::signPct($d), 'raw' => $d,
                'level' => abs($d) <= 0.15 ? 'ok' : (abs($d) <= 0.3 ? 'warn' : 'bad'),
            ];
        }
        $alerts = [];
        if ((float) $pace['spend'] > 0) {
            $sp = $rows[0]['raw'];
            $cv = $rows[2]['raw'];
            if ($sp > 0.2) {
                $alerts[] = ['type' => 'pace_spend', 'level' => 'bad', 'text' => 'بودجه سریع‌تر از برنامه خرج می‌شود (' . Fmt::signPct($sp) . '). با این سرعت بودجه روز ' . Fmt::fa((string) (int) self::jr($days / (1 + $sp))) . ' تمام می‌شود.'];
            }
            if ($sp < -0.2) {
                $alerts[] = ['type' => 'pace_spend', 'level' => 'warn', 'text' => 'بودجه کندتر از برنامه خرج می‌شود (' . Fmt::signPct($sp) . '). احتمالاً پیشنهاد قیمت یا سقف روزانه پایین است.'];
            }
            if ($cv < -0.25) {
                $alerts[] = ['type' => 'pace_conv', 'level' => 'bad', 'text' => 'خرید ' . Fmt::signPct($cv) . ' عقب است. اگر تا روز ' . Fmt::fa((string) (int) ceil($days / 2)) . ' جبران نشود، هدف از دست می‌رود — جابه‌جایی بودجه به ردیف دوم تخصیص را بررسی کنید.'];
            }
            if (!$alerts) {
                $alerts[] = ['type' => 'pace_ok', 'level' => 'ok', 'text' => 'کمپین در مسیر پیش‌بینی است.'];
            }
        }
        $realloc = ['has' => false];
        if (count($alloc) >= 2 && (float) $pace['spend'] > 0 && $rows[2]['raw'] <= -0.25) {
            $remaining = (float) $alloc[0]['budget'] * (1 - $frac);
            $base = $this->expected($alloc);
            $altAlloc = $this->shiftAlloc($alloc, 0.3, $remaining);
            $alt = $this->expected($altAlloc);
            if ($alt['conv'] > $base['conv']) {
                $realloc = [
                    'has' => true, 'alloc' => $altAlloc, 'from' => $alloc[0]['ch'] . ' · ' . $alloc[0]['seg'], 'to' => $alloc[1]['ch'] . ' · ' . $alloc[1]['seg'],
                    'amount' => $remaining * 0.3, 'baseConv' => $base['conv'], 'altConv' => $alt['conv'],
                    'text' => 'انتقال ۳۰٪ بودجه‌ی باقی‌مانده از ' . $alloc[0]['ch'] . ' به ' . $alloc[1]['ch'] . ' خرید مورد انتظار را از ' . Fmt::num($base['conv']) . ' به ' . Fmt::num($alt['conv']) . ' می‌رساند.',
                ];
            }
        }
        return ['rows' => $rows, 'alerts' => $alerts, 'realloc' => $realloc, 'frac' => $frac, 'days' => $days];
    }

    // ------------------------------------------------------------------ verifier

    private static function yes(mixed $v): bool
    {
        return $v === true || $v === 'بله' || $v === '1' || $v === 1 || $v === 'yes';
    }

    /**
     * Decision tree — first match wins.
     * @param array<string,mixed> $vi keys: pb,pi,pc,ab,ai,ac,complete,matched,fraud(%),window,season,reach,holdout(%)
     * @return array<string,mixed>
     */
    public function verify(array $vi): array
    {
        $C = $this->cfg;
        $dev = static fn (float $a, float $p) => $p > 0 ? ($a - $p) / $p : 0.0;
        $pb = (float) ($vi['pb'] ?? 0);
        $pi = (float) ($vi['pi'] ?? 0);
        $pc = (float) ($vi['pc'] ?? 0);
        $ab = (float) ($vi['ab'] ?? 0);
        $ai = (float) ($vi['ai'] ?? 0);
        $ac = (float) ($vi['ac'] ?? 0);
        $dB = $dev($ab, $pb);
        $dI = $dev($ai, $pi);
        $dC = $dev($ac, $pc);
        $pCvr = $pi > 0 ? $pc / $pi : 0.0;
        $aCvr = $ai > 0 ? $ac / $ai : 0.0;
        $dCvr = $dev($aCvr, $pCvr);
        $fraud = (float) ($vi['fraud'] ?? 0) / 100;
        $window = (int) ($vi['window'] ?? $C['attrWindow']);
        $winOk = $window === (int) $C['attrWindow'];
        $hold = (float) ($vi['holdout'] ?? 0) / 100;
        $reach = (float) ($vi['reach'] ?? 0);
        $treat = $reach > 0 ? $ac / $reach : 0.0;
        $lift = $hold > 0 && $treat > 0 ? ($treat - $hold) / $treat : null;
        $calib = false;
        $block = '';
        $sub = '';
        if (!self::yes($vi['complete'] ?? 'بله') || !self::yes($vi['matched'] ?? 'بله') || !$winOk) {
            $cause = 'ناسازگاری داده';
            $sub = !$winOk ? 'window' : 'quality';
            $expl = !$winOk
                ? 'پنجره‌ی انتساب نتیجه (' . Fmt::fa((string) $window) . ' روز) با پنجره‌ی طرح (' . Fmt::fa((string) $C['attrWindow']) . ' روز) نمی‌خواند؛ مقایسه‌ی پیش‌بینی و واقعی معتبر نیست.'
                : 'داده‌ی ناقص یا شناسه‌ی ترکر نامنطبق، هر تفسیری از انحراف را بی‌اعتبار می‌کند.';
            $block = 'ابتدا کیفیت داده، انطباق ترکر و پنجره‌ی انتساب اصلاح شود؛ تا آن زمان نرخ‌ها دست نمی‌خورند.';
        } elseif ($fraud > (float) $C['fraudTh']) {
            $cause = 'ناسازگاری داده';
            $sub = 'fraud';
            $expl = 'نرخ نصب مشکوک به تقلب ' . Fmt::pct($fraud, 0) . ' است، بالاتر از آستانه‌ی ' . Fmt::pct((float) $C['fraudTh'], 0) . '. نصب‌ها قابل اتکا نیستند.';
            $block = 'نصب تقلبی نرخ کانال را به‌طور مصنوعی بهتر نشان می‌دهد؛ کالیبراسیون با این داده حافظه را آلوده می‌کند.';
        } elseif (abs($dB) > (float) $C['execTh']) {
            $cause = 'انحراف اجرا';
            $expl = 'بودجه‌ی مصرف‌شده با طرح نمی‌خواند (' . Fmt::signPct($dB) . ')؛ پیش از قضاوت درباره‌ی برآورد، اجرا بررسی شود.';
            $block = 'انحراف از اجرا آمده، نه از نرخ؛ کالیبره‌کردن در این حالت حافظه‌ی سیستم را با نویز خراب می‌کند.';
        } elseif (abs($dB) > 0.02 && $dI / $dB >= (float) $C['scaleLo'] && $dI / $dB <= (float) $C['scaleHi'] && abs($dCvr) <= 0.15) {
            $cause = 'مقیاس، نه کیفیت';
            $expl = 'کمپین بزرگ‌تر یا کوچک‌تر از طرح اجرا شد (بودجه ' . Fmt::signPct($dB) . '، نصب ' . Fmt::signPct($dI) . ') و CVR ثابت ماند (' . Fmt::signPct($dCvr) . ')؛ کارایی تغییر نکرده.';
            $block = 'نسبت انحراف نصب به بودجه نزدیک یک و CVR پایدار است؛ نرخ کانال غلط نبوده.';
        } elseif (abs($dI) > (float) $C['estTh'] || abs($dCvr) > (float) $C['estTh']) {
            $cause = 'خطای برآورد';
            $calib = true;
            $expl = 'بودجه طبق طرح خرج شد (' . Fmt::signPct($dB) . ') ولی خروجی نخواند — نصب ' . Fmt::signPct($dI) . '، CVR ' . Fmt::signPct($dCvr) . '؛ نرخ‌ها باید اصلاح شوند.';
        } else {
            $cause = 'در دامنه‌ی انتظار';
            $calib = true;
            $expl = 'بودجه و خروجی داخل دامنه‌ی انتظار ماندند؛ مشاهده به‌عنوان نمونه‌ی معتبر به نرخ‌ها اضافه می‌شود.';
        }
        $cleanInst = $ai * (1 - $fraud);
        $obsCpi = $cleanInst > 0 ? $ab / $cleanInst : 0.0;
        $obsCvr = $cleanInst > 0 ? $ac / $cleanInst : 0.0;
        if ($lift === null) {
            $liftText = 'گروه کنترل ثبت نشده — اثر افزایشی قابل سنجش نیست؛ بخشی از خریدها ممکن است ارگانیک باشد.';
        } elseif ($lift <= 0) {
            $liftText = 'گروه کنترل به همان اندازه خرید داشت — اثر افزایشی کمپین صفر یا منفی است.';
        } else {
            $liftText = Fmt::pct($lift, 0) . ' از خریدها افزایشی است؛ بقیه بدون کمپین هم اتفاق می‌افتاد.';
        }
        return [
            'dB' => $dB, 'dI' => $dI, 'dC' => $dC, 'dCvr' => $dCvr, 'cause' => $cause, 'causeId' => self::CAUSE_IDS[$cause], 'sub' => $sub,
            'expl' => $expl, 'calib' => $calib, 'block' => $block, 'obsCpi' => $obsCpi, 'obsCvr' => $obsCvr,
            'lift' => $lift, 'liftText' => $liftText, 'fraud' => $fraud, 'window' => $window,
        ];
    }

    /**
     * Multi-row verification (PRD v6 §8.7, FIX B2). Each row gets its own decision tree; overall = earliest cause in tree order.
     * @param list<array<string,mixed>> $rows each: ch,seg,pb,pi,pc,ab,ai,ac
     * @param array<string,mixed> $flags complete,matched,fraud,window,season,reach,holdout
     * @return array<string,mixed>
     */
    public function verifyRows(array $rows, array $flags): array
    {
        $tot = ['pb' => 0.0, 'pi' => 0.0, 'pc' => 0.0, 'ab' => 0.0, 'ai' => 0.0, 'ac' => 0.0];
        $per = [];
        foreach ($rows as $r) {
            foreach ($tot as $k => $_) {
                $tot[$k] += (float) ($r[$k] ?? 0);
            }
            $v = $this->verify(array_merge($flags, $r));
            $per[] = ['ch' => $r['ch'], 'seg' => $r['seg'], 'vi' => $r, 'vr' => $v];
        }
        $overall = $this->verify(array_merge($flags, $tot));
        if (count($per) > 1) {
            $idx = static fn (string $c) => (int) array_search($c, self::CAUSES, true);
            $worst = $per[0];
            foreach ($per as $p) {
                if ($idx($p['vr']['cause']) < $idx($worst['vr']['cause'])) {
                    $worst = $p;
                }
            }
            if ($idx($worst['vr']['cause']) < $idx($overall['cause'])) {
                $overall['cause'] = $worst['vr']['cause'];
                $overall['causeId'] = $worst['vr']['causeId'];
                $overall['expl'] = 'ردیف ' . $worst['ch'] . '|' . $worst['seg'] . ': ' . $worst['vr']['expl'];
                $overall['block'] = $worst['vr']['block'];
            }
            $overall['calib'] = (bool) array_filter($per, static fn ($p) => $p['vr']['calib']);
        }
        return ['overall' => $overall, 'rows' => $per, 'totals' => $tot];
    }

    /**
     * Weighted, de-inflated, de-seasoned calibration of one rate row.
     * @param array<string,mixed> $r rate row
     * @param array<string,mixed> $vr verify() result for this row
     * @return array{row:array<string,mixed>,before:array<string,float>,after:array<string,float>,weights:string,wNew:float,wOld:float}
     */
    public function calibrate(array $r, float $actualSpend, array $vr, bool $seasonal): array
    {
        $before = ['cpi' => (float) $r['cpi'], 'cvr' => (float) $r['cvr'], 'n' => (float) $r['n']];
        $typical = max((float) $r['ceiling'] * (float) $r['cpi'] * 0.4, 1);
        $wNew = min(max($actualSpend / $typical, 0.3), 3);
        $wOld = (float) $r['n'] * 0.9 ** (float) ($r['age'] ?? 0);
        $season = $seasonal ? 1 + (float) ($r['lift'] ?? 0) : 1;
        $oCpi = (float) $vr['obsCpi'] / $this->infl($r) * $season;
        $oCvr = (float) $vr['obsCvr'] / $season;
        $newCpi = ((float) $r['cpi'] * $wOld + $oCpi * $wNew) / ($wOld + $wNew);
        $newCvr = ((float) $r['cvr'] * $wOld + $oCvr * $wNew) / ($wOld + $wNew);
        $row = $r;
        $row['cpi'] = self::jr($newCpi);
        $row['cvr'] = round($newCvr, 6);
        $row['n'] = (int) $r['n'] + 1;
        $row['age'] = 0;
        $after = ['cpi' => (float) $row['cpi'], 'cvr' => (float) $row['cvr'], 'n' => (float) $row['n']];
        $weights = 'وزن مشاهده‌ی جدید ' . Fmt::dec($wNew, 2) . ' (اندازه‌ی کمپین) در برابر ' . Fmt::dec($wOld, 2) . ' (نمونه‌های قبلی با کاهش تازگی)' . ($season > 1 ? ' · اثر فصل حذف شد' : '');
        return ['row' => $row, 'before' => $before, 'after' => $after, 'weights' => $weights, 'wNew' => $wNew, 'wOld' => $wOld];
    }

    /** @return list<array{label:string,vi:array<string,mixed>}> */
    public static function presets(): array
    {
        return [
            ['label' => 'خطای برآورد', 'vi' => ['name' => 'یکتانت فعال — مهر', 'ch' => 'یکتانت', 'seg' => 'فعال', 'pb' => 500000000, 'pi' => 10000, 'pc' => 800, 'ab' => 520000000, 'ai' => 6800, 'ac' => 590, 'complete' => 'بله', 'matched' => 'بله', 'src' => 'مکتوب و عددی']],
            ['label' => 'انحراف اجرا', 'vi' => ['name' => 'گوگل نصب — آبان', 'ch' => 'گوگل', 'seg' => 'کاربر جدید', 'pb' => 300000000, 'pi' => 4800, 'pc' => 360, 'ab' => 195000000, 'ai' => 3100, 'ac' => 230, 'complete' => 'بله', 'matched' => 'بله', 'src' => 'عددی ولی نامکتوب']],
            ['label' => 'مقیاس، نه کیفیت', 'vi' => ['name' => 'پوش فعال — آذر', 'ch' => 'پوش', 'seg' => 'فعال', 'pb' => 100000000, 'pi' => 11000, 'pc' => 680, 'ab' => 112000000, 'ai' => 12300, 'ac' => 762, 'complete' => 'بله', 'matched' => 'بله', 'src' => 'مکتوب و عددی']],
            ['label' => 'ناسازگاری داده', 'vi' => ['name' => 'اینستاگرام — دی', 'ch' => 'اینستاگرام', 'seg' => 'کاربر جدید', 'pb' => 200000000, 'pi' => 5200, 'pc' => 250, 'ab' => 210000000, 'ai' => 2900, 'ac' => 140, 'complete' => 'خیر', 'matched' => 'خیر', 'src' => 'تجربی و ذهنی']],
            ['label' => 'در دامنه‌ی انتظار', 'vi' => ['name' => 'پیامک پرارزش — بهمن', 'ch' => 'پیامک', 'seg' => 'پرارزش', 'pb' => 120000000, 'pi' => 9200, 'pc' => 875, 'ab' => 124000000, 'ai' => 8900, 'ac' => 845, 'complete' => 'بله', 'matched' => 'بله', 'src' => 'مکتوب و عددی']],
        ];
    }

    /** @return array<string,mixed> */
    public static function blankVI(): array
    {
        return [
            'name' => 'کمپین بدون عنوان', 'ch' => 'یکتانت', 'seg' => 'فعال', 'from' => '', 'to' => '',
            'pb' => 500000000, 'pi' => 10000, 'pc' => 800, 'src' => 'مکتوب و عددی',
            'ab' => 520000000, 'ai' => 6800, 'ac' => 590, 'complete' => 'بله', 'matched' => 'بله',
            'fraud' => 3, 'window' => 7, 'season' => 'خیر', 'reach' => 0, 'holdout' => 0,
        ];
    }

    /** @return list<array{num:int,text:string,cause:string}> */
    public function treeTexts(): array
    {
        $C = $this->cfg;
        $t = [
            'داده ناقص، ترکر نامنطبق، پنجره‌ی انتساب ناهمسان یا تقلب > ' . Fmt::pct((float) $C['fraudTh'], 0) . ' → «ناسازگاری داده» · کالیبراسیون اعمال نمی‌شود',
            '|انحراف بودجه| > ' . Fmt::pct((float) $C['execTh'], 0) . ' → «انحراف اجرا» · کالیبراسیون اعمال نمی‌شود',
            'نسبت انحراف نصب÷بودجه بین ' . Fmt::dec((float) $C['scaleLo'], 1) . ' و ' . Fmt::dec((float) $C['scaleHi'], 1) . ' و CVR پایدار (±۱۵٪) → «مقیاس، نه کیفیت» · اعمال نمی‌شود',
            '|انحراف نصب| یا |انحراف CVR| > ' . Fmt::pct((float) $C['estTh'], 0) . ' → «خطای برآورد» · ✅ کالیبراسیون اعمال می‌شود',
            'در غیر این صورت → «در دامنه‌ی انتظار» · ✅ کالیبراسیون اعمال می‌شود',
        ];
        $out = [];
        foreach ($t as $i => $text) {
            $out[] = ['num' => $i + 1, 'text' => $text, 'cause' => self::CAUSES[$i]];
        }
        return $out;
    }

    // ------------------------------------------------------------------ readiness & learning

    /**
     * @param list<array<string,mixed>> $rates
     * @return array{checks:list<array<string,mixed>>,pass:int,total:int,ready:bool}
     */
    public function readiness(int $historyCount, array $rates): array
    {
        $P = $this->profile;
        $strong = count(array_filter($rates, static fn ($r) => (int) $r['n'] >= 5));
        $c = [
            ['label' => 'کمپین تاریخی ثبت‌شده', 'need' => 'حداقل ۱۵ ردیف', 'have' => Fmt::fa((string) $historyCount) . ' ردیف', 'ok' => $historyCount >= 15],
            ['label' => 'ردیف‌های نرخ با نمونه‌ی کافی', 'need' => 'حداقل ۸ ردیف با sample_n ≥ ۵', 'have' => Fmt::fa((string) $strong) . ' ردیف', 'ok' => $strong >= 8],
            ['label' => 'حاشیه‌ی سود ناخالص', 'need' => 'اجباری — دیدگاه مدیر مالی بدون آن کار نمی‌کند', 'have' => $P['margin'] ? Fmt::pct((float) $P['margin'], 0) : 'خالی', 'ok' => (bool) $P['margin']],
            ['label' => 'هدف کسب‌وکار', 'need' => 'اجباری — مبنای مرتب‌سازی اینسایت‌ها', 'have' => $P['goal'] ?: 'خالی', 'ok' => (bool) $P['goal']],
            ['label' => 'بودجه‌ی ماهانه‌ی تبلیغات', 'need' => 'اجباری — مقیاس پیشنهادها', 'have' => $P['budget'] ? Fmt::money((float) $P['budget']) . ' ت' : 'خالی', 'ok' => (bool) $P['budget']],
            ['label' => 'CAC هدف و LTV', 'need' => 'اختیاری — دیدگاه سگمنت پرارزش', 'have' => ($P['targetCac'] && $P['ltv']) ? 'ثبت شده' : 'ناقص', 'ok' => (bool) ($P['targetCac'] && $P['ltv']), 'soft' => true],
        ];
        $hard = array_values(array_filter($c, static fn ($x) => empty($x['soft'])));
        $pass = count(array_filter($hard, static fn ($x) => $x['ok']));
        return ['checks' => $c, 'pass' => $pass, 'total' => count($hard), 'ready' => $pass === count($hard)];
    }

    /**
     * Decision quality per perspective (perspective log).
     * @param list<array<string,mixed>> $log rows: perspective, cause, calibrated, planned_cac, actual_cac, actual_poas|null
     * @return array{rows:list<array<string,mixed>>,best:string}
     */
    public function decisionQuality(array $log): array
    {
        $g = [];
        foreach ($log as $r) {
            $k = (string) $r['perspective'];
            $g[$k] ??= ['n' => 0, 'err' => 0.0, 'calib' => 0, 'good' => 0];
            $planned = (float) $r['planned_cac'];
            $actual = (float) $r['actual_cac'];
            $poas = $r['actual_poas'] !== null ? (float) $r['actual_poas'] : ($actual > 0 ? $planned / $actual - 1 : 0.0);
            $good = $poas >= 0 && $r['cause'] !== 'انحراف اجرا' && $r['cause'] !== 'ناسازگاری داده';
            $g[$k]['n']++;
            $g[$k]['err'] += $planned > 0 ? abs($actual / $planned - 1) : 0;
            $g[$k]['calib'] += !empty($r['calibrated']) ? 1 : 0;
            $g[$k]['good'] += $good ? 1 : 0;
        }
        $keys = array_keys($g);
        usort($keys, static function ($a, $b) use ($g) {
            $ra = $g[$a]['good'] / $g[$a]['n'];
            $rb = $g[$b]['good'] / $g[$b]['n'];
            return $rb <=> $ra ?: $g[$b]['n'] <=> $g[$a]['n'];
        });
        $rows = [];
        foreach ($keys as $k) {
            $x = $g[$k];
            $rows[] = [
                'label' => $k, 'n' => $x['n'], 'good' => $x['good'],
                'value' => 'تصمیم درست ' . Fmt::fa((string) $x['good']) . ' از ' . Fmt::fa((string) $x['n']) . ' · خطای پیش‌بینی ' . Fmt::signPct($x['err'] / $x['n']) . ($x['n'] < 3 ? ' · نمونه کم' : ''),
                'pct' => max((int) self::jr($x['good'] / $x['n'] * 100), 6),
            ];
        }
        return ['rows' => $rows, 'best' => $keys[0] ?? '—'];
    }

    // ------------------------------------------------------------------ ask (deterministic)

    public const INTENTS = ['cheapest_cac', 'most_expensive_cac', 'most_stable', 'best_retention', 'highest_fraud', 'best_poas', 'channel_summary', 'channel_segment', 'unsupported'];

    /** Regex intent matcher (prototype `answer`) + channel/segment extraction. @return array{intent:string,channel:?string,segment:?string} */
    public function matchIntent(string $q): array
    {
        $q = trim($q);
        $ch = null;
        foreach (self::CHANNELS as $c) {
            if (str_contains($q, $c)) {
                $ch = $c;
                break;
            }
        }
        $seg = null;
        foreach (self::SEGMENTS as $s) {
            if (str_contains($q, $s)) {
                $seg = $s;
                break;
            }
        }
        $intent = 'unsupported';
        if ($q === '') {
            $intent = 'unsupported';
        } elseif ($ch !== null && $seg !== null) {
            $intent = 'channel_segment';
        } elseif (preg_match('/ارزان|کمترین.*cac|cac.*کم/iu', $q)) {
            $intent = 'cheapest_cac';
        } elseif (preg_match('/گران|بیشترین.*cac/iu', $q)) {
            $intent = 'most_expensive_cac';
        } elseif (preg_match('/نوسان|پایدار|مطمئن/iu', $q)) {
            $intent = 'most_stable';
        } elseif (preg_match('/ماندگار|retention|d30/iu', $q)) {
            $intent = 'best_retention';
        } elseif (preg_match('/تقلب|fraud/iu', $q)) {
            $intent = 'highest_fraud';
        } elseif (preg_match('/سود|poas|بهترین/iu', $q)) {
            $intent = 'best_poas';
        } elseif ($ch !== null) {
            $intent = 'channel_summary';
        }
        return ['intent' => $intent, 'channel' => $ch, 'segment' => $seg];
    }

    /** @param array<string,mixed> $r */
    public function poasFirst(array $r): float
    {
        $c = $this->cpiAdj($r);
        return $c > 0 ? ((float) $r['aov'] * (float) $this->profile['margin'] * (float) $r['cvr'] - $c) / $c : 0.0;
    }

    /**
     * Run a deterministic query for an intent. Returns rows + facts + template answer.
     * @param list<array<string,mixed>> $rates
     * @return array{grounded:bool,text:string,src:string,rows:list<array<string,mixed>>,facts:list<array<string,mixed>>}
     */
    public function answerIntent(string $intent, ?string $channel, ?string $segment, array $rates): array
    {
        $none = ['grounded' => false, 'text' => 'این پرسش به یک عدد مشخص در جدول نرخ نگاشت نشد. لایه‌ی ۲ حق ساختن عدد ندارد؛ یکی از پرسش‌های پیشنهادی را امتحان کنید.', 'src' => '—', 'rows' => [], 'facts' => []];
        if (!$rates) {
            return $none;
        }
        $by = function (callable $fn, int $dir) use ($rates): array {
            $s = $rates;
            usort($s, static fn ($a, $b) => $dir * ($fn($a) <=> $fn($b)));
            return $s[0];
        };
        $ref = static fn (array $r) => 'rates:' . $r['ch'] . '|' . $r['seg'];
        $src = static fn (array $r) => 'rates: ' . $r['ch'] . '|' . $r['seg'] . ' · n=' . Fmt::fa((string) $r['n']);
        $fact = static fn (array $r, string $field, string $label, float $value, string $display) => ['ref' => 'rates:' . $r['ch'] . '|' . $r['seg'] . '#' . $field, 'label' => $label, 'value' => $value, 'display' => $display];
        $baseFacts = function (array $r) use ($fact): array {
            return [
                $fact($r, 'cac', 'CAC با تعدیل تورم', $this->cac($r), Fmt::money($this->cac($r)) . ' تومان'),
                $fact($r, 'cvr', 'CVR', (float) $r['cvr'], Fmt::pct((float) $r['cvr'])),
                $fact($r, 'variance', 'نوسان', (float) $r['variance'], '±' . Fmt::pct((float) $r['variance'], 0)),
                $fact($r, 'sample_n', 'تعداد نمونه', (float) $r['n'], Fmt::fa((string) $r['n']) . ' کمپین'),
            ];
        };
        $pack = static fn (string $text, array $r, array $facts) => ['grounded' => true, 'text' => $text, 'src' => $src($r), 'rows' => [['ref' => $ref($r), 'channel' => $r['ch'], 'segment' => $r['seg'], 'low_sample' => (int) $r['n'] < 5, 'benchmark' => ($r['source'] ?? '') === 'benchmark']], 'facts' => $facts];

        switch ($intent) {
            case 'cheapest_cac':
                $r = $by(fn ($x) => $this->cac($x), 1);
                return $pack('کمترین CAC متعلق به ' . $r['ch'] . ' روی «' . $r['seg'] . '» است: ' . Fmt::money($this->cac($r)) . ' تومان (با تعدیل تورم).', $r, $baseFacts($r));
            case 'most_expensive_cac':
                $r = $by(fn ($x) => $this->cac($x), -1);
                return $pack('گران‌ترین جذب: ' . $r['ch'] . ' روی «' . $r['seg'] . '» با CAC ' . Fmt::money($this->cac($r)) . ' تومان.', $r, $baseFacts($r));
            case 'most_stable':
                $r = $by(static fn ($x) => (float) $x['variance'], 1);
                return $pack('پایدارترین ردیف ' . $r['ch'] . ' / ' . $r['seg'] . ' با نوسان ±' . Fmt::pct((float) $r['variance'], 0) . ' است.', $r, $baseFacts($r));
            case 'best_retention':
                $r = $by(static fn ($x) => (float) ($x['d30'] ?? 0), -1);
                return $pack('بیشترین ماندگاری روز ۳۰: ' . $r['ch'] . ' / ' . $r['seg'] . ' با ' . Fmt::pct((float) ($r['d30'] ?? 0), 0) . '.', $r, array_merge([$fact($r, 'd30', 'ماندگاری روز ۳۰', (float) $r['d30'], Fmt::pct((float) $r['d30'], 0))], $baseFacts($r)));
            case 'highest_fraud':
                $r = $by(static fn ($x) => (float) ($x['fraud'] ?? 0), -1);
                return $pack('بیشترین نرخ تقلب در ' . $r['ch'] . ' است: ' . Fmt::pct((float) ($r['fraud'] ?? 0), 0) . ' از نصب‌ها.', $r, array_merge([$fact($r, 'fraud', 'نرخ تقلب', (float) $r['fraud'], Fmt::pct((float) $r['fraud'], 0))], $baseFacts($r)));
            case 'best_poas':
                $r = $by(fn ($x) => $this->poasFirst($x), -1);
                $p = $this->poasFirst($r);
                return $pack('سودآورترین ردیف در سفارش اول: ' . $r['ch'] . ' / ' . $r['seg'] . ' با POAS ' . Fmt::signPct($p) . '.', $r, array_merge([$fact($r, 'poas', 'POAS سفارش اول', $p, Fmt::signPct($p))], $baseFacts($r)));
            case 'channel_summary':
                if ($channel === null) {
                    return $none;
                }
                $rs = array_values(array_filter($rates, static fn ($x) => $x['ch'] === $channel));
                if (!$rs) {
                    return $none;
                }
                usort($rs, fn ($a, $b) => $this->cac($a) <=> $this->cac($b));
                $r = $rs[0];
                $facts = $baseFacts($r);
                $facts[] = ['ref' => 'rates:' . $channel . '#segments_count', 'label' => 'تعداد سگمنت‌ها', 'value' => count($rs), 'display' => Fmt::fa((string) count($rs))];
                return $pack($channel . ' در ' . Fmt::fa((string) count($rs)) . ' سگمنت داده دارد؛ بهترین CAC روی «' . $r['seg'] . '»: ' . Fmt::money($this->cac($r)) . ' تومان.', $r, $facts);
            case 'channel_segment':
                foreach ($rates as $r) {
                    if ($r['ch'] === $channel && $r['seg'] === $segment) {
                        return $pack($r['ch'] . ' روی «' . $r['seg'] . '»: CAC ' . Fmt::money($this->cac($r)) . ' تومان، CVR ' . Fmt::pct((float) $r['cvr']) . '، نوسان ±' . Fmt::pct((float) $r['variance'], 0) . ' (n=' . Fmt::fa((string) $r['n']) . ').', $r, $baseFacts($r));
                    }
                }
                return ['grounded' => false, 'text' => 'برای ' . $channel . ' روی «' . $segment . '» ردیفی در جدول نرخ ثبت نشده؛ سیستم عددی نمی‌سازد.', 'src' => '—', 'rows' => [], 'facts' => []];
        }
        return $none;
    }

    // ------------------------------------------------------------------ CSV import

    /** @return array<string,array{cols:list<string>,nums:list<string>,sample:list<list<string|int|float>>}> */
    public static function importSpec(): array
    {
        return [
            'history' => ['cols' => ['name', 'channel', 'segment', 'spend', 'installs', 'conversions', 'revenue', 'month'], 'nums' => ['spend', 'installs', 'conversions', 'revenue'],
                'sample' => [['نصب پاییزه گوگل', 'گوگل', 'کاربر جدید', 180000000, 2790, 205, 174000000, 'مهر'], ['پوش هفتگی', 'پوش', 'فعال', 24000000, 2600, 158, 181000000, 'مهر']]],
            'rates' => ['cols' => ['channel', 'segment', 'unit_cost', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd30', 'fraud'], 'nums' => ['unit_cost', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd30', 'fraud'],
                'sample' => [['گوگل', 'کاربر جدید', 62000, 0.075, 850000, 0.22, 6, 25000, 0.14, 0.02], ['پیامک', 'پرارزش', 13000, 0.095, 2400000, 0.17, 3, 2600, 0.55, 0]]],
        ];
    }

    /** RFC4180-ish CSV parser identical to the prototype. @return list<list<string>> */
    public static function parseCsv(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $rows = [];
        $row = [];
        $cur = '';
        $q = false;
        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = $text[$i];
            if ($q) {
                if ($c === '"' && ($text[$i + 1] ?? '') === '"') {
                    $cur .= '"';
                    $i++;
                } elseif ($c === '"') {
                    $q = false;
                } else {
                    $cur .= $c;
                }
            } elseif ($c === '"') {
                $q = true;
            } elseif ($c === ',') {
                $row[] = $cur;
                $cur = '';
            } elseif ($c === "\n" || $c === "\r") {
                if ($c === "\r" && ($text[$i + 1] ?? '') === "\n") {
                    $i++;
                }
                $row[] = $cur;
                if (array_filter($row, static fn ($x) => trim($x) !== '')) {
                    $rows[] = $row;
                }
                $row = [];
                $cur = '';
            } else {
                $cur .= $c;
            }
        }
        $row[] = $cur;
        if (array_filter($row, static fn ($x) => trim($x) !== '')) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Validate an uploaded CSV. `$mapping` (target => source header) comes from csv_map (AI) or exact match.
     * @param array<string,string|null>|null $mapping
     * @return array{kind:string,ok:list<array<string,mixed>>,errors:list<array{line:int,msg:string}>,headers:list<string>}
     */
    public static function readImport(string $kind, string $text, ?array $mapping = null, float $unitFactor = 1.0): array
    {
        $spec = self::importSpec()[$kind];
        $rows = self::parseCsv($text);
        if (!$rows) {
            return ['kind' => $kind, 'ok' => [], 'errors' => [['line' => 0, 'msg' => 'فایل خالی است.']], 'headers' => []];
        }
        $rawHead = array_map(static fn ($h) => trim((string) $h), $rows[0]);
        $head = array_map(static fn ($h) => mb_strtolower($h), $rawHead);
        $colIdx = [];
        foreach ($spec['cols'] as $c) {
            $src = $mapping[$c] ?? null;
            $idx = $src !== null ? array_search($src, $rawHead, true) : false;
            if ($idx === false) {
                $idx = array_search($c, $head, true);
            }
            $colIdx[$c] = $idx;
        }
        $missing = array_keys(array_filter($colIdx, static fn ($i) => $i === false));
        if ($missing) {
            return ['kind' => $kind, 'ok' => [], 'errors' => [['line' => 1, 'msg' => 'ستون‌های لازم پیدا نشد: ' . implode('، ', $missing)]], 'headers' => $rawHead];
        }
        $ok = [];
        $errors = [];
        foreach (array_slice($rows, 1) as $i => $r) {
            $o = [];
            $bad = '';
            foreach ($spec['cols'] as $c) {
                $v = trim((string) ($r[$colIdx[$c]] ?? ''));
                if (in_array($c, $spec['nums'], true)) {
                    $n = Fmt::parseNum($v);
                    if ($n === null) {
                        $bad = $bad ?: ('«' . $c . '» عدد نیست');
                    }
                    if ($n !== null && in_array($c, ['spend', 'revenue', 'unit_cost', 'aov'], true)) {
                        $n *= $unitFactor;
                    }
                    $o[$c] = $n;
                } else {
                    if ($v === '') {
                        $bad = $bad ?: ('«' . $c . '» خالی است');
                    }
                    $o[$c] = $v;
                }
            }
            if (!$bad && !in_array($o['channel'], self::CHANNELS, true)) {
                $bad = 'کانال ناشناخته: ' . $o['channel'];
            }
            if (!$bad && !in_array($o['segment'], self::SEGMENTS, true)) {
                $bad = 'سگمنت ناشناخته: ' . $o['segment'];
            }
            if (!$bad && $kind === 'history' && $o['conversions'] > $o['installs'] && !in_array($o['channel'], self::OWNED, true)) {
                $bad = 'خرید از نصب بیشتر است';
            }
            if (!$bad && $kind === 'rates' && ($o['cvr'] <= 0 || $o['cvr'] > 1)) {
                $bad = 'CVR باید بین ۰ و ۱ باشد';
            }
            if ($bad) {
                $errors[] = ['line' => $i + 2, 'msg' => $bad];
            } else {
                $ok[] = $o;
            }
        }
        return ['kind' => $kind, 'ok' => $ok, 'errors' => $errors, 'headers' => $rawHead];
    }
}
