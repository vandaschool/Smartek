<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Crypto;
use App\Core\DB;
use App\Core\Jalali;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Settings;
use App\Core\Totp;
use App\Core\Url;
use App\Core\View;
use App\Services\Emails;
use App\Services\Ws;

final class AuthController extends Controller
{
    private function authView(string $mode, array $vars = []): void
    {
        View::render('pages/auth', array_merge(['mode' => $mode, 'old' => Session::takeOld(), 'error' => $vars['error'] ?? '', 'title' => $mode === 'signup' ? 'ثبت‌نام' : 'ورود'], $vars), 'bare');
    }

    public static function passwordError(string $p): string
    {
        if (mb_strlen($p) < 8 || !preg_match('/\pL/u', $p) || !preg_match('/\d|[۰-۹]/u', $p)) {
            return 'رمز عبور باید دست‌کم ۸ کاراکتر و شامل حرف و عدد باشد.';
        }
        return '';
    }

    public static function validEmail(string $e): bool
    {
        return (bool) filter_var($e, FILTER_VALIDATE_EMAIL);
    }

    public function loginForm(): void
    {
        $this->authView('login');
    }

    public function signupForm(): void
    {
        $this->authView('signup');
    }

    public function login(): void
    {
        RateLimit::enforce('login:' . Request::ip(), 10, 60);
        $email = mb_strtolower(Request::str('email', '', 190));
        $pass = (string) Request::post('password', '');
        $u = DB::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        if (!$u || !password_verify($pass, (string) $u['password_hash'])) {
            Session::old(['email' => $email]);
            $this->authView('login', ['error' => 'ایمیل یا رمز عبور درست نیست.', 'old' => ['email' => $email]]);
            return;
        }
        if (password_needs_rehash((string) $u['password_hash'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
            DB::update('users', ['password_hash' => Crypto::hashPassword($pass)], ['id' => $u['id']]);
        }
        if ($u['totp_enabled_at']) {
            Session::regenerate();
            Session::set('pending_2fa', (int) $u['id']);
            Response::redirect('/2fa');
        }
        $this->finishLogin((int) $u['id']);
    }

    private function finishLogin(int $uid): never
    {
        Auth::login($uid);
        $to = (string) Session::get('intended', '/campaigns');
        Session::forget('intended');
        Response::redirect(str_starts_with($to, '/') ? $to : '/campaigns');
    }

    public function twoFaForm(): void
    {
        if (!Session::get('pending_2fa')) {
            Response::redirect('/login');
        }
        View::render('pages/twofa', ['error' => '', 'title' => 'ورود دومرحله‌ای'], 'bare');
    }

    public function twoFa(): void
    {
        RateLimit::enforce('2fa:' . Request::ip(), 10, 60);
        $uid = (int) Session::get('pending_2fa', 0);
        $u = $uid ? DB::one('SELECT * FROM users WHERE id = ?', [$uid]) : null;
        if (!$u) {
            Response::redirect('/login');
        }
        $code = Request::str('code', '', 20);
        $ok = Totp::verify(Crypto::decrypt((string) $u['totp_secret_enc']), $code);
        if (!$ok) {
            $codes = json_decode((string) $u['recovery_codes'], true) ?: [];
            $h = Crypto::hash(strtoupper(preg_replace('/\s/', '', $code) ?? ''));
            if (in_array($h, $codes, true)) {
                $ok = true;
                DB::update('users', ['recovery_codes' => json_encode(array_values(array_diff($codes, [$h])))], ['id' => $uid]);
            }
        }
        if (!$ok) {
            View::render('pages/twofa', ['error' => 'کد واردشده درست نیست.', 'title' => 'ورود دومرحله‌ای'], 'bare');
            return;
        }
        $this->finishLogin($uid);
    }

    public function signup(): void
    {
        RateLimit::enforce('signup:' . Request::ip(), 10, 3600);
        $name = Request::str('name', '', 120);
        $company = Request::str('company', '', 160);
        $email = mb_strtolower(Request::str('email', '', 190));
        $pass = (string) Request::post('password', '');
        $old = ['name' => $name, 'company' => $company, 'email' => $email];
        $err = '';
        if (!self::validEmail($email)) {
            $err = 'ایمیل کاری معتبر وارد کنید.';
        } elseif (($pe = self::passwordError($pass)) !== '') {
            $err = $pe;
        } elseif (mb_strlen($name) < 3) {
            $err = 'نام را کامل وارد کنید.';
        } elseif (mb_strlen($company) < 2) {
            $err = 'نام کسب‌وکار را وارد کنید.';
        } elseif (DB::val('SELECT 1 FROM users WHERE email = ?', [$email])) {
            $err = 'با این ایمیل قبلاً حساب ساخته شده است. وارد شوید یا رمز را بازیابی کنید.';
        }
        if ($err !== '') {
            $this->authView('signup', ['error' => $err, 'old' => $old]);
            return;
        }
        $uid = DB::insert('users', ['email' => $email, 'name' => $name, 'company' => $company, 'password_hash' => Crypto::hashPassword($pass), 'created_at' => DB::now(), 'prefs' => json_encode(['welcome' => 1])]);
        $ws = Ws::createWorkspace($company, $uid, true);
        self::sendVerification($uid, $email, $name);
        Audit::event('signup_completed', array_filter(['utm_source' => Request::str('utm_source'), 'utm_medium' => Request::str('utm_medium'), 'utm_campaign' => Request::str('utm_campaign')]), $ws);
        Auth::login($uid);
        Audit::log('ثبت‌نام و ساخت فضای کاری', $company, $ws);
        Response::redirect('/data');
    }

    /** Ephemeral demo account (prototype «ورود به حالت دمو»). */
    public function demo(): void
    {
        if (!Settings::bool('demo_enabled', true)) {
            Response::redirect('/signup');
        }
        RateLimit::enforce('demo:' . Request::ip(), 5, 3600);
        $email = 'demo-' . bin2hex(random_bytes(5)) . '@demo.local';
        $uid = DB::insert('users', ['email' => $email, 'name' => 'کاربر دمو', 'company' => 'مرچنت نمونه', 'password_hash' => Crypto::hashPassword(Crypto::token(16)), 'email_verified_at' => DB::now(), 'created_at' => DB::now(), 'prefs' => json_encode(['welcome' => 1, 'demo_user' => 1])]);
        Ws::createWorkspace('فضای کاری مرچنت نمونه', $uid, true);
        Auth::login($uid);
        Response::redirect('/data');
    }

    public static function sendVerification(int $uid, string $email, string $name): void
    {
        $token = Crypto::token();
        DB::insert('email_tokens', ['user_id' => $uid, 'kind' => 'verify', 'token_hash' => Crypto::hash($token), 'expires_at' => date('Y-m-d H:i:s', time() + 86400), 'created_at' => DB::now()]);
        Emails::verify($email, $name, Url::to('/verify-email/' . $token, [], true));
    }

    public function resendVerification(): void
    {
        RateLimit::enforce('verify-resend:' . Auth::id(), 3, 600);
        $u = Auth::user();
        if (!empty($u['email_verified_at'])) {
            Response::redirect('/campaigns');
        }
        self::sendVerification((int) $u['id'], (string) $u['email'], (string) $u['name']);
        $this->flash('ایمیل تأیید دوباره ارسال شد.');
        Response::back();
    }

    public function verifyEmail(string $token): void
    {
        $t = DB::one("SELECT * FROM email_tokens WHERE token_hash = ? AND kind = 'verify' AND used_at IS NULL AND expires_at > NOW()", [Crypto::hash($token)]);
        if (!$t) {
            Session::flash('لینک تأیید نامعتبر یا منقضی است. از داخل پنل دوباره درخواست دهید.', 'bad');
            Response::redirect(Auth::check() ? '/campaigns' : '/login');
        }
        DB::q('UPDATE email_tokens SET used_at = NOW() WHERE id = ?', [$t['id']]);
        DB::q('UPDATE users SET email_verified_at = NOW() WHERE id = ?', [$t['user_id']]);
        Audit::event('email_verified', [], null);
        Session::flash('ایمیل شما تأیید شد.');
        Response::redirect(Auth::check() ? '/campaigns' : '/login');
    }

    public function logout(): void
    {
        Auth::logout();
        Response::redirect('/login');
    }

    public function forgotForm(): void
    {
        View::render('pages/forgot', ['sent' => false, 'email' => '', 'error' => '', 'title' => 'بازیابی رمز عبور'], 'bare');
    }

    public function forgot(): void
    {
        RateLimit::enforce('forgot:' . Request::ip(), 5, 600);
        $email = mb_strtolower(Request::str('email', '', 190));
        if (!self::validEmail($email)) {
            View::render('pages/forgot', ['sent' => false, 'email' => $email, 'error' => 'ایمیل کاری معتبر وارد کنید.', 'title' => 'بازیابی رمز عبور'], 'bare');
            return;
        }
        $u = DB::one('SELECT * FROM users WHERE email = ? AND deleted_at IS NULL', [$email]);
        if ($u) {
            $token = Crypto::token();
            DB::insert('email_tokens', ['user_id' => $u['id'], 'kind' => 'reset', 'token_hash' => Crypto::hash($token), 'expires_at' => date('Y-m-d H:i:s', time() + 1800), 'created_at' => DB::now()]);
            Emails::reset($email, Url::to('/reset/' . $token, [], true), Request::device(), Jalali::dt(DB::now()));
        }
        View::render('pages/forgot', ['sent' => true, 'email' => $email, 'error' => '', 'title' => 'بازیابی رمز عبور'], 'bare');
    }

    public function resetForm(string $token): void
    {
        $t = DB::one("SELECT * FROM email_tokens WHERE token_hash = ? AND kind = 'reset' AND used_at IS NULL AND expires_at > NOW()", [Crypto::hash($token)]);
        View::render('pages/reset', ['token' => $token, 'valid' => (bool) $t, 'error' => '', 'title' => 'رمز تازه'], 'bare');
    }

    public function reset(string $token): void
    {
        $t = DB::one("SELECT * FROM email_tokens WHERE token_hash = ? AND kind = 'reset' AND used_at IS NULL AND expires_at > NOW()", [Crypto::hash($token)]);
        if (!$t) {
            View::render('pages/reset', ['token' => $token, 'valid' => false, 'error' => '', 'title' => 'رمز تازه'], 'bare');
            return;
        }
        $p = (string) Request::post('password', '');
        if (($err = self::passwordError($p)) !== '' || $p !== (string) Request::post('password2', '')) {
            View::render('pages/reset', ['token' => $token, 'valid' => true, 'error' => $err ?: 'تکرار رمز با رمز یکی نیست.', 'title' => 'رمز تازه'], 'bare');
            return;
        }
        DB::tx(static function () use ($t, $p): void {
            DB::update('users', ['password_hash' => Crypto::hashPassword($p), 'must_change_password' => 0], ['id' => $t['user_id']]);
            DB::q('UPDATE email_tokens SET used_at = NOW() WHERE id = ?', [$t['id']]);
            DB::q('UPDATE sessions SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL', [$t['user_id']]);
        });
        Session::flash('رمز عبور تازه ثبت شد. وارد شوید.');
        Response::redirect('/login');
    }

    public function inviteForm(string $token): void
    {
        $inv = DB::one("SELECT i.*, w.name AS ws_name FROM invites i JOIN workspaces w ON w.id = i.workspace_id WHERE i.token_hash = ? AND i.status = 'pending' AND i.expires_at > NOW()", [Crypto::hash($token)]);
        $exists = $inv ? (bool) DB::val('SELECT 1 FROM users WHERE email = ?', [$inv['email']]) : false;
        View::render('pages/invite', ['inv' => $inv, 'token' => $token, 'exists' => $exists, 'error' => '', 'title' => 'پذیرش دعوت'], 'bare');
    }

    public function inviteAccept(string $token): void
    {
        $inv = DB::one("SELECT i.*, w.name AS ws_name FROM invites i JOIN workspaces w ON w.id = i.workspace_id WHERE i.token_hash = ? AND i.status = 'pending' AND i.expires_at > NOW()", [Crypto::hash($token)]);
        if (!$inv) {
            Response::redirect('/invite/' . $token);
        }
        $user = DB::one('SELECT * FROM users WHERE email = ?', [$inv['email']]);
        if ($user) {
            if (!Auth::check() || Auth::id() !== (int) $user['id']) {
                Session::set('intended', '/invite/' . $token);
                Session::flash('برای پذیرش دعوت با ایمیل ' . $inv['email'] . ' وارد شوید.', 'info');
                Response::redirect('/login');
            }
            $uid = (int) $user['id'];
        } else {
            $name = Request::str('name', '', 120);
            $pass = (string) Request::post('password', '');
            $err = mb_strlen($name) < 3 ? 'نام را کامل وارد کنید.' : self::passwordError($pass);
            if ($err !== '') {
                View::render('pages/invite', ['inv' => $inv, 'token' => $token, 'exists' => false, 'error' => $err, 'title' => 'پذیرش دعوت'], 'bare');
                return;
            }
            $uid = DB::insert('users', ['email' => $inv['email'], 'name' => $name, 'company' => $inv['ws_name'], 'password_hash' => Crypto::hashPassword($pass), 'email_verified_at' => DB::now(), 'created_at' => DB::now()]);
        }
        DB::tx(static function () use ($inv, $uid): void {
            DB::q('INSERT INTO memberships (workspace_id, user_id, role, created_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE role = VALUES(role)', [$inv['workspace_id'], $uid, $inv['role']]);
            DB::q("UPDATE invites SET status = 'accepted' WHERE id = ?", [$inv['id']]);
            DB::q('UPDATE users SET last_workspace_id = ? WHERE id = ?', [$inv['workspace_id'], $uid]);
        });
        if (!Auth::check()) {
            Auth::login($uid);
        }
        Auth::switchWorkspace((int) $inv['workspace_id']);
        Audit::log('پذیرش دعوت', (string) $inv['email'], (int) $inv['workspace_id']);
        Audit::notify($inv['email'] . ' دعوت را پذیرفت.', 'ok', '/team', (int) $inv['workspace_id'], ['owner'], 'invite_accepted');
        Session::flash('به فضای کاری «' . $inv['ws_name'] . '» پیوستید.');
        Response::redirect('/campaigns');
    }

    public function workspaceForm(): void
    {
        View::render('pages/workspace_new', ['title' => 'فضای کاری تازه'], 'bare');
    }

    public function workspaceCreate(): void
    {
        $name = Request::str('name', '', 160) ?: 'فضای کاری من';
        $wsCount = (int) DB::val('SELECT COUNT(*) FROM memberships WHERE user_id = ? AND role = ?', [Auth::id(), 'owner']);
        $enterprise = (bool) DB::val("SELECT 1 FROM memberships m JOIN workspaces w ON w.id = m.workspace_id WHERE m.user_id = ? AND w.tier = 'enterprise'", [Auth::id()]);
        if ($wsCount > 0 && !$enterprise && !Auth::isAdmin()) {
            Session::flash('چند فضای کاری فقط در پلن سازمانی ممکن است.', 'bad');
            Response::redirect('/billing');
        }
        $ws = Ws::createWorkspace($name, Auth::id(), Request::post('demo') === '1');
        Auth::switchWorkspace($ws);
        Response::redirect('/data');
    }

    public function passwordForm(): void
    {
        View::render('pages/password', ['error' => '', 'title' => 'تغییر رمز عبور'], 'bare');
    }

    public function passwordSave(): void
    {
        $u = Auth::user();
        $p = (string) Request::post('password', '');
        $cur = (string) Request::post('current', '');
        $err = '';
        if (!password_verify($cur, (string) $u['password_hash'])) {
            $err = 'رمز فعلی درست نیست.';
        } elseif (($e = self::passwordError($p)) !== '') {
            $err = $e;
        } elseif ($p !== (string) Request::post('password2', '')) {
            $err = 'تکرار رمز با رمز یکی نیست.';
        }
        if ($err !== '') {
            View::render('pages/password', ['error' => $err, 'title' => 'تغییر رمز عبور'], 'bare');
            return;
        }
        DB::update('users', ['password_hash' => Crypto::hashPassword($p), 'must_change_password' => 0], ['id' => $u['id']]);
        DB::q('UPDATE sessions SET revoked_at = NOW() WHERE user_id = ? AND id <> ? AND revoked_at IS NULL', [$u['id'], Auth::sessionId()]);
        Session::flash('رمز عبور تغییر کرد.');
        Response::redirect('/campaigns');
    }
}
