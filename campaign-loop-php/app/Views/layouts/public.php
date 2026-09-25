<?php
/** @var string $content */
use App\Core\Auth;
use App\Core\Session;

$flash = Session::takeFlash();
$title = $title ?? 'Campaign Loop — حلقه‌ی کمپین اسمارتک';
$description = $description ?? 'طرح کمپین از ده دیدگاه، پیش‌بینی با بازه‌ی تجربی، راستی‌آزمایی نتیجه و اصلاح خودکار نرخ‌ها — محصول اسمارتک.';
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= e(\App\Core\Url::base() . '/assets/img/logo-tagline.png') ?>">
<link rel="icon" type="image/png" href="<?= e(asset('img/logo-mark.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="pub-hdr no-print">
  <a href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-horizontal.png')) ?>" alt="Campaign Loop"></a>
  <nav>
    <a href="<?= e(url('/')) ?>#how">چطور کار می‌کند</a>
    <a href="<?= e(url('/')) ?>#pricing">قیمت‌ها</a>
    <a href="<?= e(url('/')) ?>#demo">درخواست دمو</a>
  </nav>
  <?php if (Auth::check()): ?>
    <a class="btn primary sm" href="<?= e(url('/campaigns')) ?>">ورود به پنل</a>
  <?php else: ?>
    <a class="btn sm" href="<?= e(url('/login')) ?>">ورود</a>
    <a class="btn primary sm" href="<?= e(url('/signup')) ?>">ثبت‌نام رایگان</a>
  <?php endif; ?>
</header>
<?= $content ?>
<footer class="pub-foot no-print">
  <img src="<?= e(asset('img/logo-tagline.png')) ?>" alt="Campaign Loop">
  <div class="row gap16">
    <a href="<?= e(url('/legal/terms')) ?>">شرایط استفاده</a>
    <a href="<?= e(url('/legal/privacy')) ?>">حریم خصوصی</a>
    <a href="<?= e(url('/legal/data')) ?>">پردازش داده</a>
  </div>
  <div>© <?= fa(\App\Core\Jalali::year()) ?> اسمارتک</div>
</footer>
<?php if ($flash): ?>
<div class="toasts" role="status"><?php foreach ($flash as $f): ?><div class="toast <?= e($f['kind']) ?>"><?= e($f['text']) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
