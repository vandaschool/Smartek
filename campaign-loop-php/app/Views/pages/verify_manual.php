<?php
use App\Engine\Engine;

$opt = static fn (array $opts, string $cur) => implode('', array_map(static fn ($o) => '<option' . ((string) $o === (string) $cur ? ' selected' : '') . '>' . e($o) . '</option>', $opts));
$plan = null;
$sim = null;
?>
<div class="col gap20">
  <div class="head">
    <div class="eyebrow">ایستگاه ۱ — VERIFIER</div>
    <h1 class="title">راستی‌آزمایی و انتساب علت</h1>
    <p class="lead">مسیر دستی مستقل از دو ایستگاه دیگر کار می‌کند. اگر پیش‌بینی از ایستگاه ۳ آمده باشد، بازه هم در کارت دیده می‌شود.</p>
  </div>
  <div class="card no-print" style="gap:14px">
    <div class="h4">داده‌ی نمونه — هر پنج شاخه‌ی درخت تصمیم</div>
    <div class="row gap8"><?php foreach (Engine::presets() as $i => $p): ?><a class="btn" href="<?= e(url('/verify', ['preset' => $i])) ?>"><?= e($p['label']) ?></a><?php endforeach; ?></div>
  </div>
  <div class="grid" style="--min:330px;gap:18px">
    <form method="post" action="<?= e(url('/verify')) ?>" class="card p20" style="gap:14px"><?= csrf_field() ?>
      <div class="h2">ورودی</div>
      <div class="frow"><label class="k">نام کمپین</label><input class="inp sm" name="name" value="<?= e($vi['name']) ?>" required minlength="3"></div>
      <div class="frow"><label class="k">کانال</label><select class="inp sm" name="ch"><?= $opt(Engine::CHANNELS, $vi['ch']) ?></select></div>
      <div class="frow"><label class="k">سگمنت</label><select class="inp sm" name="seg"><?= $opt(Engine::SEGMENTS, $vi['seg']) ?></select></div>
      <div class="frow"><label class="k">از تاریخ (۱۴۰۵/۰۷/۰۱)</label><input class="inp sm ltr" name="from" value="<?= e($vi['from']) ?>"></div>
      <div class="frow"><label class="k">تا تاریخ</label><input class="inp sm ltr" name="to" value="<?= e($vi['to']) ?>"></div>
      <div class="frow"><label class="k">بودجه‌ی طرح</label><input class="inp sm num" name="pb" value="<?= e($vi['pb']) ?>" inputmode="decimal"></div>
      <div class="frow"><label class="k">نصب مورد انتظار</label><input class="inp sm num" name="pi" value="<?= e($vi['pi']) ?>" inputmode="numeric"></div>
      <div class="frow"><label class="k">خرید مورد انتظار</label><input class="inp sm num" name="pc" value="<?= e($vi['pc']) ?>" inputmode="numeric"></div>
      <div class="frow"><label class="k">منبع پیش‌بینی</label><select class="inp sm" name="src"><?= $opt(Engine::SOURCES, $vi['src']) ?></select></div>
      <div class="frow"><label class="k">هزینه‌ی واقعی</label><input class="inp sm num" name="ab" value="<?= e($vi['ab']) ?>" inputmode="decimal"></div>
      <div class="frow"><label class="k">نصب واقعی</label><input class="inp sm num" name="ai" value="<?= e($vi['ai']) ?>" inputmode="numeric"></div>
      <div class="frow"><label class="k">خرید واقعی</label><input class="inp sm num" name="ac" value="<?= e($vi['ac']) ?>" inputmode="numeric"></div>
      <?php include APP_ROOT . '/app/Views/partials/verify_flags.php'; ?>
      <button class="btn primary md"<?= perm('result') ?>>انتساب علت</button>
    </form>
    <?php include APP_ROOT . '/app/Views/partials/verify_card.php'; ?>
  </div>
</div>
