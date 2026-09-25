<?php
/** @var \App\Engine\Engine $e @var list<array> $rates @var list<array> $history @var array|null $imp @var array $wsRow */
use App\Engine\Engine;

$edit = can('editData');
?>
<div class="col gap22">
  <div class="head">
    <h1 class="title">داده‌ها</h1>
    <p class="lead">سه جدولی که حافظه‌ی سیستم‌اند. هر عددی که در اینسایت‌ها و داشبورد می‌بینید به یک ردیف همین‌جا برمی‌گردد. ستون <span class="mono">sample_n</span> وزن کالیبراسیون است.</p>
  </div>

  <?php if ($wsRow['benchmark_mode']): ?>
    <div class="callout warn">نرخ‌ها از <b>مرجع صنعت</b> بارگذاری شده‌اند (sample_n = ۳). اینسایت‌ها برچسب «بر پایه‌ی مرجع» می‌گیرند تا اولین کالیبراسیون.</div>
  <?php endif; ?>

  <div class="card" id="import">
    <div class="row base gap10"><div class="h2 nowrap">ورود داده با فایل</div><div class="small t3 grow" style="line-height:1.8">قالب را دانلود کنید، در Excel پر کنید و با فرمت CSV (UTF-8) بارگذاری کنید. قبل از ثبت، پیش‌نمایش و خطاها را می‌بینید. اگر سرستون‌ها با قالب فرق داشته باشند، نگاشت هوشمند ستون‌ها را پیشنهاد می‌دهد.</div></div>
    <div class="grid" style="--min:260px;gap:12px">
      <?php foreach (['history' => ['کمپین‌های گذشته', 'name, channel, segment, spend, installs, conversions, revenue, month'], 'rates' => ['جدول نرخ', 'channel, segment, unit_cost, cvr, aov, variance, sample_n, ceiling, d30, fraud']] as $k => [$lbl, $cols]): ?>
        <div class="card soft" style="border-radius:6px;padding:14px;gap:10px">
          <div class="h4"><?= e($lbl) ?></div>
          <div class="mono xs t3" style="text-align:left"><?= e($cols) ?></div>
          <div class="row gap8">
            <a class="btn" href="<?= e(url('/data/template/' . $k)) ?>">دانلود قالب</a>
            <?php if ($edit): ?>
              <form method="post" action="<?= e(url('/data/import/' . $k)) ?>" enctype="multipart/form-data" class="inline"><?= csrf_field() ?>
                <label class="btn outline">بارگذاری CSV<input type="file" name="file" accept=".csv,text/csv" hidden onchange="this.form.submit()"></label>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($imp): $spec = Engine::importSpec()[$imp['kind']]; ?>
      <div class="card teal" style="background:#f4fafa;border-radius:6px;padding:14px;gap:10px">
        <div class="row base gap10">
          <div class="h4" style="font-size:14px">پیش‌نمایش · <?= $imp['kind'] === 'history' ? 'تاریخچه‌ی کمپین' : 'جدول نرخ' ?></div>
          <div class="xs t3 ltr"><?= e($imp['file']) ?></div>
          <div class="grow"></div>
          <div class="small" style="color:var(--teal-t);font-weight:600"><?= fa($imp['okN']) ?> ردیف معتبر</div>
          <div class="small" style="color:var(--bad-s);font-weight:600"><?= fa($imp['errN']) ?> ردیف خطادار</div>
        </div>
        <?php if (!empty($imp['ai']) && !$imp['ai']['fallback']): ?>
          <div class="ai-box"><div class="row gap8"><span class="ai-label">توضیح هوشمند</span><span class="xs t3">نگاشت ستون‌ها پیشنهاد مدل است؛ پیش از ثبت بررسی کنید.</span></div>
            <?php foreach ($imp['ai']['warnings'] as $w): ?><div class="small" style="color:var(--warn-t)">⚠ <?= e($w) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <?php if ($imp['mapping'] !== null || $imp['errN'] && $imp['okN'] === 0): ?>
          <form method="post" action="<?= e(url('/data/import-remap')) ?>" class="col gap8"><?= csrf_field() ?>
            <div class="small t3">نگاشت ستون‌ها</div>
            <div class="grid" style="--min:200px;gap:8px">
              <?php foreach ($spec['cols'] as $c): $sel = $imp['mapping'][$c] ?? null; ?>
                <label class="field"><span class="mono xs t3"><?= e($c) ?></span>
                  <select name="map_<?= e($c) ?>" class="inp sm" style="<?= $sel === null ? 'border-color:var(--bad-bd)' : '' ?>">
                    <option value="">— انتخاب نشده —</option>
                    <?php foreach ($imp['headers'] as $h): ?><option value="<?= e($h) ?>"<?= $sel === $h || ($sel === null && mb_strtolower($h) === $c) ? ' selected' : '' ?>><?= e($h) ?></option><?php endforeach; ?>
                  </select></label>
              <?php endforeach; ?>
            </div>
            <div class="row gap8"><label class="small t3">ضریب واحد پول</label>
              <select name="unit" class="inp sm" style="width:auto"><?php foreach (['1' => 'تومان (بدون تغییر)', '1000' => 'هزار تومان × ۱٬۰۰۰', '1000000' => 'میلیون تومان × ۱٬۰۰۰٬۰۰۰', '0.1' => 'ریال ÷ ۱۰'] as $v => $l): ?><option value="<?= $v ?>"<?= (string) $imp['unit'] === (string) (float) $v || (float) $imp['unit'] === (float) $v ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
              <button class="btn sm">اعمال نگاشت</button></div>
          </form>
        <?php endif; ?>
        <?php foreach ($imp['preview'] as $p): ?>
          <div class="row small" style="border-top:1px solid #e0eeee;padding-top:7px;gap:12px"><div class="grow" style="font-weight:500"><?= e($p['name'] ?? $p['channel']) ?></div><div class="grow t2"><?= e($p['channel'] . ' · ' . $p['segment']) ?></div><div class="t2"><?= isset($p['spend']) ? e(money($p['spend'])) . ' ت' : e(num($p['unit_cost'])) . ' ت / واحد' ?></div></div>
        <?php endforeach; ?>
        <?php if ($imp['errors']): ?>
          <div class="callout bad col gap4" style="gap:5px"><?php foreach (array_slice($imp['errors'], 0, 8) as $er): ?><div class="small">سطر <?= fa($er['line']) ?>: <?= e($er['msg']) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="row gap8">
          <?php if ($imp['okN'] > 0): ?><form method="post" action="<?= e(url('/data/import-commit')) ?>" class="inline"><?= csrf_field() ?><button class="btn primary">ثبت ردیف‌های معتبر</button></form><?php endif; ?>
          <form method="post" action="<?= e(url('/data/import-cancel')) ?>" class="inline"><?= csrf_field() ?><button class="btn">انصراف</button></form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= e(url('/data/rates')) ?>" class="card flush"><?= csrf_field() ?>
    <div class="card-h">
      <div class="h2">نرخ‌های تاریخی — کانال × سگمنت</div><div class="mono xs muted">rates</div><div class="grow"></div>
      <div class="xs t3">برای پوش و پیامک «هزینه‌ی واحد» یعنی هزینه به ازای کاربر دریافت‌کننده</div>
      <a class="btn sm" href="<?= e(url('/data/export/rates')) ?>">خروجی CSV</a>
      <?php if ($edit): ?><button class="btn sm primary">ذخیره‌ی تغییرات</button><?php endif; ?>
    </div>
    <?php if (!$rates): ?><div class="empty solid" style="border:none">هنوز ردیف نرخی ثبت نشده. قالب CSV را بارگذاری کنید یا در صفحه‌ی آمادگی با نرخ مرجع صنعت شروع کنید.</div><?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl" data-cards style="min-width:1000px">
        <thead><tr><th>کانال</th><th>سگمنت</th><th>نوع</th><th>هزینه‌ی واحد</th><th>تعدیل تورم</th><th>CVR</th><th>AOV</th><th>CAC</th><th>نوسان</th><th>sample_n</th><th>سقف حجم</th><th>ماندگاری D30</th><th>تقلب</th><?php if ($edit): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($rates as $r): ?>
          <tr>
            <td data-label="کانال" class="m"><?= e($r['ch']) ?></td>
            <td data-label="سگمنت" class="t3"><?= e($r['seg']) ?><?= $r['source'] === 'benchmark' ? ' <span class="badge sm warn">مرجع</span>' : '' ?></td>
            <td data-label="نوع"><?= $r['type'] === 'owned' ? '<span class="badge sm warn">خودی</span>' : '<span class="badge sm teal" style="font-weight:400">جذب پولی</span>' ?></td>
            <td data-label="هزینه‌ی واحد"><?php if ($edit): ?><input class="inp num" name="cpi[<?= (int) $r['id'] ?>]" value="<?= e($r['cpi']) ?>" inputmode="decimal" aria-label="هزینه‌ی واحد <?= e($r['ch'] . ' ' . $r['seg']) ?>"><?php else: ?><?= e(num($r['cpi'])) ?><?php endif; ?></td>
            <td data-label="تعدیل تورم" class="nowrap small t2"><?= e(num($e->cpiAdj($r))) ?> <span class="t3">· <?= fa($r['age']) ?> ماه</span></td>
            <td data-label="CVR"><?php if ($edit): ?><input class="inp num" style="width:74px" name="cvr[<?= (int) $r['id'] ?>]" value="<?= e($r['cvr']) ?>" inputmode="decimal" aria-label="CVR"><?php else: ?><?= e(pct($r['cvr'])) ?><?php endif; ?></td>
            <td data-label="AOV" class="t3"><?= e(money($r['aov'])) ?></td>
            <td data-label="CAC" class="b"><?= e(money($e->cac($r))) ?></td>
            <td data-label="نوسان" class="t3">±<?= e(pct($r['variance'], 0)) ?></td>
            <td data-label="sample_n"><span class="badge n <?= $r['n'] < 5 ? 'lown' : '' ?>" title="<?= $r['n'] < 5 ? 'کم‌نمونه — زیر ۵' : '' ?>"><?= fa($r['n']) ?></span><?= $r['age'] > 3 ? ' <span class="badge sm soft" title="بیش از ۳ ماه از آخرین مشاهده">قدیمی</span>' : '' ?></td>
            <td data-label="سقف حجم" class="t3"><?= e(num($r['ceiling'])) ?></td>
            <td data-label="ماندگاری D30" class="t2"><?= e(pct($r['d30'], 0)) ?></td>
            <td data-label="تقلب" class="t2"><?= $r['type'] === 'owned' ? '—' : e(pct($r['fraud'], 0)) ?></td>
            <?php if ($edit): ?><td><button class="btn link danger xs" formaction="<?= e(url('/data/rates/' . $r['id'] . '/delete')) ?>" data-confirm="ردیف <?= e($r['ch'] . '|' . $r['seg']) ?> حذف شود؟" aria-label="حذف ردیف">حذف</button></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </form>

  <?php if ($edit): ?>
  <details class="card" id="add-rate">
    <summary class="h3" style="cursor:pointer">افزودن یا جایگزینی یک ردیف نرخ</summary>
    <form method="post" action="<?= e(url('/data/rates/add')) ?>" class="grid mt8" style="--min:170px;gap:10px"><?= csrf_field() ?>
      <div class="field"><label>کانال</label><select name="channel" class="inp"><?php foreach (Engine::CHANNELS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>سگمنت</label><select name="segment" class="inp"><?php foreach (Engine::SEGMENTS as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>هزینه‌ی واحد (تومان)</label><input class="inp num" name="unit_cost" required inputmode="decimal"></div>
      <div class="field"><label>CVR (کسری، مثلاً ۰٫۰۷)</label><input class="inp num" name="cvr" required inputmode="decimal"></div>
      <div class="field"><label>AOV (تومان)</label><input class="inp num" name="aov" required inputmode="decimal"></div>
      <div class="field"><label>نوسان (کسری)</label><input class="inp num" name="variance" value="0.2" inputmode="decimal"></div>
      <div class="field"><label>sample_n</label><input class="inp num" name="sample_n" value="1" inputmode="numeric"></div>
      <div class="field"><label>سقف حجم</label><input class="inp num" name="ceiling" value="5000" inputmode="numeric"></div>
      <div class="field"><label>ماندگاری D30 (کسری)</label><input class="inp num" name="d30" value="0.2" inputmode="decimal"></div>
      <div class="field"><label>تقلب (کسری)</label><input class="inp num" name="fraud" value="0.03" inputmode="decimal"></div>
      <div class="field"><label>ضریب اوج (کسری)</label><input class="inp num" name="seasonal_lift" value="0.05" inputmode="decimal"></div>
      <div class="field" style="justify-content:end"><button class="btn primary">ثبت ردیف</button></div>
    </form>
  </details>
  <?php endif; ?>

  <div class="grid" style="--min:320px">
    <div class="card">
      <div class="h2">قاعده‌ی مرز دو لایه</div>
      <p style="font-size:13.5px;line-height:1.85;color:var(--t2)">لایه‌ی ۱ عدد می‌سازد؛ لایه‌ی ۲ همان عدد را روایت می‌کند و <strong>حق ساختن عدد ندارد</strong>. هر عددی در متن یک اینسایت باید شناسه‌ای داشته باشد که به یک ردیف <span class="mono">rates</span> برگردد.</p>
      <div class="callout soft small">عددی که شناسه‌ی منبع ندارد نمایش داده نمی‌شود — این تنها ضمانت راستی‌آزمایی‌پذیری کل سیستم است.</div>
      <div class="row gap10 mt4">
        <?php if ($edit): ?>
          <form method="post" action="<?= e(url('/data/reseed')) ?>" class="inline" data-confirm="نرخ‌ها، پروفایل و تاریخچه به داده‌ی دموی اولیه برمی‌گردند. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn" style="color:var(--t3)">بازنشانی داده‌ی دمو</button></form>
          <?php if ($wsRow['is_demo']): ?><form method="post" action="<?= e(url('/data/reseed')) ?>" class="inline" data-confirm="همه‌ی نرخ‌ها و کمپین‌های تاریخی دمو پاک می‌شوند تا داده‌ی خودتان را وارد کنید. ادامه می‌دهید؟"><?= csrf_field() ?><input type="hidden" name="mode" value="clear"><button class="btn danger">پاک‌کردن داده‌ی دمو</button></form><?php endif; ?>
        <?php endif; ?>
        <a class="btn primary" href="<?= e(url('/setup')) ?>">ادامه به پروفایل و آمادگی ←</a>
      </div>
    </div>
  </div>

  <div class="card flush" id="history">
    <div class="card-h"><div class="h2">کمپین‌های گذشته</div><div class="mono xs muted">campaign_history</div><div class="grow"></div><div class="xs muted"><?= fa(count($history)) ?> ردیف</div><a class="btn sm" href="<?= e(url('/data/export/history')) ?>">خروجی CSV</a></div>
    <?php if (!$history): ?><div class="empty solid" style="border:none">هنوز کمپین تاریخی ثبت نشده. حداقل ۱۵ ردیف برای اینسایت معتبر لازم است.</div><?php else: ?>
    <div class="tbl-wrap">
      <table class="tbl" data-cards style="min-width:880px;font-size:12.5px">
        <thead><tr><th>کمپین</th><th>کانال</th><th>سگمنت</th><th>هزینه</th><th>نصب</th><th>خرید</th><th>درآمد</th><th>CAC</th><th>منبع</th><?php if ($edit): ?><th></th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach ($history as $h): ?>
          <tr>
            <td data-label="کمپین" class="m"><?= e($h['name']) ?></td>
            <td data-label="کانال" class="t3"><?= e($h['channel']) ?></td>
            <td data-label="سگمنت" class="t3"><?= e($h['segment']) ?></td>
            <td data-label="هزینه"><?= e(money($h['spend'])) ?></td>
            <td data-label="نصب"><?= e(num($h['installs'])) ?></td>
            <td data-label="خرید"><?= e(num($h['conversions'])) ?></td>
            <td data-label="درآمد"><?= e(money($h['revenue'])) ?></td>
            <td data-label="CAC" class="b"><?= $h['conversions'] > 0 ? e(money($h['spend'] / $h['conversions'])) : '—' ?></td>
            <td data-label="منبع" class="muted"><?= e($h['source']) ?></td>
            <?php if ($edit): ?><td><form method="post" action="<?= e(url('/data/history/' . $h['id'] . '/delete')) ?>" class="inline" data-confirm="این کمپین تاریخی حذف شود؟"><?= csrf_field() ?><button class="btn link danger xs">حذف</button></form></td><?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($edit): ?>
  <details class="card" id="add-history">
    <summary class="h3" style="cursor:pointer">افزودن دستی یک کمپین تاریخی</summary>
    <form method="post" action="<?= e(url('/data/history/add')) ?>" class="grid mt8" style="--min:170px;gap:10px"><?= csrf_field() ?>
      <div class="field"><label>نام کمپین</label><input class="inp" name="name" required></div>
      <div class="field"><label>کانال</label><select name="channel" class="inp"><?php foreach (Engine::CHANNELS as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>سگمنت</label><select name="segment" class="inp"><?php foreach (Engine::SEGMENTS as $s): ?><option><?= e($s) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label>هزینه (تومان)</label><input class="inp num" name="spend" required inputmode="decimal"></div>
      <div class="field"><label>نصب</label><input class="inp num" name="installs" required inputmode="numeric"></div>
      <div class="field"><label>خرید</label><input class="inp num" name="conversions" required inputmode="numeric"></div>
      <div class="field"><label>درآمد (تومان)</label><input class="inp num" name="revenue" required inputmode="decimal"></div>
      <div class="field"><label>ماه</label><select name="month" class="inp"><?php foreach (\App\Core\Jalali::MONTHS as $m): ?><option><?= e($m) ?></option><?php endforeach; ?></select></div>
      <div class="field" style="justify-content:end"><button class="btn primary">ثبت</button></div>
    </form>
  </details>
  <?php endif; ?>
</div>
