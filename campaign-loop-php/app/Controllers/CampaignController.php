<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AI\AI;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Jalali;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Url;
use App\Engine\Engine;
use App\Services\Emails;
use App\Services\Loop;
use App\Services\Report;
use App\Services\Ws;

final class CampaignController extends Controller
{
    // ------------------------------------------------------------------ list

    public function index(): void
    {
        $ws = $this->ws();
        $rows = DB::all('SELECT c.*, p.code AS plan_code, p.perspective, p.version FROM campaigns c LEFT JOIN plans p ON p.campaign_id = c.id WHERE c.workspace_id = ? ORDER BY c.id DESC', [$ws]);
        $w = DB::one('SELECT tier FROM workspaces WHERE id = ?', [$ws]);
        $this->page('campaigns', 'pages/campaigns', [
            'title' => 'کمپین‌ها', 'rows' => $rows, 'overLimit' => Loop::overLimit($ws), 'usage' => Loop::usageThisMonth($ws),
            'tier' => $w['tier'] ?? 'trial', 'need' => Request::str('need'),
        ]);
    }

    public function create(): void
    {
        $ws = $this->ws();
        if (Loop::overLimit($ws)) {
            $this->flash('سقف ۳ کمپین پلن آزمایشی پر شده. برای کمپین بیشتر پلن را ارتقا دهید.', 'bad');
            Response::redirect('/billing');
        }
        $id = Loop::createDraft($ws);
        Session::set('cur_campaign', $id);
        Response::redirect('/c/' . $id . '/design');
    }

    public function archive(string $id): void
    {
        $c = $this->campaign($id);
        if ($c['status'] === 'live') {
            $this->flash('کمپین در حال اجرا را نمی‌توان بایگانی کرد؛ ابتدا نتیجه را ثبت و کمپین را ببندید.', 'bad');
            Response::redirect('/campaigns');
        }
        DB::update('campaigns', ['status' => 'archived', 'updated_at' => DB::now()], ['id' => $c['id']]);
        Audit::log('بایگانی کمپین', (string) ($c['seq'] ? Ws::code('plan', (int) $c['seq']) : $c['name']));
        Response::redirect('/campaigns');
    }

    public function deleteDraft(string $id): void
    {
        $c = $this->campaign($id);
        if ($c['status'] !== 'draft') {
            Response::abort(400, 'فقط کمپین پیش‌نویس حذف می‌شود.');
        }
        DB::delete('campaigns', ['id' => $c['id']]);
        Session::forget('cur_campaign');
        Audit::log('حذف پیش‌نویس کمپین', (string) $c['name']);
        Response::redirect('/campaigns');
    }

    // ------------------------------------------------------------------ design

    public function design(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $old = Session::takeOld();
        $this->page('design', 'pages/design', [
            'title' => 'طراحی', 'c' => $c, 'old' => $old, 'errors' => $old['_errors'] ?? [],
            'occasions' => Ws::occasions(), 'profile' => Ws::profile($ws), 'locked' => !empty($c['current_run_id']),
            'blocked' => Ws::blocked($ws),
        ]);
    }

    public function designSave(string $id): void
    {
        $c = $this->campaign($id);
        if (!empty($c['current_run_id'])) {
            $this->flash('نتیجه‌ی این کمپین ثبت شده؛ ورودی طراحی دیگر قابل تغییر نیست.', 'bad');
            Response::redirect('/c/' . $c['id'] . '/design');
        }
        $in = [
            'name' => Request::str('name', '', 160), 'goalType' => Request::str('goalType'), 'goalValue' => Request::num('goalValue') ?? 0,
            'budget' => Request::num('budget') ?? 0, 'from' => Request::str('from'), 'to' => Request::str('to'),
            'channels' => Request::arr('channels'), 'segments' => Request::arr('segments'), 'occasion' => Request::str('occasion', 'بدون مناسبت'),
            'risk' => Request::str('risk', 'متعادل'),
        ];
        $r = Loop::design($this->ws(), $c, $in);
        if ($r['errors']) {
            Session::old(['_errors' => $r['errors']]);
            Response::redirect('/c/' . $c['id'] . '/design');
        }
        Response::redirect('/c/' . $c['id'] . '/insights');
    }

