<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AI\AI;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Mailer;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;

/** Platform administration (users.is_admin): AI/Metis, SMTP, payment, connectors, occasions, leads, tickets. */
final class AdminController extends Controller
{
    /** key => [type, allowed values|null] ; type: str|int|bool|secret|enum */
    private const FIELDS = [
        'ai_provider' => ['enum', ['mock', 'metis']],
        'metis_api_key' => ['secret', null],
        'metis_base_url' => ['url', null],
        'metis_model_fast' => ['str', null],
        'metis_model_smart' => ['str', null],
        'ai_timeout_fast_ms' => ['int', [1000, 60000]],
        'ai_timeout_smart_ms' => ['int', [1000, 120000]],
        'ai_json_schema' => ['bool', null],
        'ai_debug' => ['bool', null],
        'ai_budget_trial' => ['int', [0, 1000000000]],
        'ai_budget_growth' => ['int', [0, 1000000000]],
        'ai_budget_enterprise' => ['int', [0, 1000000000]],
        'smtp_host' => ['str', null],
        'smtp_port' => ['int', [1, 65535]],
        'smtp_secure' => ['enum', ['tls', 'ssl', 'none']],
        'smtp_user' => ['str', null],
        'smtp_pass' => ['secret', null],
        'mail_from' => ['str', null],
        'mail_from_name' => ['str', null],
        'payment_provider' => ['enum', ['sandbox', 'zarinpal', 'zarinpal_sandbox']],
        'zarinpal_merchant_id' => ['secret', null],
        'price_growth_rial' => ['int', [10000, 100000000000]],
        'price_growth_label' => ['str', null],
        'connector_mode' => ['enum', ['mock', 'live']],
        'adtrace_base_url' => ['url', null],
        'intrack_base_url' => ['url', null],
        'demo_enabled' => ['bool', null],
    ];

