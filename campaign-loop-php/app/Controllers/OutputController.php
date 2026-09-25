<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AI\AI;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Engine\Engine;
use App\Services\Nav;
use App\Services\Ws;

final class OutputController extends Controller
{
    public function log(): void
    {
        $ws = $this->ws();
        $rows = DB::all('SELECT * FROM perspective_log WHERE workspace_id = ? ORDER BY id DESC', [$ws]);
        $dq = Ws::engine($ws)->decisionQuality($rows);
        $celebrate = (bool) Session::get('celebrate');
        Session::forget('celebrate');
        $this->page('log', 'pages/log', ['title' => 'دفترچه‌ی دیدگاه‌ها', 'rows' => $rows, 'dq' => $dq, 'celebrate' => $celebrate]);
    }

    public function logCsv(): void
    {
        $out = [['id', 'name', 'perspective', 'reason', 'cause', 'calibrated', 'planned_cac', 'actual_cac', 'actual_poas', 'goal_hit', 'lift', 'source']];
        foreach (DB::all('SELECT * FROM perspective_log WHERE workspace_id = ? ORDER BY id', [$this->ws()]) as $r) {
            $out[] = [$r['code'], $r['name'], $r['perspective'], $r['reason'], $r['cause'], $r['calibrated'] ? 'true' : 'false', round((float) $r['planned_cac']), round((float) $r['actual_cac']), $r['actual_poas'], $r['goal_hit'], $r['lift'], $r['seeded'] ? 'demo' : 'workspace'];
        }
        Response::csv('perspective-log.csv', $out);
    }

    public function dash(): void
    {
        $ws = $this->ws();
        $view = Request::str('view', 'cfo');
        if (!in_array($view, ['cfo', 'ceo', 'analyst', 'ops'], true)) {
            $view = 'cfo';
        }
        $this->page('dash', 'pages/dash', ['title' => 'داشبورد ذی‌نفع', 'view' => $view, 'd' => self::dashData($ws, $view), 'bench' => self::benchRows($ws)]);
    }

    /** @return list<array<string,mixed>> */
    public static function benchRows(int $ws): array
    {
        $e = Ws::engine($ws);
        $rates = Ws::rates($ws);
        $period = date('Y-m', strtotime('first day of last month'));
        $real = [];
        foreach (DB::all('SELECT channel, median_cac FROM benchmarks WHERE period = ? AND n_merchants >= 10', [$period]) as $b) {
            $real[$b['channel']] = (float) $b['median_cac'];
        }
        $ind = [];
        foreach (DB::all('SELECT channel, factor FROM industry_factors') as $r) {
            $ind[$r['channel']] = (float) $r['factor'];
        }
        $out = [];
        foreach (Engine::CHANNELS as $ch) {
            $rs = array_filter($rates, static fn ($r) => $r['ch'] === $ch);
            if (!$rs) {
                continue;
            }
            $mine = array_sum(array_map(static fn ($r) => $e->cac($r), $rs)) / count($rs);
            $med = $real[$ch] ?? $mine * ($ind[$ch] ?? 1);
            $d = $med > 0 ? $mine / $med - 1 : 0;
            $out[] = ['ch' => $ch, 'mine' => Fmt::money($mine), 'med' => Fmt::money($med), 'd' => Fmt::signPct($d), 'better' => $d < 0, 'real' => isset($real[$ch])];
        }
        return $out;
    }

