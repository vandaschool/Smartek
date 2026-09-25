<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Fmt;
use App\Core\Jalali;

/** One-page campaign report (prototype reportData). */
final class Report
{
    /** @param array<string,mixed> $c @return array<string,mixed>|null */
    public static function build(int $ws, array $c): ?array
    {
        $plan = Loop::plan((int) $c['id']);
        if (!$plan) {
            return null;
        }
        $e = Ws::engine($ws);
        $sim = Loop::latestSim((int) $c['id']);
        $simR = $sim['r'] ?? Loop::simulate($ws, $c, $plan);
        $run = !empty($c['current_run_id']) ? Loop::run((int) $c['current_run_id']) : null;
        $vr = $run['ver']['r'] ?? null;
        $vi = $run['in'] ?? null;
        $cals = $run['cals'] ?? [];
        $p = Ws::profile($ws);
        $company = (string) DB::val('SELECT name FROM workspaces WHERE id = ?', [$ws]);
        $calText = 'کالیبراسیونی اعمال نشده' . ($vr && empty($vr['calib']) ? ' — شاخه‌ی «' . $vr['cause'] . '» عمداً نرخ‌ها را دست نمی‌زند.' : '.');
        if ($cals) {
            $parts = [];
            foreach ($cals as $cal) {
                $a = Loop::j($cal['after_row']);
                $b = $a['before'];
                $parts[] = 'ردیف ' . $cal['row_label'] . ': CPI از ' . Fmt::num($b['cpi']) . ' به ' . Fmt::num($a['cpi']) . ' · CVR از ' . Fmt::dec($b['cvr'] * 100, 3) . '٪ به ' . Fmt::dec($a['cvr'] * 100, 3) . '٪ · sample_n از ' . Fmt::fa((string) $b['n']) . ' به ' . Fmt::fa((string) $a['n']);
            }
            $calText = implode(' | ', $parts);
        }
        return [
            'title' => (string) ($vi['name'] ?? $c['name']),
            'merchant' => $company . ' · هدف کسب‌وکار: ' . ($p['goal'] ?: '—') . ' · حاشیه‌ی سود ' . Fmt::pct((float) $p['margin'], 0),
            'window' => Jalali::fa((string) $c['date_from']) . ' تا ' . Jalali::fa((string) $c['date_to']),
            'chain' => [
                ['k' => 'plan_id', 'v' => $plan['code'] . ' · v' . $plan['version']],
                ['k' => 'sim_id', 'v' => $sim['code'] ?? '—'],
                ['k' => 'run_id', 'v' => $run['code'] ?? '—'],
                ['k' => 'calibration_id', 'v' => $cals ? implode(', ', array_column($cals, 'code')) : '—'],
            ],
            'perspective' => $plan['perspective'],
            'reason' => $plan['reason'] ?: '—',
            'goal' => 'هدف: ' . Fmt::num((float) $plan['goal_value']) . ' ' . $plan['goal_type'] . ' با بودجه‌ی ' . Fmt::money((float) $c['budget']) . ' تومان',
            'alloc' => array_map(static fn ($a) => [
                'label' => $a['ch'] . ' · ' . $a['seg'], 'budget' => Fmt::money((float) $a['budget']), 'share' => Fmt::pct((float) ($a['share'] ?? 0), 0),
                'cac' => Fmt::money($e->cac($a)), 'evidence' => 'rates: ' . $a['ch'] . '|' . $a['seg'] . ' · n=' . Fmt::fa((string) $a['n']),
            ], $plan['alloc']),
            'forecast' => [
                ['k' => 'نصب', 'v' => Fmt::num($simR['installs']), 'band' => Fmt::num($simR['iLow']) . ' – ' . Fmt::num($simR['iHigh'])],
                ['k' => 'خرید', 'v' => Fmt::num($simR['conv']), 'band' => Fmt::num($simR['cLow']) . ' – ' . Fmt::num($simR['cHigh'])],
                ['k' => 'درآمد', 'v' => Fmt::money($simR['rev']), 'band' => Fmt::money($simR['rLow']) . ' – ' . Fmt::money($simR['rHigh'])],
                ['k' => 'CAC پیش‌بینی‌شده', 'v' => Fmt::money($simR['cac']), 'band' => 'ROAS ' . Fmt::dec($simR['roas'], 2) . '×'],
            ],
            'verdicts' => [$simR['risk']['text'], $simR['prof']['text']],
            'result' => $vr ? [
                ['k' => 'بودجه', 'p' => Fmt::money((float) $vi['pb']), 'a' => Fmt::money((float) $vi['ab']), 'd' => Fmt::signPct($vr['dB'])],
                ['k' => 'نصب', 'p' => Fmt::num((float) $vi['pi']), 'a' => Fmt::num((float) $vi['ai']), 'd' => Fmt::signPct($vr['dI'])],
                ['k' => 'خرید', 'p' => Fmt::num((float) $vi['pc']), 'a' => Fmt::num((float) $vi['ac']), 'd' => Fmt::signPct($vr['dC'])],
            ] : [],
            'cause' => $vr['cause'] ?? '—',
            'causeText' => $vr['expl'] ?? 'نتیجه‌ی واقعی ثبت نشده است.',
            'lift' => $vr['liftText'] ?? '',
            'narrative' => $run['ver']['narrative'] ?? null,
            'calib' => $calText,
            'hasResult' => (bool) $vr,
            'closed' => $c['status'] === 'closed',
        ];
    }
}