    // ------------------------------------------------------------------ insights & plan

    public function insights(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $ins = Loop::j($c['insights']) ?: [];
        $plan = Loop::plan((int) $c['id']);
        $sel = Request::str('sel') ?: ($c['sel_id'] ?: ($plan['primary_id'] ?? ''));
        $sec = Request::has('sec') ? Request::str('sec') : ($c['sec_id'] ?: ($plan['secondary_id'] ?? ''));
        if ($sec === $sel) {
            $sec = '';
        }
        $ids = array_column($ins, 'id');
        if (!in_array($sel, $ids, true)) {
            $sel = '';
        }
        if (!in_array($sec, $ids, true)) {
            $sec = '';
        }
        $mix = Request::int('mix', (int) ($c['mix'] ?: 60));
        $e = Ws::engine($ws);
        $this->page('insights', 'pages/insights', [
            'title' => 'اینسایت‌ها', 'c' => $c, 'ins' => $ins, 'sel' => $sel, 'sec' => $sec, 'mix' => $mix, 'plan' => $plan, 'e' => $e,
            'profile' => Ws::profile($ws), 'reason' => (string) ($c['reason_draft'] ?: ($plan['reason'] ?? '')),
            'aiOn' => AI::enabled($ws), 'error' => Session::takeOld()['_error'] ?? '',
        ]);
    }

    public function savePlan(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $sel = Request::str('sel');
        $sec = Request::str('sec');
        $reason = Request::str('reason', '', 2000);
        $mix = Request::int('mix', 60);
        $plan = Loop::plan((int) $c['id']);
        if (!$plan && Loop::overLimit($ws)) {
            $this->flash('سقف ۳ کمپین پلن آزمایشی پر شده.', 'bad');
            Response::redirect('/billing');
        }
        DB::update('campaigns', ['reason_draft' => $reason], ['id' => $c['id']]);
        $ins = Loop::j($c['insights']) ?: [];
        $perspName = current(array_filter($ins, static fn ($i) => $i['id'] === $sel))['perspective'] ?? '';
        $spec = mb_strlen($reason) >= 10 ? AI::reasonHint($ws, $reason, $perspName, (string) (Ws::profile($ws)['goal'] ?? ''), '') : null;
        $r = Loop::savePlan($ws, $c, $sel, $sec, $mix, $reason, $spec['is_specific'] ?? null);
        if (!$r['ok']) {
            Session::old(['_error' => $r['error']]);
            Response::redirect('/c/' . $c['id'] . '/insights?sel=' . urlencode($sel) . '&sec=' . urlencode($sec) . '&mix=' . $mix);
        }
        $c = Loop::campaign($ws, (int) $c['id']);
        $plan = Loop::plan((int) $c['id']);
        Loop::saveSim($ws, $c, $plan);
        $this->flash(!empty($r['editing']) ? 'نسخه‌ی تازه‌ی طرح ثبت شد.' : 'طرح ' . Fmt::fa($plan['code']) . ' ثبت شد.');
        Response::redirect('/c/' . $c['id'] . '/sim');
    }

    // ------------------------------------------------------------------ simulator

    /** @return array{0:array<string,mixed>,1:array<string,mixed>} */
    private function needPlan(string $id, string $page): array
    {
        $c = $this->campaign($id);
        $plan = Loop::plan((int) $c['id']);
        if (!$plan) {
            $this->page($page, 'pages/noplan', ['title' => 'طرحی ثبت نشده', 'c' => $c, 'pageLabel' => $page]);
            throw new \App\Core\HttpStop('noplan');
        }
        return [$c, $plan];
    }