    /** Port of prototype dashData(). @return array<string,mixed> */
    public static function dashData(int $ws, string $d): array
    {
        $history = Ws::history($ws);
        $profile = Ws::profile($ws);
        $rates = Ws::rates($ws);
        $e = Ws::engine($ws);
        $tot = ['spend' => 0.0, 'conv' => 0.0, 'rev' => 0.0, 'inst' => 0.0];
        $by = [];
        foreach ($history as $h) {
            $tot['spend'] += $h['spend'];
            $tot['conv'] += $h['conversions'];
            $tot['rev'] += $h['revenue'];
            $tot['inst'] += $h['installs'];
            $b = &$by[$h['channel']];
            $b ??= ['spend' => 0.0, 'conv' => 0.0, 'rev' => 0.0, 'inst' => 0.0, 'n' => 0];
            $b['spend'] += $h['spend'];
            $b['conv'] += $h['conversions'];
            $b['rev'] += $h['revenue'];
            $b['inst'] += $h['installs'];
            $b['n']++;
            unset($b);
        }
        $n = count($history);
        $cac = $tot['conv'] ? $tot['spend'] / $tot['conv'] : 0;
        $marginCap = $profile['margin'] * ($tot['conv'] ? $tot['rev'] / $tot['conv'] : 0);
        $losing = array_filter($history, static fn ($h) => $h['conversions'] > 0 && ($h['spend'] / $h['conversions']) > $profile['margin'] * ($h['revenue'] / max($h['conversions'], 1)));
        if (!$history) {
            return ['title' => 'داده‌ای نیست', 'cards' => [], 'list' => [], 'empty' => true];
        }
        if ($d === 'cfo') {
            $list = [];
            foreach ($by as $k => $b) {
                $list[] = ['label' => $k, 'raw' => $b['rev'] - $b['spend']];
            }
            usort($list, static fn ($a, $b) => $b['raw'] <=> $a['raw']);
            $max = max(array_map(static fn ($x) => abs($x['raw']), $list)) ?: 1;
            return [
                'title' => 'سود واحد هر کانال (درآمد − هزینه)',
                'cards' => [
                    ['label' => 'CAC کل در برابر سقف حاشیه', 'value' => Fmt::money($cac), 'note' => 'سقف حاشیه: ' . Fmt::money($marginCap) . ' تومان · ' . ($cac <= $marginCap ? 'زیر سقف' : 'بالای سقف'), 'src' => 'campaign_history + merchant_profile.gross_margin'],
                    ['label' => 'POAS کل (سود ناخالص ÷ هزینه)', 'value' => Fmt::signPct($e->poas($tot['rev'], $tot['spend'])), 'note' => 'ROAS درآمدی ' . Fmt::dec($tot['spend'] ? $tot['rev'] / $tot['spend'] : 0, 2) . '× — ولی با حاشیه‌ی ' . Fmt::pct($profile['margin'], 0) . ' سود واقعی این است', 'src' => 'campaign_history × merchant_profile.gross_margin'],
                    ['label' => 'کمپین‌های ضررده در سطح واحد', 'value' => Fmt::fa((string) count($losing)), 'note' => 'از ' . Fmt::fa((string) $n) . ' کمپین ثبت‌شده', 'src' => 'campaign_history × gross_margin'],
                    ['label' => 'CAC هدف مرچنت', 'value' => Fmt::money($profile['targetCac']), 'note' => !$profile['targetCac'] ? 'CAC هدف ثبت نشده' : ($cac <= $profile['targetCac'] ? 'CAC واقعی زیر هدف است' : 'CAC واقعی ' . Fmt::pct($cac / $profile['targetCac'] - 1, 0) . ' بالای هدف است'), 'src' => 'merchant_profile.target_cac'],
                ],
                'list' => array_map(static fn ($x) => ['label' => $x['label'], 'value' => Fmt::money($x['raw']) . ' ت', 'pct' => (int) round(abs($x['raw']) / $max * 100)], $list),
            ];
        }
        if ($d === 'ceo') {
            $months = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
            $mv = [];
            foreach ($months as $m) {
                $v = array_sum(array_map(static fn ($h) => $h['month'] === $m ? $h['conversions'] : 0, $history));
                if ($v > 0) {
                    $mv[] = ['label' => $m, 'raw' => $v];
                }
            }
            $max = max(array_merge([1], array_column($mv, 'raw')));
            $top = $by;
            uasort($top, static fn ($a, $b) => $b['spend'] <=> $a['spend']);
            $topK = (string) array_key_first($top);
            $cid = Nav::currentCampaignId();
            $goal = $cid ? DB::one('SELECT goal_value, goal_type FROM campaigns WHERE id = ?', [$cid]) : null;
            return [
                'title' => 'روند خرید ماهانه',
                'cards' => [
                    ['label' => 'حجم کل خروجی', 'value' => Fmt::num($tot['conv']), 'note' => Fmt::num($tot['inst']) . ' نصب · ' . Fmt::fa((string) $n) . ' کمپین', 'src' => 'campaign_history.conversions'],
                    ['label' => 'درآمد کل', 'value' => Fmt::money($tot['rev']), 'note' => 'میانگین ' . Fmt::money($tot['rev'] / $n) . ' به ازای هر کمپین', 'src' => 'campaign_history.revenue'],
                    ['label' => 'بیشترین سهم کانال', 'value' => $topK, 'note' => Fmt::pct($tot['spend'] ? $by[$topK]['spend'] / $tot['spend'] : 0, 0) . ' از بودجه', 'src' => 'campaign_history.spend by channel'],
                    ['label' => 'فاصله تا هدف کمپین بعدی', 'value' => $goal && $goal['goal_value'] ? Fmt::num((float) $goal['goal_value']) : '—', 'note' => $goal ? 'هدف ثبت‌شده در فرم طراحی (' . $goal['goal_type'] . ')' : 'هنوز هدفی ثبت نشده', 'src' => 'designer input.campaign_goal_value'],
                ],
                'list' => array_map(static fn ($x) => ['label' => $x['label'], 'value' => Fmt::num($x['raw']) . ' خرید', 'pct' => (int) round($x['raw'] / $max * 100)], $mv),
            ];
        }
        if ($d === 'analyst') {
            $sorted = $rates;
            usort($sorted, static fn ($a, $b) => $b['variance'] <=> $a['variance']);
            $thin = count(array_filter($rates, static fn ($r) => $r['n'] < 5));
            $smart = count(array_filter($history, static fn ($h) => in_array($h['source'], ['اسمارتک', 'adtrace', 'intrack'], true)));
            return [
                'title' => 'پرنوسان‌ترین ردیف‌های نرخ',
                'cards' => [
                    ['label' => 'میانگین نوسان جدول نرخ', 'value' => '±' . Fmt::pct($rates ? array_sum(array_column($rates, 'variance')) / count($rates) : 0, 0), 'note' => 'بازه‌ی Simulator از همین ستون ساخته می‌شود', 'src' => 'rates.variance'],
                    ['label' => 'ردیف‌های کم‌نمونه', 'value' => Fmt::fa((string) $thin), 'note' => 'زیر ۵ نمونه — پیش‌بینی این ردیف‌ها ضعیف است', 'src' => 'rates.sample_n < 5'],
                    ['label' => 'کیفیت داده‌ی تاریخی', 'value' => Fmt::pct($smart / $n, 0), 'note' => 'سهم ردیف‌های با منبع اسمارتک (نه ورود دستی)', 'src' => 'campaign_history.source'],
                    ['label' => 'کل نمونه‌های پشت نرخ‌ها', 'value' => Fmt::fa((string) array_sum(array_column($rates, 'n'))), 'note' => 'مجموع sample_n همه‌ی ردیف‌ها', 'src' => 'rates.sample_n'],
                ],
                'list' => array_map(static fn ($r) => ['label' => $r['ch'] . '/' . $r['seg'], 'value' => '±' . Fmt::pct($r['variance'], 0), 'pct' => (int) round(min($r['variance'] / 0.4, 1) * 100)], array_slice($sorted, 0, 7)),
            ];
        }
        $list = [];
        foreach ($by as $k => $b) {
            $list[] = ['label' => $k, 'raw' => $b['n']];
        }
        $max = max(array_merge([1], array_column($list, 'raw')));
        $open = (int) DB::val("SELECT COUNT(*) FROM campaigns c JOIN verifications v ON v.run_id = c.current_run_id WHERE c.workspace_id = ? AND c.status = 'live'", [$ws]);
        $cals = (int) DB::val('SELECT COUNT(*) FROM calibrations WHERE workspace_id = ? AND reverted_at IS NULL', [$ws]);
        $live = (int) DB::val("SELECT COUNT(*) FROM campaigns WHERE workspace_id = ? AND status = 'live'", [$ws]);
        $fam = $rates;
        usort($fam, static fn ($a, $b) => $b['n'] <=> $a['n']);
        return [
            'title' => 'تعداد کمپین اجراشده در هر کانال',
            'cards' => [
                ['label' => 'کمپین‌های ثبت‌شده', 'value' => Fmt::fa((string) $n), 'note' => 'حافظه‌ی عملیاتی سیستم · ' . Fmt::fa((string) $live) . ' کمپین در حال اجرا', 'src' => 'campaign_history'],
                ['label' => 'کارت انحراف باز', 'value' => Fmt::fa((string) $open), 'note' => $open ? 'نتیجه ثبت شده ولی کمپین بسته نشده' : 'کارتی در انتظار بررسی نیست', 'src' => 'verifier.cause'],
                ['label' => 'آشناترین ردیف', 'value' => $fam ? $fam[0]['ch'] : '—', 'note' => $fam ? Fmt::fa((string) $fam[0]['n']) . ' کمپین اجراشده' : '', 'src' => 'rates.sample_n'],
                ['label' => 'کالیبراسیون اعمال‌شده', 'value' => Fmt::fa((string) $cals), 'note' => $cals ? 'در این فضای کاری' : 'هنوز نرخی به‌روزرسانی نشده', 'src' => 'calibration_id'],
            ],
            'list' => array_map(static fn ($x) => ['label' => $x['label'], 'value' => Fmt::fa((string) $x['raw']) . ' کمپین', 'pct' => (int) round($x['raw'] / $max * 100)], $list),
        ];
    }

