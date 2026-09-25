<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Http;
use App\Core\Jalali;
use App\Core\Log;
use App\Core\Settings;

/**
 * Adtrace (paid channels: spend, installs, fraud) & Intrack (owned channels: reach, conversions, revenue).
 * Mode: settings.connector_mode = mock | live. Live endpoints are configurable because the real
 * API contract will be provided later (docs/INTEGRATIONS.md); mock mode produces deterministic data
 * from the campaign forecast so the whole loop can be exercised automatically.
 */
final class Connectors
{
    public const KINDS = [
        'adtrace' => ['name' => 'ادتریس', 'note' => 'کمپین‌های تبلیغاتی و هزینه‌ی هر کانال — ورودی جدول نرخ‌ها و پایش روزانه'],
        'intrack' => ['name' => 'اینترک', 'note' => 'رویدادهای کاربر، سگمنت‌ها و نصب/خرید — ورودی تاریخچه‌ی کمپین و پیامک/پوش'],
    ];

    public static function mode(): string
    {
        return Settings::get('connector_mode', 'mock') === 'live' ? 'live' : 'mock';
    }

    /** @return array<string,mixed>|null */
    public static function account(int $ws, string $kind): ?array
    {
        return DB::one('SELECT * FROM connector_accounts WHERE workspace_id = ? AND kind = ?', [$ws, $kind]);
    }

