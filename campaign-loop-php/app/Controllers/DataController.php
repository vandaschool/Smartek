<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AI\AI;
use App\Core\Audit;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Engine\Engine;
use App\Services\Ws;

final class DataController extends Controller
{
    public function index(): void
    {
        $ws = $this->ws();
        $e = Ws::engine($ws);
        $this->page('data', 'pages/data', [
            'title' => 'داده‌ها', 'e' => $e, 'rates' => Ws::rates($ws), 'history' => Ws::history($ws),
            'imp' => Session::get('import'), 'wsRow' => DB::one('SELECT * FROM workspaces WHERE id = ?', [$ws]),
        ]);
    }

    public function saveRates(): void
    {
        $ws = $this->ws();
        $cpi = (array) Request::post('cpi', []);
        $cvr = (array) Request::post('cvr', []);
        $n = 0;
        foreach (Ws::rates($ws) as $r) {
            $id = (string) $r['id'];
            $newCpi = isset($cpi[$id]) ? Fmt::parseNum($cpi[$id]) : null;
            $newCvr = isset($cvr[$id]) ? Fmt::parseNum($cvr[$id]) : null;
            $upd = [];
            if ($newCpi !== null && $newCpi > 0 && abs($newCpi - (float) $r['cpi']) > 1e-9) {
                $upd['unit_cost'] = $newCpi;
            }
            if ($newCvr !== null && $newCvr > 0 && $newCvr <= 1 && abs($newCvr - (float) $r['cvr']) > 1e-9) {
                $upd['cvr'] = $newCvr;
            }
            if ($upd) {
                $upd['updated_at'] = DB::now();
                DB::update('rates', $upd, ['id' => $r['id'], 'workspace_id' => $ws]);
                Audit::log('ویرایش ردیف نرخ ' . $r['ch'] . '|' . $r['seg'], implode(' · ', array_map(static fn ($k, $v) => $k . '=' . $v, array_keys($upd), $upd)));
                $n++;
            }
        }
        $this->flash($n ? Fmt::fa((string) $n) . ' ردیف نرخ به‌روز شد.' : 'تغییری ثبت نشد.', $n ? 'ok' : 'info');
        Response::redirect('/data');
    }

