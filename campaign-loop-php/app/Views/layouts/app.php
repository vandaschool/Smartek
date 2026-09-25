<?php
/** @var string $content @var array $nav @var string $pageKey */
use App\Core\Auth;
use App\Core\Session;
use App\Services\Nav;

$u = Auth::user();
$ws = Auth::ws();
$flash = Session::takeFlash();
$loop = $nav['loop'];
$cur = $loop['campaign'];
$prefs = json_decode((string) ($u['prefs'] ?? ''), true) ?: [];
$cid = $cur['id'] ?? 0;
$tourSteps = [
    ['title' => 'از داده شروع کنید', 'text' => 'سه جدول حافظه‌ی سیستم‌اند. نرخ‌ها با تورم ماهانه تعدیل می‌شوند و پوش و پیامک جدا از جذب پولی محاسبه می‌شوند.', 'href' => Nav::url('data', $cid)],
    ['title' => 'آمادگی را بسنجید', 'text' => 'اگر داده کم است، با نرخ‌های مرجع صنعت شروع کنید؛ سیستم برچسب «فرض» روی اینسایت‌ها می‌زند.', 'href' => Nav::url('setup', $cid)],
    ['title' => 'ده دیدگاه', 'text' => 'یک دیدگاه انتخاب و دلیلش را بنویسید. این دلیل بعداً در دفترچه سنجیده می‌شود.', 'href' => Nav::url('insights', $cid)],
    ['title' => 'پیش‌بینی با POAS', 'text' => 'بازده نزولی، تقلب و ماندگاری روز ۳۰ در پیش‌بینی لحاظ شده‌اند. بازه تجربی است، نه فاصله‌ی اطمینان.', 'href' => Nav::url('sim', $cid)],
    ['title' => 'پایش حین اجرا', 'text' => 'عدد روز جاری را وارد کنید تا ببینید کمپین از مسیر خارج شده یا نه — قبل از اینکه دیر شود.', 'href' => Nav::url('pace', $cid)],
    ['title' => 'راستی‌آزمایی', 'text' => 'پنج شاخه، آستانه‌های قابل تنظیم، گروه کنترل برای اثر افزایشی. کالیبراسیون فقط در شاخه‌های معتبر.', 'href' => Nav::url('verify', $cid)],
    ['title' => 'گزارش و یادگیری', 'text' => 'گزارش را چاپ یا CSV کنید، کمپین را ببندید و در دفترچه ببینید کدام دیدگاه تصمیم درست‌تری داده.', 'href' => Nav::url('report', $cid)],
];
$roleNote = Auth::can('plan') ? '' : (Auth::can('result') ? 'این نقش فقط نتیجه‌ی واقعی ثبت می‌کند.' : 'این نقش فقط‌خواندنی است.');
$title = $title ?? '';
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<title><?= e(($title ? $title . ' · ' : '') . 'Campaign Loop') ?></title>
<link rel="icon" type="image/png" href="<?= e(asset('img/logo-mark.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body data-page="<?= e($pageKey) ?>">
<header class="hdr no-print">
  <div class="brand">
    <a href="<?= e(url('/campaigns')) ?>"><img src="<?= e(asset('img/logo-horizontal.png')) ?>" alt="Campaign Loop"></a>
    <div class="sep"></div>
    <span class="partner">Smartech</span>
  </div>
  <nav class="tabs" aria-label="فازها">
    <?php foreach ($nav['phases'] as $ph): ?>
      <a href="<?= e($ph['href']) ?>" class="<?= $ph['on'] ? 'on' : '' ?>"<?= $ph['on'] ? ' aria-current="page"' : '' ?>><?= e($ph['label']) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="tools">
    <?php if (count($nav['wsList']) > 1): ?>
      <form method="post" action="<?= e(url('/ws/switch')) ?>" class="inline"><?= csrf_field() ?>
        <select name="ws" class="ws-select" aria-label="فضای کاری" data-autosubmit>
          <?php foreach ($nav['wsList'] as $w): ?><option value="<?= (int) $w['id'] ?>"<?= (int) $w['id'] === Auth::wsId() ? ' selected' : '' ?>><?= e($w['name']) ?></option><?php endforeach; ?>
        </select>
      </form>
    <?php else: ?>
      <div class="company small t3 nowrap" style="max-width:140px;overflow:hidden;text-overflow:ellipsis"><?= e($ws['name'] ?? '') ?></div>
    <?php endif; ?>
    <div class="notif-wrap">
      <button type="button" class="notif-btn" data-toggle="#notif-pop" aria-label="اعلان‌ها">اعلان‌ها<?php if ($nav['unread']): ?><span class="count"><?= fa($nav['unread']) ?></span><?php endif; ?></button>
      <div class="notif-pop" id="notif-pop" hidden>
        <div class="h"><span>اعلان‌ها</span><?php if ($nav['unread']): ?><form method="post" action="<?= e(url('/notifications/read')) ?>" class="inline"><?= csrf_field() ?><button class="btn link xs">خواندن همه</button></form><?php endif; ?></div>
        <?php if (!$nav['notifs']): ?><div style="padding:20px 14px" class="small t3">اعلانی نیست. هشدار پایش و پایان کمپین اینجا می‌آید.</div><?php endif; ?>
        <?php foreach ($nav['notifs'] as $n): ?>
          <a class="it<?= $n['read_at'] ? '' : ' unread' ?>" href="<?= e($n['link'] ? url($n['link']) : '#') ?>"><span class="dot <?= e($n['kind']) ?>">●</span><span><?= e($n['text']) ?><br><span class="xs muted"><?= e(jdt($n['created_at'])) ?></span></span></a>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="menu-wrap">
      <button type="button" class="btn sm" data-toggle="#user-menu" style="gap:8px;padding:3px 8px 3px 4px;border-color:transparent">
        <span class="avatar"><?= e(mb_substr(trim((string) $u['name']) ?: '؟', 0, 1)) ?></span><span class="uname"><?= e($u['name']) ?></span>
      </button>
      <div class="menu-pop" id="user-menu" hidden>
        <div class="xs muted" style="padding:6px 10px"><?= e(Auth::roleLabel()) ?> · <?= e($ws['name'] ?? '') ?></div>
        <a href="<?= e(url('/settings')) ?>">تنظیمات</a>
        <a href="<?= e(url('/security')) ?>">امنیت</a>
        <?php if (Auth::isAdmin()): ?><a href="<?= e(url('/admin')) ?>">مدیریت سامانه</a><?php endif; ?>
        <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button>خروج</button></form>
      </div>
    </div>
  </div>
