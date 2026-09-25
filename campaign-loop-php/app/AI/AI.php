<?php
declare(strict_types=1);

namespace App\AI;

use App\Core\Auth;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Http;
use App\Core\Log;
use App\Core\Settings;
use App\Engine\Engine;

/**
 * Layer 2 via Metis (OpenAI-compatible ChatGPT API).
 * Every task: cache → circuit breaker → model → JSON validation → number firewall → fallback.
 * Prompts: /prompts/<task>.v1.md (System block) + _shared.v1.md.
 */
final class AI
{
    public const VERSION = 'v1';

    public static function configured(): bool
    {
        return Settings::get('ai_provider', 'mock') === 'metis' && Settings::get('metis_api_key') !== '';
    }

    public static function enabled(int $ws): bool
    {
        if (!self::configured()) {
            return false;
        }
        return (bool) DB::val('SELECT ai_enabled FROM workspaces WHERE id = ?', [$ws]);
    }

    // ------------------------------------------------------------------ prompts

    private static function block(string $file, string $heading): string
    {
        $p = APP_ROOT . '/prompts/' . $file;
        if (!is_file($p)) {
            return '';
        }
        $s = (string) file_get_contents($p);
        $i = strpos($s, '## ' . $heading);
        if ($i === false) {
            return '';
        }
        if (!preg_match('/```(?:text|json)?\n(.*?)```/s', substr($s, $i), $m)) {
            return '';
        }
        return trim($m[1]);
    }

    public static function system(string $task): string
    {
        return self::block('_shared.v1.md', 'System (shared)') . "\n\n" . self::block($task . '.v1.md', 'System');
    }

    /** @return array<string,mixed>|null */
    public static function schema(string $task): ?array
    {
        $j = json_decode(self::block($task . '.v1.md', 'Output schema'), true);
        return is_array($j) ? $j : null;
    }

    // ------------------------------------------------------------------ breaker & budget

    private static function breakerFile(): string
    {
        return APP_ROOT . '/storage/cache/ai-breaker.json';
    }

    private static function breakerOpen(): bool
    {
        $b = json_decode((string) @file_get_contents(self::breakerFile()), true) ?: [];
        return ($b['open_until'] ?? 0) > time();
    }

    private static function breakerFail(): void
    {
        $b = json_decode((string) @file_get_contents(self::breakerFile()), true) ?: [];
        $now = time();
        $fails = array_values(array_filter($b['fails'] ?? [], static fn ($t) => $t > $now - 60));
        $fails[] = $now;
        $b['fails'] = $fails;
        if (count($fails) >= 5) {
            $b['open_until'] = $now + 120;
            $b['fails'] = [];
        }
        @file_put_contents(self::breakerFile(), json_encode($b));
    }

    private static function overBudget(?int $ws): bool
    {
        if (!$ws) {
            return false;
        }
        $tier = (string) DB::val('SELECT tier FROM workspaces WHERE id = ?', [$ws]);
        $limit = (int) Settings::get('ai_budget_' . ($tier ?: 'trial'), '200000');
        if ($limit <= 0) {
            return false;
        }
        $used = (int) DB::val("SELECT COALESCE(SUM(tokens_in + tokens_out),0) FROM llm_calls WHERE workspace_id = ? AND created_at >= ?", [$ws, date('Y-m-01 00:00:00')]);
        return $used >= $limit;
    }

