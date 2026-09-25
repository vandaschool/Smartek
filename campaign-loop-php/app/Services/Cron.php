<?php
declare(strict_types=1);

namespace App\Services;

use App\AI\AI;
use App\Core\Audit;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Jalali;
use App\Core\Log;
use App\Core\Url;

/**
 * Scheduled jobs. Run cron.php every 15 minutes; each job has its own minimum interval
 * (recorded in cron_runs) so frequent invocations are cheap and idempotent.
 */
final class Cron
{
    /** job => minimum minutes between runs */
    private const JOBS = [
        'connector_sync' => 60,
        'campaign_ending' => 60,
        'awaiting_result' => 360,
        'monthly_report' => 360,
        'expire_invites' => 60,
        'purge_demo' => 720,
        'data_delete' => 720,
        'benchmarks' => 1440,
        'cleanup' => 360,
    ];

    /** @return array<string,string> job => status */
    public static function run(bool $force = false, ?string $only = null): array
    {
        $out = [];
        foreach (self::JOBS as $job => $every) {
            if ($only !== null && $only !== $job) {
                continue;
            }
            $last = DB::val('SELECT last_run_at FROM cron_runs WHERE job = ?', [$job]);
            if (!$force && $last && strtotime((string) $last) > time() - $every * 60 + 30) {
                $out[$job] = 'skip';
                continue;
            }
            try {
                $status = (string) self::$job();
            } catch (\Throwable $e) {
                Log::error('cron ' . $job, ['msg' => $e->getMessage(), 'line' => $e->getLine()]);
                $status = 'error: ' . mb_substr($e->getMessage(), 0, 200);
            }
            DB::q('INSERT INTO cron_runs (job, last_run_at, last_status) VALUES (?, NOW(), ?) ON DUPLICATE KEY UPDATE last_run_at = NOW(), last_status = VALUES(last_status)', [$job, mb_substr($status, 0, 250)]);
            $out[$job] = $status;
        }
        return $out;
    }

    /** @return list<array<string,mixed>> users of a workspace with one of the roles */
    private static function members(int $ws, array $roles): array
    {
        $in = implode(',', array_fill(0, count($roles), '?'));
        return DB::all("SELECT u.id, u.email, u.name FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.role IN ($in) AND u.deleted_at IS NULL AND u.email NOT LIKE '%@demo.local'", array_merge([$ws], $roles));
    }

    private static function connector_sync(): string
    {
        return 'synced ' . Connectors::sync() . ' day-rows';
    }

    /** Two days before date_to: remind owner/analyst/ops to record the result (once per campaign). */
    private static function campaign_ending(): string
    {
        $n = 0;
        $rows = DB::all("SELECT * FROM campaigns WHERE status = 'live' AND ending_notified_at IS NULL AND date_to IS NOT NULL AND date_to BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 2 DAY)");
        foreach ($rows as $c) {
            $ws = (int) $c['workspace_id'];
            $link = Url::to('/c/' . $c['id'] . '/verify', [], true);
            foreach (self::members($ws, ['owner', 'analyst', 'campaign_ops']) as $u) {
                if (Emails::wants((int) $u['id'], 'campaign_ending')) {
                    Emails::campaignEnding((string) $u['email'], (string) $c['name'], $link);
                }
            }
            Audit::notify('«' . $c['name'] . '» تا ' . Jalali::fa((string) $c['date_to']) . ' تمام می‌شود؛ بعد از پایان نتیجه را ثبت کنید.', 'info', '/c/' . $c['id'] . '/verify', $ws, ['owner', 'analyst', 'campaign_ops'], 'campaign_ending');
            DB::update('campaigns', ['ending_notified_at' => DB::now()], ['id' => $c['id']]);
            $n++;
        }
        return 'notified ' . $n;
    }