    public function index(): void
    {
        $since = date('Y-m-d H:i:s', time() - 7 * 86400);
        $breaker = json_decode((string) @file_get_contents(APP_ROOT . '/storage/cache/ai-breaker.json'), true) ?: [];
        $vals = [];
        foreach (self::FIELDS as $k => $f) {
            $vals[$k] = $f[0] === 'secret' ? (Settings::has($k) ? '••••••••' : '') : Settings::get($k);
        }
        $this->page('admin', 'pages/admin', [
            'title' => 'مدیریت سامانه', 'v' => $vals, 'breaker' => $breaker,
            'llm' => DB::all("SELECT task, COUNT(*) n, SUM(status='ok') ok, SUM(status='cached') cached, SUM(status IN ('fallback','error','timeout','budget','breaker')) fb, SUM(status='violation') viol, ROUND(AVG(NULLIF(latency_ms,0))) ms, SUM(tokens_in + tokens_out) tok FROM llm_calls WHERE created_at >= ? GROUP BY task ORDER BY n DESC", [$since]),
            'llmErr' => DB::all("SELECT task, status, error_code, created_at FROM llm_calls WHERE created_at >= ? AND status NOT IN ('ok','cached') ORDER BY id DESC LIMIT 10", [$since]),
            'stats' => [
                'users' => (int) DB::val('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
                'ws' => (int) DB::val("SELECT COUNT(*) FROM workspaces w WHERE w.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM memberships m2 JOIN users u2 ON u2.id = m2.user_id WHERE m2.workspace_id = w.id AND m2.role = 'owner' AND u2.email LIKE '%@demo.local')"),
                'demo' => (int) DB::val("SELECT COUNT(*) FROM users WHERE email LIKE '%@demo.local' AND deleted_at IS NULL"),
                'loops' => (int) DB::val('SELECT COUNT(*) FROM perspective_log WHERE seeded = 0'),
                'paid' => (int) DB::val("SELECT COUNT(*) FROM workspaces WHERE tier <> 'trial' AND tier_until >= CURDATE()"),
            ],
            'occasions' => DB::all('SELECT * FROM occasions ORDER BY sort, id'),
            'leads' => DB::all('SELECT * FROM leads ORDER BY id DESC LIMIT 30'),
            'tickets' => DB::all("SELECT t.*, u.name, u.email, w.name AS ws FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id LEFT JOIN workspaces w ON w.id = t.workspace_id ORDER BY t.status = 'open' DESC, t.id DESC LIMIT 30"),
            'workspaces' => DB::all("SELECT w.id, w.name, w.tier, w.tier_until, w.created_at, (SELECT COUNT(*) FROM memberships m WHERE m.workspace_id = w.id) members, (SELECT COUNT(*) FROM campaigns c WHERE c.workspace_id = w.id) camps FROM workspaces w WHERE w.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM memberships m2 JOIN users u2 ON u2.id = m2.user_id WHERE m2.workspace_id = w.id AND m2.role = 'owner' AND u2.email LIKE '%@demo.local') ORDER BY w.id DESC LIMIT 30"),
            'cron' => DB::all('SELECT * FROM cron_runs ORDER BY job'),
            'aiResult' => Session::get('ai_test'), 'cronUrl' => url('/cron.php') . '?token=…',
        ]);
        Session::forget('ai_test');
    }

    public function save(): void
    {
        $changed = [];
        foreach (self::FIELDS as $k => [$type, $allowed]) {
            if ($type === 'bool') {
                if (Request::has($k . '__present')) {
                    $v = Request::post($k) === '1' ? '1' : '0';
                    if ($v !== Settings::get($k, '0')) {
                        Settings::set($k, $v);
                        $changed[] = $k;
                    }
                }
                continue;
            }
            if (!Request::has($k)) {
                continue;
            }
            $v = trim((string) Request::post($k, ''));
            if ($type === 'secret') {
                if (Request::post($k . '__clear') === '1') {
                    Settings::set($k, '');
                    $changed[] = $k;
                } elseif ($v !== '' && $v !== '••••••••') {
                    Settings::set($k, $v);
                    $changed[] = $k;
                }
                continue;
            }
            if ($type === 'enum' && !in_array($v, $allowed, true)) {
                continue;
            }
            if ($type === 'int') {
                $v = Fmt::en($v);
                if (!ctype_digit($v) || (int) $v < $allowed[0] || (int) $v > $allowed[1]) {
                    continue;
                }
            }
            if ($type === 'url' && $v !== '' && !preg_match('~^https?://~i', $v)) {
                $this->flash('آدرس باید با http:// یا https:// شروع شود: ' . $k, 'bad');
                continue;
            }
            $v = mb_substr($v, 0, 255);
            if ($v !== Settings::get($k)) {
                Settings::set($k, $v);
                $changed[] = $k;
            }
        }
        if ($changed) {
            Audit::log('تغییر تنظیمات سامانه', implode('، ', $changed));
            if (array_intersect($changed, ['metis_api_key', 'metis_base_url', 'ai_provider'])) {
                @unlink(APP_ROOT . '/storage/cache/ai-breaker.json');
            }
        }
        $this->flash($changed ? 'تنظیمات ذخیره شد (' . Fmt::fa((string) count($changed)) . ' مورد).' : 'تغییری نبود.');
        Response::redirect('/admin' . (Request::str('tab') !== '' ? '#' . Request::str('tab') : ''));
    }

    public function aiTest(): void
    {
        @unlink(APP_ROOT . '/storage/cache/ai-breaker.json');
        $r = AI::test();
        Session::set('ai_test', $r);
        Audit::log('آزمون اتصال متیس', $r['ok'] ? 'موفق' : 'ناموفق');
        Response::redirect('/admin#ai');
    }

    public function mailTest(): void
    {
        $to = (string) Auth::user()['email'];
        $ok = Mailer::send($to, 'آزمون ایمیل Campaign Loop', '<div dir="rtl" style="font-family:Tahoma,sans-serif">این یک ایمیل آزمایشی است. اگر آن را می‌بینید، تنظیمات ارسال ایمیل درست است.</div>');
        $this->flash($ok ? 'ایمیل آزمایشی به ' . $to . ' ارسال شد.' : 'ارسال ایمیل ناموفق بود. storage/logs را بررسی کنید.', $ok ? 'ok' : 'bad');
        Response::redirect('/admin#mail');
    }

    public function occasions(): void
    {
        $raw = static fn (string $k): array => is_array($v = Request::input($k, [])) ? array_values($v) : [];
        [$ids, $names, $lift, $cpi, $mf, $mt] = [$raw('id'), $raw('name'), $raw('lift'), $raw('cpi'), $raw('mf'), $raw('mt')];
        $del = $raw('del');
        foreach ($names as $i => $n) {
            $n = trim((string) $n);
            $id = (int) ($ids[$i] ?? 0);
            if ($id && in_array((string) $id, array_map('strval', $del), true)) {
                DB::delete('occasions', ['id' => $id]);
                continue;
            }
            if ($n === '') {
                continue;
            }
            $row = ['name' => mb_substr($n, 0, 80), 'purchase_lift' => (float) Fmt::parseNum($lift[$i] ?? 0), 'cpi_delta' => (float) Fmt::parseNum($cpi[$i] ?? 0),
                'month_from' => max(0, min(12, (int) Fmt::parseNum($mf[$i] ?? 0))), 'month_to' => max(0, min(12, (int) Fmt::parseNum($mt[$i] ?? 0))), 'sort' => $i];
            if ($id) {
                DB::update('occasions', $row, ['id' => $id]);
            } else {
                DB::insert('occasions', $row);
            }
        }
        Audit::log('ویرایش مناسبت‌ها', Fmt::fa((string) count($names)) . ' ردیف');
        $this->flash('مناسبت‌ها ذخیره شد.');
        Response::redirect('/admin#occasions');
    }

    public function ticketReply(string $id): void
    {
        $t = DB::one('SELECT * FROM support_tickets WHERE id = ?', [(int) $id]);
        if (!$t) {
            Response::abort(404);
        }
        $reply = Request::str('reply', '', 4000);
        DB::update('support_tickets', ['reply' => $reply, 'status' => $reply !== '' ? 'answered' : 'open'], ['id' => (int) $id]);
        if ($reply !== '' && $t['user_id'] && $t['workspace_id']) {
            DB::insert('notifications', ['workspace_id' => $t['workspace_id'], 'user_id' => $t['user_id'], 'kind' => 'info', 'type' => 'ticket', 'text' => 'به درخواست پشتیبانی شما پاسخ داده شد.', 'link' => '/help', 'created_at' => DB::now()]);
            $u = DB::one('SELECT email FROM users WHERE id = ?', [$t['user_id']]);
            if ($u) {
                Mailer::send((string) $u['email'], 'پاسخ پشتیبانی Campaign Loop', '<div dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.9">' . nl2br(htmlspecialchars($reply, ENT_QUOTES, 'UTF-8')) . '</div>');
            }
        }
        $this->flash('پاسخ ثبت شد.');
        Response::redirect('/admin#tickets');
    }
}