    public function addRate(): void
    {
        $ws = $this->ws();
        $ch = Request::str('channel');
        $seg = Request::str('segment');
        $vals = [];
        foreach (['unit_cost', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd30', 'fraud', 'seasonal_lift'] as $k) {
            $vals[$k] = Request::num($k);
        }
        $err = '';
        if (!in_array($ch, Engine::CHANNELS, true) || !in_array($seg, Engine::SEGMENTS, true)) {
            $err = 'کانال یا سگمنت نامعتبر است.';
        } elseif (!$vals['unit_cost'] || $vals['unit_cost'] <= 0 || !$vals['aov'] || $vals['aov'] <= 0) {
            $err = 'هزینه‌ی واحد و AOV باید بیشتر از صفر باشند.';
        } elseif (!$vals['cvr'] || $vals['cvr'] <= 0 || $vals['cvr'] > 1) {
            $err = 'CVR باید بین ۰ و ۱ باشد.';
        }
        if ($err) {
            $this->flash($err, 'bad');
            Response::redirect('/data#add-rate');
        }
        $d30 = (float) ($vals['d30'] ?? 0);
        $type = Engine::channelType($ch);
        DB::q('INSERT INTO rates (workspace_id, channel, segment, type, unit_cost, cvr, aov, variance, sample_n, ceiling, seasonal_lift, d7, d30, fraud, observed_at, source, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,CURDATE(),?,NOW())
            ON DUPLICATE KEY UPDATE unit_cost=VALUES(unit_cost), cvr=VALUES(cvr), aov=VALUES(aov), variance=VALUES(variance), sample_n=VALUES(sample_n), ceiling=VALUES(ceiling), seasonal_lift=VALUES(seasonal_lift), d7=VALUES(d7), d30=VALUES(d30), fraud=VALUES(fraud), observed_at=VALUES(observed_at), source=VALUES(source), updated_at=NOW()',
            [$ws, $ch, $seg, $type, $vals['unit_cost'], $vals['cvr'], (int) $vals['aov'], max(0.01, (float) ($vals['variance'] ?? 0.2)), (int) ($vals['sample_n'] ?? 1), max(1, (int) ($vals['ceiling'] ?? 5000)), (float) ($vals['seasonal_lift'] ?? 0.05), min($d30 * 2, 0.9), $d30, $type === 'owned' ? 0 : (float) ($vals['fraud'] ?? 0), 'history']);
        Audit::log('افزودن/جایگزینی ردیف نرخ', $ch . '|' . $seg);
        $this->flash('ردیف ' . $ch . '|' . $seg . ' ثبت شد.');
        Response::redirect('/data');
    }

    public function deleteRate(string $id): void
    {
        $r = DB::one('SELECT * FROM rates WHERE id = ? AND workspace_id = ?', [(int) $id, $this->ws()]);
        if ($r) {
            DB::delete('rates', ['id' => $r['id']]);
            Audit::log('حذف ردیف نرخ', $r['channel'] . '|' . $r['segment']);
            $this->flash('ردیف حذف شد.');
        }
        Response::redirect('/data');
    }

    private function nextHistoryCode(int $ws): string
    {
        $n = (int) DB::val('SELECT history_seq FROM workspaces WHERE id = ?', [$ws]);
        $n = max($n, 200 + Ws::historyCount($ws));
        DB::q('UPDATE workspaces SET history_seq = ? WHERE id = ?', [$n + 1, $ws]);
        return 'ک-' . Fmt::fa((string) $n);
    }

    public function addHistory(): void
    {
        $ws = $this->ws();
        $o = [
            'name' => Request::str('name', '', 160), 'channel' => Request::str('channel'), 'segment' => Request::str('segment'),
            'spend' => Request::num('spend'), 'installs' => Request::num('installs'), 'conversions' => Request::num('conversions'),
            'revenue' => Request::num('revenue'), 'month' => Request::str('month', '', 20),
        ];
        $err = '';
        if (mb_strlen($o['name']) < 2) {
            $err = 'نام کمپین را وارد کنید.';
        } elseif (!in_array($o['channel'], Engine::CHANNELS, true) || !in_array($o['segment'], Engine::SEGMENTS, true)) {
            $err = 'کانال یا سگمنت نامعتبر است.';
        } elseif ($o['spend'] === null || $o['installs'] === null || $o['conversions'] === null || $o['revenue'] === null) {
            $err = 'هزینه، نصب، خرید و درآمد باید عدد باشند.';
        } elseif ($o['conversions'] > $o['installs'] && !in_array($o['channel'], Engine::OWNED, true)) {
            $err = 'خرید از نصب بیشتر است';
        }
        if ($err) {
            $this->flash($err, 'bad');
            Response::redirect('/data#add-history');
        }
        DB::insert('campaign_history', ['workspace_id' => $ws, 'code' => $this->nextHistoryCode($ws), 'name' => $o['name'], 'channel' => $o['channel'], 'segment' => $o['segment'], 'spend' => (int) $o['spend'], 'installs' => (int) $o['installs'], 'conversions' => (int) $o['conversions'], 'revenue' => (int) $o['revenue'], 'month' => $o['month'], 'source' => 'ورود دستی', 'created_at' => DB::now()]);
        Audit::log('افزودن کمپین تاریخی', $o['name']);
        $this->flash('کمپین تاریخی ثبت شد.');
        Response::redirect('/data#history');
    }

    public function deleteHistory(string $id): void
    {
        $h = DB::one('SELECT * FROM campaign_history WHERE id = ? AND workspace_id = ?', [(int) $id, $this->ws()]);
        if ($h) {
            DB::delete('campaign_history', ['id' => $h['id']]);
            Audit::log('حذف کمپین تاریخی', (string) $h['name']);
        }
        Response::redirect('/data#history');
    }

    public function template(string $kind): void
    {
        $spec = Engine::importSpec()[$kind] ?? null;
        if (!$spec) {
            Response::abort(404);
        }
        Response::csv($kind === 'history' ? 'template-campaign-history.csv' : 'template-rates.csv', array_merge([$spec['cols']], $spec['sample']));
    }

    public function export(string $kind): void
    {
        $ws = $this->ws();
        if ($kind === 'rates') {
            $e = Ws::engine($ws);
            $rows = [['channel', 'segment', 'type', 'cpi', 'cpi_adj', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd7', 'd30', 'fraud', 'age_months']];
            foreach (Ws::rates($ws) as $r) {
                $rows[] = [$r['ch'], $r['seg'], $r['type'], $r['cpi'], $e->cpiAdj($r), $r['cvr'], $r['aov'], $r['variance'], $r['n'], $r['ceiling'], $r['d7'], $r['d30'], $r['fraud'], $r['age']];
            }
            Response::csv('rates.csv', $rows);
        }
        if ($kind === 'history') {
            $rows = [['code', 'name', 'channel', 'segment', 'spend', 'installs', 'conversions', 'revenue', 'month', 'source']];
            foreach (Ws::history($ws) as $h) {
                $rows[] = [$h['code'], $h['name'], $h['channel'], $h['segment'], $h['spend'], $h['installs'], $h['conversions'], $h['revenue'], $h['month'], $h['source']];
            }
            Response::csv('campaign-history.csv', $rows);
        }
        Response::abort(404);
    }

    private static function readUploadText(string $path): string
    {
        $t = (string) file_get_contents($path);
        if (!mb_check_encoding($t, 'UTF-8')) {
            $conv = @iconv('Windows-1256', 'UTF-8//IGNORE', $t);
            if ($conv !== false) {
                $t = $conv;
            }
        }
        return str_replace(['ي', 'ك'], ['ی', 'ک'], $t);
    }

    public function upload(string $kind): void
    {
        if (!in_array($kind, ['history', 'rates'], true)) {
            Response::abort(404);
        }
        $f = $_FILES['file'] ?? null;
        if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 2 * 1024 * 1024) {
            $this->flash('فایل CSV (حداکثر ۲ مگابایت) انتخاب نشد یا بارگذاری ناموفق بود.', 'bad');
            Response::redirect('/data');
        }
        $name = preg_replace('/[^\pL\pN._ -]/u', '', (string) $f['name']) ?: 'upload.csv';
        $dest = APP_ROOT . '/storage/uploads/' . $this->ws() . '-' . bin2hex(random_bytes(8)) . '.csv';
        if (!move_uploaded_file((string) $f['tmp_name'], $dest)) {
            $this->flash('ذخیره‌ی فایل ممکن نشد. دسترسی پوشه‌ی storage/uploads را بررسی کنید.', 'bad');
            Response::redirect('/data');
        }
        $text = self::readUploadText($dest);
        $rows = Engine::parseCsv($text);
        $headers = array_map('trim', $rows[0] ?? []);
        $spec = Engine::importSpec()[$kind];
        $lower = array_map('mb_strtolower', $headers);
        $exact = !array_diff($spec['cols'], $lower);
        $mapping = null;
        $ai = null;
        if (!$exact && $headers) {
            $ai = AI::csvMap($kind, $headers, array_slice($rows, 1, 3));
            $mapping = $ai['mapping'] ?? null;
        }
        $this->storePreview($kind, $name, $dest, $headers, $mapping, 1.0, $ai);
        Response::redirect('/data#import');
    }

    /** @param list<string> $headers @param array<string,string|null>|null $mapping @param array<string,mixed>|null $ai */
    private function storePreview(string $kind, string $file, string $path, array $headers, ?array $mapping, float $unit, ?array $ai): void
    {
        $r = Engine::readImport($kind, self::readUploadText($path), $mapping, $unit);
        Session::set('import', [
            'kind' => $kind, 'file' => $file, 'path' => $path, 'headers' => $headers, 'mapping' => $mapping, 'unit' => $unit,
            'okN' => count($r['ok']), 'errors' => array_slice($r['errors'], 0, 50), 'errN' => count($r['errors']),
            'preview' => array_slice($r['ok'], 0, 5), 'ai' => $ai ? ['warnings' => $ai['warnings'] ?? [], 'confidence' => $ai['confidence'] ?? null, 'fallback' => $ai['fallback'] ?? true] : null,
        ]);
    }

    public function remap(): void
    {
        $imp = Session::get('import');
        if (!$imp) {
            Response::redirect('/data');
        }
        $spec = Engine::importSpec()[$imp['kind']];
        $mapping = [];
        foreach ($spec['cols'] as $c) {
            $v = Request::str('map_' . $c);
            $mapping[$c] = in_array($v, $imp['headers'], true) ? $v : null;
        }
        $unit = (float) (Request::num('unit') ?? 1);
        if (!in_array($unit, [1.0, 1000.0, 1000000.0, 0.1], true)) {
            $unit = 1.0;
        }
        $this->storePreview($imp['kind'], $imp['file'], $imp['path'], $imp['headers'], $mapping, $unit, $imp['ai']);
        Response::redirect('/data#import');
    }

    public function commit(): void
    {
        $ws = $this->ws();
        $imp = Session::get('import');
        if (!$imp || !is_file($imp['path'])) {
            Response::redirect('/data');
        }
        $r = Engine::readImport($imp['kind'], self::readUploadText($imp['path']), $imp['mapping'], (float) $imp['unit']);
        if (!$r['ok']) {
            $this->flash('ردیف معتبری برای ثبت نیست.', 'bad');
            Response::redirect('/data#import');
        }
        DB::tx(function () use ($ws, $imp, $r): void {
            if ($imp['kind'] === 'history') {
                foreach ($r['ok'] as $o) {
                    DB::insert('campaign_history', ['workspace_id' => $ws, 'code' => $this->nextHistoryCode($ws), 'name' => $o['name'], 'channel' => $o['channel'], 'segment' => $o['segment'], 'spend' => (int) round($o['spend']), 'installs' => (int) round($o['installs']), 'conversions' => (int) round($o['conversions']), 'revenue' => (int) round($o['revenue']), 'month' => $o['month'], 'source' => 'آپلود CSV', 'created_at' => DB::now()]);
                }
            } else {
                foreach ($r['ok'] as $o) {
                    $type = Engine::channelType($o['channel']);
                    DB::q('INSERT INTO rates (workspace_id, channel, segment, type, unit_cost, cvr, aov, variance, sample_n, ceiling, seasonal_lift, d7, d30, fraud, observed_at, source, updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,0.05,?,?,?,CURDATE(),\'csv\',NOW())
                        ON DUPLICATE KEY UPDATE type=VALUES(type), unit_cost=VALUES(unit_cost), cvr=VALUES(cvr), aov=VALUES(aov), variance=VALUES(variance), sample_n=VALUES(sample_n), ceiling=VALUES(ceiling), d7=VALUES(d7), d30=VALUES(d30), fraud=VALUES(fraud), observed_at=VALUES(observed_at), source=VALUES(source), updated_at=NOW()',
                        [$ws, $o['channel'], $o['segment'], $type, $o['unit_cost'], $o['cvr'], (int) round($o['aov']), $o['variance'], (int) $o['sample_n'], max(1, (int) $o['ceiling']), min($o['d30'] * 2, 0.9), $o['d30'], $type === 'owned' ? 0 : $o['fraud']]);
                }
                DB::q('UPDATE workspaces SET benchmark_mode = 0 WHERE id = ?', [$ws]);
            }
        });
        @unlink($imp['path']);
        Session::forget('import');
        Audit::log('آپلود CSV ' . ($imp['kind'] === 'history' ? 'تاریخچه' : 'نرخ‌ها'), Fmt::fa((string) count($r['ok'])) . ' ردیف');
        Audit::event('import_committed', ['kind' => $imp['kind'], 'ok_rows' => count($r['ok']), 'error_rows' => count($r['errors'])]);
        $this->flash(Fmt::fa((string) count($r['ok'])) . ' ردیف ثبت شد.');
        Response::redirect('/data');
    }

    public function cancel(): void
    {
        $imp = Session::get('import');
        if ($imp && is_file($imp['path'])) {
            @unlink($imp['path']);
        }
        Session::forget('import');
        Response::redirect('/data');
    }

    public function reseed(): void
    {
        $ws = $this->ws();
        if (Request::str('mode') === 'clear') {
            DB::q('DELETE FROM rates WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM campaign_history WHERE workspace_id = ?', [$ws]);
            DB::q('DELETE FROM perspective_log WHERE workspace_id = ? AND seeded = 1', [$ws]);
            DB::q('UPDATE workspaces SET is_demo = 0, benchmark_mode = 0 WHERE id = ?', [$ws]);
            Audit::log('پاک‌کردن داده‌ی دمو', '');
            $this->flash('داده‌ی دمو پاک شد. قالب CSV را دانلود و داده‌ی خودتان را بارگذاری کنید.');
        } else {
            Ws::seedDemo($ws);
            Audit::log('بازنشانی داده‌ی دمو', '');
            $this->flash('داده‌ی دمو بازنشانی شد.');
        }
        Response::redirect('/data');
    }

    public function setup(): void
    {
        $ws = $this->ws();
        $e = Ws::engine($ws);
        $this->page('setup', 'pages/setup', [
            'title' => 'پروفایل و آمادگی', 'p' => Ws::profile($ws), 'rd' => $e->readiness(Ws::historyCount($ws), Ws::rates($ws)),
            'benchmark' => (bool) DB::val('SELECT benchmark_mode FROM workspaces WHERE id = ?', [$ws]),
        ]);
    }

    public function saveProfile(): void
    {
        $ws = $this->ws();
        $margin = Request::num('margin') ?? 0;
        if ($margin > 1) {
            $margin /= 100;
        }
        $goal = Request::str('goal');
        $blocked = array_values(array_intersect(Engine::CHANNELS, Request::arr('blocked')));
        DB::q('REPLACE INTO merchant_profiles (workspace_id, monthly_budget, gross_margin, target_cac, ltv, business_goal, season_note, blocked_channels, updated_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [
            $ws, (int) (Request::num('budget') ?? 0), max(0, min(1, $margin)), (int) (Request::num('targetCac') ?? 0), (int) (Request::num('ltv') ?? 0),
            in_array($goal, Engine::GOALS, true) ? $goal : '', Request::str('season', '', 255), implode('،', $blocked),
        ]);
        Audit::log('ذخیره‌ی پروفایل مرچنت', 'حاشیه ' . Fmt::pct($margin, 0) . ' · هدف ' . $goal);
        $this->flash('پروفایل ذخیره شد.');
        Response::redirect('/setup');
    }

    public function benchmarks(): void
    {
        Ws::loadBenchmarks($this->ws());
        Audit::log('بارگذاری نرخ‌های مرجع صنعت', Fmt::fa('14') . ' ردیف');
        $this->flash('نرخ‌های مرجع صنعت بارگذاری شد؛ اینسایت‌ها برچسب «بر پایه‌ی مرجع» می‌گیرند تا اولین کالیبراسیون.');
        Response::redirect('/setup');
    }
}