    /** Campaign ended + attribution window passed and still no result: one in-app nudge. */
    private static function awaiting_result(): string
    {
        $n = 0;
        $rows = DB::all("SELECT c.*, r.attr_window FROM campaigns c JOIN rules r ON r.workspace_id = c.workspace_id WHERE c.status = 'live' AND c.current_run_id IS NULL AND c.date_to IS NOT NULL AND DATE_ADD(c.date_to, INTERVAL r.attr_window DAY) < CURDATE()");
        foreach ($rows as $c) {
            $exists = DB::val("SELECT 1 FROM notifications WHERE workspace_id = ? AND type = 'awaiting_result' AND link = ? LIMIT 1", [$c['workspace_id'], '/c/' . $c['id'] . '/verify']);
            if ($exists) {
                continue;
            }
            Audit::notify('نتیجه‌ی «' . $c['name'] . '» هنوز ثبت نشده؛ حلقه باز مانده است.', 'bad', '/c/' . $c['id'] . '/verify', (int) $c['workspace_id'], ['owner', 'analyst', 'campaign_ops'], 'awaiting_result');
            $n++;
        }
        return 'nudged ' . $n;
    }

    /** First run in a new Jalali month: summary of the previous month for owners (+ AI narration when enabled). */
    private static function monthly_report(): string
    {
        $today = Jalali::today();
        [$jy, $jm] = [(int) substr($today, 0, 4), (int) substr($today, 5, 2)];
        [$py, $pm] = $jm === 1 ? [$jy - 1, 12] : [$jy, $jm - 1];
        $key = 'monthly_report:' . sprintf('%04d-%02d', $py, $pm);
        if (DB::val('SELECT 1 FROM cron_runs WHERE job = ?', [$key])) {
            return 'already sent for ' . $key;
        }
        $from = Jalali::toIso(sprintf('%04d/%02d/01', $py, $pm)) . ' 00:00:00';
        $to = Jalali::toIso(sprintf('%04d/%02d/01', $jy, $jm)) . ' 00:00:00';
        $label = Jalali::monthName($pm) . ' ' . Fmt::fa((string) $py);
        $sent = 0;
        $wss = DB::all("SELECT w.* FROM workspaces w WHERE w.deleted_at IS NULL AND EXISTS (SELECT 1 FROM campaigns c WHERE c.workspace_id = w.id AND c.created_at < ?) AND NOT EXISTS (SELECT 1 FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = w.id AND m.role = 'owner' AND u.email LIKE '%@demo.local')", [$to]);
        foreach ($wss as $w) {
            $ws = (int) $w['id'];
            $k = self::monthKpis($ws, $from, $to, $py, $pm);
            $ai = AI::enabled($ws) ? AI::monthlySummary($ws, $label, $k['best'], $k['bestLowN'], $k['facts']) : null;
            $link = Url::to('/dash', ['view' => 'cfo'], true);
            foreach (self::members($ws, ['owner', 'viewer']) as $u) {
                if (Emails::wants((int) $u['id'], 'monthly_report') && Emails::monthly((string) $u['email'], $label, (string) $w['name'], $k['display'], $ai, $link)) {
                    $sent++;
                }
            }
            Audit::notify('گزارش ' . $label . ' آماده است.', 'info', '/dash?view=cfo', $ws, ['owner', 'viewer'], 'monthly_report');
        }
        DB::q('INSERT INTO cron_runs (job, last_run_at, last_status) VALUES (?, NOW(), ?)', [$key, 'sent ' . $sent]);
        return $key . ' sent ' . $sent;
    }

