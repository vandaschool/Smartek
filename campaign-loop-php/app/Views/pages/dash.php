<div class="col gap20">
  <div class="head"><h1 class="title">داشبورد ذی‌نفع</h1><p class="lead">همان داده، از زاویه‌ای که برای آن نقش معنا دارد. هیچ عدد جدیدی ساخته نمی‌شود — این تمام کاری است که لایه‌ی ۲ می‌کند.</p></div>
  <div class="row gap8 no-print">
    <?php foreach (['cfo' => 'مدیر مالی', 'ceo' => 'مدیرعامل', 'analyst' => 'تحلیل‌گر داده', 'ops' => 'مسئول کمپین'] as $k => $l): ?>
      <a class="btn md<?= $view === $k ? ' primary' : '' ?>" href="<?= e(url('/dash', ['view' => $k])) ?>"<?= $view === $k ? ' aria-current="page"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
    <button type="button" class="btn md" data-print>چاپ</button>
  </div>
  <?php if (!empty($d['empty'])): ?><div class="empty solid">هنوز کمپین تاریخی ثبت نشده؛ از صفحه‌ی داده‌ها شروع کنید.</div><?php else: ?>
  <div class="grid" style="--min:250px">
    <?php foreach ($d['cards'] as $c): ?>
      <div class="card"><div class="small muted"><?= e($c['label']) ?></div><div class="kpi-v lg"><?= e($c['value']) ?></div><div class="small t3" style="line-height:1.8"><?= e($c['note']) ?></div><div class="src" style="margin-top:auto"><?= e($c['src']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card flush">
    <div class="card-h"><div class="h3">مقایسه با میانه‌ی صنعت</div><div class="xs t3">میانگین CAC شما در هر کانال در برابر میانه‌ی مرچنت‌های هم‌رده<?= array_filter($bench, static fn ($b) => $b['real']) ? '' : ' (داده‌ی نمونه — تا وقتی دست‌کم ۱۰ مرچنت داده دارند)' ?></div></div>
    <?php foreach ($bench as $b): ?>
      <div class="list-row"><div style="min-width:90px;font-weight:600"><?= e($b['ch']) ?></div><div class="grow">شما <?= e($b['mine']) ?></div><div class="grow t3">میانه <?= e($b['med']) ?></div><div style="min-width:110px;font-weight:600;color:<?= $b['better'] ? 'var(--pos)' : 'var(--bad-s)' ?>"><?= e($b['d']) ?> <?= $b['better'] ? 'ارزان‌تر' : 'گران‌تر' ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card p20" style="gap:14px">
    <div class="h3"><?= e($d['title']) ?></div>
    <?php foreach ($d['list'] as $r): ?>
      <div class="row" style="gap:12px;flex-wrap:nowrap"><div style="min-width:130px;font-size:13px;font-weight:500"><?= e($r['label']) ?></div><div class="bar"><i style="width:<?= (int) $r['pct'] ?>%"></i></div><div class="small t2 ltr" style="min-width:96px;text-align:left"><?= e($r['value']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
