<?php
/**
 * Campaign Loop — web installer.
 * Open https://your-site/install/ once, fill the form, then delete (or keep locked) this folder.
 */
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $c): void {
    $p = APP_ROOT . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (str_starts_with($c, 'App\\') && is_file($p)) {
        require $p;
    }
});
require APP_ROOT . '/app/helpers.php';

use App\Core\Config;
use App\Core\DB;

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
session_name('clinstall');
session_start();
if (empty($_SESSION['t'])) {
    $_SESSION['t'] = bin2hex(random_bytes(16));
}

function h(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** @return list<string> */
function splitSql(string $sql): array
{
    $out = [];
    $cur = '';
    $q = null;
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $c = $sql[$i];
        if ($q === null && $c === '-' && ($sql[$i + 1] ?? '') === '-') {
            $nl = strpos($sql, "\n", $i);
            $i = $nl === false ? $len : $nl;
            continue;
        }
        if ($q !== null) {
            $cur .= $c;
            if ($c === '\\') {
                $cur .= $sql[++$i] ?? '';
                continue;
            }
            if ($c === $q) {
                $q = null;
            }
            continue;
        }
        if ($c === "'" || $c === '"' || $c === '`') {
            $q = $c;
            $cur .= $c;
            continue;
        }
        if ($c === ';') {
            if (trim($cur) !== '') {
                $out[] = trim($cur);
            }
            $cur = '';
            continue;
        }
        $cur .= $c;
    }
    if (trim($cur) !== '') {
        $out[] = trim($cur);
    }
    return $out;
}

$checks = [
    ['PHP نسخه‌ی ۸٫۱ یا بالاتر', PHP_VERSION_ID >= 80100, PHP_VERSION, true],
    ['افزونه‌ی pdo_mysql', extension_loaded('pdo_mysql'), '', true],
    ['افزونه‌ی mbstring', extension_loaded('mbstring'), '', true],
    ['افزونه‌ی openssl', extension_loaded('openssl'), '', true],
    ['افزونه‌ی json', extension_loaded('json'), '', true],
    ['افزونه‌ی curl (برای متیس و درگاه پرداخت)', extension_loaded('curl'), '', false],
    ['پوشه‌ی storage قابل نوشتن', is_writable(APP_ROOT . '/storage'), '', true],
    ['امکان نوشتن config.php در ریشه', is_writable(APP_ROOT) || is_writable(APP_ROOT . '/config.php'), '', false],
];
$hardOk = !array_filter($checks, static fn ($c) => $c[3] && !$c[1]);

$installed = false;
if (is_file(APP_ROOT . '/config.php')) {
    try {
        Config::load(require APP_ROOT . '/config.php');
        $installed = (int) DB::val('SELECT COUNT(*) FROM users') > 0;
    } catch (Throwable $e) {
        $installed = false;
    }
}

$error = '';
$done = null;
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$guessUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/install/index.php')), '/\\');
$f = array_merge(['host' => 'localhost', 'port' => '3306', 'name' => '', 'user' => '', 'pass' => '', 'url' => $guessUrl, 'aname' => 'مدیر سامانه', 'aemail' => '', 'apass' => '', 'company' => 'فضای کاری من', 'demo' => '1', 'pretty' => '1'], $_POST);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $f['demo'] = isset($_POST['demo']) ? '1' : '0';
}

