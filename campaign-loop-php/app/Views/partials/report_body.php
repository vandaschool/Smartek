<?php /** @var array $report */ ?>
<div class="card p30" style="gap:24px">
  <div class="row between start" style="gap:16px;border-bottom:1px solid var(--bd2);padding-bottom:18px">
    <div class="col gap6"><div style="font-size:20px;font-weight:700"><?= e($report['title']) ?></div><div class="small t3"><?= e($report['merchant']) ?></div><div class="small muted">بازه: <?= e($report['window']) ?></div></div>
    <div class="col gap4"><?php foreach ($report['chain'] as $x): ?><div class="row mono xs t3" style="gap:8px"><span class="muted"><?= e($x['k']) ?></span><span><?= e(fa($x['v'])) ?></span></div><?php endforeach; ?></div>
  </div>
  <div class="col gap8"><div class="cap">۱ · تصمیم انسان</div>
    <div style="font-size:15px">دیدگاه انتخاب‌شده: <strong><?= e($report['perspective']) ?></strong></div>
    <div class="t2" style="font-size:13.5px">دلیل ثبت‌شده: <?= e($report['reason']) ?></div>
    <div class="t3" style="font-size:13.5px"><?= e($report['goal']) ?></div></div>
  <div class="col gap10"><div class="cap">۲ · تخصیص و شاهدش</div>
    <?php foreach ($report['alloc'] as $a): ?><div class="row base" style="gap:14px;border-top:1px solid var(--bd3);padding-top:9px"><div style="font-size:13.5px;font-weight:600;min-width:170px"><?= e($a['label']) ?></div><div class="small t2"><?= e($a['budget']) ?> ت · <?= e($a['share']) ?></div><div class="small t2">CAC <?= e($a['cac']) ?></div><div class="src"><?= e($a['evidence']) ?></div></div><?php endforeach; ?></div>
  <div class="col gap10"><div class="cap">۳ · پیش‌بینی با بازه</div>
    <div class="grid" style="--min:170px;gap:14px"><?php foreach ($report['forecast'] as $f): ?><div class="col" style="gap:3px"><div class="kpi-l"><?= e($f['k']) ?></div><div style="font-size:18px;font-weight:700"><?= e($f['v']) ?></div><div class="xs muted"><?= e($f['band']) ?></div></div><?php endforeach; ?></div>
    <?php foreach ($report['verdicts'] as $v): ?><div class="small t2" style="background:var(--soft);border-radius:6px;padding:10px 13px;line-height:1.85"><?= e($v) ?></div><?php endforeach; ?></div>
  <div class="col gap10"><div class="cap">۴ · نتیجه‌ی واقعی و انتساب علت</div>
    <?php foreach ($report['result'] as $r): ?><div class="row" style="gap:16px;border-top:1px solid var(--bd3);padding-top:9px"><div style="font-size:13.5px;font-weight:600;min-width:74px"><?= e($r['k']) ?></div><div class="small muted">پیش‌بینی <?= e($r['p']) ?></div><div class="small">واقعی <?= e($r['a']) ?></div><div class="small" style="font-weight:600"><?= e($r['d']) ?></div></div><?php endforeach; ?>
    <div style="font-size:14.5px;font-weight:700;margin-top:4px">علت: <?= e($report['cause']) ?></div>
    <div class="t2" style="font-size:13.5px;line-height:1.85"><?= e($report['causeText']) ?></div>
    <?php if ($report['lift']): ?><div class="small t3">اثر افزایشی: <?= e($report['lift']) ?></div><?php endif; ?></div>
  <div class="col gap8" style="border-top:1px solid var(--bd2);padding-top:18px"><div class="cap">۵ · اثر روی حافظه‌ی سیستم</div>
    <div class="t2" style="font-size:13.5px;line-height:1.85"><?= e($report['calib']) ?></div>
    <div class="xs muted" style="line-height:1.9;margin-top:8px">همه‌ی اعداد این گزارش از جدول نرخ‌های ثبت‌شده آمده‌اند. بازه‌ها برون‌یابی نوسان تاریخی همان کانال‌اند، نه خروجی یک مدل.</div></div>
</div>
