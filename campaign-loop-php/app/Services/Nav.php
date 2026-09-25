<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Session;

/** Builds the app shell: phases, sidebar steps, loop status bar, next action, notifications. */
final class Nav
{
    public const PHASES = [
        ['label' => 'آماده‌سازی', 'sub' => 'کمپین‌ها، داده، پروفایل'],
        ['label' => 'حلقه', 'sub' => 'طرح ← پیش‌بینی ← پایش ← نتیجه'],
        ['label' => 'خروجی', 'sub' => 'گزارش و یادگیری'],
        ['label' => 'پلتفرم', 'sub' => 'اتصال، تیم، قواعد، لاگ'],
    ];

    /** @return list<array{k:string,label:string,ph:int,camp?:bool}> */
    public static function pages(): array
    {
        $p = [
            ['k' => 'campaigns', 'label' => 'کمپین‌ها', 'ph' => 0],
            ['k' => 'data', 'label' => 'داده‌ها', 'ph' => 0],
            ['k' => 'setup', 'label' => 'پروفایل و آمادگی', 'ph' => 0],
            ['k' => 'design', 'label' => 'طراحی', 'ph' => 1, 'camp' => true],
            ['k' => 'insights', 'label' => 'اینسایت‌ها', 'ph' => 1, 'camp' => true],
            ['k' => 'sim', 'label' => 'شبیه‌سازی', 'ph' => 1, 'camp' => true],
            ['k' => 'pace', 'label' => 'پایش حین اجرا', 'ph' => 1, 'camp' => true],
            ['k' => 'verify', 'label' => 'راستی‌آزمایی', 'ph' => 1, 'camp' => true],
            ['k' => 'loop', 'label' => 'حلقه', 'ph' => 2, 'camp' => true],
            ['k' => 'report', 'label' => 'گزارش کمپین', 'ph' => 2, 'camp' => true],
            ['k' => 'log', 'label' => 'دفترچه‌ی دیدگاه‌ها', 'ph' => 2],
            ['k' => 'dash', 'label' => 'داشبورد ذی‌نفع', 'ph' => 2],
            ['k' => 'method', 'label' => 'روش‌شناسی', 'ph' => 2],
            ['k' => 'ask', 'label' => 'پرسش از داده', 'ph' => 2],
            ['k' => 'connect', 'label' => 'اتصال داده', 'ph' => 3],
            ['k' => 'team', 'label' => 'تیم و نقش‌ها', 'ph' => 3],
            ['k' => 'rules', 'label' => 'قواعد و آستانه‌ها', 'ph' => 3],
            ['k' => 'audit', 'label' => 'لاگ تغییرات', 'ph' => 3],
            ['k' => 'analytics', 'label' => 'آنالیتیکس محصول', 'ph' => 3],
            ['k' => 'security', 'label' => 'امنیت', 'ph' => 3],
            ['k' => 'billing', 'label' => 'پلن و صورتحساب', 'ph' => 3],
            ['k' => 'help', 'label' => 'راهنما و پشتیبانی', 'ph' => 3],
            ['k' => 'settings', 'label' => 'تنظیمات', 'ph' => 3],
        ];
        if (Auth::isAdmin()) {
            $p[] = ['k' => 'admin', 'label' => 'مدیریت سامانه', 'ph' => 3];
        }
        return $p;
    }

    public static function currentCampaignId(): int
    {
        $id = (int) Session::get('cur_campaign', 0);
        if ($id > 0 && !DB::val('SELECT 1 FROM campaigns WHERE id = ? AND workspace_id = ?', [$id, Auth::wsId()])) {
            Session::forget('cur_campaign');
            return 0;
        }
        return $id;
    }

    public static function url(string $k, int $cid): string
    {
        $camp = ['design', 'insights', 'sim', 'pace', 'verify', 'loop', 'report'];
        if (in_array($k, $camp, true)) {
            if ($cid > 0) {
                return url('/c/' . $cid . '/' . $k);
            }
            return $k === 'verify' ? url('/verify') : url('/campaigns', ['need' => $k]);
        }
        return url('/' . $k);
    }

