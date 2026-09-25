<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Jalali;
use App\Engine\Engine;
use App\Engine\Seed;

/** Workspace-scoped data access that feeds the engine. */
final class Ws
{
    public static function monthsSince(string $date): int
    {
        $a = new \DateTimeImmutable(substr($date, 0, 10));
        $b = new \DateTimeImmutable(date('Y-m-d'));
        if ($a > $b) {
            return 0;
        }
        $d = $a->diff($b);
        return $d->y * 12 + $d->m;
    }

    public static function monthsAgo(int $months): string
    {
        $t = new \DateTimeImmutable('first day of this month');
        $t = $t->modify('-' . $months . ' months');
        $day = min((int) date('j'), (int) $t->format('t'));
        return $t->format('Y-m-') . sprintf('%02d', $day);
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    public static function rateRow(array $r): array
    {
        $cpi = (float) $r['unit_cost'];
        return [
            'id' => (int) $r['id'], 'ch' => $r['channel'], 'seg' => $r['segment'], 'type' => $r['type'],
            'cpi' => $cpi == floor($cpi) ? (int) $cpi : $cpi, 'cvr' => (float) $r['cvr'], 'aov' => (int) $r['aov'],
            'variance' => (float) $r['variance'], 'n' => (int) $r['sample_n'], 'ceiling' => (int) $r['ceiling'],
            'lift' => (float) $r['seasonal_lift'], 'd7' => (float) $r['d7'], 'd30' => (float) $r['d30'], 'fraud' => (float) $r['fraud'],
            'age' => self::monthsSince((string) $r['observed_at']), 'observed_at' => $r['observed_at'], 'source' => $r['source'],
            'upd' => $r['source'] === 'benchmark' ? 'مرجع صنعت' : Jalali::fa((string) $r['observed_at']),
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function rates(int $ws): array
    {
        $order = "FIELD(channel,'گوگل','تپسل','یکتانت','اینستاگرام','پوش','پیامک'), FIELD(segment,'کاربر جدید','فعال','در معرض ریزش','پرارزش','بازگشتی'), id";
        return array_map([self::class, 'rateRow'], DB::all('SELECT * FROM rates WHERE workspace_id = ? ORDER BY ' . $order, [$ws]));
    }

    /** @return array<string,float|int> */
    public static function rules(int $ws): array
    {
        $r = DB::one('SELECT * FROM rules WHERE workspace_id = ?', [$ws]);
        if (!$r) {
            return Engine::DEFAULT_RULES;
        }
        return [
            'execTh' => (float) $r['exec_th'], 'estTh' => (float) $r['est_th'], 'scaleLo' => (float) $r['scale_lo'], 'scaleHi' => (float) $r['scale_hi'],
            'inflation' => (float) $r['inflation'], 'attrWindow' => (int) $r['attr_window'], 'fraudTh' => (float) $r['fraud_th'],
        ];
    }

    /** @return array<string,mixed> */
    public static function profile(int $ws): array
    {
        $p = DB::one('SELECT * FROM merchant_profiles WHERE workspace_id = ?', [$ws]);
        if (!$p) {
            return ['budget' => 0, 'margin' => 0.0, 'targetCac' => 0, 'ltv' => 0, 'goal' => '', 'season' => '', 'blocked' => ''];
        }
        return [
            'budget' => (int) $p['monthly_budget'], 'margin' => (float) $p['gross_margin'], 'targetCac' => (int) $p['target_cac'],
            'ltv' => (int) $p['ltv'], 'goal' => (string) $p['business_goal'], 'season' => (string) $p['season_note'], 'blocked' => (string) $p['blocked_channels'],
        ];
    }

    public static function engine(int $ws): Engine
    {
        return new Engine(self::rules($ws), self::profile($ws));
    }

    /** @return list<string> */
    public static function blocked(int $ws): array
    {
        $b = (string) (self::profile($ws)['blocked'] ?? '');
        return array_values(array_filter(array_map('trim', preg_split('/[،,]/u', $b) ?: [])));
    }

    /** @return list<array<string,mixed>> */
    public static function history(int $ws): array
    {
        return DB::all('SELECT * FROM campaign_history WHERE workspace_id = ? ORDER BY id', [$ws]);
    }

    public static function historyCount(int $ws): int
    {
        return (int) DB::val('SELECT COUNT(*) FROM campaign_history WHERE workspace_id = ?', [$ws]);
    }

    /** @return list<array{k:string,lift:float,cpi:float}> */
    public static function occasions(): array
    {
        $rows = DB::all('SELECT name, purchase_lift, cpi_delta FROM occasions ORDER BY sort, id');
        if (!$rows) {
            return Engine::defaultOccasions();
        }
        return array_map(static fn ($r) => ['k' => (string) $r['name'], 'lift' => (float) $r['purchase_lift'], 'cpi' => (float) $r['cpi_delta']], $rows);
    }

    /** @return array{k:string,lift:float,cpi:float} */
    public static function occasion(string $name): array
    {
        foreach (self::occasions() as $o) {
            if ($o['k'] === $name) {
                return $o;
            }
        }
        return ['k' => 'بدون مناسبت', 'lift' => 0.0, 'cpi' => 0.0];
    }

    /** Atomically reserve the next plan/sim/run/calibration sequence number. */
    public static function nextSeq(int $ws): int
    {
        return DB::tx(static function () use ($ws): int {
            $n = (int) DB::val('SELECT seq_next FROM workspaces WHERE id = ? FOR UPDATE', [$ws]);
            DB::q('UPDATE workspaces SET seq_next = seq_next + 1 WHERE id = ?', [$ws]);
            return $n;
        });
    }

    public static function code(string $prefix, int $seq, ?string $iso = null): string
    {
        return $prefix . '-' . Jalali::year($iso) . '-' . $seq;
    }

    public static function createWorkspace(string $name, int $ownerId, bool $demo): int
    {
        return DB::tx(static function () use ($name, $ownerId, $demo): int {
            $ws = DB::insert('workspaces', ['name' => $name, 'created_at' => DB::now(), 'is_demo' => $demo ? 1 : 0, 'tier' => 'trial']);
            DB::insert('memberships', ['workspace_id' => $ws, 'user_id' => $ownerId, 'role' => 'owner', 'created_at' => DB::now()]);
            DB::insert('rules', ['workspace_id' => $ws]);
            DB::insert('merchant_profiles', ['workspace_id' => $ws]);
            if ($demo) {
                self::seedDemo($ws);
            }
            DB::q('UPDATE users SET last_workspace_id = ? WHERE id = ?', [$ws, $ownerId]);
            return $ws;
        });
    }

    /** Replace rates/profile/history/perspective log with the demo dataset. */
    public static function seedDemo(int $ws, bool $keepLoop = false): void
    {
        DB::tx(static function () use ($ws, $keepLoop): void {
            DB::q('DELETE FROM rates WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM campaign_history WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM perspective_log WHERE workspace_id = ? AND seeded = 1', [$ws]);
            foreach (Seed::rates() as $r) {
                DB::insert('rates', [
                    'workspace_id' => $ws, 'channel' => $r['ch'], 'segment' => $r['seg'], 'type' => $r['type'], 'unit_cost' => $r['cpi'],
                    'cvr' => $r['cvr'], 'aov' => $r['aov'], 'variance' => $r['variance'], 'sample_n' => $r['n'], 'ceiling' => $r['ceiling'],
                    'seasonal_lift' => $r['lift'], 'd7' => $r['d7'], 'd30' => $r['d30'], 'fraud' => $r['fraud'],
                    'observed_at' => self::monthsAgo((int) $r['age']), 'source' => 'history', 'updated_at' => DB::now(),
                ]);
            }
            $p = Seed::profile();
            DB::q('REPLACE INTO merchant_profiles (workspace_id, monthly_budget, gross_margin, target_cac, ltv, business_goal, season_note, blocked_channels, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW())',
                [$ws, $p['budget'], $p['margin'], $p['targetCac'], $p['ltv'], $p['goal'], $p['season'], '']);
            foreach (Seed::history() as $h) {
                DB::insert('campaign_history', array_merge($h, ['workspace_id' => $ws, 'created_at' => DB::now()]));
            }
            foreach (Seed::perspectiveLog() as $l) {
                DB::insert('perspective_log', array_merge($l, ['workspace_id' => $ws, 'created_at' => DB::now()]));
            }
            DB::q('UPDATE workspaces SET benchmark_mode = 0, is_demo = 1 WHERE id = ?', [$ws]);
            if (!$keepLoop) {
                DB::q('UPDATE workspaces SET seq_next = GREATEST(seq_next, 101) WHERE id = ?', [$ws]);
            }
        });
    }

    /** Full demo reset: data + all loop state of the workspace. */
    public static function resetAll(int $ws): void
    {
        DB::tx(static function () use ($ws): void {
            $camps = array_map('intval', array_column(DB::all('SELECT id FROM campaigns WHERE workspace_id = ?', [$ws]), 'id'));
            if ($camps) {
                $in = implode(',', $camps);
                $plans = array_map('intval', array_column(DB::all("SELECT id FROM plans WHERE campaign_id IN ($in)"), 'id'));
                if ($plans) {
                    DB::q('DELETE FROM plan_versions WHERE plan_id IN (' . implode(',', $plans) . ')');
                }
                DB::q("DELETE FROM plans WHERE campaign_id IN ($in)");
                DB::q("DELETE FROM simulations WHERE campaign_id IN ($in)");
                DB::q("DELETE FROM pace_snapshots WHERE campaign_id IN ($in)");
                DB::q("DELETE FROM connector_links WHERE campaign_id IN ($in)");
            }
            $runs = array_map('intval', array_column(DB::all('SELECT id FROM runs WHERE workspace_id = ?', [$ws]), 'id'));
            if ($runs) {
                DB::q('DELETE FROM verifications WHERE run_id IN (' . implode(',', $runs) . ')');
            }
            DB::q('DELETE FROM runs WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM calibrations WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM campaigns WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM perspective_log WHERE workspace_id = ?', [$ws]);
            DB::q('UPDATE workspaces SET seq_next = 101, log_seq = 121, history_seq = 200 WHERE id = ?', [$ws]);
            DB::q('UPDATE rules SET exec_th=0.15, est_th=0.25, scale_lo=0.8, scale_hi=1.2, inflation=0.035, attr_window=7, fraud_th=0.08 WHERE workspace_id = ?', [$ws]);
        });
        self::seedDemo($ws);
    }

    /** Industry benchmark start: seed rates with sample_n = 3, age 0, source benchmark (prototype `benchmarks`). */
    public static function loadBenchmarks(int $ws): void
    {
        DB::tx(static function () use ($ws): void {
            DB::q('DELETE FROM rates WHERE workspace_id = ?', [$ws]);
            foreach (Seed::rates() as $r) {
                DB::insert('rates', [
                    'workspace_id' => $ws, 'channel' => $r['ch'], 'segment' => $r['seg'], 'type' => $r['type'], 'unit_cost' => $r['cpi'],
                    'cvr' => $r['cvr'], 'aov' => $r['aov'], 'variance' => $r['variance'], 'sample_n' => 3, 'ceiling' => $r['ceiling'],
                    'seasonal_lift' => $r['lift'], 'd7' => $r['d7'], 'd30' => $r['d30'], 'fraud' => $r['fraud'],
                    'observed_at' => date('Y-m-d'), 'source' => 'benchmark', 'updated_at' => DB::now(),
                ]);
            }
            if (self::historyCount($ws) < 15) {
                DB::q('DELETE FROM campaign_history WHERE workspace_id = ?', [$ws]);
                foreach (array_slice(Seed::history(), 0, 15) as $h) {
                    DB::insert('campaign_history', array_merge($h, ['workspace_id' => $ws, 'created_at' => DB::now()]));
                }
            }
            DB::q('UPDATE workspaces SET benchmark_mode = 1 WHERE id = ?', [$ws]);
        });
    }
}
