<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Http;
use App\Core\Settings;
use App\Core\Url;

/** ZarinPal v4 REST gateway (+ sandbox mode that activates instantly for testing). */
final class Payment
{
    public static function provider(): string
    {
        return Settings::get('payment_provider', 'sandbox');
    }

    private static function host(): string
    {
        return self::provider() === 'zarinpal_sandbox' ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }

    /** @return array{ok:bool,redirect?:string,error?:string} */
    public static function start(int $ws, int $uid, string $tier): array
    {
        $amount = (int) Settings::get('price_' . $tier . '_rial', '49000000');
        $pid = DB::insert('payments', ['workspace_id' => $ws, 'tier' => $tier, 'amount_rial' => $amount, 'status' => 'pending', 'created_by' => $uid, 'created_at' => DB::now()]);
        if (self::provider() === 'sandbox') {
            self::activate($ws, $tier, $pid, 'SANDBOX-' . $pid);
            return ['ok' => true, 'redirect' => Url::to('/billing')];
        }
        $merchant = Settings::get('zarinpal_merchant_id');
        if ($merchant === '') {
            return ['ok' => false, 'error' => 'شناسه‌ی پذیرنده‌ی زرین‌پال در مدیریت سامانه وارد نشده است.'];
        }
        $r = Http::postJson(self::host() . '/pg/v4/payment/request.json', [
            'merchant_id' => $merchant, 'amount' => $amount, 'currency' => 'IRR',
            'callback_url' => Url::to('/billing/callback', ['pid' => $pid], true), 'description' => 'اشتراک Campaign Loop — پلن ' . $tier,
        ], [], 15000);
        $auth = $r['data']['data']['authority'] ?? '';
        if (($r['data']['data']['code'] ?? 0) !== 100 || $auth === '') {
            return ['ok' => false, 'error' => 'درگاه پرداخت پاسخ نداد: ' . json_encode($r['data']['errors'] ?? $r['error'], JSON_UNESCAPED_UNICODE)];
        }
        DB::update('payments', ['authority' => $auth], ['id' => $pid]);
        return ['ok' => true, 'redirect' => self::host() . '/pg/StartPay/' . $auth];
    }

    /** @return array{ok:bool,message:string} */
    public static function verify(int $pid, string $authority, string $status): array
    {
        $p = DB::one('SELECT * FROM payments WHERE id = ? AND authority = ?', [$pid, $authority]);
        if (!$p || $p['status'] !== 'pending') {
            return ['ok' => false, 'message' => 'پرداخت پیدا نشد یا قبلاً بررسی شده است.'];
        }
        if ($status !== 'OK') {
            DB::update('payments', ['status' => 'failed'], ['id' => $pid]);
            return ['ok' => false, 'message' => 'پرداخت لغو شد.'];
        }
        $r = Http::postJson(self::host() . '/pg/v4/payment/verify.json', ['merchant_id' => Settings::get('zarinpal_merchant_id'), 'amount' => (int) $p['amount_rial'], 'authority' => $authority], [], 15000);
        $code = $r['data']['data']['code'] ?? 0;
        if ($code === 100 || $code === 101) {
            self::activate((int) $p['workspace_id'], (string) $p['tier'], $pid, (string) ($r['data']['data']['ref_id'] ?? ''));
            return ['ok' => true, 'message' => 'پرداخت موفق بود. کد پیگیری: ' . ($r['data']['data']['ref_id'] ?? '')];
        }
        DB::update('payments', ['status' => 'failed'], ['id' => $pid]);
        return ['ok' => false, 'message' => 'تأیید پرداخت ناموفق بود.'];
    }

    public static function activate(int $ws, string $tier, int $pid, string $ref): void
    {
        DB::update('payments', ['status' => 'paid', 'ref_id' => $ref, 'verified_at' => DB::now()], ['id' => $pid]);
        DB::q('UPDATE workspaces SET tier = ?, tier_until = DATE_ADD(GREATEST(COALESCE(tier_until, CURDATE()), CURDATE()), INTERVAL 30 DAY) WHERE id = ?', [$tier, $ws]);
        Audit::log('تغییر پلن', $tier, $ws);
        Audit::event('subscription_started', ['tier' => $tier], $ws);
        Audit::notify('پلن به «' . ($tier === 'growth' ? 'رشد' : $tier) . '» تغییر کرد.', 'ok', '/billing', $ws, ['owner'], 'plan_changed');
    }
}
