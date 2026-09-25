<?php
/** Auth screens: header with logo only (prototype auth view). @var string $content */
use App\Core\Session;

$flash = Session::takeFlash();
?><!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<meta name="base-url" content="<?= e(url('/')) ?>">
<meta name="robots" content="noindex">
<title><?= e(($title ?? 'ورود') . ' · Campaign Loop') ?></title>
<link rel="icon" type="image/png" href="<?= e(asset('img/logo-mark.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<header class="hdr">
  <div class="brand">
    <a href="<?= e(url('/')) ?>"><img src="<?= e(asset('img/logo-horizontal.png')) ?>" alt="Campaign Loop"></a>
    <div class="sep"></div>
    <span class="partner">Smartech</span>
  </div>
</header>
<?= $content ?>
<?php if ($flash): ?>
<div class="toasts" role="status"><?php foreach ($flash as $f): ?><div class="toast <?= e($f['kind']) ?>"><?= e($f['text']) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