    /** @return array<string,mixed> */
    public static function build(string $page): array
    {
        $cid = self::currentCampaignId();
        $pages = self::pages();
        $cur = null;
        foreach ($pages as $p) {
            if ($p['k'] === $page) {
                $cur = $p;
            }
        }
        $ph = $cur['ph'] ?? 0;
        $phases = [];
        foreach (self::PHASES as $i => $d) {
            $first = current(array_filter($pages, static fn ($p) => $p['ph'] === $i));
            $phases[] = ['label' => $d['label'], 'on' => $i === $ph, 'href' => self::url($first['k'], $cid)];
        }
        $steps = [];
        $n = 0;
        foreach ($pages as $p) {
            if ($p['ph'] !== $ph) {
                continue;
            }
            $steps[] = ['num' => Fmt::fa((string) ++$n), 'label' => $p['label'], 'on' => $p['k'] === $page, 'href' => self::url($p['k'], $cid)];
        }
        $ws = Auth::wsId();
        $wsList = DB::all('SELECT w.id, w.name FROM workspaces w JOIN memberships m ON m.workspace_id = w.id WHERE m.user_id = ? AND w.deleted_at IS NULL ORDER BY w.id', [Auth::id()]);
        $notifs = DB::all('SELECT * FROM notifications WHERE user_id = ? AND workspace_id = ? ORDER BY id DESC LIMIT 20', [Auth::id(), $ws]);
        $unread = (int) DB::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND workspace_id = ? AND read_at IS NULL', [Auth::id(), $ws]);
        return [
            'page' => $page, 'phases' => $phases, 'steps' => $steps,
            'phaseLabel' => self::PHASES[$ph]['label'] . ' · ' . self::PHASES[$ph]['sub'],
            'loop' => self::loopBar($cid),
            'wsList' => $wsList, 'notifs' => $notifs, 'unread' => $unread,
        ];
    }

    /** @return array<string,mixed> */
    public static function loopBar(int $cid): array
    {
        $ws = Auth::wsId();
        $e = Ws::engine($ws);
        $rd = $e->readiness(Ws::historyCount($ws), Ws::rates($ws));
        $c = $cid ? Loop::campaign($ws, $cid) : null;
        $plan = $c ? Loop::plan((int) $c['id']) : null;
        $sim = $c && $plan ? Loop::latestSim((int) $c['id']) : null;
        $pace = $c ? Loop::latestPace((int) $c['id']) : null;
        $run = $c && $c['current_run_id'] ? Loop::run((int) $c['current_run_id']) : null;
        $simR = $sim['r'] ?? ($plan ? Loop::simulate($ws, $c, $plan) : null);
        $vr = $run['ver']['r'] ?? null;
        $cal = $run['cals'][0] ?? null;
        $chips = [
            ['label' => 'طرح', 'note' => $plan ? $plan['perspective'] : 'انتخاب نشده', 'done' => (bool) $plan],
            ['label' => 'پیش‌بینی', 'note' => $simR ? 'POAS ' . Fmt::signPct($simR['poas']) : 'ساخته نشده', 'done' => (bool) $simR],
            ['label' => 'پایش', 'note' => $pace && $pace['spend'] > 0 ? 'روز ' . Fmt::fa((string) $pace['day']) : 'داده‌ای نیست', 'done' => $pace && $pace['spend'] > 0],
            ['label' => 'نتیجه‌ی واقعی', 'note' => $vr ? $vr['cause'] : 'ثبت نشده', 'done' => (bool) $vr],
            ['label' => 'کالیبراسیون', 'note' => $cal ? 'ردیف ' . $cal['row_label'] : ($vr && empty($vr['calib']) ? 'در این شاخه اعمال نمی‌شود' : 'اعمال نشده'), 'done' => (bool) $cal],
        ];
        $base = $c ? '/c/' . $c['id'] : '';
        if (!$rd['ready']) {
            $next = ['label' => 'تکمیل آمادگی داده', 'href' => url('/setup')];
        } elseif (!$c) {
            $next = ['label' => 'کمپین تازه', 'href' => url('/campaigns')];
        } elseif (!$c['insights']) {
            $next = ['label' => 'تولید اینسایت‌ها', 'href' => url($base . '/design')];
        } elseif (!$plan) {
            $next = ['label' => 'انتخاب یک دیدگاه', 'href' => url($base . '/insights')];
        } elseif (!($pace && $pace['spend'] > 0) && !$vr) {
            $next = ['label' => 'پایش حین اجرا', 'href' => url($base . '/pace')];
        } elseif (!$vr) {
            $next = ['label' => 'ثبت نتیجه‌ی واقعی', 'href' => url($base . '/verify')];
        } elseif (!$cal && !empty($vr['calib'])) {
            $next = ['label' => 'اعمال کالیبراسیون', 'href' => url($base . '/verify')];
        } elseif ($c['status'] !== 'closed') {
            $next = ['label' => 'دیدن گزارش کمپین', 'href' => url($base . '/report')];
        } else {
            $next = ['label' => 'حلقه‌ی بعدی', 'href' => url('/campaigns')];
        }
        return ['chips' => $chips, 'next' => $next, 'campaign' => $c, 'ready' => $rd['ready']];
    }
}
