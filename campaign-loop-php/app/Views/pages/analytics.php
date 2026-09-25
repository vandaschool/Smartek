<?php
/** @var array $rows @var int $north @var int $northMonth @var int $events @var array|null $ai */
$mx = max(1, ...array_map(static fn ($r) => $r['n'], $rows));
?>
<div class="col gap18">
  <div class="head">
    <h1 class="title">آنالیتیکس محصول</h1>
    <p class="lead">خود محصول چقدر استفاده می‌شود. شاخص اصلی: حلقه‌های بسته‌شده. رویدادها همان‌هایی‌اند که بک‌اند ثبت می‌کند.</p>
  </div>
  <div class="grid" style="--min:220px;gap:14px">
    <div class="card dark" style="gap:6px"><div style="font-size:12.5px;color:#9fd3d3">شاخص اصلی · حلقه‌های بسته‌شده</div><div style="font-size:30px;font-weight:700"><?= fa($north) ?></div><div class="xs" style="color:#d7e2e8">در این فضای کاری (بدون داده‌ی دمو) · این ماه: <?= fa($northMonth) ?></div><?= src('perspective_log.count') ?></div>
    <div class="card" style="gap:6px"><div class="small muted">رویدادهای ثبت‌شده</div><div style="font-size:30px;font-weight:700"><?= fa($events) ?></div><div class="xs muted ltr" style="text-align:right">plan_created · pace_logged · run_verified · …</div></div>
    <div class="card" style="gap:6px"><div class="small muted">فراخوانی هوش مصنوعی این ماه</div><div style="font-size:30px;font-weight:700"><?= fa((int) ($ai['n'] ?? 0)) ?></div>
      <div class="xs muted">موفق <?= fa((int) ($ai['ok'] ?? 0)) ?> · کش <?= fa((int) ($ai['cached'] ?? 0)) ?> · جایگزین قالبی <?= fa((int) ($ai['fb'] ?? 0)) ?> · ردشده در فایروال عدد <?= fa((int) ($ai['viol'] ?? 0)) ?></div><?= src('llm_calls') ?></div>
  </div>
  <div class="card">
    <div class="h2">قیف استفاده</div>
    <?php foreach ($rows as $r): ?>
      <div class="row" style="gap:12px;flex-wrap:nowrap">
        <div style="min-width:120px;font-size:13px"><?= e($r['label']) ?></div>
        <div class="bar"><div style="height:100%;width:<?= max((int) round($r['n'] / $mx * 100), 3) ?>%;background:var(--primary)"></div></div>
        <div style="min-width:40px;font-size:13px;font-weight:600"><?= fa($r['n']) ?></div>
        <div class="mono xs muted" style="min-width:130px"><?= e($r['key']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
