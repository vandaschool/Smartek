<div class="col gap20">
  <div class="head"><h1 class="title">دفترچه‌ی دیدگاه‌ها</h1><p class="lead">هر کمپین بسته‌شده با دیدگاه انتخاب‌شده و دلیلش ثبت می‌شود. این ستون هیچ‌جای دیگری وجود ندارد — و بعد از بیست کمپین می‌گوید کدام دیدگاه بیشتر درست از آب درآمده.</p></div>
  <?php if ($celebrate): ?>
    <div class="card dark p22 row" style="gap:18px">
      <img src="<?= e(asset('img/logo-mark.png')) ?>" alt="" style="width:64px;height:64px;background:#fff;border-radius:12px;padding:6px">
      <div class="col grow gap6" style="min-width:240px"><div style="font-size:18px;font-weight:700">اولین حلقه بسته شد.</div><div style="font-size:13.5px;color:#d7e2e8;line-height:1.9">طرح، پیش‌بینی، نتیجه و علت حالا به هم وصل‌اند. کمپین بعدی با نرخ‌های اصلاح‌شده طراحی می‌شود — هرچه حلقه‌ها بیشتر شوند، این دفترچه دقیق‌تر می‌گوید کدام دیدگاه برای شما درست است.</div></div>
      <form method="post" action="<?= e(url('/campaigns/new')) ?>"><?= csrf_field() ?><button class="btn teal">حلقه‌ی بعدی ←</button></form>
    </div>
  <?php endif; ?>
  <div class="grid" style="--min:240px">
    <div class="card blue"><div class="small muted">بهترین کیفیت تصمیم تا امروز</div><div style="font-size:22px;font-weight:700"><?= e($dq['best']) ?></div><div class="small t3" style="line-height:1.8">بیشترین سهم تصمیم درست (POAS نامنفی و انحراف قابل انتساب)</div></div>
    <div class="card"><div class="small muted">کمپین‌های بسته‌شده</div><div style="font-size:22px;font-weight:700"><?= fa(count($rows)) ?></div><div class="small t3">شامل داده‌ی دموی اولیه</div></div>
    <div class="card"><div class="small muted">کمپین بعدی</div><div class="small t2" style="line-height:1.8">با نرخ‌های کالیبره‌شده، همان طرح عدد متفاوتی می‌دهد.</div>
      <form method="post" action="<?= e(url('/campaigns/new')) ?>" style="margin-top:auto"><?= csrf_field() ?><button class="btn teal-o"<?= perm('plan') ?>>شروع دور بعدی حلقه ←</button></form></div>
  </div>
  <div class="card p20" style="gap:14px">
    <div class="row base gap10"><div class="h3">کیفیت تصمیم هر دیدگاه</div><div class="grow xs t3">تصمیم درست = POAS نامنفی و انحراف قابل انتساب. فقط دیدگاه‌های انتخاب‌شده سنجیده می‌شوند (سوگیری انتخاب).</div><a class="btn sm" href="<?= e(url('/log.csv')) ?>">خروجی CSV</a></div>
    <?php if (!$dq['rows']): ?><div class="empty solid">بعد از اولین حلقه، کیفیت تصمیم هر دیدگاه اینجا دیده می‌شود.</div><?php endif; ?>
    <?php foreach ($dq['rows'] as $r): ?>
      <div class="row" style="gap:12px;flex-wrap:nowrap"><div style="min-width:150px;font-size:13px;font-weight:500"><?= e($r['label']) ?></div><div class="bar"><i style="width:<?= (int) $r['pct'] ?>%"></i></div><div class="xs t3" style="min-width:210px;text-align:left"><?= e($r['value']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="col gap12">
    <?php foreach ($rows as $r): ?>
      <div class="card row start" style="gap:16px;padding:16px 18px">
        <div class="col gap4 grow" style="min-width:200px"><div style="font-size:14px;font-weight:600"><?= e($r['name']) ?></div><div class="src"><?= e($r['code']) ?> · <?= $r['seeded'] ? 'داده‌ی دمو' : 'این فضای کاری' ?></div></div>
        <div class="col gap4" style="min-width:210px;flex:1.4"><div style="font-size:13px;font-weight:600;color:var(--teal)"><?= e($r['perspective']) ?></div><div class="small t3" style="line-height:1.75"><?= e($r['reason']) ?></div></div>
        <div class="col gap4" style="min-width:150px"><div class="small t2">علت: <?= e($r['cause']) ?></div><div class="xs" style="color:<?= $r['calibrated'] ? 'var(--teal)' : 'var(--muted)' ?>">کالیبراسیون <?= $r['calibrated'] ? 'اعمال شد' : 'اعمال نشد' ?></div></div>
        <div class="col gap4" style="min-width:130px"><div class="xs muted">CAC طرح <?= e(money($r['planned_cac'])) ?></div><div class="small t2">واقعی <?= e(money($r['actual_cac'])) ?> (<?= $r['planned_cac'] > 0 ? e(spct($r['actual_cac'] / $r['planned_cac'] - 1)) : '—' ?>)</div>
          <?php if ($r['actual_poas'] !== null): ?><div class="xs t3">POAS واقعی <?= e(spct($r['actual_poas'])) ?><?= $r['goal_hit'] !== null ? ' · هدف ' . ($r['goal_hit'] ? 'محقق شد' : 'محقق نشد') : '' ?></div><?php endif; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
