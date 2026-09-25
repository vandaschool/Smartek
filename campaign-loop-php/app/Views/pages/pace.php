<?php
$frac = $pace['frac'];
?>
<div class="col gap20">
  <div class="head">
    <div class="eyebrow">بین ایستگاه ۳ و ۱</div>
    <h1 class="title">پایش حین اجرا</h1>
    <p class="lead">عدد تجمعی تا امروز را وارد کنید. سیستم آن را با سهم روزانه‌ی پیش‌بینی مقایسه می‌کند تا انحراف را قبل از پایان کمپین ببینید.</p>
  </div>
  <div class="grid" style="--min:300px;gap:18px">
    <form method="post" action="<?= e(url('/c/' . $c['id'] . '/pace')) ?>" class="card"><?= csrf_field() ?>
      <div class="row between base"><div class="h3">داده‌ی امروز</div><div class="small t3">روز <?= fa($pc['day']) ?> از <?= fa($days) ?></div></div>
      <div class="bar thin"><i style="width:<?= round($frac * 100) ?>%"></i></div>
      <div class="frow"><label class="k" for="pc-d">روز چندم کمپین</label><input id="pc-d" class="inp num" style="width:130px" name="day" value="<?= e($pc['day']) ?>" inputmode="numeric"></div>
      <div class="frow"><label class="k" for="pc-s">هزینه‌ی تجمعی (تومان)</label><input id="pc-s" class="inp num" style="width:130px" name="spend" value="<?= e((string) (int) $pc['spend']) ?>" inputmode="decimal"></div>
      <div class="frow"><label class="k" for="pc-i">نصب تجمعی</label><input id="pc-i" class="inp num" style="width:130px" name="installs" value="<?= e((string) (int) $pc['installs']) ?>" inputmode="numeric"></div>
      <div class="frow"><label class="k" for="pc-c">خرید تجمعی</label><input id="pc-c" class="inp num" style="width:130px" name="conv" value="<?= e((string) (int) $pc['conv']) ?>" inputmode="numeric"></div>
      <div class="row gap8">
        <button class="btn primary"<?= perm('result') ?>>ثبت پایش و ارسال هشدار</button>
        <?php if ($isDemo): ?><a class="btn" href="<?= e(url('/c/' . $c['id'] . '/pace', ['demo' => 1])) ?>">پرکردن با داده‌ی نمونه</a><?php endif; ?>
      </div>
      <?php if ($prefilled): ?><div class="hint">داده‌ی نمونه در فرم قرار گرفت؛ برای ثبت، «ثبت پایش» را بزنید.</div><?php endif; ?>
    </form>

    <div class="col gap14">
      <div class="card flush">
        <div class="card-h"><div class="h3">برنامه تا امروز در برابر واقعی</div></div>
        <?php foreach ($pace['rows'] as $r): ?>
          <div class="list-row"><div style="min-width:60px;font-weight:600"><?= e($r['k']) ?></div><div class="grow t3">برنامه <?= e($r['exp']) ?></div><div class="grow">واقعی <?= e($r['act']) ?></div><div class="dev <?= e($r['level']) ?>" style="min-width:56px"><?= e($r['dev']) ?></div></div>
        <?php endforeach; ?>
      </div>
      <?php foreach ($pace['alerts'] as $a): ?>
        <div class="callout <?= $a['level'] === 'ok' ? 'ok' : ($a['level'] === 'warn' ? 'warn' : 'bad') ?>"><?= $a['level'] === 'ok' ? '✓' : '⚠' ?> <?= e($a['text']) ?></div>
      <?php endforeach; ?>
      <?php if ($pace['realloc']['has']): ?>
        <div class="card" style="border-color:var(--primary-bd);background:var(--primary-bg)">
          <div class="h4" style="color:var(--primary-t)">پیشنهاد اصلاح حین اجرا</div>
          <div class="small" style="line-height:1.85"><?= e($pace['realloc']['text']) ?></div>
          <?php if (can('plan') && empty($c['current_run_id'])): ?><form method="post" action="<?= e(url('/c/' . $c['id'] . '/realloc')) ?>"><?= csrf_field() ?><button class="btn primary">اعمال جابه‌جایی بودجه</button></form><?php endif; ?>
        </div>
      <?php endif; ?>
      <a class="btn primary md" style="align-self:start" href="<?= e(url('/c/' . $c['id'] . '/verify')) ?>">پایان کمپین — ثبت نتیجه ←</a>
      <?php if ($history): ?>
        <div class="card flush"><div class="card-h"><div class="h4">سابقه‌ی پایش</div></div>
          <?php foreach ($history as $h): ?><div class="list-row small"><span>روز <?= fa($h['day']) ?></span><span class="t3">هزینه <?= e(money($h['spend'])) ?> · نصب <?= e(num($h['installs'])) ?> · خرید <?= e(num($h['conversions'])) ?></span><span class="grow"></span><span class="xs muted"><?= e($h['source'] === 'manual' ? 'دستی' : $h['source']) ?> · <?= e(jdt($h['created_at'])) ?></span></div><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  </div>
</div>