    /** @return array{display:array<string,string>,facts:list<array<string,mixed>>,best:string,bestLowN:bool} */
    public static function monthKpis(int $ws, string $from, string $to, int $py, int $pm): array
    {
        $p = sprintf('%04d-%02d', $py, $pm);
        $log = DB::all('SELECT * FROM perspective_log WHERE workspace_id = ? AND seeded = 0 AND created_at >= ? AND created_at < ?', [$ws, $from, $to]);
        $closed = count($log);
        $poasVals = array_values(array_filter(array_map(static fn ($r) => $r['actual_poas'] !== null ? (float) $r['actual_poas'] : null, $log), static fn ($x) => $x !== null));
        $poas = $poasVals ? array_sum($poasVals) / count($poasVals) : null;
        $cacVals = array_values(array_filter(array_map(static fn ($r) => (float) $r['actual_cac'], $log), static fn ($x) => $x > 0));
        $cac = $cacVals ? array_sum($cacVals) / count($cacVals) : null;
        $errVals = array_values(array_filter(array_map(static fn ($r) => (float) $r['planned_cac'] > 0 ? abs((float) $r['actual_cac'] / (float) $r['planned_cac'] - 1) : null, $log), static fn ($x) => $x !== null));
        $err = $errVals ? array_sum($errVals) / count($errVals) : null;
        $target = (float) Ws::profile($ws)['targetCac'];
        $vsT = ($cac !== null && $target > 0) ? $cac / $target - 1 : null;
        $cals = (int) DB::val('SELECT COUNT(*) FROM calibrations WHERE workspace_id = ? AND reverted_at IS NULL AND created_at >= ? AND created_at < ?', [$ws, $from, $to]);
        $awaiting = (int) DB::val("SELECT COUNT(*) FROM campaigns WHERE workspace_id = ? AND status = 'live' AND current_run_id IS NULL AND date_to < CURDATE()", [$ws]);
        $dq = Ws::engine($ws)->decisionQuality(DB::all('SELECT * FROM perspective_log WHERE workspace_id = ?', [$ws]));
        $bestRow = $dq['rows'][0] ?? null;
        $best = (string) $dq['best'];
        $display = [
            'closed_loops' => Fmt::fa((string) $closed), 'poas' => $poas !== null ? Fmt::signPct($poas) : '—',
            'cac_vs_target' => $vsT !== null ? Fmt::signPct($vsT) : '—', 'forecast_error' => $err !== null ? Fmt::pct($err, 0) : '—',
            'best_perspective' => $best, 'pending_count' => Fmt::fa((string) $awaiting),
        ];
        $facts = [
            ['ref' => "kpi:$p#closed_loops", 'label' => 'حلقه‌های بسته‌شده', 'value' => $closed, 'display' => $display['closed_loops']],
            ['ref' => "kpi:$p#calibrations", 'label' => 'کالیبراسیون‌های اعمال‌شده', 'value' => $cals, 'display' => Fmt::fa((string) $cals)],
            ['ref' => "kpi:$p#awaiting_result", 'label' => 'کمپین در انتظار نتیجه', 'value' => $awaiting, 'display' => $display['pending_count']],
        ];
        if ($poas !== null) {
            $facts[] = ['ref' => "kpi:$p#poas", 'label' => 'POAS کل ماه', 'value' => round($poas, 4), 'display' => $display['poas']];
        }
        if ($cac !== null) {
            $facts[] = ['ref' => "kpi:$p#cac", 'label' => 'CAC کل ماه', 'value' => round($cac), 'display' => Fmt::money($cac) . ' تومان'];
        }
        if ($target > 0) {
            $facts[] = ['ref' => 'profile#target_cac', 'label' => 'CAC هدف', 'value' => $target, 'display' => Fmt::money($target) . ' تومان'];
        }
        if ($vsT !== null) {
            $facts[] = ['ref' => "kpi:$p#cac_vs_target", 'label' => 'CAC نسبت به هدف', 'value' => round($vsT, 4), 'display' => $display['cac_vs_target']];
        }
        if ($bestRow) {
            $facts[] = ['ref' => 'log#best_quality', 'label' => 'کیفیت تصمیم بهترین دیدگاه', 'value' => round($bestRow['good'] / max(1, $bestRow['n']), 4), 'display' => Fmt::fa((string) $bestRow['good']) . ' از ' . Fmt::fa((string) $bestRow['n'])];
        }
        return ['display' => $display, 'facts' => $facts, 'best' => $best, 'bestLowN' => $bestRow ? $bestRow['n'] < 3 : true];
    }

    private static function expire_invites(): string
    {
        $n = DB::q("UPDATE invites SET status = 'expired' WHERE status = 'pending' AND expires_at < NOW()")->rowCount();
        return 'expired ' . $n;
    }

    /** Ephemeral demo accounts (@demo.local) older than 7 days are removed with their workspaces. */
    private static function purge_demo(): string
    {
        $users = DB::all("SELECT id FROM users WHERE email LIKE '%@demo.local' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        foreach ($users as $u) {
            foreach (DB::all("SELECT workspace_id FROM memberships WHERE user_id = ? AND role = 'owner'", [$u['id']]) as $m) {
                self::wipeWorkspace((int) $m['workspace_id'], true);
            }
            foreach (['memberships', 'sessions', 'email_tokens', 'notifications'] as $t) {
                DB::q("DELETE FROM $t WHERE user_id = ?", [$u['id']]);
            }
            DB::q('DELETE FROM users WHERE id = ?', [$u['id']]);
        }
        return 'purged ' . count($users);
    }

