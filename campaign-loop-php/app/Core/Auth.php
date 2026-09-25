<?php
declare(strict_types=1);

namespace App\Core;

final class Auth
{
    public const ROLES = ['owner' => 'مالک', 'analyst' => 'تحلیل‌گر', 'campaign_ops' => 'مسئول کمپین', 'viewer' => 'ناظر'];

    /** Server-side permission matrix (mirrors prototype can()). */
    private const CAN = [
        'owner' => ['editData', 'plan', 'result', 'calibrate', 'team', 'billing', 'connect', 'analytics', 'audit'],
        'analyst' => ['plan', 'result', 'calibrate', 'audit'],
        'campaign_ops' => ['result'],
        'viewer' => [],
    ];

    /** @var array<string,mixed>|null */
    private static ?array $user = null;
    /** @var array<string,mixed>|null */
    private static ?array $ws = null;
    private static ?string $role = null;
    private static bool $loaded = false;

    public static function boot(): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;
        $uid = (int) Session::get('uid', 0);
        $sid = (string) Session::get('sid', '');
        if ($uid <= 0 || $sid === '') {
            return;
        }
        $sess = DB::one('SELECT * FROM sessions WHERE id = ? AND user_id = ?', [$sid, $uid]);
        if (!$sess || $sess['revoked_at'] !== null) {
            Session::destroy();
            return;
        }
        $user = DB::one('SELECT * FROM users WHERE id = ? AND deleted_at IS NULL', [$uid]);
        if (!$user) {
            Session::destroy();
            return;
        }
        if (strtotime((string) $sess['last_seen_at']) < time() - 300) {
            DB::q('UPDATE sessions SET last_seen_at = NOW(), ip = ? WHERE id = ?', [Request::ip(), $sid]);
        }
        self::$user = $user;
        self::loadWorkspace((int) Session::get('ws_id', (int) ($user['last_workspace_id'] ?? 0)));
    }

    private static function loadWorkspace(int $wsId): void
    {
        $uid = (int) self::$user['id'];
        $row = null;
        if ($wsId > 0) {
            $row = DB::one('SELECT w.*, m.role FROM workspaces w JOIN memberships m ON m.workspace_id = w.id WHERE w.id = ? AND m.user_id = ? AND w.deleted_at IS NULL', [$wsId, $uid]);
        }
        if (!$row) {
            $row = DB::one('SELECT w.*, m.role FROM workspaces w JOIN memberships m ON m.workspace_id = w.id WHERE m.user_id = ? AND w.deleted_at IS NULL ORDER BY m.created_at LIMIT 1', [$uid]);
        }
        if ($row) {
            self::$role = (string) $row['role'];
            unset($row['role']);
            self::$ws = $row;
            Session::set('ws_id', (int) $row['id']);
        }
    }

    public static function switchWorkspace(int $wsId): bool
    {
        $ok = DB::one('SELECT 1 FROM memberships WHERE workspace_id = ? AND user_id = ?', [$wsId, self::id()]);
        if (!$ok) {
            return false;
        }
        Session::set('ws_id', $wsId);
        Session::forget('cur_campaign');
        DB::q('UPDATE users SET last_workspace_id = ? WHERE id = ?', [$wsId, self::id()]);
        self::loadWorkspace($wsId);
        return true;
    }

    public static function check(): bool
    {
        self::boot();
        return self::$user !== null;
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        self::boot();
        return self::$user;
    }

    public static function id(): int
    {
        return (int) (self::user()['id'] ?? 0);
    }

    /** @return array<string,mixed>|null */
    public static function ws(): ?array
    {
        self::boot();
        return self::$ws;
    }

    public static function wsId(): int
    {
        return (int) (self::ws()['id'] ?? 0);
    }

    public static function role(): string
    {
        self::boot();
        return self::$role ?? 'viewer';
    }

    public static function roleLabel(?string $role = null): string
    {
        return self::ROLES[$role ?? self::role()] ?? 'ناظر';
    }

    public static function can(string $action): bool
    {
        if (!self::check() || self::$ws === null) {
            return false;
        }
        return in_array($action, self::CAN[self::role()] ?? [], true);
    }

    public static function isAdmin(): bool
    {
        return (bool) (self::user()['is_admin'] ?? false);
    }

    public static function require(string $action): void
    {
        if (!self::can($action)) {
            Response::abort(403, 'نقش «' . self::roleLabel() . '» اجازه‌ی این کار را ندارد.');
        }
    }

    public static function verified(): bool
    {
        return !empty(self::user()['email_verified_at']);
    }

    public static function login(int $uid): void
    {
        Session::regenerate();
        $sid = bin2hex(random_bytes(20));
        DB::insert('sessions', [
            'id' => $sid,
            'user_id' => $uid,
            'device' => Request::device(),
            'ip' => Request::ip(),
            'created_at' => DB::now(),
            'last_seen_at' => DB::now(),
        ]);
        Session::set('uid', $uid);
        Session::set('sid', $sid);
        Session::forget('pending_2fa');
        DB::q('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$uid]);
        self::$loaded = false;
        self::$user = null;
        self::$ws = null;
        self::boot();
    }

    public static function logout(): void
    {
        $sid = (string) Session::get('sid', '');
        if ($sid !== '') {
            DB::q('UPDATE sessions SET revoked_at = NOW() WHERE id = ?', [$sid]);
        }
        Session::destroy();
        self::$user = null;
        self::$ws = null;
    }

    public static function sessionId(): string
    {
        return (string) Session::get('sid', '');
    }

    /** Refresh cached workspace row (after settings change). */
    public static function reload(): void
    {
        self::$loaded = false;
        self::$user = null;
        self::$ws = null;
        self::boot();
    }
}
