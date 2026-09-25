<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\DB;
use App\Core\Mailer;
use App\Core\Url;

/** Transactional email templates (design/Emails.dc.html) — RTL, inline styles, Tahoma fallback. */
final class Emails
{
    private static function h(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private static function layout(string $body, bool $security, string $unsubscribe = ''): string
    {
        $logo = Url::base() . '/assets/img/logo-horizontal.png';
        $tag = Url::base() . '/assets/img/logo-tagline.png';
        $foot = $security ? 'این ایمیل امنیتی است و لغو اشتراک ندارد.' : '<a href="' . self::h(Url::to('/settings', [], true)) . '#notif" style="color:#6b7072">تنظیم اعلان‌ها</a>' . ($unsubscribe ? ' · <a href="' . self::h(Url::to('/settings', [], true)) . '#notif" style="color:#6b7072">' . self::h($unsubscribe) . '</a>' : '');
        return '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"></head><body style="margin:0;background:#f5f5f5;font-family:Vazirmatn,Tahoma,Arial,sans-serif;direction:rtl">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:24px 0"><tr><td align="center">'
            . '<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e5e5e5;border-radius:12px">'
            . '<tr><td style="padding:22px 28px;border-bottom:1px solid #e9e9e9"><img src="' . self::h($logo) . '" alt="Campaign Loop" height="30" style="height:30px"></td></tr>'
            . '<tr><td style="padding:28px;color:#00142b;font-size:14px;line-height:2;text-align:right">' . $body . '</td></tr>'
            . '<tr><td style="padding:18px 28px;border-top:1px solid #e9e9e9;font-size:12px;color:#6b7072;text-align:right"><img src="' . self::h($tag) . '" alt="" height="22" style="height:22px;display:block;margin-bottom:8px">' . $foot . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function button(string $label, string $href): string
    {
        return '<p style="margin:22px 0"><a href="' . self::h($href) . '" style="display:inline-block;background:#066ca5;color:#ffffff;text-decoration:none;padding:11px 20px;border-radius:6px;font-weight:600">' . self::h($label) . '</a></p>';
    }

    public static function verify(string $to, string $name, string $link): bool
    {
        $b = '<h1 style="font-size:19px;margin:0 0 10px">سلام ' . self::h($name) . '، یک قدم مانده.</h1>'
            . '<p style="margin:0">برای فعال شدن دعوت هم‌تیمی و خروجی گزارش، ایمیل خود را تأیید کنید. این لینک ۲۴ ساعت اعتبار دارد.</p>'
            . self::button('تأیید ایمیل', $link)
            . '<p style="margin:0;font-size:12.5px;color:#6b7072">اگر این حساب را شما نساخته‌اید، این ایمیل را نادیده بگیرید.</p>';
        return Mailer::send($to, 'ایمیل خود را تأیید کنید', self::layout($b, true));
    }

    public static function reset(string $to, string $link, string $device, string $time): bool
    {
        $b = '<h1 style="font-size:19px;margin:0 0 10px">درخواست بازیابی رمز عبور</h1>'
            . '<p style="margin:0">برای حساب <span dir="ltr">' . self::h($to) . '</span> درخواست رمز تازه ثبت شد. لینک ۳۰ دقیقه و فقط یک بار معتبر است.</p>'
            . self::button('تعیین رمز تازه', $link)
            . '<p style="margin:0;font-size:12.5px;color:#6b7072">درخواست از ' . self::h($device) . ' · ' . self::h($time) . '. اگر شما نبودید، رمزتان را عوض نکنید و به پشتیبانی خبر دهید.</p>';
        return Mailer::send($to, 'تعیین رمز عبور تازه', self::layout($b, true));
    }

    public static function invite(string $to, string $inviter, string $workspace, string $role, string $link): bool
    {
        $b = '<h1 style="font-size:19px;margin:0 0 10px">به تیم ' . self::h($workspace) . ' بپیوندید</h1>'
            . '<p style="margin:0">' . self::h($inviter) . ' شما را با نقش <b>' . self::h($role) . '</b> به Campaign Loop دعوت کرده؛ جایی که تیم کمپین‌ها را پیش‌بینی، پایش و راستی‌آزمایی می‌کند.</p>'
            . self::button('پذیرش دعوت', $link)
            . '<p style="margin:0;font-size:12.5px;color:#6b7072">دعوت تا ۷ روز معتبر است.</p>';
        return Mailer::send($to, $inviter . ' شما را به ' . $workspace . ' دعوت کرد', self::layout($b, true));
    }

    /** @param array<string,string> $v day,total,delta,spend_pct,expected_pct,conv_pct,realloc_text */
    public static function paceAlert(string $to, string $campaign, array $v, string $link): bool
    {
        $cell = static fn (string $k, string $x) => '<td style="padding:10px;border:1px solid #e9e9e9;text-align:center"><div style="font-size:12px;color:#6b7072">' . $k . '</div><div style="font-size:17px;font-weight:700">' . self::h($x) . '</div></td>';
        $b = '<h1 style="font-size:18px;margin:0 0 12px">روز ' . self::h($v['day']) . ' از ' . self::h($v['total']) . ': خرید ' . self::h($v['delta']) . ' عقب‌تر از برنامه</h1>'
            . '<table role="presentation" width="100%" cellspacing="0" style="border-collapse:collapse"><tr>' . $cell('خرج شده', $v['spend_pct']) . $cell('انتظار', $v['expected_pct']) . $cell('خرید', $v['conv_pct']) . '</tr></table>'
            . ($v['realloc_text'] !== '' ? '<p style="margin:14px 0 0;background:#f2f8fc;border:1px solid #cfe2ef;border-radius:6px;padding:10px 12px;color:#064e77">' . self::h($v['realloc_text']) . '</p>' : '')
            . self::button('دیدن پایش', $link);
        return Mailer::send($to, 'خرید ' . $campaign . ' از برنامه عقب است', self::layout($b, false, 'لغو ایمیل‌های هشدار'));
    }

    public static function campaignEnding(string $to, string $campaign, string $link): bool
    {
        $b = '<h1 style="font-size:18px;margin:0 0 10px">دو روز تا پایان ' . self::h($campaign) . '</h1>'
            . '<p style="margin:0">بعد از پایان، نتیجه‌ی واقعی را ثبت کنید تا سیستم علت انحراف را بگوید و نرخ‌ها برای کمپین بعدی دقیق‌تر شوند. ثبت نتیجه حدود ۳ دقیقه طول می‌کشد.</p>'
            . self::button('ثبت نتیجه', $link);
        return Mailer::send($to, $campaign . ' تمام می‌شود — حلقه را ببندید', self::layout($b, false, 'لغو این نوع ایمیل'));
    }

    /** @param array<string,string> $k @param array{headline:string,bullets:list<string>}|null $ai */
    public static function monthly(string $to, string $month, string $workspace, array $k, ?array $ai, string $link): bool
    {
        $cell = static fn (string $l, string $x) => '<td style="padding:12px;border:1px solid #e9e9e9;width:25%"><div style="font-size:12px;color:#6b7072">' . $l . '</div><div style="font-size:18px;font-weight:700">' . self::h($x) . '</div></td>';
        $b = '<h1 style="font-size:19px;margin:0 0 12px">گزارش ' . self::h($month) . ' · ' . self::h($workspace) . '</h1>';
        if ($ai) {
            $b .= '<div style="background:#f7fbfe;border:1px solid #cfe2ef;border-radius:8px;padding:12px 14px;margin-bottom:14px"><div style="font-size:11px;color:#064e77;margin-bottom:4px">توضیح هوشمند</div><div style="font-weight:600">' . self::h($ai['headline']) . '</div><ul style="margin:6px 0 0;padding-right:18px">';
            foreach ($ai['bullets'] as $x) {
                $b .= '<li>' . self::h($x) . '</li>';
            }
            $b .= '</ul></div>';
        }
        $b .= '<table role="presentation" width="100%" cellspacing="0" style="border-collapse:collapse"><tr>' . $cell('حلقه‌های بسته‌شده', $k['closed_loops']) . $cell('POAS ماه', $k['poas']) . $cell('CAC در برابر هدف', $k['cac_vs_target']) . $cell('خطای پیش‌بینی', $k['forecast_error']) . '</tr></table>'
            . '<p style="margin:14px 0 0">بهترین دیدگاه ماه: <b>' . self::h($k['best_perspective']) . '</b></p>'
            . '<p style="margin:4px 0 0">کمپین‌های در انتظار نتیجه: <b>' . self::h($k['pending_count']) . '</b></p>'
            . self::button('داشبورد مدیر مالی', $link);
        return Mailer::send($to, 'گزارش ' . $month . ' · ' . $workspace, self::layout($b, false, 'لغو گزارش ماهانه'));
    }

    /** Should this user get this notification email? */
    public static function wants(int $userId, string $type): bool
    {
        $p = json_decode((string) DB::val('SELECT prefs FROM users WHERE id = ?', [$userId]), true) ?: [];
        return !in_array($type, $p['email_off'] ?? [], true);
    }
}
