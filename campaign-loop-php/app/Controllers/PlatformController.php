<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Fmt;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Totp;
use App\Core\Url;
use App\Engine\Engine;
use App\Services\Connectors;
use App\Services\Emails;
use App\Services\Loop;
use App\Services\Payment;
use App\Services\Ws;

final class PlatformController extends Controller
{
    // ------------------------------------------------------------------ connect & API keys

    public function connect(): void
    {
        $ws = $this->ws();
        $accs = [];
        foreach (array_keys(Connectors::KINDS) as $k) {
            $accs[$k] = Connectors::account($ws, $k);
        }
        $camps = DB::all("SELECT c.id, c.name, p.code, (SELECT GROUP_CONCAT(CONCAT(a.kind, ':', l.external_id)) FROM connector_links l JOIN connector_accounts a ON a.id = l.connector_account_id WHERE l.campaign_id = c.id) AS links FROM campaigns c JOIN plans p ON p.campaign_id = c.id WHERE c.workspace_id = ? AND c.status = 'live'", [$ws]);
        $this->page('connect', 'pages/connect', [
            'title' => 'اتصال داده', 'accs' => $accs, 'mode' => Connectors::mode(), 'camps' => $camps,
            'keys' => DB::all('SELECT * FROM api_keys WHERE workspace_id = ? AND revoked_at IS NULL ORDER BY id DESC', [$ws]),
            'newKey' => Session::get('new_api_key'), 'tier' => (string) DB::val('SELECT tier FROM workspaces WHERE id = ?', [$ws]),
        ]);
        Session::forget('new_api_key');
    }

    public function connectSave(string $kind): void
    {
        if (!isset(Connectors::KINDS[$kind])) {
            Response::abort(404);
        }
        $ws = $this->ws();
        $key = Request::str('api_key', '', 500);
        $base = Request::str('base_url', '', 255);
        $t = Connectors::testConnection($kind, $key, $base);
        if (!$t['ok']) {
            $this->flash('اتصال برقرار نشد: ' . ($t['error'] ?? ''), 'bad');
            Response::redirect('/connect');
        }
        DB::q('INSERT INTO connector_accounts (workspace_id, kind, status, creds_enc, base_url, created_by, created_at) VALUES (?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), creds_enc = VALUES(creds_enc), base_url = VALUES(base_url), fail_count = 0, last_error = ""',
            [$ws, $kind, 'connected', Crypto::encrypt($key), $base, Auth::id()]);
        Audit::log('اتصال ' . Connectors::KINDS[$kind]['name'], Connectors::mode());
        $this->flash(Connectors::KINDS[$kind]['name'] . ' متصل شد.');
        Response::redirect('/connect');
    }

    public function disconnect(string $kind): void
    {
        DB::q("UPDATE connector_accounts SET status = 'revoked', creds_enc = NULL WHERE workspace_id = ? AND kind = ?", [$this->ws(), $kind]);
        Audit::log('قطع اتصال ' . (Connectors::KINDS[$kind]['name'] ?? $kind), '');
        Response::redirect('/connect');
    }

    public function syncNow(string $kind): void
    {
        $n = Connectors::sync($this->ws());
        $this->flash('همگام‌سازی انجام شد: ' . Fmt::fa((string) $n) . ' روز داده‌ی تازه.');
        Response::redirect('/connect');
    }

    public function linkCampaign(string $id): void
    {
        $c = $this->campaign($id);
        $kind = Request::str('kind');
        $acc = Connectors::account($this->ws(), $kind);
        if (!$acc || $acc['status'] !== 'connected') {
            $this->flash('ابتدا ' . (Connectors::KINDS[$kind]['name'] ?? '') . ' را متصل کنید.', 'bad');
            Response::redirect('/connect');
        }
        $ext = Request::str('external_id', '', 120) ?: ('c' . $c['id']);
        DB::q('REPLACE INTO connector_links (campaign_id, connector_account_id, external_id) VALUES (?,?,?)', [$c['id'], $acc['id'], $ext]);
        Audit::log('اتصال کمپین به ' . Connectors::KINDS[$kind]['name'], $c['name'] . ' → ' . $ext);
        $n = Connectors::sync($this->ws());
        $this->flash('کمپین متصل شد و ' . Fmt::fa((string) $n) . ' روز داده همگام شد.');
        Response::redirect('/connect');
    }

