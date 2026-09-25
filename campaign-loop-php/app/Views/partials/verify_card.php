<?php
/** @var array|null $run @var \App\Engine\Engine $e @var array|null $plan @var array|null $sim @var bool $aiOn @var array|null $undoable */
use App\Core\Auth;
use App\Services\Loop;

$vr = $run['ver']['r'] ?? null;
$vi = $run['in'] ?? null;
$cals = $run['cals'] ?? [];
$treeActive = $vr['cause'] ?? null;
?>
<div class="col gap16" id="card">
  <?php if (!$vr): ?>
    <div class="empty">یکی از داده‌های نمونه را بارگذاری کنید یا فرم را پر کنید، سپس «انتساب علت» را بزنید.</div>
  <?php else:
      $sr = $sim['r'] ?? null;
      $dev = static fn (float $d) => abs($d) <= 0.15;
      $rowsT = [
          ['بودجه', money($vi['pb']), money($vi['ab']), $vr['dB'], null],
          ['نصب', num($vi['pi']), num($vi['ai']), $vr['dI'], $sr ? num($sr['iLow']) . '–' . num($sr['iHigh']) : null],
          ['خرید', num($vi['pc']), num($vi['ac']), $vr['dC'], $sr ? num($sr['cLow']) . '–' . num($sr['cHigh']) : null],
      ];
  ?>
    <div class="card flush">
      <div class="card-h" style="flex-direction:column;align-items:stretch;gap:6px">
        <div class="h2">کارت راستی‌آزمایی کمپین</div>
        <div class="small t3"><?= e(($vi['name'] ?? '') . ' · ' . $vi['ch'] . ' · ' . $vi['seg'] . (!empty($vi['rows']) && count($vi['rows']) > 1 ? ' (+' . fa(count($vi['rows']) - 1) . ' ردیف)' : '') . ($vi['from'] ? ' · ' . fa($vi['from']) . ' تا ' . fa($vi['to']) : '')) ?></div>
        <div class="idlbl"><?= $plan ? 'plan_id: ' . e(fa($plan['code'])) . '  ·  ' : '' ?>sim_id: <?= e($sim ? fa($sim['code']) : '—') ?>  ·  run_id: <?= e(fa($run['code'])) ?></div>
      </div>
      <table class="tbl" data-cards>
        <thead><tr><th>متریک</th><th>پیش‌بینی<?= $sr ? ' (بازه)' : '' ?></th><th>واقعی</th><th>انحراف</th></tr></thead>
        <tbody><?php foreach ($rowsT as [$m, $p, $a, $d, $bnd]): ?>
          <tr><td data-label="متریک" class="m"><?= e($m) ?></td><td data-label="پیش‌بینی" class="t3"><?= e($p) ?><?= $bnd ? ' <span class="xs muted">(' . e($bnd) . ')</span>' : '' ?></td><td data-label="واقعی"><?= e($a) ?></td>
            <td data-label="انحراف" class="dev <?= $dev($d) ? 'ok' : 'bad' ?>"><?= e(spct($d)) ?> <?= $dev($d) ? '✓' : '⚠' ?></td></tr>
        <?php endforeach; ?></tbody>
      </table>
      <div style="padding:16px 18px;border-top:1px solid var(--bd2);background:var(--soft)" class="col gap8">
        <div style="font-size:14.5px;font-weight:700">علت محتمل: <?= e($vr['cause']) ?></div>
        <div style="font-size:13.5px;line-height:1.85;color:var(--t2)"><?= e($vr['expl']) ?></div>
        <?php if (!empty($vr['withinBand'])): ?><div class="small t3"><?= !empty($vr['withinBand']['belowLow']) ? 'واقعی حتی از کران پایین پیش‌بینی هم پایین‌تر بود.' : (($vr['withinBand']['conversions'] && $vr['withinBand']['installs']) ? 'واقعی داخل بازه‌ی تجربی افتاد.' : 'واقعی بالاتر از کران بالای بازه‌ی تجربی بود.') ?></div><?php endif; ?>
        <?php if ($aiOn): ?><div class="ai-box" hidden data-ai-src="<?= e(url('/ai/run/' . $run['id'])) ?>"></div><?php endif; ?>
      </div>
      <?php if (count($vr['rowResults'] ?? []) > 1): ?>
        <div style="padding:12px 18px;border-top:1px solid var(--bd2)" class="col gap6">
          <div class="small" style="font-weight:600">علت به تفکیک ردیف</div>
          <?php foreach ($vr['rowResults'] as $rr): ?><div class="row small" style="gap:10px"><span style="min-width:150px"><?= e($rr['ch'] . ' · ' . $rr['seg']) ?></span><span class="t3">نصب <?= e(spct($rr['vr']['dI'])) ?> · CVR <?= e(spct($rr['vr']['dCvr'])) ?></span><span class="badge sm <?= $rr['vr']['calib'] ? 'teal' : 'warn' ?>"><?= e($rr['vr']['cause']) ?></span></div><?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div style="padding:14px 18px;border-top:1px solid var(--bd2);font-size:12.5px;color:var(--t3);line-height:1.9" class="col gap4">
        <div>دیدگاه انتخاب‌شده: <strong style="color:var(--ink)"><?= e($plan['perspective'] ?? 'ثبت نشده (مسیر دستی)') ?></strong></div>
        <div>منبع پیش‌بینی: <?= $plan ? 'ایستگاه ۳ — ' . e(fa($sim['code'] ?? 'sim_id')) : 'ورود دستی مرچنت · ' . e($vi['src'] ?? '') ?></div>
        <div>کیفیت داده: تقلب <?= e(pct(($vi['fraud'] ?? 0) / 100, 0)) ?> · پنجره <?= fa($vi['window'] ?? 7) ?> روز · <?= ($vi['complete'] ?? 'بله') === 'بله' ? 'کامل' : 'ناقص' ?> · شناسه‌ی ترکر: <?= ($vi['matched'] ?? 'بله') === 'بله' ? 'منطبق' : 'نامنطبق' ?></div>
        <div>اثر افزایشی (گروه کنترل): <?= e($vr['liftText']) ?></div>
      </div>
      <div style="padding:16px 18px;border-top:1px solid var(--bd2)">
        <?php if (!$cals && !empty($vr['calib'])): ?>
          <?php if (can('calibrate')): ?>
            <div class="col gap12">
              <div style="font-size:13px;color:var(--teal);line-height:1.85">✅ در این شاخه کالیبراسیون معتبر است — <?php foreach ($vr['rowResults'] as $rr): if (!$rr['vr']['calib']) { continue; } ?>CPI مشاهده‌شده <?= e(money($rr['vr']['obsCpi'])) ?> و CVR مشاهده‌شده <?= e(pct($rr['vr']['obsCvr'])) ?> با وزن sample_n در نرخ ردیف <?= e($rr['ch'] . '|' . $rr['seg']) ?> میانگین می‌شود. <?php endforeach; ?></div>
              <form method="post" action="<?= e(url('/runs/' . $run['id'] . '/calibrate')) ?>"><?= csrf_field() ?><button class="btn primary md">اعمال کالیبراسیون</button></form>
            </div>
          <?php else: ?>
            <div class="small" style="color:var(--warn-t)">کالیبراسیون در این شاخه معتبر است ولی نقش «<?= e(Auth::roleLabel()) ?>» اجازه‌ی اعمال آن را ندارد.</div>
          <?php endif; ?>
        <?php elseif (!$cals): ?>
          <div class="callout warn" style="padding:14px;color:#4a3a12;line-height:1.9">⛔ کالیبراسیون اعمال نمی‌شود. <?= e($vr['block']) ?></div>
        <?php else: ?>
          <div class="callout ok col gap10" style="padding:14px;color:var(--ink)">
            <?php foreach ($cals as $cal): $a = Loop::j($cal['after_row']); $b = $a['before']; ?>
              <div style="line-height:1.9">کالیبراسیون اعمال شد — <?= e($cal['weights']) ?> — ردیف <?= e($cal['row_label']) ?>: CPI از <?= e(money($b['cpi'])) ?> به <?= e(money($a['cpi'])) ?> · CVR از <?= e(pct($b['cvr'])) ?> به <?= e(pct($a['cvr'])) ?> · sample_n = <?= fa($a['n']) ?> <span class="idlbl"><?= e(fa($cal['code'])) ?></span></div>
            <?php endforeach; ?>
            <div class="row gap8">
              <?php if ($undoable && can('calibrate') && in_array((int) $undoable['id'], array_map('intval', array_column($cals, 'id')), true)): ?>
                <form method="post" action="<?= e(url('/calibrations/' . $undoable['id'] . '/revert')) ?>" class="inline" data-confirm="نرخ ردیف به مقدار پیش از کالیبراسیون برمی‌گردد. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn sm danger">بازگردانی <?= e(fa($undoable['code'])) ?></button></form>
              <?php endif; ?>
              <?php if ($run['campaign_id']): ?><a class="btn teal-o" href="<?= e(url('/c/' . $run['campaign_id'] . '/loop')) ?>">دیدن صفحه‌ی حلقه ←</a><?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($run['campaign_id']): ?><div class="row gap8 mt12"><a class="btn outline" href="<?= e(url('/c/' . $run['campaign_id'] . '/report')) ?>">گزارش کمپین و بستن حلقه ←</a></div><?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <div class="h4" style="font-size:14px">درخت تصمیم — اولین شرط برنده است</div>
    <?php foreach ($e->treeTexts() as $t): ?>
      <div class="tree <?= $treeActive === $t['cause'] ? 'on' : '' ?>"><b><?= fa($t['num']) ?></b><div><?= e($t['text']) ?></div></div>
    <?php endforeach; ?>
  </div>
</div>
