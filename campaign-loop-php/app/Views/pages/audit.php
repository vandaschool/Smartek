<?php
/** @var array $rows @var string $q @var int $p */
use App\Core\Auth;
?>
<div class="col gap20">
  <div class="head">
    <h1 class="title">لاگ تغییرات</h1>
    <p class="lead">چه کسی، با چه نقشی، چه چیزی را تغییر داد. برای حافظه‌ای که خودش را اصلاح می‌کند، این لاگ الزامی است.</p>
  </div>
  <form method="get" action="<?= e(url('/audit')) ?>" class="row gap8" style="max-width:520px">
    <input class="inp grow" name="q" value="<?= e($q) ?>" placeholder="جست‌وجو در رویداد، جزئیات یا نام" aria-label="جست‌وجو">
    <button class="btn">جست‌وجو</button>
    <?php if ($q !== ''): ?><a class="btn link" href="<?= e(url('/audit')) ?>">پاک کردن</a><?php endif; ?>
  </form>
  <?php if (!$rows): ?>
    <div class="empty">هنوز رویدادی ثبت نشده.</div>
  <?php else: ?>
    <div class="card flush">
      <?php foreach ($rows as $a): ?>
        <div class="list-row">
          <div style="min-width:110px" class="muted small"><?= e(jdt($a['created_at'])) ?></div>
          <div style="min-width:150px;font-weight:500"><?= e($a['name'] ?? 'سیستم') ?> · <?= e(in_array($a['role'], ['', 'system'], true) ? 'خودکار' : Auth::roleLabel((string) $a['role'])) ?></div>
          <div class="grow"><?= e($a['action']) ?></div>
          <div class="muted"><?= e($a['detail']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="row gap8">
      <?php if ($p > 1): ?><a class="btn sm" href="<?= e(url('/audit', ['q' => $q, 'p' => $p - 1])) ?>">→ جدیدتر</a><?php endif; ?>
      <?php if (count($rows) === 100): ?><a class="btn sm" href="<?= e(url('/audit', ['q' => $q, 'p' => $p + 1])) ?>">قدیمی‌تر ←</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>