</header>

<div class="shell">
  <aside class="side no-print" aria-label="گام‌ها">
    <div class="ph"><?= e($nav['phaseLabel']) ?></div>
    <?php foreach ($nav['steps'] as $s): ?>
      <a href="<?= e($s['href']) ?>" class="<?= $s['on'] ? 'on' : '' ?>"<?= $s['on'] ? ' aria-current="page"' : '' ?>><span class="n"><?= e($s['num']) ?></span><span><?= e($s['label']) ?></span></a>
    <?php endforeach; ?>
  </aside>
  <main class="main">
    <div class="mobnav no-print">
      <div class="r1"><?php foreach ($nav['phases'] as $ph): ?><a href="<?= e($ph['href']) ?>" class="<?= $ph['on'] ? 'on' : '' ?>"><?= e($ph['label']) ?></a><?php endforeach; ?></div>
      <div class="r2"><?php foreach ($nav['steps'] as $s): ?><a href="<?= e($s['href']) ?>" class="<?= $s['on'] ? 'on' : '' ?>"><?= e($s['num']) ?> · <?= e($s['label']) ?></a><?php endforeach; ?></div>
    </div>
    <div class="loopbar no-print">
      <div class="lbl">وضعیت حلقه</div>
      <?php if ($cur): ?><a class="camp" href="<?= e(url('/campaigns')) ?>" title="<?= e($cur['name']) ?>">«<?= e($cur['name']) ?>»</a><?php endif; ?>
      <?php foreach ($loop['chips'] as $c): ?>
        <div class="chip <?= $c['done'] ? 'done' : 'pending' ?>"><span class="d"></span><span class="l"><?= e($c['label']) ?></span><span class="nt"><?= e($c['note']) ?></span></div>
      <?php endforeach; ?>
      <div class="grow"></div>
      <span class="xs t3 nowrap">نقش: <b><?= e(Auth::roleLabel()) ?></b></span>
      <button type="button" class="btn sm teal-soft" data-tour-start>تور دمو</button>
      <a class="btn sm primary" href="<?= e($loop['next']['href']) ?>">قدم بعدی: <?= e($loop['next']['label']) ?> ←</a>
    </div>
    <div class="wrap">
      <div class="callout bad banner" id="offline-banner" hidden>اتصال اینترنت قطع است. تغییرات روی همین دستگاه نگه داشته می‌شوند و پس از اتصال همگام می‌شوند.</div>
      <?php if (!Auth::verified()): ?>
        <div class="callout info banner row"><span class="grow">ایمیل تأیید برای شما ارسال شد. تا تأیید، دعوت هم‌تیمی و خروجی گزارش غیرفعال است.</span>
          <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="inline"><?= csrf_field() ?><button class="btn sm primary">ارسال دوباره‌ی ایمیل تأیید</button></form></div>
      <?php endif; ?>
      <?php if ($roleNote !== '' && in_array($pageKey, ['campaigns', 'data', 'setup', 'design', 'insights', 'sim', 'pace', 'verify', 'report'], true)): ?>
        <div class="callout warn banner">نقش فعال: <strong><?= e(Auth::roleLabel()) ?></strong> — <?= e($roleNote) ?></div>
      <?php endif; ?>
      <?= $content ?>
    </div>
  </main>