    public function apiKeyCreate(): void
    {
        $raw = 'sk_loop_' . bin2hex(random_bytes(20));
        DB::insert('api_keys', ['workspace_id' => $this->ws(), 'name' => Request::str('name', 'کلید سرور', 80), 'key_hash' => Crypto::hash($raw), 'last4' => substr($raw, -4), 'created_by' => Auth::id(), 'created_at' => DB::now()]);
        Session::set('new_api_key', $raw);
        Audit::log('ساختن کلید API', '…' . substr($raw, -4));
        Response::redirect('/connect#api');
    }

    public function apiKeyRevoke(string $id): void
    {
        DB::q('UPDATE api_keys SET revoked_at = NOW() WHERE id = ? AND workspace_id = ?', [(int) $id, $this->ws()]);
        Audit::log('لغو کلید API', '#' . $id);
        Response::redirect('/connect#api');
    }

    // ------------------------------------------------------------------ team

    public function team(): void
    {
        $ws = $this->ws();
        $this->page('team', 'pages/team', [
            'title' => 'تیم و نقش‌ها',
            'members' => DB::all('SELECT u.id, u.name, u.email, m.role FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? ORDER BY FIELD(m.role, "owner","analyst","campaign_ops","viewer"), u.name', [$ws]),
            'invites' => DB::all("SELECT * FROM invites WHERE workspace_id = ? AND status = 'pending' AND expires_at > NOW() ORDER BY id DESC", [$ws]),
            'seats' => ['trial' => 1, 'growth' => 5][(string) DB::val('SELECT tier FROM workspaces WHERE id = ?', [$ws])] ?? 1000,
        ]);
    }