    /** @return array{ok:bool,error?:string} */
    public static function testConnection(string $kind, string $key, string $base): array
    {
        if (self::mode() === 'mock') {
            return strlen($key) >= 4 ? ['ok' => true] : ['ok' => false, 'error' => 'کلید API کوتاه است.'];
        }
        $url = rtrim($base ?: Settings::get($kind . '_base_url'), '/');
        if ($url === '') {
            return ['ok' => false, 'error' => 'آدرس API تنظیم نشده است (مدیریت سامانه ← اتصال‌ها).'];
        }
        $r = Http::request('GET', $url . '/ping', ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json'], null, 10000);
        return $r['status'] >= 200 && $r['status'] < 300 ? ['ok' => true] : ['ok' => false, 'error' => 'پاسخ سرویس: HTTP ' . $r['status'] . ($r['error'] ? ' · ' . $r['error'] : '')];
    }

    /**
     * Daily stats for one external campaign.
     * @param array<string,mixed> $acc
     * @param array<string,mixed> $c campaign
     * @return array{spend:float,installs:float,conversions:float,revenue:float,fraud_installs:float,reach:float}|null
     */
    public static function fetchDaily(array $acc, array $c, string $externalId, string $day): ?array
    {
        if (self::mode() === 'mock') {
            $plan = Loop::plan((int) $c['id']);
            $sim = $plan ? Loop::latestSim((int) $c['id']) : null;
            if (!$sim) {
                return null;
            }
            $days = Loop::durationDays($c);
            $seed = crc32($externalId . $day);
            $noise = 0.85 + ($seed % 30) / 100;
            $s = $sim['r'];
            $share = $acc['kind'] === 'intrack' ? ($s['owned'] / max($s['budget'], 1)) : ($s['paid'] / max($s['budget'], 1));
            $conv = $s['conv'] / $days * $noise * 0.8 * $share;
            return [
                'spend' => $s['budget'] / $days * $noise * $share, 'installs' => $s['installs'] / $days * $noise * 0.85 * $share,
                'conversions' => $conv, 'revenue' => $conv * ($s['aovW'] ?? 0), 'fraud_installs' => $acc['kind'] === 'adtrace' ? $s['fraudInst'] / $days * $share : 0,
                'reach' => $acc['kind'] === 'intrack' ? $s['installs'] / $days * $share : 0,
            ];
        }
        $key = Crypto::decrypt((string) $acc['creds_enc']);
        $base = rtrim($acc['base_url'] ?: Settings::get($acc['kind'] . '_base_url'), '/');
        $r = Http::request('GET', $base . '/campaigns/' . rawurlencode($externalId) . '/stats?date=' . $day, ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json'], null, 15000);
        $d = json_decode($r['body'], true);
        if ($r['status'] < 200 || $r['status'] >= 300 || !is_array($d)) {
            throw new \RuntimeException('HTTP ' . $r['status'] . ' ' . $r['error']);
        }
        $d = $d['data'] ?? $d;
        return [
            'spend' => (float) ($d['spend'] ?? $d['cost'] ?? 0), 'installs' => (float) ($d['installs'] ?? 0), 'conversions' => (float) ($d['conversions'] ?? $d['purchases'] ?? 0),
            'revenue' => (float) ($d['revenue'] ?? 0), 'fraud_installs' => (float) ($d['fraud_installs'] ?? 0), 'reach' => (float) ($d['reach'] ?? $d['delivered'] ?? 0),
        ];
    }

    /** Sync all linked live campaigns of a workspace (or all workspaces). Returns number of day-rows synced. */
    public static function sync(?int $wsOnly = null): int
    {
        $n = 0;
        $links = DB::all("SELECT l.*, a.kind, a.creds_enc, a.base_url, a.workspace_id, a.id AS acc_id, a.fail_count FROM connector_links l JOIN connector_accounts a ON a.id = l.connector_account_id JOIN campaigns c ON c.id = l.campaign_id
            WHERE a.status = 'connected' AND c.status = 'live'" . ($wsOnly ? ' AND a.workspace_id = ' . (int) $wsOnly : ''));
        foreach ($links as $l) {
            $ws = (int) $l['workspace_id'];
            $c = Loop::campaign($ws, (int) $l['campaign_id']);
            if (!$c || !$c['date_from']) {
                continue;
            }
            $end = min(date('Y-m-d', strtotime('-1 day')), (string) $c['date_to']);
            $day = (string) $c['date_from'];
            try {
                while ($day <= $end) {
                    if (!DB::val('SELECT 1 FROM connector_syncs WHERE connector_account_id = ? AND campaign_id = ? AND day = ?', [$l['acc_id'], $c['id'], $day])) {
                        $d = self::fetchDaily(['kind' => $l['kind'], 'creds_enc' => $l['creds_enc'], 'base_url' => $l['base_url']], $c, (string) $l['external_id'], $day);
                        if ($d !== null) {
                            DB::insert('connector_syncs', ['connector_account_id' => $l['acc_id'], 'campaign_id' => $c['id'], 'day' => $day, 'payload' => json_encode($d), 'status' => 'ok', 'created_at' => DB::now()]);
                            $n++;
                        }
                    }
                    $day = date('Y-m-d', strtotime($day . ' +1 day'));
                }
                DB::update('connector_accounts', ['last_sync_at' => DB::now(), 'last_error' => '', 'fail_count' => 0], ['id' => $l['acc_id']]);
            } catch (\Throwable $e) {
                $fails = (int) $l['fail_count'] + 1;
                DB::update('connector_accounts', ['last_error' => mb_substr($e->getMessage(), 0, 250), 'fail_count' => $fails, 'status' => $fails >= 5 ? 'error' : 'connected'], ['id' => $l['acc_id']]);
                Log::error('connector sync', ['kind' => $l['kind'], 'msg' => $e->getMessage()]);
                if ($fails >= 5) {
                    Audit::notify('همگام‌سازی ' . self::KINDS[$l['kind']]['name'] . ' پنج بار ناموفق بود و متوقف شد. کلید را بررسی کنید.', 'bad', '/connect', $ws, ['owner'], 'connector_error');
                }
                continue;
            }
            self::rollup($ws, $c);
        }
        return $n;
    }

    /** Aggregate synced days → cumulative pace snapshot, alerts, and auto-drafted run after the attribution window. @param array<string,mixed> $c */
    public static function rollup(int $ws, array $c): void
    {
        $rows = DB::all('SELECT s.day, s.payload FROM connector_syncs s WHERE s.campaign_id = ? ORDER BY s.day', [$c['id']]);
        if (!$rows) {
            return;
        }
        $t = ['spend' => 0.0, 'installs' => 0.0, 'conversions' => 0.0, 'revenue' => 0.0, 'fraud_installs' => 0.0, 'reach' => 0.0];
        $lastDay = '';
        foreach ($rows as $r) {
            $p = json_decode((string) $r['payload'], true) ?: [];
            foreach ($t as $k => $_) {
                $t[$k] += (float) ($p[$k] ?? 0);
            }
            $lastDay = (string) $r['day'];
        }
        $dayN = (int) floor((strtotime($lastDay) - strtotime((string) $c['date_from'])) / 86400) + 1;
        $prev = Loop::latestPace((int) $c['id']);
        if (!$prev || (int) $prev['day'] !== $dayN || $prev['source'] === 'manual') {
            DB::insert('pace_snapshots', ['campaign_id' => $c['id'], 'day' => $dayN, 'spend' => (int) round($t['spend']), 'installs' => (int) round($t['installs']), 'conversions' => (int) round($t['conversions']), 'source' => 'sync', 'created_at' => DB::now()]);
            $plan = Loop::plan((int) $c['id']);
            if ($plan) {
                \App\Controllers\CampaignController::paceAlerts($ws, $c, $plan, ['day' => $dayN, 'spend' => $t['spend'], 'installs' => $t['installs'], 'conv' => $t['conversions']]);
            }
        }
        $rules = Ws::rules($ws);
        $ready = date('Y-m-d') > date('Y-m-d', strtotime((string) $c['date_to'] . ' +' . (int) $rules['attrWindow'] . ' days'));
        if ($ready && empty($c['current_run_id']) && !DB::val("SELECT 1 FROM runs WHERE campaign_id = ? AND status = 'draft'", [$c['id']])) {
            self::draftRun($ws, $c, $t, (int) $rules['attrWindow']);
        }
    }

    /** @param array<string,float> $t totals */
    private static function draftRun(int $ws, array $c, array $t, int $window): void
    {
        $plan = Loop::plan((int) $c['id']);
        if (!$plan) {
            return;
        }
        $sim = Loop::ensureSim($ws, $c, $plan)['r'];
        $rows = [];
        foreach ($sim['rows'] as $r) {
            $sh = (float) $r['share'];
            $rows[] = ['ch' => $r['ch'], 'seg' => $r['seg'], 'pb' => round($r['budget']), 'pi' => round($r['installs']), 'pc' => round($r['conv']), 'ab' => round($t['spend'] * $sh), 'ai' => round($t['installs'] * $sh), 'ac' => round($t['conversions'] * $sh)];
        }
        $inst = max($t['installs'], 1);
        $vi = [
            'name' => $c['name'], 'ch' => $rows[0]['ch'], 'seg' => $rows[0]['seg'], 'from' => Jalali::fromIso((string) $c['date_from']), 'to' => Jalali::fromIso((string) $c['date_to']),
            'src' => 'مکتوب و عددی', 'complete' => 'بله', 'matched' => 'بله', 'fraud' => round($t['fraud_installs'] / $inst * 100, 2), 'window' => $window, 'season' => 'خیر',
            'reach' => round($t['reach']), 'holdout' => 0, 'rows' => $rows, 'totals_only' => count($rows) > 1,
        ];
        Loop::verify($ws, $c, $vi, 'sync', 'draft');
        Audit::notify('نتیجه‌ی «' . $c['name'] . '» از داده‌ی همگام‌شده پیش‌نویس شد؛ بررسی و تأیید کنید.', 'info', '/c/' . $c['id'] . '/verify', $ws, ['owner', 'analyst', 'campaign_ops'], 'run_draft');
    }
}