    /** Pending delete requests older than 30 days → wipe workspace data. */
    private static function data_delete(): string
    {
        $reqs = DB::all("SELECT * FROM data_requests WHERE type = 'delete' AND status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        foreach ($reqs as $r) {
            self::wipeWorkspace((int) $r['workspace_id'], false);
            DB::update('data_requests', ['status' => 'done', 'completed_at' => DB::now()], ['id' => $r['id']]);
        }
        return 'deleted ' . count($reqs);
    }

    public static function wipeWorkspace(int $ws, bool $hard): void
    {
        DB::tx(static function () use ($ws, $hard): void {
            $camps = array_map('intval', array_column(DB::all('SELECT id FROM campaigns WHERE workspace_id = ?', [$ws]), 'id'));
            if ($camps) {
                $in = implode(',', $camps);
                foreach (['plan_versions' => 'plan_id IN (SELECT id FROM plans WHERE campaign_id IN (' . $in . '))', 'simulations' => "campaign_id IN ($in)", 'pace_snapshots' => "campaign_id IN ($in)", 'connector_links' => "campaign_id IN ($in)", 'connector_syncs' => "campaign_id IN ($in)"] as $t => $w) {
                    DB::q("DELETE FROM $t WHERE $w");
                }
            }
            DB::q('DELETE v FROM verifications v JOIN runs r ON r.id = v.run_id WHERE r.workspace_id = ?', [$ws]);
            foreach (['plans', 'runs', 'calibrations', 'campaigns', 'rates', 'campaign_history', 'perspective_log', 'audit_log', 'events', 'notifications', 'api_keys', 'connector_accounts', 'invites', 'llm_calls'] as $t) {
                DB::q("DELETE FROM $t WHERE workspace_id = ?", [$ws]);
            }
            if ($hard) {
                foreach (['merchant_profiles', 'rules', 'memberships', 'payments', 'data_requests', 'support_tickets'] as $t) {
                    DB::q("DELETE FROM $t WHERE workspace_id = ?", [$ws]);
                }
                DB::q('DELETE FROM workspaces WHERE id = ?', [$ws]);
            } else {
                DB::q("UPDATE workspaces SET deleted_at = NOW(), name = CONCAT('حذف‌شده #', id) WHERE id = ?", [$ws]);
                DB::q('DELETE FROM memberships WHERE workspace_id = ?', [$ws]);
            }
        });
    }

    /** Anonymous industry medians per channel (only when ≥10 merchants contribute — privacy floor). */
    private static function benchmarks(): string
    {
        $period = substr(Jalali::today(), 0, 7);
        $rows = DB::all("SELECT r.workspace_id, r.channel, AVG(r.unit_cost / NULLIF(r.cvr, 0)) cac FROM rates r JOIN workspaces w ON w.id = r.workspace_id
            WHERE w.deleted_at IS NULL AND w.is_demo = 0 AND r.source <> 'benchmark' AND r.cvr > 0 GROUP BY r.workspace_id, r.channel");
        $by = [];
        foreach ($rows as $r) {
            $by[(string) $r['channel']][] = (float) $r['cac'];
        }
        $n = 0;
        foreach ($by as $ch => $vals) {
            if (count($vals) < 10) {
                continue;
            }
            sort($vals);
            $mid = intdiv(count($vals), 2);
            $median = count($vals) % 2 ? $vals[$mid] : ($vals[$mid - 1] + $vals[$mid]) / 2;
            DB::q('REPLACE INTO benchmarks (channel, period, median_cac, n_merchants, created_at) VALUES (?,?,?,?,NOW())', [$ch, str_replace('/', '-', $period), (int) round($median), count($vals)]);
            $n++;
        }
        return 'channels ' . $n;
    }

    private static function cleanup(): string
    {
        $a = DB::q('DELETE FROM ai_cache WHERE expires_at < NOW()')->rowCount();
        $b = DB::q('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400])->rowCount();
        $c = DB::q('DELETE FROM sessions WHERE (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 30 DAY)) OR last_seen_at < DATE_SUB(NOW(), INTERVAL 60 DAY)')->rowCount();
        $d = DB::q('DELETE FROM email_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)')->rowCount();
        DB::q('DELETE FROM llm_calls WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)');
        return "cache $a · limits $b · sessions $c · tokens $d";
    }
}