    public function invite(): void
    {
        $ws = $this->ws();
        if (!Auth::verified()) {
            $this->flash('برای دعوت هم‌تیمی ابتدا ایمیل خود را تأیید کنید.', 'bad');
            Response::redirect('/team');
        }
        $email = mb_strtolower(Request::str('email', '', 190));
        $role = Request::str('role');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, ['analyst', 'campaign_ops', 'viewer'], true)) {
            $this->flash('ایمیل یا نقش نامعتبر است.', 'bad');
            Response::redirect('/team');
        }
        if (DB::val('SELECT 1 FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND u.email = ?', [$ws, $email]) || DB::val("SELECT 1 FROM invites WHERE workspace_id = ? AND email = ? AND status = 'pending' AND expires_at > NOW()", [$ws, $email])) {
            $this->flash('این ایمیل قبلاً عضو یا دعوت شده است.', 'bad');
            Response::redirect('/team');
        }
        $tier = (string) DB::val('SELECT tier FROM workspaces WHERE id = ?', [$ws]);
        $seats = ['trial' => 1, 'growth' => 5][$tier] ?? 1000;
        $used = (int) DB::val('SELECT COUNT(*) FROM memberships WHERE workspace_id = ?', [$ws]) + (int) DB::val("SELECT COUNT(*) FROM invites WHERE workspace_id = ? AND status = 'pending' AND expires_at > NOW()", [$ws]);
        if ($used >= $seats && !Auth::isAdmin()) {
            $this->flash('ظرفیت کاربر پلن فعلی پر است. پلن را ارتقا دهید.', 'bad');
            Response::redirect('/billing');
        }
        $token = Crypto::token();
        DB::insert('invites', ['workspace_id' => $ws, 'email' => $email, 'role' => $role, 'token_hash' => Crypto::hash($token), 'expires_at' => date('Y-m-d H:i:s', time() + 7 * 86400), 'invited_by' => Auth::id(), 'created_at' => DB::now()]);
        Emails::invite($email, (string) Auth::user()['name'], (string) Auth::ws()['name'], Auth::roleLabel($role), Url::to('/invite/' . $token, [], true));
        Audit::log('دعوت هم‌تیمی', $email . ' · ' . Auth::roleLabel($role));
        $this->flash('دعوت برای ' . $email . ' ارسال شد (اعتبار ۷ روز).');
        Response::redirect('/team');
    }

    public function inviteRevoke(string $id): void
    {
        DB::q("UPDATE invites SET status = 'revoked' WHERE id = ? AND workspace_id = ?", [(int) $id, $this->ws()]);
        Audit::log('لغو دعوت', '#' . $id);
        Response::redirect('/team');
    }

    public function memberRemove(string $uid): void
    {
        $ws = $this->ws();
        $m = DB::one('SELECT m.role, u.email FROM memberships m JOIN users u ON u.id = m.user_id WHERE m.workspace_id = ? AND m.user_id = ?', [$ws, (int) $uid]);
        if (!$m || $m['role'] === 'owner') {
            $this->flash('مالک فضای کاری حذف نمی‌شود.', 'bad');
            Response::redirect('/team');
        }
        DB::q('DELETE FROM memberships WHERE workspace_id = ? AND user_id = ?', [$ws, (int) $uid]);
        DB::q('UPDATE sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [(int) $uid]);
        Audit::log('حذف عضو', (string) $m['email']);
        Response::redirect('/team');
    }

    public function memberRole(string $uid): void
    {
        $ws = $this->ws();
        $role = Request::str('role');
        if (!in_array($role, ['analyst', 'campaign_ops', 'viewer'], true) || (int) $uid === Auth::id()) {
            Response::redirect('/team');
        }
        DB::q("UPDATE memberships SET role = ? WHERE workspace_id = ? AND user_id = ? AND role <> 'owner'", [$role, $ws, (int) $uid]);
        Audit::log('تغییر نقش عضو', '#' . $uid . ' → ' . Auth::roleLabel($role));
        Response::redirect('/team');
    }

    // ------------------------------------------------------------------ rules & audit & analytics

    public function rules(): void
    {
        $r = DB::one('SELECT * FROM rules WHERE workspace_id = ?', [$this->ws()]);
        $this->page('rules', 'pages/rules', ['title' => 'قواعد و آستانه‌ها', 'cfg' => Ws::rules($this->ws()), 'auto' => (int) ($r['auto_calibrate'] ?? 0)]);
    }

    public function rulesSave(): void
    {
        $ws = $this->ws();
        $old = Ws::rules($ws);
        $map = ['execTh' => 'exec_th', 'estTh' => 'est_th', 'scaleLo' => 'scale_lo', 'scaleHi' => 'scale_hi', 'inflation' => 'inflation', 'attrWindow' => 'attr_window', 'fraudTh' => 'fraud_th'];
        $labels = ['execTh' => 'آستانه‌ی انحراف اجرا (بودجه)', 'estTh' => 'آستانه‌ی خطای برآورد', 'scaleLo' => 'کران پایین نسبت مقیاس', 'scaleHi' => 'کران بالای نسبت مقیاس', 'inflation' => 'تورم ماهانه‌ی هزینه‌ی رسانه', 'attrWindow' => 'پنجره‌ی انتساب طرح (روز)', 'fraudTh' => 'آستانه‌ی تقلب'];
        $upd = [];
        foreach ($map as $k => $col) {
            $v = Request::num($k);
            if ($v === null || $v < 0 || ($k !== 'attrWindow' && $k !== 'scaleHi' && $k !== 'scaleLo' && $v > 1) || ($k === 'attrWindow' && ($v < 1 || $v > 90))) {
                continue;
            }
            if (abs($v - (float) $old[$k]) > 1e-9) {
                $upd[$col] = $k === 'attrWindow' ? (int) $v : $v;
                Audit::log('تغییر قاعده: ' . $labels[$k], (string) $v);
            }
        }
        $auto = Request::post('auto_calibrate') === '1' ? 1 : 0;
        $upd['auto_calibrate'] = $auto;
        if (($upd['scale_lo'] ?? $old['scaleLo']) >= ($upd['scale_hi'] ?? $old['scaleHi'])) {
            $this->flash('کران پایین نسبت مقیاس باید کمتر از کران بالا باشد.', 'bad');
            Response::redirect('/rules');
        }
        $upd['updated_at'] = DB::now();
        DB::update('rules', $upd, ['workspace_id' => $ws]);
        $this->flash('قواعد ذخیره شد.');
        Response::redirect('/rules');
    }

    public function rulesReset(): void
    {
        DB::q('UPDATE rules SET exec_th=0.15, est_th=0.25, scale_lo=0.8, scale_hi=1.2, inflation=0.035, attr_window=7, fraud_th=0.08, updated_at=NOW() WHERE workspace_id = ?', [$this->ws()]);
        Audit::log('بازنشانی قواعد', 'پیش‌فرض');
        Response::redirect('/rules');
    }

    public function audit(): void
    {
        $ws = $this->ws();
        $q = Request::str('q', '', 100);
        $page = max(1, Request::int('p', 1));
        $params = [$ws];
        $where = 'a.workspace_id = ?';
        if ($q !== '') {
            $where .= ' AND (a.action LIKE ? OR a.detail LIKE ? OR u.name LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $rows = DB::all("SELECT a.*, u.name FROM audit_log a LEFT JOIN users u ON u.id = a.user_id WHERE $where ORDER BY a.id DESC LIMIT 100 OFFSET " . (($page - 1) * 100), $params);
        $this->page('audit', 'pages/audit', ['title' => 'لاگ تغییرات', 'rows' => $rows, 'q' => $q, 'p' => $page]);
    }

    public function analytics(): void
    {
        $ws = $this->ws();
        $count = static fn (string $ev) => (int) DB::val('SELECT COUNT(*) FROM events WHERE workspace_id = ? AND name = ?', [$ws, $ev]);
        $steps = [['ثبت طرح', 'plan_created'], ['ویرایش طرح', 'plan_edited'], ['ثبت پایش', 'pace_logged'], ['راستی‌آزمایی', 'run_verified'], ['بستن حلقه', 'campaign_closed'], ['پرسش از داده', 'question_asked']];
        $rows = array_map(static fn ($s) => ['label' => $s[0], 'key' => $s[1], 'n' => $count($s[1])], $steps);
        $j = \App\Core\Jalali::today();
        $monthStart = \App\Core\Jalali::toIso(substr($j, 0, 8) . '01') ?? date('Y-m-01');
        $ai = DB::one("SELECT COUNT(*) n, SUM(status='ok') ok, SUM(status='cached') cached, SUM(status IN ('fallback','error','timeout')) fb, SUM(status='violation') viol, SUM(tokens_in+tokens_out) tok FROM llm_calls WHERE workspace_id = ? AND created_at >= ?", [$ws, date('Y-m-01')]);
        $this->page('analytics', 'pages/analytics', [
            'title' => 'آنالیتیکس محصول', 'rows' => $rows,
            'north' => (int) DB::val('SELECT COUNT(*) FROM perspective_log WHERE workspace_id = ? AND seeded = 0', [$ws]),
            'northMonth' => (int) DB::val('SELECT COUNT(*) FROM perspective_log WHERE workspace_id = ? AND seeded = 0 AND created_at >= ?', [$ws, $monthStart]),
            'events' => (int) DB::val('SELECT COUNT(*) FROM events WHERE workspace_id = ?', [$ws]), 'ai' => $ai,
        ]);
    }

    // ------------------------------------------------------------------ security

    public function security(): void
    {
        $u = Auth::user();
        $this->page('security', 'pages/security', [
            'title' => 'امنیت', 'u' => $u,
            'sessions' => DB::all('SELECT * FROM sessions WHERE user_id = ? AND revoked_at IS NULL AND last_seen_at > DATE_SUB(NOW(), INTERVAL 14 DAY) ORDER BY last_seen_at DESC', [$u['id']]),
            'setup' => Session::get('totp_setup'), 'codes' => Session::get('recovery_codes'),
            'requests' => DB::all('SELECT * FROM data_requests WHERE workspace_id = ? ORDER BY id DESC LIMIT 5', [$this->ws()]),
        ]);
        Session::forget('recovery_codes');
    }

    public function twoFaSetup(): void
    {
        Session::set('totp_setup', Totp::secret());
        Response::redirect('/security#twofa');
    }

    public function twoFaEnable(): void
    {
        $secret = (string) Session::get('totp_setup', '');
        if ($secret === '' || !Totp::verify($secret, Request::str('code'))) {
            $this->flash('کد واردشده درست نیست. ساعت گوشی را بررسی و دوباره تلاش کنید.', 'bad');
            Response::redirect('/security#twofa');
        }
        $codes = [];
        $hashes = [];
        for ($i = 0; $i < 8; $i++) {
            $c = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = $c;
            $hashes[] = Crypto::hash($c);
        }
        DB::update('users', ['totp_secret_enc' => Crypto::encrypt($secret), 'totp_enabled_at' => DB::now(), 'recovery_codes' => json_encode($hashes)], ['id' => Auth::id()]);
        Session::forget('totp_setup');
        Session::set('recovery_codes', $codes);
        Audit::log('فعال‌سازی ورود دومرحله‌ای', '');
        $this->flash('ورود دومرحله‌ای فعال شد. کدهای بازیابی را جایی امن نگه دارید.');
        Response::redirect('/security#twofa');
    }

    public function twoFaDisable(): void
    {
        $u = Auth::user();
        if (!Totp::verify(Crypto::decrypt((string) $u['totp_secret_enc']), Request::str('code')) && !password_verify((string) Request::post('password', ''), (string) $u['password_hash'])) {
            $this->flash('برای غیرفعال‌سازی، کد فعلی اپ یا رمز عبور لازم است.', 'bad');
            Response::redirect('/security#twofa');
        }
        DB::update('users', ['totp_secret_enc' => null, 'totp_enabled_at' => null, 'recovery_codes' => null], ['id' => $u['id']]);
        Audit::log('غیرفعال‌سازی ورود دومرحله‌ای', '');
        Response::redirect('/security#twofa');
    }

    public function sessionEnd(string $id): void
    {
        DB::q('UPDATE sessions SET revoked_at = NOW() WHERE id = ? AND user_id = ?', [$id, Auth::id()]);
        Audit::log('پایان نشست', substr($id, 0, 6));
        Response::redirect('/security');
    }

    public function exportData(): void
    {
        $ws = $this->ws();
        $data = [
            'exported_at' => date('c'), 'workspace' => DB::one('SELECT id, name, currency, tier, created_at FROM workspaces WHERE id = ?', [$ws]),
            'merchant_profile' => DB::one('SELECT * FROM merchant_profiles WHERE workspace_id = ?', [$ws]),
            'rules' => DB::one('SELECT * FROM rules WHERE workspace_id = ?', [$ws]),
            'rates' => DB::all('SELECT * FROM rates WHERE workspace_id = ?', [$ws]),
            'campaign_history' => DB::all('SELECT * FROM campaign_history WHERE workspace_id = ?', [$ws]),
            'campaigns' => DB::all('SELECT id, seq, name, status, goal_type, goal_value, budget, date_from, date_to, channels, segments, occasion, risk, created_at, closed_at FROM campaigns WHERE workspace_id = ?', [$ws]),
            'plans' => DB::all('SELECT * FROM plans WHERE workspace_id = ?', [$ws]),
            'runs' => DB::all('SELECT r.*, v.cause, v.result FROM runs r LEFT JOIN verifications v ON v.run_id = r.id WHERE r.workspace_id = ?', [$ws]),
            'calibrations' => DB::all('SELECT * FROM calibrations WHERE workspace_id = ?', [$ws]),
            'perspective_log' => DB::all('SELECT * FROM perspective_log WHERE workspace_id = ?', [$ws]),
        ];
        DB::insert('data_requests', ['workspace_id' => $ws, 'type' => 'export', 'status' => 'done', 'requested_by' => Auth::id(), 'created_at' => DB::now(), 'completed_at' => DB::now()]);
        Audit::log('دریافت نسخه‌ی داده', 'JSON');
        Response::download('campaign-loop-export-' . date('Ymd') . '.json', (string) json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'application/json; charset=utf-8');
    }

    public function deleteRequest(): void
    {
        $ws = $this->ws();
        DB::insert('data_requests', ['workspace_id' => $ws, 'type' => 'delete', 'status' => 'pending', 'requested_by' => Auth::id(), 'created_at' => DB::now()]);
        Audit::log('درخواست حذف داده', 'پاک‌سازی ظرف ۳۰ روز');
        $this->flash('درخواست حذف ثبت شد. داده‌ی این فضای کاری ظرف ۳۰ روز پاک می‌شود؛ تا آن زمان می‌توانید با پشتیبانی لغو کنید.');
        Response::redirect('/security');
    }

    // ------------------------------------------------------------------ billing

    public function billing(): void
    {
        $ws = $this->ws();
        $w = DB::one('SELECT tier, tier_until FROM workspaces WHERE id = ?', [$ws]);
        $this->page('billing', 'pages/billing', [
            'title' => 'پلن و صورتحساب', 'tier' => $w['tier'], 'until' => $w['tier_until'], 'usage' => Loop::usageThisMonth($ws),
            'payments' => DB::all('SELECT * FROM payments WHERE workspace_id = ? ORDER BY id DESC LIMIT 10', [$ws]), 'provider' => Payment::provider(),
        ]);
    }

    public function checkout(): void
    {
        $tier = Request::str('tier');
        Audit::event('upgrade_clicked', ['tier' => $tier]);
        if ($tier === 'enterprise') {
            DB::insert('support_tickets', ['workspace_id' => $this->ws(), 'user_id' => Auth::id(), 'body' => 'درخواست پلن سازمانی — لطفاً با من تماس بگیرید.', 'created_at' => DB::now()]);
            $this->flash('درخواست پلن سازمانی ثبت شد؛ تیم فروش با شما تماس می‌گیرد.');
            Response::redirect('/billing');
        }
        if ($tier !== 'growth') {
            Response::redirect('/billing');
        }
        $r = Payment::start($this->ws(), Auth::id(), 'growth');
        if (!$r['ok']) {
            $this->flash($r['error'] ?? 'خطا', 'bad');
            Response::redirect('/billing');
        }
        if (Payment::provider() === 'sandbox') {
            $this->flash('پرداخت آزمایشی انجام شد و پلن «رشد» برای ۳۰ روز فعال شد.');
        }
        header('Location: ' . $r['redirect'], true, 303);
        throw new \App\Core\HttpStop('redirect');
    }

    public function paymentCallback(): void
    {
        $r = Payment::verify(Request::int('pid'), Request::str('Authority'), Request::str('Status'));
        Session::flash($r['message'], $r['ok'] ? 'ok' : 'bad');
        Response::redirect('/billing');
    }

    // ------------------------------------------------------------------ help & settings

    public function help(): void
    {
        $this->page('help', 'pages/help', ['title' => 'راهنما و پشتیبانی', 'tickets' => DB::all('SELECT * FROM support_tickets WHERE user_id = ? ORDER BY id DESC LIMIT 5', [Auth::id()])]);
    }

    public function ticket(): void
    {
        $body = Request::str('body', '', 4000);
        if (mb_strlen($body) < 5) {
            $this->flash('متن درخواست را بنویسید.', 'bad');
            Response::redirect('/help');
        }
        DB::insert('support_tickets', ['workspace_id' => $this->ws(), 'user_id' => Auth::id(), 'body' => $body, 'created_at' => DB::now()]);
        Audit::notify('درخواست پشتیبانی ثبت شد. پاسخ تا ۴ ساعت کاری.', 'ok', '/help', $this->ws(), null, 'ticket');
        $this->flash('ثبت شد — پاسخ تا ۴ ساعت کاری');
        Response::redirect('/help');
    }

    public function settings(): void
    {
        $u = Auth::user();
        $this->page('settings', 'pages/settings', ['title' => 'تنظیمات', 'u' => $u, 'ws' => Auth::ws(), 'prefs' => json_decode((string) ($u['prefs'] ?? ''), true) ?: []]);
    }

    public function settingsWorkspace(): void
    {
        $name = Request::str('name', '', 160);
        $upd = ['currency' => Request::str('currency', 'تومان', 40), 'tz' => Request::str('tz', '', 60), 'ai_enabled' => Request::post('ai_enabled') === '1' ? 1 : 0];
        if (mb_strlen($name) >= 2) {
            $upd['name'] = $name;
        }
        DB::update('workspaces', $upd, ['id' => $this->ws()]);
        Audit::log('ویرایش تنظیمات فضای کاری', $name);
        $this->flash('تنظیمات فضای کاری ذخیره شد.');
        Response::redirect('/settings');
    }

    public function settingsAccount(): void
    {
        $u = Auth::user();
        $prefs = json_decode((string) ($u['prefs'] ?? ''), true) ?: [];
        $off = [];
        foreach (['pace_alert', 'campaign_ending', 'monthly_report'] as $t) {
            if (Request::post('mail_' . $t) !== '1') {
                $off[] = $t;
            }
        }
        $prefs['email_off'] = $off;
        $upd = ['prefs' => json_encode($prefs, JSON_UNESCAPED_UNICODE)];
        $name = Request::str('name', '', 120);
        if (mb_strlen($name) >= 3) {
            $upd['name'] = $name;
        }
        DB::update('users', $upd, ['id' => $u['id']]);
        $this->flash('حساب کاربری ذخیره شد.');
        Response::redirect('/settings');
    }

    public function settingsReset(): void
    {
        Ws::resetAll($this->ws());
        Session::forget('cur_campaign');
        Audit::log('بازنشانی کامل به داده‌ی دمو', '');
        $this->flash('همه‌ی داده‌ها به داده‌ی دموی اولیه برگشت.');
        Response::redirect('/data');
    }

    public function switchWs(): void
    {
        Auth::switchWorkspace(Request::int('ws'));
        Response::redirect('/campaigns');
    }

    public function notificationsRead(): void
    {
        DB::q('UPDATE notifications SET read_at = NOW() WHERE user_id = ? AND workspace_id = ? AND read_at IS NULL', [Auth::id(), $this->ws()]);
        Response::back();
    }

    public function prefs(): void
    {
        $u = Auth::user();
        $prefs = json_decode((string) ($u['prefs'] ?? ''), true) ?: [];
        if (Request::has('welcome')) {
            $prefs['welcome'] = 0;
        }
        DB::update('users', ['prefs' => json_encode($prefs, JSON_UNESCAPED_UNICODE)], ['id' => $u['id']]);
        $go = Request::str('go');
        if ($go !== '') {
            Audit::event('welcome_choice', ['choice' => $go]);
        }
        if ($go === 'demo') {
            Response::redirect('/setup');
        }
        if ($go === 'upload') {
            Response::redirect('/data#import');
        }
        if ($go === 'tour') {
            Session::set('start_tour', 1);
            Response::redirect('/data');
        }
        Response::back('/data');
    }
}
