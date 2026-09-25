<?php
use App\Services\Loop;

$vr = $run['ver']['r'] ?? null;
$cals = $run['cals'] ?? [];
$sr = $sim['r'] ?? null;
$chain = [
    ['ایستگاه ۲ — طرح', $plan ? fa($plan['code']) . ' · v' . fa($plan['version']) : '', $plan ? 'دیدگاه: ' . $plan['perspective'] : 'اینسایتی انتخاب نشده', (bool) $plan],
    ['ایستگاه ۳ — پیش‌بینی', $sim ? fa($sim['code']) : '', $sr ? 'CAC ' . money($sr['cac']) . ' · بازه از نوسان تاریخی' : 'طرحی برای پیش‌بینی نیست', (bool) $sim],
    ['اجرای واقعی', $run ? fa($run['code']) : '', $vr ? 'علت: ' . $vr['cause'] : 'نتیجه‌ای ثبت نشده', (bool) $run],
    ['ایستگاه ۱ — کالیبراسیون', $cals ? fa(implode(', ', array_column($cals, 'code'))) : '', $cals ? 'ردیف ' . implode('، ', array_column($cals, 'row_label')) . ' به‌روزرسانی شد' : ($vr && empty($vr['calib']) ? 'در این شاخه اعمال نمی‌شود' : 'کالیبراسیونی اعمال نشده'), (bool) $cals],
];
?>
<div class="col gap20">
  <div class="head"><h1 class="title">حلقه</h1><p class="lead">زنجیره‌ی شناسه: طرح، پیش‌بینی و نتیجه به هم وصل‌اند، پس انحراف قابل انتساب است. این زنجیره خودِ محصول است.</p></div>
  <div class="card p22 row" style="gap:12px;align-items:stretch">
    <?php foreach ($chain as [$st, $id, $note, $done]): ?>
      <div class="chain <?= $done ? 'done' : 'pending' ?>"><div class="s"><?= e($st) ?></div><div class="i"><?= $done ? e($id) : '—' ?></div><div class="nt"><?= e($note) ?></div></div>
    <?php endforeach; ?>
  </div>
  <div class="card flush">
    <div class="card-h"><div class="h2">نرخ قبل / نرخ بعد</div></div>
    <?php if (!$cals): ?>
      <div style="padding:34px;text-align:center" class="muted">هنوز کالیبراسیونی اعمال نشده. در ایستگاه ۱ یکی از شاخه‌های «خطای برآورد» یا «در دامنه‌ی انتظار» را اجرا کنید.</div>
    <?php else: ?>
      <table class="tbl" data-cards><thead><tr><th>ردیف</th><th>متریک</th><th>قبل</th><th>بعد</th><th>تغییر</th></tr></thead><tbody>
      <?php foreach ($cals as $cal): $a = Loop::j($cal['after_row']); $b = $a['before']; ?>
        <tr><td data-label="ردیف" class="m"><?= e($cal['row_label']) ?></td><td data-label="متریک" class="mono t3">cpi</td><td data-label="قبل" class="t3"><?= e(num($b['cpi'])) ?></td><td data-label="بعد" class="b"><?= e(num($a['cpi'])) ?></td><td data-label="تغییر" style="color:var(--warn-s)"><?= e(spct($a['cpi'] / $b['cpi'] - 1)) ?></td></tr>
        <tr><td data-label="ردیف" class="m"><?= e($cal['row_label']) ?></td><td data-label="متریک" class="mono t3">cvr</td><td data-label="قبل" class="t3"><?= e(dec($b['cvr'] * 100, 3)) ?>٪</td><td data-label="بعد" class="b"><?= e(dec($a['cvr'] * 100, 3)) ?>٪</td><td data-label="تغییر" style="color:var(--warn-s)"><?= e(spct($a['cvr'] / $b['cvr'] - 1)) ?></td></tr>
        <tr><td data-label="ردیف" class="m"><?= e($cal['row_label']) ?></td><td data-label="متریک" class="mono t3">sample_n</td><td data-label="قبل" class="t3"><?= fa($b['n']) ?></td><td data-label="بعد" class="b"><?= fa($a['n']) ?></td><td data-label="تغییر" style="color:var(--warn-s)">+۱</td></tr>
      <?php endforeach; ?></tbody></table>
    <?php endif; ?>
  </div>
  <div class="card blue p22">
    <div class="h2">ظریف‌ترین قاعده‌ی سیستم</div>
    <p style="font-size:14px;line-height:1.95;color:var(--t2);max-width:74ch">وقتی انحراف از <strong style="color:var(--ink)">اجرا</strong> یا از <strong style="color:var(--ink)">مقیاس</strong> آمده، نرخ کانال غلط نبوده. کالیبره‌کردن در آن حالت، حافظه‌ی سیستم را با نویز خراب می‌کند. در سه شاخه‌ی «ناسازگاری داده»، «انحراف اجرا» و «مقیاس، نه کیفیت» کالیبراسیون عمداً اعمال نمی‌شود.</p>
  </div>
</div>
