<?php
$base = '/c/' . $c['id'] . '/insights';
$selIns = null;
$secIns = null;
foreach ($ins as $i) {
    if ($i['id'] === $sel) {
        $selIns = $i;
    }
    if ($i['id'] === $sec) {
        $secIns = $i;
    }
}
$locked = !empty($c['current_run_id']);
?>
<div class="col gap20">
  <div class="head">
    <div class="eyebrow">ایستگاه ۲ — DESIGNER · لایه‌ی ۲</div>
    <h1 class="title">ده اینسایت، از ده دیدگاه</h1>
    <p class="lead" style="max-width:68ch">هر «دیدگاه» یک تابع هدف متفاوت روی همان داده است. مرتب‌سازی بر اساس تناسب با هدف کسب‌وکار «<?= e($profile['goal'] ?: '—') ?>» و ریسک‌پذیری «<?= e($c['risk']) ?>» است، ولی هیچ اینسایتی فیلتر نمی‌شود — تنوع دیدگاه خودش ارزش است.</p>
  </div>
  <?php if ($plan): ?><div class="callout info">طرح فعلی: <b><?= e(fa($plan['code'])) ?></b> · نسخه‌ی <?= fa($plan['version']) ?> · <?= e($plan['perspective']) ?><?= $locked ? ' — نتیجه ثبت شده و طرح قفل است.' : ' — ثبت دوباره، نسخه‌ی تازه می‌سازد.' ?></div><?php endif; ?>
  <?php if (!$ins): ?>
    <div class="empty">هنوز اینسایتی تولید نشده. <a class="btn link" href="<?= e(url('/c/' . $c['id'] . '/design')) ?>" style="color:var(--teal)">برو به فرم طراحی</a></div>
  <?php endif; ?>
  <?php foreach ($ins as $k => $i):
      $isSel = $i['id'] === $sel;
      $isSec = $i['id'] === $sec;
      $bench = (bool) array_filter($i['alloc'], static fn ($a) => ($a['source'] ?? '') === 'benchmark');
      $lowN = (int) ($i['alloc'][0]['n'] ?? 0) < 5;
  ?>
    <div class="card flush" id="ins-<?= e($i['id']) ?>">
      <div class="ins-h">
        <span class="num-sq"><?= fa($k + 1) ?></span>
        <div class="h3">از دید <?= e($i['perspective']) ?></div>
        <div class="obj"><?= e($i['objective']) ?></div>
        <?php if ($bench): ?><span class="badge sm warn">بر پایه‌ی مرجع</span><?php endif; ?>
        <?php if ($lowN): ?><span class="badge sm lown">نمونه‌ی کم</span><?php endif; ?>
        <div class="grow"></div>
        <?php if ($isSel): ?><span class="badge teal">انتخاب‌شده</span><?php endif; ?>
        <?php if ($isSec): ?><span class="badge teal">ترکیب‌شده</span><?php endif; ?>
      </div>
      <div class="ins-b">
        <p class="ins-claim"><?= e($i['claim']) ?></p>
        <div class="ins-fields">
          <div><div class="k">پیشنهاد</div><div class="v"><?= e($i['proposal']) ?></div></div>
          <div><div class="k teal">شاهد</div><div class="v"><?= e($i['evidence']) ?></div></div>
          <div><div class="k warn">ریسک</div><div class="v"><?= e($i['risk']) ?></div></div>
          <div><div class="k">معیار موفقیت</div><div class="v"><?= e($i['successMetric']) ?></div></div>
        </div>
        <?php if ($aiOn): ?><div class="ai-box" hidden data-ai-src="<?= e(url('/ai/insight/' . $c['id'] . '/' . $i['id'])) ?>"></div><?php endif; ?>
        <div class="row gap8"><span class="xs muted">تخصیص بودجه:</span>
          <?php foreach ($i['alloc'] as $a): ?><span class="alloc-chip"><?= e($a['ch'] . ' · ' . $a['seg']) ?> · <?= e(pct($a['share'], 0)) ?></span><?php endforeach; ?></div>
        <div class="row gap10" style="border-top:1px solid var(--bd2);padding-top:14px">
          <?php if ($isSel): ?>
            <span class="btn primary">✓ مبنای کمپین</span>
          <?php elseif (!$locked): ?>
            <a class="btn" style="font-weight:600" href="<?= e(url($base, ['sel' => $i['id'], 'sec' => $sec === $i['id'] ? '' : $sec, 'mix' => $mix]) . '#decide') ?>">انتخاب این اینسایت</a>
            <?php if ($sel): ?><a class="btn warn-o" href="<?= e(url($base, ['sel' => $sel, 'sec' => $i['id'], 'mix' => $mix]) . '#decide') ?>">ترکیب با انتخاب فعلی</a><?php endif; ?>
          <?php endif; ?>
          <div class="grow"></div>
          <span class="xs muted">CAC طرح: <?= e(money($i['exp']['cac'])) ?> ت · خرید مورد انتظار: <?= e(num($i['exp']['conv'])) ?></span>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <?php if ($selIns && !$locked): ?>
    <form method="post" action="<?= e(url('/c/' . $c['id'] . '/plan')) ?>" class="card blue p20" style="gap:16px" id="decide"><?= csrf_field() ?>
      <input type="hidden" name="sel" value="<?= e($sel) ?>"><input type="hidden" name="sec" value="<?= e($sec) ?>">
      <div class="h2">نقش انسان — انتخاب و دلیلش</div>
      <div class="small t3" style="line-height:1.85">مبنای فعلی: <strong style="color:var(--ink)"><?= e($selIns['perspective']) ?></strong><?= $secIns ? ' + ' . e($secIns['perspective']) . ' (ترکیب)' : '' ?></div>
      <?php if ($secIns): ?>
        <div class="col gap8"><label class="lbl-s">سهم دیدگاه اول از بودجه: <span id="mixv"></span></label>
          <input type="range" class="teal" min="10" max="90" step="5" name="mix" value="<?= (int) $mix ?>" data-label="#mixv"></div>
      <?php else: ?><input type="hidden" name="mix" value="100"><?php endif; ?>
      <div class="col gap8"><label class="lbl-s" for="reason">چرا این دیدگاه؟ (ثبت می‌شود — بعد از بیست کمپین همین ستون می‌گوید مشاور واقعی چه زاویه‌ای دارد)</label>
        <textarea id="reason" name="reason" rows="2" class="inp" minlength="10" required data-reason-hint="#reason-hint" data-hint-url="<?= e(url('/ai/reason-hint')) ?>" data-perspective="<?= e($selIns['perspective']) ?>"><?= e($reason) ?></textarea>
        <div class="ai-box" id="reason-hint" hidden style="font-size:12.5px"></div></div>
      <?php if ($error): ?><div class="callout bad"><?= e($error) ?></div><?php endif; ?>
      <div class="row gap10">
        <button class="btn primary md"<?= perm('plan') ?>>ثبت طرح و رفتن به شبیه‌سازی ←</button>
        <?php if ($secIns): ?><a class="btn md" style="background:none" href="<?= e(url($base, ['sel' => $sel, 'sec' => ''])) ?>">حذف ترکیب</a><?php endif; ?>
      </div>
    </form>
  <?php endif; ?>
</div>
