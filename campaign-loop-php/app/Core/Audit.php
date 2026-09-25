<?php
declare(strict_types=1);

namespace App\Core;

/** Audit log, product events and in-app notifications. */
final class Audit
{
    public static function log(string $what, string $detail = '', ?int $wsId = null): void
    {
        $ws = $wsId ?? Auth::wsId();
        if ($ws <= 0) {
            return;
        }
        DB::insert('audit_log', [
            'workspace_id' => $ws,
            'user_id' => Auth::id() ?: null,
            'role' => Auth::check() ? Auth::role() : 'system',
            'action' => mb_substr($what, 0, 190),
            'detail' => mb_substr($detail, 0, 500),
            'created_at' => DB::now(),
        ]);
    }

    /** @param array<string,mixed> $props */
    public static function event(string $name, array $props = [], ?int $wsId = null): void
    {
        DB::insert('events', [
            'workspace_id' => $wsId ?? (Auth::wsId() ?: null),
            'user_id' => Auth::id() ?: null,
            'name' => $name,
            'props' => json_encode($props, JSON_UNESCAPED_UNICODE),
            'created_at' => DB::now(),
        ]);
    }

    /**
     * Fan out an in-app notification to workspace members.
     * @param list<string>|null $roles limit to roles (null = all)
     */
    public static function notify(string $text, string $kind = 'info', string $link = '', ?int $wsId = null, ?array $roles = null, string $type = 'info'): void
    {
        $ws = $wsId ?? Auth::wsId();
        if ($ws <= 0) {
            return;
        }
        $members = DB::all('SELECT user_id, role FROM memberships WHERE workspace_id = ?', [$ws]);
        foreach ($members as $m) {
            if ($roles !== null && !in_array($m['role'], $roles, true)) {
                continue;
            }
            DB::insert('notifications', [
                'workspace_id' => $ws,
                'user_id' => (int) $m['user_id'],
                'kind' => $kind,
                'type' => $type,
                'text' => mb_substr($text, 0, 500),
                'link' => $link,
                'created_at' => DB::now(),
            ]);
        }
    }
}
