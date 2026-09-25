<?php
use App\Engine\Engine;

$band = static function (float $low, float $high, float $exp): array {
    $top = $high ?: 1;
    return ['low' => round($low / $top * 100), 'w' => max(round(($high - $low) / $top * 100), 2), 'exp' => round($exp / $top * 100)];
};
$funnel = [
    ['نصب', num($s['installs']), num($s['iLow']), num($s['iHigh']), $band($s['iLow'], $s['iHigh'], $s['installs'])],
    ['خرید', num($s['conv']), num($s['cLow']), num($s['cHigh']), $band($s['cLow'], $s['cHigh'], $s['conv'])],
    ['درآمد', money($s['rev']), money($s['rLow']), money($s['rHigh']), $band($s['rLow'], $s['rHigh'], $s['rev'])],
];
$kpis = [
    ['CAC پیش‌بینی‌شده', money($s['cac']) . ' ت'], ['POAS (سود ÷ هزینه)', spct($s['poas'])], ['ROAS درآمدی', dec($s['roas'], 2) . '×'],
    ['دوره‌ی بازگشت CAC', dec($s['payback'], 1) . ' ماه'], ['LTV ÷ CAC', $s['ltvCac'] ? dec($s['ltvCac'], 1) . '×' : '—'],
    ['کاربر ماندگار روز ۳۰', num($s['ret30'])], ['نصب تقلبی حذف‌شده', num($s['fraudInst'])],
    ['پوشش هدف', $s['coverage'] ? pct($s['coverage'], 0) : '—'], ['بودجه‌ی تخصیص‌یافته', money($s['budget']) . ' ت'],
];
$locked = !empty($c['current_run_id']);
$simCode = $saved['code'] ?? '';
?>
<div class="col gap18">
  <div class="head">
    <div class="eyebrow">ایستگاه ۳ — SIMULATOR</div>
    <h1 class="title">قیف پیش‌بینی با بازه</h1>
  </div>

  <?php if ($stale): ?>
    <div class="callout info row"><span class="grow">نرخ‌های این طرح از زمان ثبت تغییر کرده‌اند (مثلاً پس از کالیبراسیون). پیش‌بینی زیر با نرخ‌های لحظه‌ی ثبت طرح است.</span>
      <?php if (!$locked && can('plan')): ?><form method="post" action="<?= e(url('/c/' . $c['id'] . '/refresh-rates')) ?>" class="inline"><?= csrf_field() ?><button class="btn sm primary">پیش‌بینی دوباره با نرخ‌های فعلی</button></form><?php endif; ?></div>
  <?php endif; ?>

  <form method="post" action="<?= e(url('/c/' . $c['id'] . '/sim')) ?>" class="card row" style="gap:22px;align-items:end" data-live-submit><?= csrf_field() ?>
    <div class="field"><label>پهنای بازه‌ی تجربی</label><select name="band" class="inp" style="width:auto"><?php foreach (Engine::BAND as $k => $v): ?><option<?= $c['sim_band'] === $k ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></div>
    <div class="field"><label>عامل بیرونی</label><select name="ext" class="inp" style="width:auto"><?php foreach (Engine::EXT as $k => $v): ?><option<?= $c['sim_ext'] === $k ? ' selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?></select></div>
    <div class="callout ok small" style="color:var(--warn-s)">ضریب عامل بیرونی: <strong>فرض اثبات‌نشده</strong></div>
    <?php if ($occ['lift']): ?><div class="xs t3">مناسبت: <?= e($occ['k']) ?> (اثر خرید <?= e(spct($occ['lift'])) ?> / هزینه <?= e(spct($occ['cpi'])) ?>)</div><?php endif; ?>
    <div class="grow"></div>
    <div class="idlbl">plan_id: <?= e(fa($plan['code'])) ?> · v<?= fa($plan['version']) ?><?= $simCode ? ' · sim_id: ' . e(fa($simCode)) : '' ?></div>
    <noscript><button class="btn sm">به‌روزرسانی</button></noscript>
  </form>

  <div class="card p22" style="gap:20px">
    <div class="h2">قیف — مورد انتظار و بازه</div>
    <?php foreach ($funnel as [$lbl, $exp, $lo, $hi, $b]): ?>
      <div class="col gap8">
        <div class="row base gap10"><div class="h4" style="min-width:74px"><?= e($lbl) ?></div><div class="kpi-v"><?= e($exp) ?></div><div class="small muted">بازه: <?= e($lo) ?> تا <?= e($hi) ?></div></div>
        <div class="band"><div class="rng" style="right:<?= $b['low'] ?>%;width:<?= $b['w'] ?>%"></div><div class="exp" style="right:<?= $b['exp'] ?>%"></div></div>
      </div>
    <?php endforeach; ?>
    <div class="grid" style="--min:160px;gap:14px;border-top:1px solid var(--bd2);padding-top:18px">
      <?php foreach ($kpis as [$l, $v]): ?><div class="col gap4"><div class="kpi-l"><?= e($l) ?></div><div class="kpi-v"><?= e($v) ?></div></div><?php endforeach; ?>
    </div>
  </div>

  <div class="grid" style="--min:300px">
    <?php foreach ([$s['risk'], $s['prof']] as $v): ?>
      <div class="verdict <?= e($v['level']) ?>"><div class="k"><?= e($v['kind']) ?></div><div class="v"><?= $v['level'] !== 'ok' ? '⚠ ' : '' ?><?= e($v['text']) ?></div></div>
    <?php endforeach; ?>
  </div>

  <div class="callout soft" style="border-radius:12px;padding:16px 18px;color:var(--t2)">این پیش‌بینی از نرخ‌های تاریخی ثبت‌شده ساخته شده، نه از یک مدل. بازه‌ی نمایش‌داده‌شده از نوسان تاریخی همان کانال می‌آید. این برون‌یابی است، نه شبیه‌سازی.</div>

  <div class="card flush">
    <div class="card-h"><div class="h3">تفکیک به ردیف تخصیص</div></div>
    <div class="tbl-wrap"><table class="tbl" data-cards style="min-width:700px">
      <thead><tr><th>کانال · سگمنت</th><th>نوع</th><th>بودجه</th><th>سهم</th><th>نصب</th><th>خرید</th><th>درآمد</th><th>منبع</th></tr></thead>
      <tbody><?php foreach ($s['rows'] as $i => $r): $a = $plan['alloc'][$i] ?? []; ?>
        <tr><td data-label="کانال · سگمنت" class="m"><?= e($r['label']) ?></td><td data-label="نوع" class="t3"><?= e($r['kind']) ?></td><td data-label="بودجه"><?= e(money($r['budget'])) ?></td><td data-label="سهم" class="t3"><?= e(pct($r['share'], 0)) ?></td><td data-label="نصب"><?= e(num($r['installs'])) ?></td><td data-label="خرید"><?= e(num($r['conv'])) ?></td><td data-label="درآمد"><?= e(money($r['revenue'])) ?></td><td data-label="منبع"><?= src('rates:' . $r['ch'] . '|' . $r['seg'] . ' · n=' . ($a['n'] ?? '')) ?></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </div>

  <div class="card p20" style="gap:14px">
    <div class="row base gap10"><div class="h2">سناریوی «اگر…»</div><div class="small t3">بودجه یا تقسیم آن را عوض کنید و دو پیش‌بینی را کنار هم ببینید. نسخه‌ی <?= fa($plan['version']) ?></div></div>
    <form method="get" action="<?= e(url('/c/' . $c['id'] . '/sim')) ?>" class="grid" style="--min:240px" data-live-submit>
      <?php if (!\App\Core\Config::get('pretty_urls', true)): ?><input type="hidden" name="r" value="/c/<?= (int) $c['id'] ?>/sim"><?php endif; ?>
      <div class="field"><label>تغییر بودجه: <span id="wbv"></span></label><input type="range" name="wb" min="-50" max="100" step="10" value="<?= (int) $wb ?>" data-label="#wbv" data-format="signpct"></div>
      <div class="field"><label>انتقال از ردیف اول به دوم: <span id="wsv"></span></label><input type="range" name="ws" min="0" max="60" step="10" value="<?= (int) $wsh ?>" data-label="#wsv"<?= count($plan['alloc']) < 2 ? ' disabled' : '' ?>></div>
    </form>
    <div style="border:1px solid var(--bd3);border-radius:6px;overflow:hidden">
      <div class="row small t3" style="padding:8px 12px;background:var(--soft);gap:12px;flex-wrap:nowrap"><div style="min-width:70px">شاخص</div><div class="grow">طرح فعلی</div><div class="grow">سناریو</div><div style="min-width:60px">تغییر</div></div>
      <?php foreach ($wi['rows'] as $r): ?><div class="row" style="padding:8px 12px;border-top:1px solid var(--bd3);font-size:13px;gap:12px;flex-wrap:nowrap"><div style="min-width:70px;font-weight:600"><?= e($r['k']) ?></div><div class="grow t2"><?= e($r['a']) ?></div><div class="grow"><?= e($r['b']) ?></div><div style="min-width:60px;font-weight:600"><?= e($r['d']) ?></div></div><?php endforeach; ?>
    </div>
    <?php if ($wi['note']): ?><div class="small" style="color:var(--warn-t)"><?= e($wi['note']) ?></div><?php endif; ?>
    <?php if (!$locked && can('plan')): ?>
      <div class="row gap8">
        <form method="post" action="<?= e(url('/c/' . $c['id'] . '/whatif')) ?>" class="inline"><?= csrf_field() ?><input type="hidden" name="wb" value="<?= (int) $wb ?>"><input type="hidden" name="ws" value="<?= (int) $wsh ?>"><button class="btn primary"<?= $wb === 0 && $wsh === 0 ? ' disabled' : '' ?>>اعمال سناریو روی طرح (نسخه‌ی تازه)</button></form>
        <a class="btn" href="<?= e(url('/c/' . $c['id'] . '/insights')) ?>">تغییر دیدگاه طرح</a>
        <form method="post" action="<?= e(url('/c/' . $c['id'] . '/refresh-rates')) ?>" class="inline"><?= csrf_field() ?><button class="btn">پیش‌بینی دوباره با نرخ‌های فعلی</button></form>
      </div>
    <?php endif; ?>
    <?php if ($versions): ?>
      <div class="col gap4" style="border-top:1px solid var(--bd3);padding-top:10px"><div class="small" style="font-weight:600">نسخه‌های قبلی طرح</div>
        <?php foreach ($versions as $v): ?><div class="small t2">v<?= fa($v['version']) ?> · <?= e($v['perspective']) ?> · <?= e($v['note'] ?: ($v['reason'] ?: '—')) ?> · <span class="muted"><?= e(jdt($v['created_at'])) ?></span></div><?php endforeach; ?></div>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= e(url('/c/' . $c['id'] . '/sim')) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="band" value="<?= e($c['sim_band']) ?>"><input type="hidden" name="ext" value="<?= e($c['sim_ext']) ?>">
    <button class="btn primary lg">ثبت پیش‌بینی و رفتن به پایش ←</button></form>
</div>
