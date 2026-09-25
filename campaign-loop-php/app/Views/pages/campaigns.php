<?php
use App\Services\Loop;

$tierLabel = ['trial' => 'آزمایشی', 'growth' => 'رشد', 'enterprise' => 'سازمانی'][$tier] ?? $tier;
$openHref = static function (array $c): string {
    $st = Loop::status($c);
    $base = '/c/' . $c['id'];
    if ($st === 'draft') {
        return url($base . ($c['insights'] ? '/insights' : '/design'));
    }
    if ($st === 'awaiting_result') {
        return url($base . '/verify');
    }
    if (in_array($st, ['closed', 'archived', 'stopped'], true)) {
        return url($base . '/report');
    }
    return url($base . ($c['current_run_id'] ? '/verify' : '/sim'));
};
?>
<div class="col gap20">
  <div class="row end gap14">
    <div class="head grow" style="min-width:260px">
      <h1 class="title">کمپین‌ها</h1>
      <p class="lead">هر کمپین شناسه‌ی یکتای خودش را دارد و زنجیره‌ی plan → sim → run → calibration برای هرکدام جداست.</p>
    </div>
    <?php if ($overLimit): ?><div class="callout warn small">سقف ۳ کمپین پلن آزمایشی پر شده. <a class="btn link" href="<?= e(url('/billing')) ?>">ارتقای پلن</a></div><?php endif; ?>
    <form method="post" action="<?= e(url('/campaigns/new')) ?>"><?= csrf_field() ?><button class="btn primary md"<?= perm('plan') ?>>کمپین جدید +</button></form>
  </div>
  <?php if ($need): ?><div class="callout info">برای این صفحه ابتدا یک کمپین بسازید یا از فهرست زیر باز کنید. مسیر دستی راستی‌آزمایی بدون کمپین هم کار می‌کند: <a href="<?= e(url('/verify')) ?>">راستی‌آزمایی دستی</a>.</div><?php endif; ?>
  <div class="small t3">پلن <?= e($tierLabel) ?> · مصرف این ماه: <?= fa($usage) ?><?= $tier === 'trial' ? ' از ۳ کمپین' : ' کمپین' ?></div>
  <?php if (!$rows): ?>
    <div class="empty solid">هنوز کمپینی طراحی نشده. با «کمپین جدید» شروع کنید.</div>
  <?php else: ?>
    <div class="card flush">
      <?php foreach ($rows as $c): $st = Loop::status($c); ?>
        <div class="list-row" style="padding:14px 18px;gap:16px">
          <div class="idlbl" style="min-width:120px"><?= e($c['plan_code'] ? fa($c['plan_code']) : '—') ?></div>
          <div class="col grow" style="gap:2px;min-width:160px">
            <a href="<?= e($openHref($c)) ?>" style="font-size:14px;font-weight:600;color:var(--ink)"><?= e($c['name']) ?></a>
            <div class="small t3">دیدگاه: <?= e($c['perspective'] ?: '—') ?> · بودجه <?= e(money($c['budget'])) ?><?= $c['version'] > 1 ? ' · نسخه‌ی ' . fa($c['version']) : '' ?></div>
          </div>
          <?php
          $cls = ['live' => 'teal', 'awaiting_result' => 'blue', 'closed' => 'gray', 'stopped' => 'warn', 'archived' => 'soft', 'draft' => 'soft'][$st] ?? 'soft';
          ?>
          <span class="badge <?= $cls ?>"><?= e(Loop::STATUS[$st] ?? $st) ?></span>
          <?php if ($st === 'awaiting_result'): ?><a class="btn sm outline" href="<?= e(url('/c/' . $c['id'] . '/verify')) ?>">ثبت نتیجه</a><?php endif; ?>
          <a class="btn sm" href="<?= e($openHref($c)) ?>">باز کردن</a>
          <?php if (can('plan') && !in_array($st, ['live', 'archived', 'draft', 'awaiting_result'], true)): ?>
            <form method="post" action="<?= e(url('/c/' . $c['id'] . '/archive')) ?>" class="inline"><?= csrf_field() ?><button class="btn link" style="color:var(--t3);font-weight:400">بایگانی</button></form>
          <?php endif; ?>
          <?php if (can('plan') && $st === 'draft'): ?>
            <form method="post" action="<?= e(url('/c/' . $c['id'] . '/delete')) ?>" class="inline" data-confirm="این پیش‌نویس حذف شود؟"><?= csrf_field() ?><button class="btn link danger">حذف</button></form>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