if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST' && hash_equals($_SESSION['t'], (string) ($_POST['t'] ?? ''))) {
    try {
        if (!$hardOk) {
            throw new RuntimeException('پیش‌نیازهای اجباری کامل نیست.');
        }
        if (!filter_var($f['aemail'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('ایمیل مدیر معتبر نیست.');
        }
        if (mb_strlen($f['apass']) < 8 || !preg_match('/\pL/u', $f['apass']) || !preg_match('/\d/', $f['apass'])) {
            throw new RuntimeException('رمز مدیر باید دست‌کم ۸ کاراکتر و شامل حرف و عدد باشد.');
        }
        $cfg = [
            'base_url' => rtrim(trim($f['url']), '/'),
            'pretty_urls' => $f['pretty'] === '1',
            'db' => ['host' => trim($f['host']), 'port' => (int) $f['port'], 'name' => trim($f['name']), 'user' => trim($f['user']), 'pass' => (string) $f['pass'], 'charset' => 'utf8mb4'],
            'app_key' => bin2hex(random_bytes(32)),
            'cron_token' => bin2hex(random_bytes(16)),
            'debug' => false,
            'timezone' => 'Asia/Tehran',
        ];
        Config::load($cfg);
        $pdo = DB::pdo();
        $exists = (int) DB::val("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'users'");
        if ($exists) {
            throw new RuntimeException('این پایگاه داده قبلاً جدول users دارد. یک پایگاه داده‌ی خالی انتخاب کنید.');
        }
        foreach ([APP_ROOT . '/database/schema.sql', APP_ROOT . '/database/seed-global.sql'] as $file) {
            foreach (splitSql((string) file_get_contents($file)) as $stmt) {
                $pdo->exec($stmt);
            }
        }
        $uid = DB::insert('users', [
            'email' => mb_strtolower(trim($f['aemail'])), 'name' => trim($f['aname']) ?: 'مدیر سامانه', 'company' => trim($f['company']),
            'password_hash' => App\Core\Crypto::hashPassword($f['apass']), 'email_verified_at' => DB::now(), 'is_admin' => 1,
            'prefs' => json_encode(['welcome' => 1]), 'created_at' => DB::now(),
        ]);
        $ws = App\Services\Ws::createWorkspace(trim($f['company']) ?: 'فضای کاری من', $uid, $f['demo'] === '1');
        DB::q("UPDATE workspaces SET tier = 'enterprise' WHERE id = ?", [$ws]);
        DB::q("INSERT INTO settings (k, v) VALUES ('installed_at', NOW()) ON DUPLICATE KEY UPDATE v = VALUES(v)");
        $php = "<?php\n// Generated by the installer on " . date('Y-m-d H:i') . "\nreturn " . var_export($cfg, true) . ";\n";
        if (@file_put_contents(APP_ROOT . '/config.php', $php) === false) {
            $done = ['manual' => $php, 'cfg' => $cfg];
        } else {
            $done = ['manual' => '', 'cfg' => $cfg];
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!doctype html>
<html lang="fa" dir="rtl">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>نصب Campaign Loop</title>
<link rel="stylesheet" href="../assets/css/app.css">
</head>
<body>
<header class="hdr"><div class="brand"><img src="../assets/img/logo-horizontal.png" alt="Campaign Loop"><div class="sep"></div><span class="partner">نصب</span></div></header>
<div class="wrap" style="margin:0 auto;max-width:860px">
<?php if ($installed): ?>
  <div class="card p22">
    <div class="h2">Campaign Loop نصب شده است.</div>
    <div class="small t2" style="line-height:1.9">برای امنیت، پوشه‌ی <span class="mono">install</span> را از هاست حذف کنید. برای نصب دوباره، ابتدا config.php را پاک و یک پایگاه داده‌ی خالی انتخاب کنید.</div>
    <a class="btn primary" href="../login">ورود به پنل</a>
  </div>
<?php elseif ($done): ?>
  <div class="card p22">
    <div class="h2" style="color:var(--teal)">✓ نصب با موفقیت انجام شد.</div>
    <?php if ($done['manual']): ?>
      <div class="callout warn">سرور اجازه‌ی نوشتن config.php را نداد. متن زیر را در فایلی به نام <b>config.php</b> در ریشه‌ی برنامه ذخیره کنید:</div>
      <textarea class="inp mono" rows="14" readonly style="direction:ltr"><?= h($done['manual']) ?></textarea>
    <?php endif; ?>
    <div class="h3 mt8">کار بعدی</div>
    <ol class="small t2" style="line-height:2.1;margin:0;padding-right:18px">
      <li>پوشه‌ی <span class="mono">install</span> را حذف کنید.</li>
      <li>کران‌جاب را تنظیم کنید (هر ۱۵ دقیقه):<br><span class="mono xs">php <?= h(APP_ROOT) ?>/cron.php</span><br>یا از طریق URL:<br><span class="mono xs"><?= h($done['cfg']['base_url']) ?>/cron.php?token=<?= h($done['cfg']['cron_token']) ?></span></li>
      <li>وارد شوید و از «مدیریت سامانه» کلید متیس، SMTP و درگاه پرداخت را وارد کنید.</li>
    </ol>
    <a class="btn primary lg" href="../login">ورود به پنل</a>
  </div>
<?php else: ?>
  <div class="head" style="margin-bottom:18px"><h1 class="title">نصب Campaign Loop</h1><p class="lead">یک پایگاه داده‌ی خالی MySQL یا MariaDB بسازید (از cPanel ← MySQL Databases)، سپس اطلاعات آن را اینجا وارد کنید. نصب‌کننده جدول‌ها را می‌سازد، حساب مدیر را ایجاد می‌کند و config.php را می‌نویسد.</p></div>
  <div class="card" style="margin-bottom:16px">
    <div class="h3">پیش‌نیازها</div>
    <?php foreach ($checks as [$label, $ok, $extra, $hard]): ?>
      <div class="row small"><span style="color:<?= $ok ? 'var(--teal)' : ($hard ? 'var(--bad-s)' : 'var(--warn-s)') ?>"><?= $ok ? '✓' : ($hard ? '✕' : '!') ?></span><span><?= h($label) ?></span><span class="muted ltr"><?= h($extra) ?></span></div>
    <?php endforeach; ?>
  </div>
  <?php if ($error): ?><div class="callout bad" style="margin-bottom:16px"><?= h($error) ?></div><?php endif; ?>
  <form method="post" class="card p22" style="gap:16px">
    <input type="hidden" name="t" value="<?= h($_SESSION['t']) ?>">
    <div class="h3">پایگاه داده</div>
    <div class="grid" style="--min:220px">
      <div class="field"><label>میزبان</label><input class="inp ltr" name="host" value="<?= h($f['host']) ?>" required></div>
      <div class="field"><label>پورت</label><input class="inp ltr" name="port" value="<?= h($f['port']) ?>" required></div>
      <div class="field"><label>نام پایگاه داده</label><input class="inp ltr" name="name" value="<?= h($f['name']) ?>" required></div>
      <div class="field"><label>نام کاربری</label><input class="inp ltr" name="user" value="<?= h($f['user']) ?>" required></div>
      <div class="field"><label>رمز عبور</label><input class="inp ltr" type="password" name="pass" value="<?= h($f['pass']) ?>"></div>
    </div>
    <div class="h3">سایت</div>
    <div class="grid" style="--min:220px">
      <div class="field"><label>آدرس کامل سایت</label><input class="inp ltr" name="url" value="<?= h($f['url']) ?>" required></div>
      <div class="field"><label>آدرس‌های تمیز (mod_rewrite)</label><select class="inp" name="pretty"><option value="1"<?= $f['pretty'] === '1' ? ' selected' : '' ?>>فعال (پیشنهادی)</option><option value="0"<?= $f['pretty'] === '0' ? ' selected' : '' ?>>غیرفعال — index.php?r=</option></select></div>
    </div>
    <div class="h3">حساب مدیر</div>
    <div class="grid" style="--min:220px">
      <div class="field"><label>نام</label><input class="inp" name="aname" value="<?= h($f['aname']) ?>" required></div>
      <div class="field"><label>ایمیل</label><input class="inp ltr" type="email" name="aemail" value="<?= h($f['aemail']) ?>" required></div>
      <div class="field"><label>رمز عبور</label><input class="inp ltr" type="password" name="apass" required placeholder="حداقل ۸ کاراکتر، حرف و عدد"></div>
      <div class="field"><label>نام کسب‌وکار / فضای کاری</label><input class="inp" name="company" value="<?= h($f['company']) ?>" required></div>
    </div>
    <label class="check"><input type="checkbox" name="demo" value="1"<?= $f['demo'] === '1' ? ' checked' : '' ?>> بارگذاری داده‌ی دمو (۱۴ ردیف نرخ، ۱۹ کمپین تاریخی) — برای تست یک حلقه‌ی کامل در ۵ دقیقه</label>
    <button class="btn primary lg" <?= $hardOk ? '' : 'disabled' ?>>نصب</button>
  </form>
<?php endif; ?>
</div>
</body>
</html>