    public function method(): void
    {
        $this->page('method', 'pages/method', ['title' => 'روش‌شناسی', 'cfg' => Ws::rules($this->ws())]);
    }

    public function ask(): void
    {
        $this->page('ask', 'pages/ask', ['title' => 'پرسش از داده', 'aiOn' => AI::enabled($this->ws())]);
    }

    public function answer(): void
    {
        $ws = $this->ws();
        RateLimit::enforce('ask:' . Auth::id(), 30, 60);
        $in = Request::json() ?? $_POST;
        $q = trim(mb_substr((string) ($in['q'] ?? ''), 0, 300));
        if ($q === '') {
            Response::json(['grounded' => false, 'text' => 'پرسش خالی است.', 'src' => '—']);
        }
        $e = Ws::engine($ws);
        $rates = Ws::rates($ws);
        $m = AI::askIntent($ws, $q, $e);
        $a = $e->answerIntent($m['intent'], $m['channel'], $m['segment'], $rates);
        $p = AI::askPhrase($ws, $q, $m['intent'], $a);
        Audit::event('question_asked', ['grounded' => $a['grounded'], 'intent' => $m['intent']]);
        Response::json([
            'grounded' => $a['grounded'], 'text' => $p['text'], 'src' => $a['src'], 'intent' => $m['intent'],
            'source_refs' => array_column($a['facts'], 'ref'), 'ai' => ['provider' => AI::enabled($ws) ? 'metis' : 'mock', 'grounded' => $a['grounded'], 'fallback' => !$p['ai']],
        ]);
    }
}