    private static function logCall(?int $ws, string $task, string $model, int $ms, int $in, int $out, string $status, string $err = '', ?string $debug = null): void
    {
        try {
            DB::insert('llm_calls', [
                'workspace_id' => $ws, 'user_id' => Auth::id() ?: null, 'task' => $task, 'prompt_version' => $task . '.' . self::VERSION,
                'provider' => self::configured() ? 'metis' : 'mock', 'model' => $model, 'latency_ms' => $ms, 'tokens_in' => $in, 'tokens_out' => $out,
                'status' => $status, 'error_code' => mb_substr($err, 0, 190), 'debug' => Settings::bool('ai_debug') ? $debug : null, 'created_at' => DB::now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('llm_call log failed', ['msg' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------ core call

    /**
     * @param array<string,mixed> $ctx
     * @param callable(array<string,mixed>):bool|null $validate extra task-specific validation
     * @return array{data:?array<string,mixed>,status:string}
     */
    public static function run(string $task, array $ctx, string $tier, ?int $ws, ?callable $validate = null, bool $firewall = true): array
    {
        if (!self::configured() || ($ws && !self::enabled($ws))) {
            return ['data' => null, 'status' => 'fallback'];
        }
        $model = $tier === 'smart' ? Settings::get('metis_model_smart', 'gpt-4o') : Settings::get('metis_model_fast', 'gpt-4o-mini');
        $userMsg = json_encode($ctx, JSON_UNESCAPED_UNICODE);
        $key = hash('sha256', $task . self::VERSION . $model . $userMsg);
        $cached = DB::one('SELECT v FROM ai_cache WHERE k = ? AND expires_at > NOW()', [$key]);
        if ($cached) {
            self::logCall($ws, $task, $model, 0, 0, 0, 'cached');
            return ['data' => json_decode((string) $cached['v'], true), 'status' => 'cached'];
        }
        if (self::breakerOpen()) {
            self::logCall($ws, $task, $model, 0, 0, 0, 'fallback', 'circuit_open');
            return ['data' => null, 'status' => 'fallback'];
        }
        if (self::overBudget($ws)) {
            self::logCall($ws, $task, $model, 0, 0, 0, 'fallback', 'budget');
            return ['data' => null, 'status' => 'fallback'];
        }
        $messages = [['role' => 'system', 'content' => self::system($task)], ['role' => 'user', 'content' => $userMsg]];
        $schema = self::schema($task);
        $timeout = (int) Settings::get($tier === 'smart' ? 'ai_timeout_smart_ms' : 'ai_timeout_fast_ms', $tier === 'smart' ? '20000' : '8000');
        $attempts = 0;
        $lastErr = '';
        while ($attempts < 2) {
            $attempts++;
            $body = ['model' => $model, 'messages' => $messages, 'temperature' => $tier === 'smart' ? 0.2 : 0.1, 'max_tokens' => 600];
            if ($schema && Settings::bool('ai_json_schema', true)) {
                $body['response_format'] = ['type' => 'json_schema', 'json_schema' => ['name' => $task, 'schema' => $schema]];
            } else {
                $body['response_format'] = ['type' => 'json_object'];
            }
            $r = Http::postJson(rtrim(Settings::get('metis_base_url', 'https://api.metisai.ir/openai/v1'), '/') . '/chat/completions', $body, ['Authorization' => 'Bearer ' . Settings::get('metis_api_key')], $timeout);
            $usage = $r['data']['usage'] ?? [];
            $tin = (int) ($usage['prompt_tokens'] ?? 0);
            $tout = (int) ($usage['completion_tokens'] ?? 0);
            if ($r['status'] < 200 || $r['status'] >= 300 || !is_array($r['data'])) {
                $lastErr = $r['error'] ?: ('http_' . $r['status'] . ' ' . mb_substr((string) ($r['data']['error']['message'] ?? $r['body']), 0, 120));
                // schema not supported → retry in json mode
                if ($r['status'] === 400 && isset($body['response_format']['json_schema'])) {
                    $schema = null;
                    continue;
                }
                self::breakerFail();
                self::logCall($ws, $task, $model, $r['ms'], $tin, $tout, $r['status'] === 0 ? 'timeout' : 'error', $lastErr);
                if ($attempts < 2) {
                    usleep(random_int(200, 600) * 1000);
                    continue;
                }
                return ['data' => null, 'status' => 'fallback'];
            }
            $content = (string) ($r['data']['choices'][0]['message']['content'] ?? '');
            $content = trim(preg_replace('/^```(?:json)?|```$/m', '', $content) ?? $content);
            $data = json_decode($content, true);
            $okShape = is_array($data) && ($validate === null || $validate($data));
            if (!$okShape) {
                $lastErr = 'schema';
                self::logCall($ws, $task, $model, $r['ms'], $tin, $tout, 'error', 'schema', $content);
                $messages[] = ['role' => 'assistant', 'content' => $content];
                $messages[] = ['role' => 'user', 'content' => 'Your previous answer did not match the required JSON schema. Return only the JSON object that matches the schema.'];
                continue;
            }
            if ($firewall) {
                $fw = Firewall::check($data, $ctx);
                if (!$fw['ok']) {
                    self::logCall($ws, $task, $model, $r['ms'], $tin, $tout, 'violation', $fw['reason'], $content);
                    return ['data' => null, 'status' => 'violation'];
                }
            }
            DB::q('REPLACE INTO ai_cache (k, v, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY))', [$key, json_encode($data, JSON_UNESCAPED_UNICODE)]);
            self::logCall($ws, $task, $model, $r['ms'], $tin, $tout, 'ok', '', $content);
            return ['data' => $data, 'status' => 'ok'];
        }
        return ['data' => null, 'status' => 'fallback'];
    }

    private static function str(mixed $v, int $max = 600): bool
    {
        return is_string($v) && $v !== '' && mb_strlen($v) <= $max;
    }

    // ------------------------------------------------------------------ tasks

    /** @return array{intent:string,channel:?string,segment:?string,metric:?string,ai:bool} */
    public static function askIntent(int $ws, string $q, Engine $e): array
    {
        $fallback = $e->matchIntent($q) + ['metric' => null, 'ai' => false];
        $r = self::run('ask_intent', ['question' => mb_substr($q, 0, 300)], 'fast', $ws, static function (array $d): bool {
            return in_array($d['intent'] ?? '', Engine::INTENTS, true);
        }, false);
        $d = $r['data'];
        if (!$d) {
            return $fallback;
        }
        $ch = in_array($d['channel'] ?? null, Engine::CHANNELS, true) ? $d['channel'] : null;
        $seg = in_array($d['segment'] ?? null, Engine::SEGMENTS, true) ? $d['segment'] : null;
        $intent = (string) $d['intent'];
        if ((float) ($d['confidence'] ?? 1) < 0.5) {
            $intent = 'unsupported';
        }
        if ($intent === 'channel_segment' && (!$ch || !$seg)) {
            $intent = $ch ? 'channel_summary' : 'unsupported';
        }
        if ($intent === 'channel_summary' && !$ch) {
            $intent = 'unsupported';
        }
        return ['intent' => $intent, 'channel' => $ch, 'segment' => $seg, 'metric' => $d['metric'] ?? null, 'ai' => true];
    }

    /** @param array<string,mixed> $answer from Engine::answerIntent @return array{text:string,ai:bool} */
    public static function askPhrase(int $ws, string $q, string $intent, array $answer): array
    {
        if (!$answer['grounded']) {
            return ['text' => $answer['text'], 'ai' => false];
        }
        $ctx = ['question' => mb_substr($q, 0, 300), 'intent' => $intent, 'rows' => $answer['rows'], 'facts' => $answer['facts'], 'template_answer' => $answer['text']];
        $keyNums = Firewall::numbers($answer['text']);
        $r = self::run('ask_phrase', $ctx, 'fast', $ws, static function (array $d) use ($keyNums): bool {
            if (!self::str($d['text'] ?? null, 400) || !is_array($d['source_refs'] ?? null) || !$d['source_refs']) {
                return false;
            }
            $mine = Firewall::numbers($d['text']);
            foreach ($keyNums as $k) {
                foreach ($mine as $m) {
                    if (abs($m - $k) <= 0.005 * max(abs($k), 1)) {
                        return true;
                    }
                }
            }
            return $keyNums === [];
        });
        return $r['data'] ? ['text' => (string) $r['data']['text'], 'ai' => true] : ['text' => $answer['text'], 'ai' => false];
    }

    /** @param array<string,mixed> $ins @param array<string,mixed> $profile @return array{why:string,risk:string}|null */
    public static function insightExplain(int $ws, array $ins, array $profile, string $risk, Engine $e): ?array
    {
        $w = $ins['alloc'][0] ?? null;
        if (!$w) {
            return null;
        }
        $ref = 'rates:' . $w['ch'] . '|' . $w['seg'];
        $f = static fn (string $r, string $label, float $v, string $d) => ['ref' => $r, 'label' => $label, 'value' => $v, 'display' => $d];
        $facts = [
            $f($ref . '#cac', 'CAC ردیف اول', $e->cac($w), Fmt::money($e->cac($w)) . ' تومان'),
            $f($ref . '#margin_cap', 'سقف حاشیه‌ی ردیف اول', $e->cap($w), Fmt::money($e->cap($w)) . ' تومان'),
            $f($ref . '#variance', 'نوسان', (float) $w['variance'], '±' . Fmt::pct((float) $w['variance'], 0)),
            $f($ref . '#sample_n', 'تعداد نمونه', (float) $w['n'], Fmt::fa((string) $w['n']) . ' کمپین'),
            $f('insight:' . $ins['id'] . '#cac', 'CAC کل تخصیص', $ins['exp']['cac'], Fmt::money($ins['exp']['cac']) . ' تومان'),
            $f('insight:' . $ins['id'] . '#poas', 'POAS کل', $ins['exp']['poas'], Fmt::signPct($ins['exp']['poas'])),
            $f('insight:' . $ins['id'] . '#conv', 'خرید مورد انتظار', $ins['exp']['conv'], Fmt::num($ins['exp']['conv'])),
            $f('insight:' . $ins['id'] . '#installs', 'نصب مورد انتظار', $ins['exp']['installs'], Fmt::num($ins['exp']['installs'])),
            $f('insight:' . $ins['id'] . '#revenue', 'درآمد مورد انتظار', $ins['exp']['revenue'], Fmt::money($ins['exp']['revenue']) . ' تومان'),
            $f('insight:' . $ins['id'] . '#roas', 'ROAS', $ins['exp']['roas'], Fmt::dec($ins['exp']['roas'], 1)),
        ];
        foreach ($ins['alloc'] as $i => $a) {
            $facts[] = $f('alloc#' . $i . '.share', 'سهم ' . $a['ch'] . '/' . $a['seg'], (float) $a['share'], Fmt::pct((float) $a['share'], 0));
        }
        $goal = (string) ($profile['goal'] ?? '');
        $ctx = [
            'perspective_id' => $ins['id'], 'perspective_name' => $ins['perspective'], 'objective' => $ins['objective'],
            'business_goal' => $goal, 'risk_appetite' => $risk,
            'top_row' => ['ref' => $ref, 'channel' => $w['ch'], 'segment' => $w['seg']],
            'allocation' => array_map(static fn ($a, $i) => ['ref' => 'rates:' . $a['ch'] . '|' . $a['seg'], 'label' => $a['ch'] . '/' . $a['seg'], 'share_fact' => 'alloc#' . $i . '.share'], $ins['alloc'], array_keys($ins['alloc'])),
            'flags' => ['low_sample' => (int) $w['n'] < 5, 'benchmark' => ($w['source'] ?? '') === 'benchmark', 'poas_negative' => $ins['exp']['poas'] < 0, 'fits_goal' => $e->fitScore($ins['id'], $goal, $risk) > 0],
            'static_claim' => $ins['claim'], 'static_risk' => $ins['risk'], 'facts' => $facts,
        ];
        $r = self::run('insight_explain', $ctx, 'fast', $ws, static fn (array $d): bool => self::str($d['why'] ?? null, 320) && self::str($d['risk'] ?? null, 320));
        return $r['data'] ? ['why' => (string) $r['data']['why'], 'risk' => (string) $r['data']['risk']] : null;
    }

    /** @param array<string,mixed> $run from Loop::run @param array<string,float|int> $rules @return array{summary:string,next_step:string}|null */
    public static function verifyNarrative(int $ws, array $run, ?string $perspective, array $rules): ?array
    {
        $vr = $run['ver']['r'] ?? null;
        if (!$vr) {
            return null;
        }
        $code = $run['code'];
        $f = static fn (string $r, string $label, float $v, string $d) => ['ref' => $r, 'label' => $label, 'value' => $v, 'display' => $d];
        $facts = [
            $f("verify:$code#dB", 'انحراف بودجه', $vr['dB'], Fmt::signPct($vr['dB'])),
            $f("verify:$code#dI", 'انحراف نصب', $vr['dI'], Fmt::signPct($vr['dI'])),
            $f("verify:$code#dC", 'انحراف خرید', $vr['dC'], Fmt::signPct($vr['dC'])),
            $f("verify:$code#dCvr", 'انحراف CVR', $vr['dCvr'], Fmt::signPct($vr['dCvr'])),
            $f('rules#exec_th', 'آستانه‌ی انحراف اجرا', (float) $rules['execTh'], Fmt::pct((float) $rules['execTh'], 0)),
            $f('rules#est_th', 'آستانه‌ی خطای برآورد', (float) $rules['estTh'], Fmt::pct((float) $rules['estTh'], 0)),
            $f('rules#fraud_th', 'آستانه‌ی تقلب', (float) $rules['fraudTh'], Fmt::pct((float) $rules['fraudTh'], 0)),
            $f('rules#attr_window', 'پنجره‌ی طرح', (float) $rules['attrWindow'], Fmt::fa((string) $rules['attrWindow']) . ' روز'),
            $f("verify:$code#window", 'پنجره‌ی نتیجه', (float) ($vr['window'] ?? 7), Fmt::fa((string) ($vr['window'] ?? 7)) . ' روز'),
            $f("verify:$code#fraud", 'نرخ تقلب', (float) $vr['fraud'], Fmt::pct((float) $vr['fraud'], 0)),
        ];
        if ($vr['lift'] !== null) {
            $facts[] = $f("verify:$code#lift", 'اثر افزایشی', (float) $vr['lift'], Fmt::pct((float) $vr['lift'], 0));
        }
        foreach ($run['cals'] as $cal) {
            $a = json_decode((string) $cal['after_row'], true);
            $facts[] = $f('calibration:' . $cal['code'] . '#cpi_before', 'CPI قبل', (float) $a['before']['cpi'], Fmt::num($a['before']['cpi']));
            $facts[] = $f('calibration:' . $cal['code'] . '#cpi_after', 'CPI بعد', (float) $a['cpi'], Fmt::num($a['cpi']));
        }
        $ctx = [
            'cause_id' => $vr['causeId'] ?? '', 'cause_label' => $vr['cause'], 'path' => $run['campaign_id'] ? 'connected' : 'manual',
            'forecast_source' => $run['in']['src'] ?? '', 'perspective' => $perspective, 'rate_ref' => 'rates:' . ($run['in']['ch'] ?? '') . '|' . ($run['in']['seg'] ?? ''),
            'calibratable' => (bool) $vr['calib'], 'calibration_applied' => (bool) $run['cals'], 'role_can_calibrate' => Auth::can('calibrate'),
            'engine_text' => ['expl' => $vr['expl'], 'block' => $vr['block'], 'lift_text' => $vr['liftText']],
            'within_band' => $vr['withinBand'] ?? null, 'facts' => $facts,
        ];
        $label = (string) $vr['cause'];
        $r = self::run('verify_narrative', $ctx, 'smart', $ws, static function (array $d) use ($label, $vr): bool {
            if (!self::str($d['summary'] ?? null, 520) || !self::str($d['next_step'] ?? null, 220)) {
                return false;
            }
            if (mb_strpos($d['summary'], $label) === false) {
                return false;
            }
            foreach (Engine::CAUSES as $c) {
                if ($c !== $label && mb_strpos($d['summary'] . $d['next_step'], $c) !== false) {
                    return false;
                }
            }
            if (empty($vr['calib']) && mb_strpos($d['next_step'], 'کالیبراسیون را اعمال') !== false) {
                return false;
            }
            return true;
        });
        return $r['data'] ? ['summary' => (string) $r['data']['summary'], 'next_step' => (string) $r['data']['next_step']] : null;
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $samples
     * @return array{mapping:array<string,string|null>,confidence:float,warnings:list<string>,fallback:bool}|null
     */
    public static function csvMap(string $kind, array $headers, array $samples): ?array
    {
        $ws = Auth::wsId() ?: null;
        $spec = Engine::importSpec()[$kind];
        $meaning = [
            'name' => ['نام کمپین', 'text', ['کمپین', 'عنوان', 'campaign']], 'channel' => ['کانال (گوگل، تپسل، یکتانت، اینستاگرام، پوش، پیامک)', 'enum', ['کانال', 'منبع', 'source', 'network']],
            'segment' => ['سگمنت (کاربر جدید، فعال، در معرض ریزش، پرارزش، بازگشتی)', 'enum', ['سگمنت', 'مخاطب', 'audience']], 'spend' => ['هزینه به تومان', 'money_toman', ['هزینه', 'cost', 'spend']],
            'installs' => ['تعداد نصب (برای پوش/پیامک: دریافت‌کننده)', 'count', ['نصب', 'installs', 'reach']], 'conversions' => ['تعداد خرید', 'count', ['خرید', 'تبدیل', 'سفارش', 'conv', 'orders']],
            'revenue' => ['درآمد به تومان', 'money_toman', ['درآمد', 'فروش', 'revenue']], 'month' => ['نام ماه جلالی (مهر، آبان، …)', 'text', ['ماه', 'month']],
            'unit_cost' => ['هزینه‌ی واحد به تومان', 'money_toman', ['CPI', 'هزینه هر نصب']], 'cvr' => ['نرخ تبدیل کسری ۰ تا ۱', 'fraction', ['CVR', 'نرخ تبدیل']],
            'aov' => ['میانگین ارزش سفارش به تومان', 'money_toman', ['AOV']], 'variance' => ['نوسان کسری', 'fraction', ['نوسان']], 'sample_n' => ['تعداد نمونه', 'count', ['sample']],
            'ceiling' => ['سقف حجم نصب', 'count', ['سقف']], 'd30' => ['ماندگاری روز ۳۰ کسری', 'fraction', ['D30', 'retention']], 'fraud' => ['نرخ تقلب کسری', 'fraction', ['fraud', 'تقلب']],
        ];
        $target = [];
        foreach ($spec['cols'] as $c) {
            $target[] = ['key' => $c, 'meaning' => $meaning[$c][0], 'type' => $meaning[$c][1], 'required' => true, 'synonyms' => $meaning[$c][2]];
        }
        $samples = array_map(static fn ($row) => array_map(static fn ($v) => preg_match('/@|^\+?\d{10,}$/', (string) $v) ? '«حذف‌شده»' : mb_substr((string) $v, 0, 60), $row), $samples);
        $ctx = ['kind' => $kind, 'target_schema' => $target, 'headers' => $headers, 'sample_rows' => $samples];
        $r = self::run('csv_map', $ctx, 'fast', $ws, static function (array $d) use ($spec, $headers): bool {
            if (!is_array($d['mapping'] ?? null)) {
                return false;
            }
            $used = [];
            foreach ($spec['cols'] as $c) {
                $v = $d['mapping'][$c] ?? null;
                if ($v === null) {
                    continue;
                }
                if (!in_array($v, $headers, true) || in_array($v, $used, true)) {
                    return false;
                }
                $used[] = $v;
            }
            return true;
        });
        if (!$r['data']) {
            // deterministic fallback: exact (case-insensitive) header match
            $map = [];
            $lower = array_map('mb_strtolower', $headers);
            foreach ($spec['cols'] as $c) {
                $i = array_search($c, $lower, true);
                $map[$c] = $i === false ? null : $headers[$i];
            }
            return ['mapping' => $map, 'confidence' => 0.0, 'warnings' => [], 'fallback' => true];
        }
        $map = [];
        foreach ($spec['cols'] as $c) {
            $map[$c] = $r['data']['mapping'][$c] ?? null;
        }
        return ['mapping' => $map, 'confidence' => (float) ($r['data']['confidence'] ?? 0), 'warnings' => array_slice(array_values(array_filter((array) ($r['data']['warnings'] ?? []), 'is_string')), 0, 3), 'fallback' => false];
    }

    /** @param list<array<string,mixed>> $facts @return array{headline:string,bullets:list<string>}|null */
    public static function monthlySummary(int $ws, string $monthLabel, string $best, bool $lowN, array $facts): ?array
    {
        $ctx = ['month_label' => $monthLabel, 'flags' => ['has_previous' => false, 'best_perspective_low_n' => $lowN], 'best_perspective' => $best, 'facts' => $facts];
        $r = self::run('monthly_summary', $ctx, 'smart', $ws, static fn (array $d): bool => self::str($d['headline'] ?? null, 140) && is_array($d['bullets'] ?? null) && count($d['bullets']) === 3 && count(array_filter($d['bullets'], static fn ($b) => self::str($b, 220))) === 3);
        return $r['data'] ? ['headline' => (string) $r['data']['headline'], 'bullets' => array_values($r['data']['bullets'])] : null;
    }

    /** @return array{is_specific:bool,hint:?string}|null */
    public static function reasonHint(int $ws, string $reason, string $perspective, string $goal, string $claim): ?array
    {
        $ctx = ['reason' => mb_substr($reason, 0, 600), 'perspective_name' => $perspective, 'secondary_perspective_name' => null, 'business_goal' => $goal, 'card_claim' => mb_substr($claim, 0, 400), 'facts' => []];
        $r = self::run('plan_reason_hint', $ctx, 'fast', $ws, static fn (array $d): bool => is_bool($d['is_specific'] ?? null) && (($d['hint'] ?? null) === null || self::str($d['hint'], 140)));
        if (!$r['data']) {
            return null;
        }
        $hint = $r['data']['is_specific'] ? null : ($r['data']['hint'] ?? null);
        if ($hint !== null && preg_match('/[0-9۰-۹]/u', $hint)) {
            $hint = null;
        }
        return ['is_specific' => (bool) $r['data']['is_specific'], 'hint' => $hint];
    }

    /** Admin connectivity test. @return array{ok:bool,message:string,ms:int} */
    public static function test(): array
    {
        $key = Settings::get('metis_api_key');
        if ($key === '') {
            return ['ok' => false, 'message' => 'کلید متیس وارد نشده است.', 'ms' => 0];
        }
        $r = Http::postJson(rtrim(Settings::get('metis_base_url', 'https://api.metisai.ir/openai/v1'), '/') . '/chat/completions', [
            'model' => Settings::get('metis_model_fast', 'gpt-4o-mini'),
            'messages' => [['role' => 'system', 'content' => 'Reply with a JSON object {"ok": true}.'], ['role' => 'user', 'content' => 'ping']],
            'response_format' => ['type' => 'json_object'], 'max_tokens' => 20,
        ], ['Authorization' => 'Bearer ' . $key], 15000);
        if ($r['status'] >= 200 && $r['status'] < 300 && isset($r['data']['choices'][0]['message']['content'])) {
            return ['ok' => true, 'message' => 'اتصال برقرار است. مدل: ' . ($r['data']['model'] ?? '') . ' · پاسخ: ' . mb_substr((string) $r['data']['choices'][0]['message']['content'], 0, 60), 'ms' => $r['ms']];
        }
        $msg = $r['error'] ?: ('HTTP ' . $r['status'] . ' · ' . mb_substr((string) ($r['data']['error']['message'] ?? $r['body']), 0, 200));
        return ['ok' => false, 'message' => $msg, 'ms' => $r['ms']];
    }
}
