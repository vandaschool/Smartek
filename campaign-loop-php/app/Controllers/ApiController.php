<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Jalali;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Engine\Engine;
use App\Services\Connectors;
use App\Services\Loop;
use App\Services\Report;

/**
 * Server-to-server API (Bearer sk_loop_… keys from «اتصال داده»). Latin digits, ISO dates + jalali strings.
 *   POST /api/v1/results             → verify a campaign result (draft by default; "confirm": true to confirm)
 *   GET  /api/v1/campaigns/{code}    → the ID chain plan → sim → run → cal and the report numbers
 *   POST /api/v1/webhooks/{kind}     → Adtrace/Intrack daily stats push (HMAC-SHA256 of raw body with the connector key)
 */
final class ApiController
{
    private function fail(int $status, string $code, string $message): never
    {
        Response::json(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], $status);
    }

    /** @return array<string,mixed> api_keys row */
    private function key(): array
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('~^Bearer\s+(sk_loop_[a-f0-9]{16,64})$~i', trim($h), $m)) {
            $this->fail(401, 'unauthorized', 'Missing or malformed Authorization: Bearer sk_loop_… header.');
        }
        $k = DB::one('SELECT * FROM api_keys WHERE key_hash = ? AND revoked_at IS NULL', [Crypto::hash($m[1])]);
        if (!$k) {
            $this->fail(401, 'unauthorized', 'Invalid or revoked API key.');
        }
        if (!RateLimit::hit('api:' . $k['id'], 120, 60)) {
            $this->fail(429, 'rate_limited', 'Too many requests; limit is 120/min per key.');
        }
        DB::q('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?', [$k['id']]);
        return $k;
    }

    /** Resolve plan/sim/run/cal code (they share one sequence) or numeric campaign id. @return array<string,mixed>|null */
    private function campaignByCode(int $ws, string $code): ?array
    {
        $code = Fmt::en(trim($code));
        if (ctype_digit($code)) {
            return Loop::campaign($ws, (int) $code);
        }
        if (!preg_match('~^(?:plan|sim|run|cal)-\d{4}-(\d+)~', $code, $m)) {
            return null;
        }
        $cid = DB::val('SELECT campaign_id FROM plans WHERE workspace_id = ? AND seq = ?', [$ws, (int) $m[1]]);
        return $cid ? Loop::campaign($ws, (int) $cid) : null;
    }

    public function results(): void
    {
        $k = $this->key();
        $ws = (int) $k['workspace_id'];
        $in = Request::json();
        if (!$in) {
            $this->fail(400, 'bad_json', 'Body must be a JSON object.');
        }
        $c = $this->campaignByCode($ws, (string) ($in['plan_code'] ?? $in['campaign'] ?? ''));
        if (!$c) {
            $this->fail(404, 'not_found', 'Campaign not found for plan_code.');
        }
        $plan = Loop::plan((int) $c['id']);
        if (!$plan) {
            $this->fail(409, 'no_plan', 'Campaign has no registered plan yet.');
        }
        if ($c['status'] === 'closed') {
            $this->fail(409, 'closed', 'Campaign loop is already closed.');
        }
        $sim = Loop::ensureSim($ws, $c, $plan);
        $given = [];
        foreach ((array) ($in['rows'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $given[(string) ($r['channel'] ?? '') . '|' . (string) ($r['segment'] ?? '')] = $r;
        }
        if (!$given) {
            $this->fail(422, 'invalid', 'rows[] is required: [{channel, segment, spend, installs, conversions}].');
        }
        $rows = [];
        foreach ($sim['r']['rows'] as $r) {
            $g = $given[$r['ch'] . '|' . $r['seg']] ?? null;
            if ($g === null) {
                $this->fail(422, 'missing_row', 'Missing row for allocation ' . $r['ch'] . ' / ' . $r['seg'] . '. Rows must match the plan allocation.');
            }
            $a = [Fmt::parseNum($g['spend'] ?? null), Fmt::parseNum($g['installs'] ?? null), Fmt::parseNum($g['conversions'] ?? null)];
            if (in_array(null, $a, true) || min($a) < 0) {
                $this->fail(422, 'invalid', 'spend, installs, conversions must be numbers ≥ 0 for ' . $r['ch'] . ' / ' . $r['seg'] . '.');
            }
            if ($a[2] > $a[1] && !in_array($r['ch'], Engine::OWNED, true)) {
                $this->fail(422, 'invalid', 'conversions > installs for paid row ' . $r['ch'] . ' / ' . $r['seg'] . '.');
            }
            $rows[] = ['ch' => $r['ch'], 'seg' => $r['seg'], 'pb' => round($r['budget']), 'pi' => round($r['installs']), 'pc' => round($r['conv']), 'ab' => $a[0], 'ai' => $a[1], 'ac' => $a[2]];
        }
        if (array_sum(array_column($rows, 'ab')) <= 0) {
            $this->fail(422, 'invalid', 'Total spend must be > 0.');
        }
        $yes = static fn ($v, bool $d) => $v === null ? ($d ? 'بله' : 'خیر') : (filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'بله' : 'خیر');
        $vi = [
            'name' => (string) $c['name'], 'ch' => $rows[0]['ch'], 'seg' => $rows[0]['seg'],
            'from' => Jalali::fromIso((string) $c['date_from']), 'to' => Jalali::fromIso((string) $c['date_to']), 'src' => 'مکتوب و عددی', 'rows' => $rows,
            'complete' => $yes($in['data_complete'] ?? null, true), 'matched' => $yes($in['definitions_match'] ?? null, true), 'season' => $yes($in['seasonal'] ?? null, false),
            'fraud' => max(0, min(100, (float) ($in['fraud_pct'] ?? 0))), 'window' => max(1, (int) ($in['window_days'] ?? 7)),
            'reach' => max(0, (float) ($in['reach'] ?? 0)), 'holdout' => max(0, min(100, (float) ($in['holdout_pct'] ?? 0))),
        ];
        $confirm = !empty($in['confirm']);
        DB::q("DELETE FROM runs WHERE campaign_id = ? AND status = 'draft'", [$c['id']]);
        $rid = Loop::verify($ws, $c, $vi, 'api', $confirm ? 'confirmed' : 'draft');
        $run = Loop::run($rid);
        Audit::log($confirm ? 'ثبت نتیجه از API' : 'پیش‌نویس نتیجه از API', (string) $run['code'] . ' · کلید …' . $k['last4'], $ws);
        if ($confirm) {
            Audit::event('run_verified', ['cause' => $run['ver']['cause'] ?? '', 'source' => 'api'], $ws);
        } else {
            Audit::notify('نتیجه‌ی «' . $c['name'] . '» از API دریافت شد؛ بررسی و تأیید کنید.', 'info', '/c/' . $c['id'] . '/verify', $ws, ['owner', 'analyst', 'campaign_ops'], 'run_draft');
        }
        $vr = $run['ver']['r'];
        Response::json(['ok' => true, 'data' => [
            'run_code' => $run['code'], 'status' => $run['status'], 'cause' => $vr['cause'], 'cause_id' => Engine::CAUSE_IDS[$vr['cause']] ?? null,
            'calibratable' => (bool) $vr['calib'], 'explanation' => $vr['expl'] ?? '', 'within_band' => $vr['withinBand'] ?? null,
            'totals' => $vr['totals'] ?? null, 'source_ref' => 'verifications#' . $run['ver']['id'],
            'url' => \App\Core\Url::to('/c/' . $c['id'] . '/verify', [], true),
        ]], 201);
    }

    public function chain(string $code): void
    {
        $k = $this->key();
        $ws = (int) $k['workspace_id'];
        $c = $this->campaignByCode($ws, $code);
        if (!$c) {
            $this->fail(404, 'not_found', 'No campaign for this code.');
        }
        $plan = Loop::plan((int) $c['id']);
        $run = !empty($c['current_run_id']) ? Loop::run((int) $c['current_run_id']) : null;
        Response::json(['ok' => true, 'data' => [
            'campaign' => ['id' => (int) $c['id'], 'name' => $c['name'], 'status' => Loop::status($c), 'goal_type' => $c['goal_type'], 'goal_value' => (int) $c['goal_value'], 'budget' => (int) $c['budget'],
                'date_from' => $c['date_from'], 'date_to' => $c['date_to'], 'jalali' => ['from' => Jalali::fromIso((string) $c['date_from']), 'to' => Jalali::fromIso((string) $c['date_to'])]],
            'plan' => $plan ? ['code' => $plan['code'], 'version' => (int) $plan['version'], 'perspective' => $plan['perspective'], 'created_at' => $plan['created_at']] : null,
            'run' => $run ? ['code' => $run['code'], 'status' => $run['status'], 'source' => $run['source'], 'cause' => $run['ver']['cause'] ?? null, 'calibrations' => array_column($run['cals'] ?? [], 'code')] : null,
            'report' => Report::build($ws, $c),
        ]]);
    }

    public function webhook(string $kind): void
    {
        if (!isset(Connectors::KINDS[$kind])) {
            $this->fail(404, 'not_found', 'Unknown connector.');
        }
        $raw = (string) file_get_contents('php://input');
        $in = json_decode($raw, true);
        if (!is_array($in) || empty($in['external_id']) || empty($in['day']) || !preg_match('~^\d{4}-\d{2}-\d{2}$~', (string) $in['day'])) {
            $this->fail(400, 'bad_payload', 'Expected JSON {external_id, day: YYYY-MM-DD, spend, installs, conversions, revenue, fraud_installs, reach}.');
        }
        $sig = (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? '');
        $sig = str_starts_with($sig, 'sha256=') ? substr($sig, 7) : $sig;
        $links = DB::all("SELECT l.*, a.id AS acc_id, a.workspace_id, a.creds_enc FROM connector_links l JOIN connector_accounts a ON a.id = l.connector_account_id WHERE a.kind = ? AND a.status = 'connected' AND l.external_id = ?", [$kind, (string) $in['external_id']]);
        $hit = null;
        foreach ($links as $l) {
            $secret = Crypto::decrypt((string) $l['creds_enc']);
            if ($secret !== '' && $sig !== '' && hash_equals(hash_hmac('sha256', $raw, $secret), strtolower($sig))) {
                $hit = $l;
                break;
            }
        }
        if (!$hit) {
            $this->fail(401, 'bad_signature', 'Signature (X-Signature: sha256=HMAC(body, api_key)) did not match any linked campaign.');
        }
        $payload = [];
        foreach (['spend', 'installs', 'conversions', 'revenue', 'fraud_installs', 'reach'] as $f) {
            $payload[$f] = max(0, (float) ($in[$f] ?? 0));
        }
        DB::q("INSERT INTO connector_syncs (connector_account_id, campaign_id, day, payload, status, created_at) VALUES (?,?,?,?, 'ok', NOW()) ON DUPLICATE KEY UPDATE payload = VALUES(payload), status = 'ok'",
            [$hit['acc_id'], $hit['campaign_id'], $in['day'], json_encode($payload)]);
        DB::q('UPDATE connector_accounts SET last_sync_at = NOW(), last_error = "", fail_count = 0 WHERE id = ?', [$hit['acc_id']]);
        $c = Loop::campaign((int) $hit['workspace_id'], (int) $hit['campaign_id']);
        if ($c && $c['status'] === 'live') {
            Connectors::rollup((int) $hit['workspace_id'], $c);
        }
        Response::json(['ok' => true]);
    }
}