</div>

<?php if ($flash): ?>
<div class="toasts" role="status" aria-live="polite">
  <?php foreach ($flash as $f): ?><div class="toast <?= e($f['kind']) ?>"><?= $f['kind'] === 'bad' ? '⚠' : '✓' ?> <span><?= e($f['text']) ?></span></div><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($prefs['welcome'])): ?>
<div class="overlay no-print" id="welcome">
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="wl-t">
    <img src="<?= e(asset('img/logo-horizontal.png')) ?>" alt="Campaign Loop" style="height:34px;width:auto;align-self:start">
    <div class="col gap6">
      <div id="wl-t" style="font-size:21px;font-weight:700"><?= e($u['name']) ?>، خوش آمدید.</div>
      <div style="font-size:14px;color:var(--t2);line-height:1.9">هر کمپین یک حلقه است: طرح، پیش‌بینی، پایش، نتیجه، اصلاح نرخ. اولین حلقه با داده‌ی دمو زیر پنج دقیقه بسته می‌شود. از کجا شروع کنیم؟</div>
    </div>
    <form method="post" action="<?= e(url('/prefs')) ?>" class="grid" style="--min:170px;gap:10px"><?= csrf_field() ?>
      <input type="hidden" name="welcome" value="0">
      <button class="welcome-opt hi" name="go" value="tour"><b>تور ۷ مرحله‌ای</b><span>پیشنهاد برای اولین بار</span></button>
      <button class="welcome-opt" name="go" value="demo"><b>شروع با داده‌ی دمو</b><span>داده آماده است؛ مستقیم به آمادگی</span></button>
      <button class="welcome-opt" name="go" value="upload"><b>آپلود داده‌ی خودم</b><span>قالب CSV و پیش‌نمایش خطا</span></button>
    </form>
    <form method="post" action="<?= e(url('/prefs')) ?>"><?= csrf_field() ?><input type="hidden" name="welcome" value="0"><button class="btn link" style="color:var(--t3);font-weight:400">بعداً</button></form>
  </div>
</div>
<?php endif; ?>

<div class="tour no-print" id="tour" hidden role="dialog" aria-label="تور دمو">
  <div class="n">تور دمو · <span data-tour-n></span></div>
  <div class="t" data-tour-t></div>
  <div class="x" data-tour-x></div>
  <div class="row gap8 mt4">
    <button type="button" class="btn teal sm" data-tour-next>بعدی ←</button>
    <button type="button" class="btn dark-o sm" data-tour-end>بستن</button>
  </div>
</div>
<script type="application/json" id="tour-steps"><?= json_encode($tourSteps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