    public function sim(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'sim');
        $ws = $this->ws();
        $e = Ws::engine($ws);
        $s = Loop::simulate($ws, $c, $plan);
        $saved = Loop::latestSim((int) $c['id']);
        $wb = max(-50, min(100, Request::int('wb', 0)));
        $wsh = max(0, min(60, Request::int('ws', 0)));
        $versions = DB::all('SELECT * FROM plan_versions WHERE plan_id = ? ORDER BY version DESC', [$plan['id']]);
        $this->page('sim', 'pages/sim', [
            'title' => 'شبیه‌سازی', 'c' => $c, 'plan' => $plan, 's' => $s, 'saved' => $saved, 'e' => $e,
            'wi' => $e->whatIf($plan['alloc'], $wb, $wsh), 'wb' => $wb, 'wsh' => $wsh, 'versions' => $versions,
            'occ' => Ws::occasion((string) $c['occasion']), 'stale' => $this->ratesChanged($ws, $plan['alloc']),
        ]);
    }

    /** True when live rates differ from the plan snapshot (e.g. after a calibration). @param list<array<string,mixed>> $alloc */
    private function ratesChanged(int $ws, array $alloc): bool
    {
        $live = [];
        foreach (Ws::rates($ws) as $r) {
            $live[$r['ch'] . '|' . $r['seg']] = $r;
        }
        foreach ($alloc as $a) {
            $r = $live[$a['ch'] . '|' . $a['seg']] ?? null;
            if ($r && (abs((float) $r['cpi'] - (float) $a['cpi']) > 0.5 || abs((float) $r['cvr'] - (float) $a['cvr']) > 1e-6 || (int) $r['n'] !== (int) $a['n'])) {
                return true;
            }
        }
        return false;
    }

    public function simSave(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'sim');
        $ws = $this->ws();
        $band = Request::str('band', (string) $c['sim_band']);
        $ext = Request::str('ext', (string) $c['sim_ext']);
        DB::update('campaigns', ['sim_band' => isset(Engine::BAND[$band]) ? $band : 'معمول', 'sim_ext' => isset(Engine::EXT[$ext]) ? $ext : 'عادی', 'updated_at' => DB::now()], ['id' => $c['id']]);
        if (Request::str('action') === 'save') {
            $c = Loop::campaign($ws, (int) $c['id']);
            $s = Loop::saveSim($ws, $c, $plan);
            Audit::log('ثبت پیش‌بینی ' . Fmt::fa($s['code']), 'باند ' . $c['sim_band'] . ' · ' . $c['sim_ext']);
            Response::redirect('/c/' . $c['id'] . '/pace');
        }
        Response::redirect('/c/' . $c['id'] . '/sim');
    }

    public function whatIfApply(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'sim');
        if (!empty($c['current_run_id'])) {
            $this->flash('نتیجه‌ی این کمپین ثبت شده؛ طرح قابل تغییر نیست.', 'bad');
            Response::redirect('/c/' . $c['id'] . '/sim');
        }
        $ws = $this->ws();
        $wb = max(-50, min(100, Request::int('wb', 0)));
        $wsh = max(0, min(60, Request::int('ws', 0)));
        $e = Ws::engine($ws);
        $alloc = $e->whatIf($plan['alloc'], $wb, $wsh)['alloc'];
        Loop::newVersion($c, $plan, $alloc, 'پیش از سناریو');
        Audit::log('اعمال سناریو روی طرح', 'بودجه ' . Fmt::fa((string) $wb) . '٪ · جابه‌جایی ' . Fmt::fa((string) $wsh) . '٪');
        Audit::event('scenario_applied', ['budget_delta_pct' => $wb, 'shift_pct' => $wsh]);
        Loop::saveSim($ws, Loop::campaign($ws, (int) $c['id']), Loop::plan((int) $c['id']));
        $this->flash('سناریو روی طرح اعمال شد؛ نسخه‌ی تازه‌ی طرح ثبت شد.');
        Response::redirect('/c/' . $c['id'] . '/sim');
    }

    public function refreshRates(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'sim');
        $ws = $this->ws();
        $before = Loop::simulate($ws, $c, $plan);
        $alloc = Loop::refreshAlloc($ws, $plan['alloc']);
        Loop::newVersion($c, $plan, $alloc, 'به‌روزرسانی نرخ‌ها');
        $c = Loop::campaign($ws, (int) $c['id']);
        $s = Loop::saveSim($ws, $c, Loop::plan((int) $c['id']));
        Audit::log('پیش‌بینی دوباره با نرخ‌های فعلی', Fmt::fa($s['code']));
        $this->flash('پیش‌بینی با نرخ‌های فعلی دوباره ساخته شد: خرید ' . Fmt::num($before['conv']) . ' → ' . Fmt::num($s['r']['conv']) . '، CAC ' . Fmt::money($before['cac']) . ' → ' . Fmt::money($s['r']['cac']) . '.');
        Response::redirect('/c/' . $c['id'] . '/sim');
    }

    // ------------------------------------------------------------------ pacing

    public function pace(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'pace');
        $ws = $this->ws();
        $sim = Loop::ensureSim($ws, $c, $plan);
        $last = Loop::latestPace((int) $c['id']);
        $days = Loop::durationDays($c);
        $pc = $last ? ['day' => (int) $last['day'], 'spend' => (float) $last['spend'], 'installs' => (float) $last['installs'], 'conv' => (float) $last['conversions']]
            : ['day' => Loop::dayOfCampaign($c), 'spend' => 0, 'installs' => 0, 'conv' => 0];
        if (Request::get('demo') === '1') {
            $d = (int) round($days * 0.4);
            $pc = ['day' => $d, 'spend' => round($sim['r']['budget'] * 0.52), 'installs' => round($sim['r']['installs'] * 0.44), 'conv' => round($sim['r']['conv'] * 0.29)];
        }
        $e = Ws::engine($ws);
        $pace = $e->pace($sim['r'], $pc, $days, $plan['alloc']);
        $this->page('pace', 'pages/pace', [
            'title' => 'پایش حین اجرا', 'c' => $c, 'plan' => $plan, 'sim' => $sim, 'pc' => $pc, 'pace' => $pace, 'days' => $days,
            'history' => DB::all('SELECT * FROM pace_snapshots WHERE campaign_id = ? ORDER BY id DESC LIMIT 10', [$c['id']]),
            'isDemo' => (bool) DB::val('SELECT is_demo FROM workspaces WHERE id = ?', [$ws]), 'prefilled' => Request::get('demo') === '1',
        ]);
    }

    public function paceSave(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'pace');
        $ws = $this->ws();
        $days = Loop::durationDays($c);
        $pc = [
            'day' => max(1, min($days, Request::int('day', 1))), 'spend' => max(0, Request::num('spend') ?? 0),
            'installs' => max(0, Request::num('installs') ?? 0), 'conv' => max(0, Request::num('conv') ?? 0),
        ];
        DB::insert('pace_snapshots', ['campaign_id' => $c['id'], 'day' => $pc['day'], 'spend' => (int) round($pc['spend']), 'installs' => (int) round($pc['installs']), 'conversions' => (int) round($pc['conv']), 'source' => 'manual', 'created_by' => Auth::id() ?: null, 'created_at' => DB::now()]);
        self::paceAlerts($ws, $c, $plan, $pc);
        Audit::event('pace_logged', ['day' => $pc['day']]);
        Audit::log('ثبت پایش روز ' . Fmt::fa((string) $pc['day']), (string) $c['name']);
        $this->flash('پایش روز ' . Fmt::fa((string) $pc['day']) . ' ثبت شد.');
        Response::redirect('/c/' . $c['id'] . '/pace');
    }

    /** Compute alerts, notify in-app and by email (shared with the daily sync job). @param array{day:int|float,spend:float,installs:float,conv:float} $pc */
    public static function paceAlerts(int $ws, array $c, array $plan, array $pc): void
    {
        $sim = Loop::ensureSim($ws, $c, $plan);
        $e = Ws::engine($ws);
        $days = Loop::durationDays($c);
        $pace = $e->pace($sim['r'], $pc, $days, $plan['alloc']);
        foreach ($pace['alerts'] as $a) {
            if ($a['level'] !== 'bad') {
                continue;
            }
            Audit::notify($a['text'], 'bad', '/c/' . $c['id'] . '/pace', $ws, null, $a['type']);
            if ($a['type'] === 'pace_conv') {
                $members = DB::all('SELECT u.id, u.email FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.role IN (\'owner\',\'analyst\',\'campaign_ops\') AND u.email_verified_at IS NOT NULL', [$ws]);
                foreach ($members as $m) {
                    if (!Emails::wants((int) $m['id'], 'pace_alert')) {
                        continue;
                    }
                    Emails::paceAlert((string) $m['email'], (string) $c['name'], [
                        'day' => Fmt::fa((string) $pc['day']), 'total' => Fmt::fa((string) $days), 'delta' => $pace['rows'][2]['dev'],
                        'spend_pct' => $pace['rows'][0]['act'], 'expected_pct' => $pace['rows'][0]['exp'], 'conv_pct' => $pace['rows'][2]['act'],
                        'realloc_text' => $pace['realloc']['has'] ? $pace['realloc']['text'] : '',
                    ], Url::to('/c/' . $c['id'] . '/pace', [], true));
                }
            }
        }
    }

    public function realloc(string $id): void
    {
        [$c, $plan] = $this->needPlan($id, 'pace');
        $ws = $this->ws();
        $last = Loop::latestPace((int) $c['id']);
        if (!$last) {
            Response::redirect('/c/' . $c['id'] . '/pace');
        }
        $sim = Loop::ensureSim($ws, $c, $plan);
        $e = Ws::engine($ws);
        $pace = $e->pace($sim['r'], ['day' => $last['day'], 'spend' => $last['spend'], 'installs' => $last['installs'], 'conv' => $last['conversions']], Loop::durationDays($c), $plan['alloc']);
        if (!$pace['realloc']['has']) {
            $this->flash('جابه‌جایی بودجه در این وضعیت خرید مورد انتظار را بالا نمی‌برد.', 'info');
            Response::redirect('/c/' . $c['id'] . '/pace');
        }
        Loop::newVersion($c, $plan, $pace['realloc']['alloc'], 'پیش از جابه‌جایی حین اجرا');
        Loop::saveSim($ws, Loop::campaign($ws, (int) $c['id']), Loop::plan((int) $c['id']));
        Audit::log('جابه‌جایی بودجه حین اجرا', '۳۰٪ · ' . $pace['realloc']['from'] . ' → ' . $pace['realloc']['to']);
        Audit::event('realloc_applied', ['shift_pct' => 30]);
        Audit::notify('بودجه‌ی کمپین جابه‌جا شد؛ نسخه‌ی تازه‌ی طرح ثبت شد.', 'ok', '/c/' . $c['id'] . '/sim', $ws, null, 'plan_changed');
        $this->flash('بودجه‌ی کمپین جابه‌جا شد؛ نسخه‌ی تازه‌ی طرح ثبت شد.');
        Response::redirect('/c/' . $c['id'] . '/pace');
    }

    // ------------------------------------------------------------------ verifier

    public function verify(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $plan = Loop::plan((int) $c['id']);
        if (!$plan) {
            Response::redirect('/verify');
        }
        $sim = Loop::ensureSim($ws, $c, $plan);
        $run = $c['current_run_id'] ? Loop::run((int) $c['current_run_id']) : null;
        $draft = DB::one("SELECT id FROM runs WHERE campaign_id = ? AND status = 'draft' ORDER BY id DESC LIMIT 1", [$c['id']]);
        $prefill = null;
        if (Request::get('demo') === '1') {
            $prefill = array_map(static fn ($r) => ['ab' => round($r['budget'] * 1.03), 'ai' => round($r['installs'] * 0.7), 'ac' => round($r['conv'] * 0.74)], $sim['r']['rows']);
        } elseif ($draft) {
            $d = Loop::run((int) $draft['id']);
            $prefill = array_map(static fn ($r) => ['ab' => $r['ab'], 'ai' => $r['ai'], 'ac' => $r['ac']], $d['in']['rows'] ?? []);
        }
        $e = Ws::engine($ws);
        $this->page('verify', 'pages/verify', [
            'title' => 'راستی‌آزمایی', 'c' => $c, 'plan' => $plan, 'sim' => $sim, 'run' => $run, 'e' => $e, 'prefill' => $prefill,
            'draft' => $draft ? Loop::run((int) $draft['id']) : null, 'rules' => Ws::rules($ws),
            'isDemo' => (bool) DB::val('SELECT is_demo FROM workspaces WHERE id = ?', [$ws]), 'aiOn' => AI::enabled($ws),
            'undoable' => $this->undoable($ws),
        ]);
    }

    /** @return array<string,mixed>|null */
    private function undoable(int $ws): ?array
    {
        return DB::one('SELECT c.* FROM calibrations c WHERE c.workspace_id = ? AND c.reverted_at IS NULL AND NOT EXISTS (SELECT 1 FROM calibrations c2 WHERE c2.rate_id = c.rate_id AND c2.id > c.id AND c2.reverted_at IS NULL) ORDER BY c.id DESC LIMIT 1', [$ws]);
    }

    /** @return array<string,mixed> */
    private function flagsFromRequest(): array
    {
        return [
            'complete' => Request::str('complete', 'بله') === 'خیر' ? 'خیر' : 'بله',
            'matched' => Request::str('matched', 'بله') === 'خیر' ? 'خیر' : 'بله',
            'fraud' => max(0, min(100, Request::num('fraud') ?? 0)),
            'window' => max(1, Request::int('window', 7)),
            'season' => Request::str('season', 'خیر') === 'بله' ? 'بله' : 'خیر',
            'reach' => max(0, Request::num('reach') ?? 0),
            'holdout' => max(0, min(100, Request::num('holdout') ?? 0)),
        ];
    }

    public function verifyRun(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $plan = Loop::plan((int) $c['id']);
        if (!$plan) {
            Response::redirect('/verify');
        }
        if ($c['status'] === 'closed') {
            $this->flash('این کمپین بسته شده است.', 'bad');
            Response::redirect('/c/' . $c['id'] . '/report');
        }
        $sim = Loop::ensureSim($ws, $c, $plan);
        $ab = (array) Request::post('ab', []);
        $ai = (array) Request::post('ai', []);
        $ac = (array) Request::post('ac', []);
        $rows = [];
        $err = '';
        foreach ($sim['r']['rows'] as $i => $r) {
            $a = [Fmt::parseNum($ab[$i] ?? ''), Fmt::parseNum($ai[$i] ?? ''), Fmt::parseNum($ac[$i] ?? '')];
            if (in_array(null, $a, true) || min($a) < 0) {
                $err = 'برای هر ردیف تخصیص، هزینه، نصب و خرید واقعی را (عدد ≥ ۰) وارد کنید.';
                break;
            }
            if ($a[2] > $a[1] && !in_array($r['ch'], Engine::OWNED, true)) {
                $err = 'در ردیف ' . $r['label'] . ' خرید از نصب بیشتر است.';
                break;
            }
            $rows[] = ['ch' => $r['ch'], 'seg' => $r['seg'], 'pb' => round($r['budget']), 'pi' => round($r['installs']), 'pc' => round($r['conv']), 'ab' => $a[0], 'ai' => $a[1], 'ac' => $a[2]];
        }
        if (!$err && array_sum(array_column($rows, 'ab')) <= 0) {
            $err = 'هزینه‌ی واقعی باید بیشتر از صفر باشد.';
        }
        if ($err) {
            $this->flash($err, 'bad');
            Response::redirect('/c/' . $c['id'] . '/verify');
        }
        $vi = array_merge($this->flagsFromRequest(), [
            'name' => Request::str('name', '', 160) ?: (string) $c['name'], 'ch' => $rows[0]['ch'], 'seg' => $rows[0]['seg'],
            'from' => Jalali::fromIso((string) $c['date_from']), 'to' => Jalali::fromIso((string) $c['date_to']), 'src' => 'مکتوب و عددی', 'rows' => $rows,
        ]);
        DB::q("DELETE FROM runs WHERE campaign_id = ? AND status = 'draft'", [$c['id']]);
        $rid = Loop::verify($ws, $c, $vi);
        $this->maybeAutoCalibrate($ws, $rid);
        Response::redirect('/c/' . $c['id'] . '/verify#card');
    }

    private function maybeAutoCalibrate(int $ws, int $runId): void
    {
        $auto = (int) DB::val('SELECT auto_calibrate FROM rules WHERE workspace_id = ?', [$ws]);
        $run = Loop::run($runId);
        if ($auto && $run && ($run['ver']['r']['cause'] ?? '') === 'در دامنه‌ی انتظار' && Auth::can('calibrate')) {
            Loop::calibrate($ws, $runId);
        }
    }

    public function verifyManual(): void
    {
        $ws = $this->ws();
        $runId = (int) Session::get('manual_run', 0);
        $run = $runId ? Loop::run($runId) : null;
        if ($run && (int) $run['workspace_id'] !== $ws) {
            $run = null;
        }
        $vi = Engine::blankVI();
        $preset = Request::int('preset', -1);
        if ($preset >= 0 && isset(Engine::presets()[$preset])) {
            $vi = array_merge($vi, Engine::presets()[$preset]['vi']);
            $run = null;
        } elseif ($run) {
            $vi = array_merge($vi, $run['in']);
        }
        $this->page('verify', 'pages/verify_manual', [
            'title' => 'راستی‌آزمایی — مسیر دستی', 'vi' => $vi, 'run' => $run, 'e' => Ws::engine($ws), 'rules' => Ws::rules($ws),
            'aiOn' => AI::enabled($ws), 'undoable' => $this->undoable($ws),
        ]);
    }

    public function verifyManualRun(): void
    {
        $ws = $this->ws();
        $vi = array_merge($this->flagsFromRequest(), [
            'name' => Request::str('name', 'کمپین بدون عنوان', 160), 'ch' => Request::str('ch'), 'seg' => Request::str('seg'),
            'from' => Request::str('from', '', 10), 'to' => Request::str('to', '', 10), 'src' => Request::str('src'),
            'pb' => Request::num('pb') ?? 0, 'pi' => Request::num('pi') ?? 0, 'pc' => Request::num('pc') ?? 0,
            'ab' => Request::num('ab') ?? 0, 'ai' => Request::num('ai') ?? 0, 'ac' => Request::num('ac') ?? 0,
        ]);
        $err = '';
        if (mb_strlen($vi['name']) < 3) {
            $err = 'نام کمپین دست‌کم ۳ کاراکتر باشد.';
        } elseif (!in_array($vi['ch'], Engine::CHANNELS, true) || !in_array($vi['seg'], Engine::SEGMENTS, true)) {
            $err = 'کانال یا سگمنت نامعتبر است.';
        } elseif (!in_array($vi['src'], Engine::SOURCES, true)) {
            $err = 'منبع پیش‌بینی را انتخاب کنید.';
        } elseif ($vi['pb'] <= 0 || $vi['pi'] <= 0 || $vi['pc'] <= 0 || $vi['ab'] <= 0) {
            $err = 'بودجه‌ی طرح، نصب و خرید مورد انتظار و هزینه‌ی واقعی باید بیشتر از صفر باشند.';
        } elseif (!in_array($vi['ch'], Engine::OWNED, true) && ($vi['pc'] > $vi['pi'] || $vi['ac'] > $vi['ai'])) {
            $err = 'خرید نمی‌تواند از نصب بیشتر باشد.';
        }
        if ($err) {
            $this->flash($err, 'bad');
            Response::redirect('/verify');
        }
        $rid = Loop::verify($ws, null, $vi);
        Session::set('manual_run', $rid);
        Audit::event('manual_verify', ['forecast_source' => $vi['src']]);
        Response::redirect('/verify#card');
    }

    public function confirmRun(string $id): void
    {
        $ws = $this->ws();
        $run = Loop::run((int) $id);
        if (!$run || (int) $run['workspace_id'] !== $ws || $run['status'] !== 'draft') {
            Response::abort(404);
        }
        DB::update('runs', ['status' => 'confirmed'], ['id' => $run['id']]);
        if ($run['campaign_id']) {
            DB::update('campaigns', ['current_run_id' => $run['id']], ['id' => $run['campaign_id']]);
        }
        Audit::log('تأیید نتیجه‌ی خودکار', $run['code']);
        Audit::event('run_verified', ['cause' => $run['ver']['cause'] ?? '']);
        $this->maybeAutoCalibrate($ws, (int) $run['id']);
        Response::redirect($run['campaign_id'] ? '/c/' . $run['campaign_id'] . '/verify#card' : '/verify');
    }

    public function calibrate(string $id): void
    {
        $r = Loop::calibrate($this->ws(), (int) $id);
        $this->flash($r['ok'] ? 'کالیبراسیون ' . Fmt::fa(implode('، ', $r['codes'])) . ' اعمال شد.' : $r['error'], $r['ok'] ? 'ok' : 'bad');
        Response::back('/verify');
    }

    public function undo(string $id): void
    {
        $r = Loop::undo($this->ws(), (int) $id);
        $this->flash($r['ok'] ? 'کالیبراسیون بازگردانده شد.' : $r['error'], $r['ok'] ? 'ok' : 'bad');
        Response::back('/verify');
    }

    // ------------------------------------------------------------------ outputs

    public function loop(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $plan = Loop::plan((int) $c['id']);
        $sim = $plan ? Loop::latestSim((int) $c['id']) : null;
        $run = $c['current_run_id'] ? Loop::run((int) $c['current_run_id']) : null;
        $this->page('loop', 'pages/loop', ['title' => 'حلقه', 'c' => $c, 'plan' => $plan, 'sim' => $sim, 'run' => $run]);
    }

    public function report(string $id): void
    {
        $c = $this->campaign($id);
        $ws = $this->ws();
        $rp = Report::build($ws, $c);
        $this->page('report', 'pages/report', ['title' => 'گزارش کمپین', 'c' => $c, 'report' => $rp, 'share' => Session::get('share_' . $c['id'])]);
    }

    public function reportCsv(string $id): void
    {
        $c = $this->campaign($id);
        if (!Auth::verified()) {
            $this->flash('برای خروجی گزارش ابتدا ایمیل خود را تأیید کنید.', 'bad');
            Response::redirect('/c/' . $c['id'] . '/report');
        }
        $rp = Report::build($this->ws(), $c);
        if (!$rp) {
            Response::abort(404);
        }
        $rows = [['section', 'key', 'value']];
        foreach ($rp['chain'] as $x) {
            $rows[] = ['chain', $x['k'], $x['v']];
        }
        foreach ($rp['alloc'] as $a) {
            $rows[] = ['allocation', $a['label'], $a['budget'] . ' | ' . $a['share'] . ' | CAC ' . $a['cac'] . ' | ' . $a['evidence']];
        }
        foreach ($rp['forecast'] as $x) {
            $rows[] = ['forecast', $x['k'], $x['v'] . ' (' . $x['band'] . ')'];
        }
        foreach ($rp['result'] as $x) {
            $rows[] = ['result', $x['k'], $x['p'] . ' → ' . $x['a'] . ' ' . $x['d']];
        }
        $rows[] = ['decision', 'perspective', $rp['perspective']];
        $rows[] = ['decision', 'reason', $rp['reason']];
        $rows[] = ['cause', 'cause', $rp['cause']];
        $rows[] = ['calibration', 'calibration', $rp['calib']];
        Response::csv('campaign-report.csv', $rows);
    }

    public function reportJson(string $id): void
    {
        $c = $this->campaign($id);
        Response::json(['campaign' => ['id' => (int) $c['id'], 'name' => $c['name'], 'status' => Loop::status($c)], 'report' => Report::build($this->ws(), $c)]);
    }

    public function share(string $id): void
    {
        $c = $this->campaign($id);
        if (!Auth::verified()) {
            $this->flash('برای اشتراک گزارش ابتدا ایمیل خود را تأیید کنید.', 'bad');
            Response::redirect('/c/' . $c['id'] . '/report');
        }
        $link = Url::to('/r/' . $c['id'] . '.' . Crypto::sign('report:' . $c['id']), [], true);
        Session::set('share_' . $c['id'], $link);
        Audit::log('ساخت لینک اشتراک گزارش', (string) $c['name']);
        Response::redirect('/c/' . $c['id'] . '/report');
    }

    public function close(string $id): void
    {
        $c = $this->campaign($id);
        $r = Loop::close($this->ws(), $c);
        if (!$r['ok']) {
            $this->flash($r['error'], 'bad');
            Response::redirect('/c/' . $c['id'] . '/report');
        }
        if (!empty($r['first'])) {
            Session::set('celebrate', 1);
        }
        $this->flash('کمپین ' . $r['code'] . ' بسته و در دفترچه ثبت شد.');
        Response::redirect('/log');
    }
}
