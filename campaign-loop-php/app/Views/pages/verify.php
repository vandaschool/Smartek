<?php
$rows = $sim['r']['rows'];
$vi = $run['in'] ?? [];
$sel = static fn (string $k, string $v, string $def) => (($vi[$k] ?? $def) === $v) ? ' selected' : '';
$closed = $c['status'] === 'closed';
?>
<div class="col gap20">
  <div class="head">
    <div class="eyebrow">ایستگاه ۱ — VERIFIER</div>
    <h1 class="title">راستی‌آزمایی و انتساب علت</h1>
    <p class="lead">پیش‌بینی از ایستگاه ۳ (<?= e(fa($sim['code'])) ?>) آمده و بازه هم در کارت دیده می‌شود. نتیجه‌ی واقعی را برای هر ردیف تخصیص وارد کنید. مسیر دستی مستقل از دو ایستگاه دیگر هم در <a href="<?= e(url('/verify')) ?>">راستی‌آزمایی دستی</a> در دسترس است.</p>
  </div>
  <?php if ($draft): ?>
    <div class="callout info row"><span class="grow">پیش‌نویس نتیجه از داده‌ی همگام‌شده‌ی ادتریس/اینترک ساخته شد (<?= e(fa($draft['code'])) ?> · علت پیش‌نمایش: <?= e($draft['ver']['cause'] ?? '—') ?>). بررسی و تأیید کنید.</span>
      <?php if (can('result')): ?><form method="post" action="<?= e(url('/runs/' . $draft['id'] . '/confirm')) ?>" class="inline"><?= csrf_field() ?><button class="btn sm primary">تأیید نتیجه</button></form><?php endif; ?></div>
  <?php endif; ?>
  <div class="grid" style="--min:330px;gap:18px">
    <form method="post" action="<?= e(url('/c/' . $c['id'] . '/verify')) ?>" class="card p20" style="gap:14px"><?= csrf_field() ?>
      <div class="row between"><div class="h2">ورودی</div><?php if ($isDemo && !$closed): ?><a class="btn sm teal-soft" href="<?= e(url('/c/' . $c['id'] . '/verify', ['demo' => 1])) ?>">پرکردن نتیجه‌ی نمونه</a><?php endif; ?></div>
      <div class="frow"><label class="k">نام کمپین</label><input class="inp sm" name="name" value="<?= e($vi['name'] ?? $c['name']) ?>"></div>
      <div class="col gap8">
        <div class="small t3">نتیجه‌ی واقعی هر ردیف (پیش‌بینی داخل پرانتز)</div>
        <?php foreach ($rows as $i => $r): $pf = $prefill[$i] ?? ($vi['rows'][$i] ?? null); ?>
          <div class="card soft" style="padding:10px 12px;gap:8px;border-radius:8px">
            <div class="small" style="font-weight:600"><?= e($r['label']) ?> <span class="muted xs">· سهم <?= e(pct($r['share'], 0)) ?></span></div>
            <div class="grid" style="--min:110px;gap:8px">
              <label class="field"><span class="xs t3">هزینه (<?= e(money($r['budget'])) ?>)</span><input class="inp sm num" name="ab[<?= $i ?>]" value="<?= e($pf ? (string) round((float) $pf['ab']) : '') ?>" inputmode="decimal" required></label>
              <label class="field"><span class="xs t3">نصب (<?= e(num($r['installs'])) ?>)</span><input class="inp sm num" name="ai[<?= $i ?>]" value="<?= e($pf ? (string) round((float) $pf['ai']) : '') ?>" inputmode="numeric" required></label>
              <label class="field"><span class="xs t3">خرید (<?= e(num($r['conv'])) ?>)</span><input class="inp sm num" name="ac[<?= $i ?>]" value="<?= e($pf ? (string) round((float) $pf['ac']) : '') ?>" inputmode="numeric" required></label>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <?php include APP_ROOT . '/app/Views/partials/verify_flags.php'; ?>
      <?php if (!$closed): ?><button class="btn primary md"<?= perm('result') ?>>انتساب علت</button><?php else: ?><div class="callout soft small">این کمپین بسته شده است.</div><?php endif; ?>
    </form>
    <?php $plan = $plan; include APP_ROOT . '/app/Views/partials/verify_card.php'; ?>
  </div>
</div>
