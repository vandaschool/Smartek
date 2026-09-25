<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Jalali;
use App\Engine\Engine;

/** Campaign loop lifecycle: design → plan → simulate → pace → run/verify → calibrate → close. */
final class Loop
{
    public const STATUS = ['draft' => 'پیش‌نویس', 'live' => 'در حال اجرا', 'awaiting_result' => 'در انتظار نتیجه', 'closed' => 'بسته‌شده', 'stopped' => 'متوقف', 'archived' => 'بایگانی'];

    public static function j(?string $s): mixed
    {
        return $s ? json_decode($s, true) : null;
    }

    public static function enc(mixed $v): string
    {
        return (string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }

    /** @return array<string,mixed>|null */
    public static function campaign(int $ws, int $id): ?array
    {
        return DB::one('SELECT * FROM campaigns WHERE id = ? AND workspace_id = ?', [$id, $ws]);
    }

    /** @return array<string,mixed>|null */
    public static function plan(int $campaignId): ?array
    {
        $p = DB::one('SELECT * FROM plans WHERE campaign_id = ?', [$campaignId]);
        if ($p) {
            $p['alloc'] = self::j($p['allocation']) ?: [];
        }
        return $p;
    }

    /** @return array<string,mixed>|null */
    public static function latestSim(int $campaignId): ?array
    {
        $s = DB::one('SELECT * FROM simulations WHERE campaign_id = ? ORDER BY id DESC LIMIT 1', [$campaignId]);
        if ($s) {
            $s['r'] = self::j($s['result']);
        }
        return $s;
    }

    /** @return array<string,mixed>|null */
    public static function latestPace(int $campaignId): ?array
    {
        return DB::one('SELECT * FROM pace_snapshots WHERE campaign_id = ? ORDER BY id DESC LIMIT 1', [$campaignId]);
    }

    /** @return array<string,mixed>|null run + verification */
    public static function run(int $runId): ?array
    {
        $r = DB::one('SELECT * FROM runs WHERE id = ?', [$runId]);
        if (!$r) {
            return null;
        }
        $r['in'] = self::j($r['input']);
        $v = DB::one('SELECT * FROM verifications WHERE run_id = ? ORDER BY id DESC LIMIT 1', [$runId]);
        if ($v) {
            $v['r'] = self::j($v['result']);
        }
        $r['ver'] = $v;
        $r['cals'] = DB::all('SELECT * FROM calibrations WHERE run_id = ? AND reverted_at IS NULL ORDER BY id', [$runId]);
        return $r;
    }

    /** Displayed status (adds computed awaiting_result). @param array<string,mixed> $c */
    public static function status(array $c): string
    {
        if ($c['status'] === 'live' && empty($c['current_run_id']) && !empty($c['date_to']) && $c['date_to'] < date('Y-m-d')) {
            return 'awaiting_result';
        }
        return (string) $c['status'];
    }

    public static function durationDays(array $c): int
    {
        if (empty($c['date_from']) || empty($c['date_to'])) {
            return 30;
        }
        return max(1, (int) ((strtotime((string) $c['date_to']) - strtotime((string) $c['date_from'])) / 86400) + 1);
    }

    public static function dayOfCampaign(array $c): int
    {
        if (empty($c['date_from'])) {
            return 1;
        }
        $d = (int) floor((time() - strtotime((string) $c['date_from'])) / 86400) + 1;
        return max(1, min($d, self::durationDays($c)));
    }

    // ------------------------------------------------------------------ design

    public static function createDraft(int $ws): int
    {
        $today = date('Y-m-d');
        $j = Jalali::fromIso($today);
        [$jy, $jm] = [(int) substr($j, 0, 4), (int) substr($j, 5, 2)];
        $ny = $jm === 12 ? $jy + 1 : $jy;
        $nm = $jm === 12 ? 1 : $jm + 1;
        $from = Jalali::toIso(sprintf('%04d/%02d/01', $ny, $nm)) ?? $today;
        $to = Jalali::toIso(sprintf('%04d/%02d/%02d', $ny, $nm, Jalali::monthLength($ny, $nm))) ?? $today;
        $blocked = Ws::blocked($ws);
        $id = DB::insert('campaigns', [
            'workspace_id' => $ws, 'name' => 'کمپین ' . Fmt::fa(sprintf('%04d/%02d/01', $ny, $nm)), 'status' => 'draft',
            'goal_type' => 'خرید', 'goal_value' => 2500, 'budget' => 500000000, 'date_from' => $from, 'date_to' => $to,
            'channels' => Loop::enc(array_values(array_diff(Engine::CHANNELS, $blocked))), 'segments' => Loop::enc(Engine::SEGMENTS),
            'occasion' => 'بدون مناسبت', 'risk' => 'متعادل', 'created_by' => Auth::id() ?: null, 'created_at' => DB::now(),
        ]);
        Audit::log('ساخت کمپین پیش‌نویس', '#' . $id);
        return $id;
    }

    /**
     * Validate + store design inputs and build 10 insights.
     * @param array<string,mixed> $in
     * @return array{errors:list<string>}
     */
    public static function design(int $ws, array $c, array $in): array
    {
        $errors = [];
        $from = Jalali::toIso((string) $in['from']);
        $to = Jalali::toIso((string) $in['to']);
        if (!$from || !$to) {
            $errors[] = 'تاریخ را به شکل ۱۴۰۵/۰۷/۰۱ وارد کنید.';
        } elseif ($from >= $to) {
            $errors[] = 'تاریخ پایان باید بعد از تاریخ شروع باشد.';
        }
        $profile = Ws::profile($ws);
        $budget = (float) $in['budget'];
        if ($budget <= 0) {
            $errors[] = 'بودجه باید بیشتر از صفر باشد.';
        } elseif ($profile['budget'] > 0 && $budget > $profile['budget'] * 3) {
            $errors[] = 'بودجه بیش از سه برابر بودجه‌ی ماهانه‌ی پروفایل است.';
        }
        if ((float) $in['goalValue'] <= 0) {
            $errors[] = 'عدد هدف باید بیشتر از صفر باشد.';
        }
        $channels = array_values(array_intersect(Engine::CHANNELS, (array) $in['channels']));
        $segments = array_values(array_intersect(Engine::SEGMENTS, (array) $in['segments']));
        if (!$channels) {
            $errors[] = 'دست‌کم یک کانال مجاز انتخاب کنید.';
        }
        if (!$segments) {
            $errors[] = 'دست‌کم یک سگمنت مجاز انتخاب کنید.';
        }
        $data = [
            'name' => mb_substr(trim((string) $in['name']) ?: (string) $c['name'], 0, 160),
            'goal_type' => in_array($in['goalType'], Engine::GOAL_TYPES, true) ? $in['goalType'] : 'خرید',
            'goal_value' => (int) round((float) $in['goalValue']), 'budget' => (int) round($budget),
            'date_from' => $from ?: $c['date_from'], 'date_to' => $to ?: $c['date_to'],
            'channels' => self::enc($channels ?: Engine::CHANNELS), 'segments' => self::enc($segments ?: Engine::SEGMENTS),
            'occasion' => (string) $in['occasion'], 'risk' => in_array($in['risk'], Engine::RISKS, true) ? $in['risk'] : 'متعادل',
            'updated_at' => DB::now(),
        ];
        DB::update('campaigns', $data, ['id' => $c['id']]);
        if ($errors) {
            return ['errors' => $errors];
        }
        $e = Ws::engine($ws);
        $ins = $e->buildInsights(Ws::rates($ws), ['budget' => $budget, 'channels' => $channels, 'segments' => $segments, 'risk' => $data['risk']]);
        $ins = array_values(array_filter($ins, [Engine::class, 'insightValid']));
        if (!$ins) {
            return ['errors' => ['هیچ ردیف نرخی با کانال‌ها و سگمنت‌های مجاز پیدا نشد. قیدها یا جدول نرخ را بررسی کنید.']];
        }
        DB::update('campaigns', ['insights' => self::enc($ins), 'insights_at' => DB::now(), 'sel_id' => null, 'sec_id' => null], ['id' => $c['id']]);
        Audit::log('تولید ده اینسایت', (string) $c['name']);
        Audit::event('insights_generated', ['n' => count($ins)]);
        return ['errors' => []];
    }

    // ------------------------------------------------------------------ plan

    /** Trial tier: 3 plans per Jalali month. */
    public static function overLimit(int $ws): bool
    {
        $w = DB::one('SELECT tier, tier_until FROM workspaces WHERE id = ?', [$ws]);
        $tier = (string) ($w['tier'] ?? 'trial');
        if ($tier !== 'trial' && (!$w['tier_until'] || $w['tier_until'] >= date('Y-m-d'))) {
            return false;
        }
        return self::usageThisMonth($ws) >= 3;
    }

    public static function usageThisMonth(int $ws): int
    {
        $j = Jalali::today();
        $start = Jalali::toIso(substr($j, 0, 8) . '01') ?? date('Y-m-01');
        return (int) DB::val('SELECT COUNT(*) FROM plans WHERE workspace_id = ? AND created_at >= ?', [$ws, $start . ' 00:00:00']);
    }

    /**
     * @param array<string,mixed> $c
     * @return array{ok:bool,error?:string,editing?:bool}
     */
    public static function savePlan(int $ws, array $c, string $sel, ?string $sec, int $mix, string $reason, ?bool $reasonSpecific = null): array
    {
        $ins = self::j($c['insights']) ?: [];
        $e = Ws::engine($ws);
        $sec = $sec !== '' && $sec !== $sel ? $sec : null;
        $mix = max(10, min(90, $mix));
        $alloc = $e->mergedAlloc($ins, $sel, $sec, $mix, (float) $c['budget']);
        if (!$alloc) {
            return ['ok' => false, 'error' => 'ابتدا یک اینسایت را انتخاب کنید.'];
        }
        if (mb_strlen(trim($reason)) < 10) {
            return ['ok' => false, 'error' => 'دلیل انتخاب را دست‌کم در ۱۰ کاراکتر بنویسید.'];
        }
        $name = static fn (?string $id) => current(array_filter($ins, static fn ($i) => $i['id'] === $id))['perspective'] ?? '';
        $persp = $name($sel) . ($sec ? ' + ' . $name($sec) : '');
        $plan = self::plan((int) $c['id']);
        $benchmark = (bool) array_filter($alloc, static fn ($a) => ($a['source'] ?? '') === 'benchmark');
        return DB::tx(static function () use ($ws, $c, $plan, $alloc, $persp, $sel, $sec, $mix, $reason, $reasonSpecific, $benchmark): array {
            if ($plan && !empty($c['current_run_id'])) {
                return ['ok' => false, 'error' => 'نتیجه‌ی این کمپین ثبت شده؛ طرح دیگر قابل ویرایش نیست.'];
            }
            if ($plan) {
                DB::insert('plan_versions', ['plan_id' => $plan['id'], 'version' => $plan['version'], 'perspective' => $plan['perspective'], 'reason' => $plan['reason'], 'allocation' => $plan['allocation'], 'note' => 'ویرایش دیدگاه', 'created_by' => Auth::id() ?: null, 'created_at' => DB::now()]);
                DB::update('plans', ['version' => (int) $plan['version'] + 1, 'perspective' => $persp, 'primary_id' => $sel, 'secondary_id' => $sec, 'mix' => $sec ? $mix : 100, 'reason' => $reason, 'reason_specific' => $reasonSpecific, 'allocation' => self::enc($alloc), 'goal_type' => $c['goal_type'], 'goal_value' => $c['goal_value'], 'benchmark_based' => $benchmark ? 1 : 0, 'updated_at' => DB::now()], ['id' => $plan['id']]);
                Audit::log('ویرایش طرح v' . Fmt::fa((string) ((int) $plan['version'] + 1)) . ' ' . Fmt::fa($plan['code']), $persp);
                Audit::event('plan_edited', ['perspective' => $persp, 'version' => (int) $plan['version'] + 1]);
                Audit::notify('طرح ' . $plan['code'] . ' ویرایش شد (نسخه‌ی ' . Fmt::fa((string) ((int) $plan['version'] + 1)) . ').', 'info', '/c/' . $c['id'] . '/sim', $ws, null, 'plan_changed');
                return ['ok' => true, 'editing' => true];
            }
            $seq = Ws::nextSeq($ws);
            $code = Ws::code('plan', $seq);
            DB::insert('plans', ['campaign_id' => $c['id'], 'workspace_id' => $ws, 'seq' => $seq, 'code' => $code, 'version' => 1, 'perspective' => $persp, 'primary_id' => $sel, 'secondary_id' => $sec, 'mix' => $sec ? $mix : 100, 'reason' => $reason, 'reason_specific' => $reasonSpecific, 'allocation' => self::enc($alloc), 'goal_type' => $c['goal_type'], 'goal_value' => $c['goal_value'], 'benchmark_based' => $benchmark ? 1 : 0, 'created_by' => Auth::id() ?: null, 'created_at' => DB::now()]);
            DB::update('campaigns', ['seq' => $seq, 'status' => 'live', 'sel_id' => $sel, 'sec_id' => $sec, 'mix' => $mix, 'updated_at' => DB::now()], ['id' => $c['id']]);
            // one live campaign per top row (BACKEND-SPEC §3)
            $top = $alloc[0]['ch'] . '|' . $alloc[0]['seg'];
            foreach (DB::all("SELECT c.id, p.allocation FROM campaigns c JOIN plans p ON p.campaign_id = c.id WHERE c.workspace_id = ? AND c.status = 'live' AND c.id <> ?", [$ws, $c['id']]) as $o) {
                $oa = self::j($o['allocation']) ?: [];
                if ($oa && ($oa[0]['ch'] . '|' . $oa[0]['seg']) === $top) {
                    DB::update('campaigns', ['status' => 'stopped', 'updated_at' => DB::now()], ['id' => $o['id']]);
                }
            }
            Audit::log('ثبت طرح ' . Fmt::fa($code), $persp);
            Audit::event('plan_created', ['perspective' => $persp, 'version' => 1]);
            return ['ok' => true, 'editing' => false];
        });
    }

    /** Store a new plan version with a modified allocation (what-if, realloc, rate refresh). @param list<array<string,mixed>> $alloc */
    public static function newVersion(array $c, array $plan, array $alloc, string $note): void
    {
        DB::tx(static function () use ($c, $plan, $alloc, $note): void {
            DB::insert('plan_versions', ['plan_id' => $plan['id'], 'version' => $plan['version'], 'perspective' => $plan['perspective'], 'reason' => $note, 'allocation' => $plan['allocation'], 'note' => $note, 'created_by' => Auth::id() ?: null, 'created_at' => DB::now()]);
            $total = array_sum(array_column($alloc, 'budget'));
            DB::update('plans', ['version' => (int) $plan['version'] + 1, 'allocation' => self::enc($alloc), 'updated_at' => DB::now()], ['id' => $plan['id']]);
            DB::update('campaigns', ['budget' => (int) round($total), 'updated_at' => DB::now()], ['id' => $c['id']]);
        });
    }

    /** Re-read current rates into the plan allocation (keeps budgets). @param list<array<string,mixed>> $alloc @return list<array<string,mixed>> */
    public static function refreshAlloc(int $ws, array $alloc): array
    {
        $rates = [];
        foreach (Ws::rates($ws) as $r) {
            $rates[$r['ch'] . '|' . $r['seg']] = $r;
        }
        return array_map(static function ($a) use ($rates) {
            $r = $rates[$a['ch'] . '|' . $a['seg']] ?? null;
            if (!$r) {
                return $a;
            }
            return array_merge($r, ['budget' => $a['budget'], 'share' => $a['share'] ?? 0]);
        }, $alloc);
    }

    // ------------------------------------------------------------------ simulate

    /** @param array<string,mixed> $c @param array<string,mixed> $plan @return array<string,mixed> */
    public static function simulate(int $ws, array $c, array $plan, ?string $band = null, ?string $ext = null): array
    {
        $e = Ws::engine($ws);
        return $e->simulate($plan['alloc'], (string) $plan['goal_type'], (float) $plan['goal_value'], [
            'band' => $band ?? (string) $c['sim_band'], 'ext' => $ext ?? (string) $c['sim_ext'], 'occasion' => Ws::occasion((string) $c['occasion']),
        ]);
    }

    /** Persist the simulation snapshot (sim_id). @param array<string,mixed> $c @param array<string,mixed> $plan @return array<string,mixed> */
    public static function saveSim(int $ws, array $c, array $plan): array
    {
        $s = self::simulate($ws, $c, $plan);
        $code = Ws::code('sim', (int) $plan['seq'], substr((string) $plan['created_at'], 0, 10));
        $id = DB::insert('simulations', [
            'campaign_id' => $c['id'], 'plan_version' => $plan['version'], 'code' => $code, 'band_width' => $c['sim_band'], 'ext_factor' => $c['sim_ext'],
            'occasion' => $c['occasion'], 'allocation' => $plan['allocation'], 'result' => self::enc($s), 'created_by' => Auth::id() ?: null, 'created_at' => DB::now(),
        ]);
        Audit::event('sim_run', ['band' => $c['sim_band'], 'ext' => $c['sim_ext']]);
        return ['id' => $id, 'code' => $code, 'r' => $s, 'plan_version' => $plan['version']];
    }

    /** Latest saved sim for the current plan version, or save one now. @return array<string,mixed> */
    public static function ensureSim(int $ws, array $c, array $plan): array
    {
        $s = self::latestSim((int) $c['id']);
        if ($s && (int) $s['plan_version'] === (int) $plan['version']) {
            return $s;
        }
        return self::saveSim($ws, $c, $plan);
    }

    // ------------------------------------------------------------------ verify & calibrate

    /**
     * Create a run + verification.
     * @param array<string,mixed>|null $c campaign (null = manual path)
     * @param array<string,mixed> $vi  single-row form (manual) — or with 'rows' for connected multi-row
     * @return int run id
     */
    public static function verify(int $ws, ?array $c, array $vi, string $source = 'manual', string $status = 'confirmed'): int
    {
        $e = Ws::engine($ws);
        $plan = $c ? self::plan((int) $c['id']) : null;
        $sim = ($c && $plan) ? self::ensureSim($ws, $c, $plan) : null;
        if (!empty($vi['rows']) && is_array($vi['rows'])) {
            $m = $e->verifyRows($vi['rows'], $vi);
            $result = $m['overall'] + ['rowResults' => $m['rows'], 'totals' => $m['totals']];
            foreach (['pb', 'pi', 'pc', 'ab', 'ai', 'ac'] as $k) {
                $vi[$k] = $m['totals'][$k];
            }
        } else {
            $result = $e->verify($vi);
            $result['rowResults'] = [['ch' => $vi['ch'], 'seg' => $vi['seg'], 'vi' => $vi, 'vr' => $result]];
        }
        if (!empty($vi['totals_only']) && !empty($result['calib'])) {
            $result['calib'] = false;
            $result['block'] = 'نتیجه به تفکیک ردیف ثبت نشده؛ نسبت‌دادن کل مشاهده به یک ردیف حافظه را آلوده می‌کند.';
            foreach ($result['rowResults'] as &$rr) {
                $rr['vr']['calib'] = false;
            }
            unset($rr);
        }
        if ($sim) {
            $sr = $sim['r'];
            $result['withinBand'] = [
                'installs' => $vi['ai'] >= $sr['iLow'] && $vi['ai'] <= $sr['iHigh'],
                'conversions' => $vi['ac'] >= $sr['cLow'] && $vi['ac'] <= $sr['cHigh'],
                'belowLow' => $vi['ac'] < $sr['cLow'] || $vi['ai'] < $sr['iLow'],
            ];
        }
        $seq = $plan ? (int) $plan['seq'] : Ws::nextSeq($ws);
        $code = Ws::code('run', $seq);
        return DB::tx(static function () use ($ws, $c, $vi, $result, $seq, $code, $source, $status, $sim, $e): int {
            $rid = DB::insert('runs', ['workspace_id' => $ws, 'campaign_id' => $c['id'] ?? null, 'simulation_id' => $sim['id'] ?? null, 'seq' => $seq, 'code' => $code, 'input' => self::enc($vi), 'status' => $status, 'source' => $source, 'created_by' => Auth::id() ?: null, 'created_at' => DB::now()]);
            DB::insert('verifications', ['run_id' => $rid, 'cause' => $result['cause'], 'calibratable' => $result['calib'] ? 1 : 0, 'result' => self::enc($result), 'rules_snapshot' => self::enc($e->cfg), 'created_at' => DB::now()]);
            if ($c && $status === 'confirmed') {
                DB::update('campaigns', ['current_run_id' => $rid, 'updated_at' => DB::now()], ['id' => $c['id']]);
            }
            if ($status === 'confirmed') {
                Audit::log('انتساب علت', $result['cause']);
                Audit::event('run_verified', ['cause' => $result['cause']]);
            }
            return $rid;
        });
    }

    /**
     * Apply calibration for every calibratable row of a run.
     * @return array{ok:bool,error?:string,codes?:list<string>}
     */
    public static function calibrate(int $ws, int $runId): array
    {
        $run = self::run($runId);
        if (!$run || (int) $run['workspace_id'] !== $ws || !$run['ver']) {
            return ['ok' => false, 'error' => 'نتیجه‌ای برای کالیبراسیون پیدا نشد.'];
        }
        if ($run['cals']) {
            return ['ok' => false, 'error' => 'کالیبراسیون این نتیجه قبلاً اعمال شده است.'];
        }
        $vr = $run['ver']['r'];
        if (empty($vr['calib'])) {
            return ['ok' => false, 'error' => 'در شاخه‌ی «' . $vr['cause'] . '» کالیبراسیون عمداً اعمال نمی‌شود.'];
        }
        $e = Ws::engine($ws);
        $rates = [];
        foreach (Ws::rates($ws) as $r) {
            $rates[$r['ch'] . '|' . $r['seg']] = $r;
        }
        $season = (($run['in']['season'] ?? 'خیر') === 'بله');
        $codes = [];
        DB::tx(static function () use ($ws, $run, $vr, $e, $rates, $season, &$codes): void {
            $multi = count($vr['rowResults'] ?? []) > 1;
            foreach ($vr['rowResults'] as $row) {
                if (empty($row['vr']['calib'])) {
                    continue;
                }
                $key = $row['ch'] . '|' . $row['seg'];
                $rate = $rates[$key] ?? null;
                if (!$rate) {
                    continue;
                }
                $dbBefore = DB::one('SELECT * FROM rates WHERE id = ?', [$rate['id']]);
                $c = $e->calibrate($rate, (float) $row['vi']['ab'], $row['vr'], $season);
                DB::update('rates', ['unit_cost' => $c['row']['cpi'], 'cvr' => $c['row']['cvr'], 'sample_n' => $c['row']['n'], 'observed_at' => date('Y-m-d'), 'source' => 'calibration', 'updated_at' => DB::now()], ['id' => $rate['id']]);
                $code = Ws::code('cal', (int) $run['seq']) . ($multi ? '-' . (count($codes) + 1) : '');
                DB::insert('calibrations', ['workspace_id' => $ws, 'verification_id' => $run['ver']['id'], 'run_id' => $run['id'], 'rate_id' => $rate['id'], 'code' => $code, 'row_label' => $key, 'before_row' => self::enc($dbBefore), 'after_row' => self::enc(['cpi' => $c['after']['cpi'], 'cvr' => $c['after']['cvr'], 'n' => $c['after']['n'], 'before' => $c['before']]), 'weights' => $c['weights'], 'applied_by' => Auth::id() ?: null, 'created_at' => DB::now()]);
                $codes[] = $code;
                Audit::log('کالیبراسیون ردیف ' . $key, 'CPI ' . Fmt::num($c['before']['cpi']) . ' → ' . Fmt::num($c['after']['cpi']));
                Audit::event('calibration_applied', ['rate_row' => $key]);
            }
        });
        if (!$codes) {
            return ['ok' => false, 'error' => 'ردیف متناظر در جدول نرخ پیدا نشد.'];
        }
        Audit::notify('کالیبراسیون ' . implode('، ', $codes) . ' اعمال شد.', 'ok', $run['campaign_id'] ? '/c/' . $run['campaign_id'] . '/loop' : '/verify', $ws, ['owner', 'analyst'], 'calibration_applied');
        return ['ok' => true, 'codes' => $codes];
    }

    /** Revert the most recent calibration (only the latest per rate row, max 10 back). @return array{ok:bool,error?:string} */
    public static function undo(int $ws, int $calId): array
    {
        $cal = DB::one('SELECT * FROM calibrations WHERE id = ? AND workspace_id = ? AND reverted_at IS NULL', [$calId, $ws]);
        if (!$cal) {
            return ['ok' => false, 'error' => 'کالیبراسیون پیدا نشد یا قبلاً بازگردانده شده است.'];
        }
        $recent = array_map('intval', array_column(DB::all('SELECT id FROM calibrations WHERE workspace_id = ? AND reverted_at IS NULL ORDER BY id DESC LIMIT 10', [$ws]), 'id'));
        if (!in_array((int) $cal['id'], $recent, true)) {
            return ['ok' => false, 'error' => 'فقط ۱۰ کالیبراسیون آخر قابل بازگردانی‌اند.'];
        }
        $newer = DB::val('SELECT COUNT(*) FROM calibrations WHERE rate_id = ? AND id > ? AND reverted_at IS NULL', [$cal['rate_id'], $cal['id']]);
        if ((int) $newer > 0) {
            return ['ok' => false, 'error' => 'فقط جدیدترین کالیبراسیون هر ردیف قابل بازگردانی است.'];
        }
        $b = self::j($cal['before_row']);
        DB::tx(static function () use ($cal, $b): void {
            DB::update('rates', ['unit_cost' => $b['unit_cost'], 'cvr' => $b['cvr'], 'sample_n' => $b['sample_n'], 'observed_at' => $b['observed_at'], 'source' => $b['source'], 'updated_at' => DB::now()], ['id' => $cal['rate_id']]);
            DB::update('calibrations', ['reverted_at' => DB::now(), 'reverted_by' => Auth::id() ?: null], ['id' => $cal['id']]);
        });
        Audit::log('بازگردانی کالیبراسیون ' . Fmt::fa($cal['code']), 'ردیف ' . $cal['row_label']);
        Audit::event('calibration_reverted', ['rate_row' => $cal['row_label']]);
        Audit::notify('کالیبراسیون ' . $cal['code'] . ' بازگردانده شد.', 'info', '/data', $ws, ['owner', 'analyst'], 'calibration_applied');
        return ['ok' => true];
    }

    // ------------------------------------------------------------------ close

    /** @param array<string,mixed> $c @return array{ok:bool,error?:string,first?:bool,code?:string} */
    public static function close(int $ws, array $c): array
    {
        $plan = self::plan((int) $c['id']);
        $run = !empty($c['current_run_id']) ? self::run((int) $c['current_run_id']) : null;
        if (!$plan || !$run || !$run['ver']) {
            return ['ok' => false, 'error' => 'برای بستن کمپین ابتدا طرح و نتیجه‌ی واقعی لازم است.'];
        }
        if ($c['status'] === 'closed') {
            return ['ok' => false, 'error' => 'این کمپین قبلاً بسته شده است.'];
        }
        $sim = self::ensureSim($ws, $c, $plan)['r'];
        $in = $run['in'];
        $vr = $run['ver']['r'];
        $ab = (float) $in['ab'];
        $ac = (float) $in['ac'];
        $ai = (float) $in['ai'];
        $profile = Ws::profile($ws);
        $aovW = (float) ($sim['aovW'] ?? 0);
        $actualPoas = $ab > 0 ? ($ac * $aovW * (float) $profile['margin'] - $ab) / $ab : 0.0;
        $gt = (string) $plan['goal_type'];
        $metric = $gt === 'نصب' ? $ai : ($gt === 'درآمد' ? $ac * $aovW : $ac);
        $goalHit = (float) $plan['goal_value'] > 0 ? $metric >= (float) $plan['goal_value'] : null;
        $first = !DB::val('SELECT COUNT(*) FROM perspective_log WHERE workspace_id = ? AND seeded = 0', [$ws]);
        $code = DB::tx(static function () use ($ws, $c, $plan, $run, $sim, $ab, $ac, $actualPoas, $goalHit, $vr, $in): string {
            $n = (int) DB::val('SELECT log_seq FROM workspaces WHERE id = ? FOR UPDATE', [$ws]);
            DB::q('UPDATE workspaces SET log_seq = log_seq + 1 WHERE id = ?', [$ws]);
            $code = 'ک-' . Fmt::fa((string) $n);
            DB::insert('perspective_log', [
                'workspace_id' => $ws, 'campaign_id' => $c['id'], 'code' => $code, 'name' => (string) ($in['name'] ?? $c['name']),
                'perspective' => $plan['perspective'], 'reason' => $plan['reason'] ?: '—', 'cause' => $vr['cause'], 'calibrated' => $run['cals'] ? 1 : 0,
                'planned_cac' => round((float) $sim['cac'], 2), 'actual_cac' => $ac > 0 ? round($ab / $ac, 2) : 0, 'actual_poas' => round($actualPoas, 6),
                'goal_hit' => $goalHit === null ? null : ($goalHit ? 1 : 0), 'lift' => $vr['lift'] ?? null, 'seeded' => 0, 'created_at' => DB::now(),
            ]);
            DB::update('campaigns', ['status' => 'closed', 'closed_at' => DB::now(), 'updated_at' => DB::now()], ['id' => $c['id']]);
            return $code;
        });
        Audit::log('بستن کمپین ' . $code, (string) $vr['cause']);
        Audit::event('campaign_closed', ['poas' => round($actualPoas, 4), 'goal_hit' => $goalHit]);
        Audit::notify('کمپین ' . $code . ' بسته و در دفترچه ثبت شد.', 'ok', '/log', $ws, null, 'campaign_closed');
        return ['ok' => true, 'first' => $first, 'code' => $code];
    }
}
