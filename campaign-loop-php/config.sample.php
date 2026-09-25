<?php
/**
 * Campaign Loop — تنظیمات اصلی
 *
 * این فایل را به config.php کپی کنید و مقادیر را پر کنید.
 * (نصب‌کننده‌ی تحت وب در آدرس /install این کار را خودکار انجام می‌دهد.)
 */
return [
    // آدرس کامل سایت بدون / در انتها. خالی = تشخیص خودکار.
    'base_url' => '',

    // اگر mod_rewrite روی هاست فعال نیست، false کنید تا آدرس‌ها به شکل index.php?r=/... ساخته شوند.
    'pretty_urls' => true,

    // اتصال به پایگاه داده‌ی MySQL / MariaDB
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'campaign_loop',
        'user'     => 'root',
        'pass'     => '',
        'charset'  => 'utf8mb4',
    ],

    // کلید رمزنگاری (۶۴ کاراکتر هگز). برای رمزکردن کلید متیس، اعتبار اتصال‌ها و 2FA استفاده می‌شود.
    // بعد از نصب هرگز تغییرش ندهید، وگرنه کلیدهای ذخیره‌شده قابل خواندن نیستند.
    'app_key' => 'CHANGE_ME_64_HEX_CHARS',

    // توکن اجرای cron از طریق URL (اگر هاست cron خط فرمان ندارد): https://site/cron.php?token=...
    'cron_token' => 'CHANGE_ME_CRON_TOKEN',

    // حالت اشکال‌زدایی: فقط روی محیط توسعه true شود.
    'debug' => false,

    'timezone' => 'Asia/Tehran',
];
