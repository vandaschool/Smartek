<?php
/** @var array $cfg @var int $auto */
$edit = can('editData');
$rows = [
    ['آستانه‌ی انحراف اجرا (بودجه)', 'execTh', 'کسری · پیش‌فرض ۰٫۱۵'],
    ['آستانه‌ی خطای برآورد', 'estTh', 'کسری · پیش‌فرض ۰٫۲۵'],
    ['کران پایین نسبت مقیاس', 'scaleLo', 'پیش‌فرض ۰٫۸'],
    ['کران بالای نسبت مقیاس', 'scaleHi', 'پیش‌فرض ۱٫۲'],
    ['تورم ماهانه‌ی هزینه‌ی رسانه', 'inflation', 'کسری · پیش‌فرض ۰٫۰۳۵'],
    ['پنجره‌ی انتساب طرح (روز)', 'attrWindow', 'پیش‌فرض ۷'],
    ['آستانه‌ی تقلب', 'fraudTh', 'کسری · پیش‌فرض ۰٫۰۸'],
];
$fmt = static fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.');
?>
<div class="col gap20">
  <div class="head">
    <h1 class="title">قواعد و آستانه‌ها</h1>
    <p class="lead">آستانه‌های درخت تصمیم و فرض‌های موتور ثابت نیستند. هر تغییر در لاگ ثبت می‌شود تا معلوم باشد نتیجه با کدام قاعده گرفته شده.</p>
  </div>
  <form method="post" action="<?= e(url('/rules')) ?>" class="card" style="max-width:720px"><?= csrf_field() ?>
    <?php foreach ($rows as $r): ?>
      <div class="row" style="gap:12px;border-bottom:1px solid var(--bd3);padding-bottom:10px;flex-wrap:nowrap">
        <div class="col grow" style="gap:2px"><label for="r-<?= e($r[1]) ?>" style="font-size:13.5px;font-weight:500"><?= e($r[0]) ?></label><div class="hint"><?= e($r[2]) ?></div></div>
        <input id="r-<?= e($r[1]) ?>" class="inp num" style="width:110px" name="<?= e($r[1]) ?>" value="<?= e($fmt($cfg[$r[1]])) ?>" inputmode="decimal"<?= $edit ? '' : ' disabled' ?>>
      </div>
    <?php endforeach; ?>
    <label class="check" style="border-bottom:1px solid var(--bd3);padding-bottom:10px"><input type="checkbox" name="auto_calibrate" value="1"<?= $auto ? ' checked' : '' ?><?= $edit ? '' : ' disabled' ?>>
      <span class="col" style="gap:2px"><span style="font-size:13.5px;font-weight:500">کالیبراسیون خودکار</span><span class="hint">وقتی نتیجه در دامنه‌ی انتظار است و شاخه‌ی درخت اجازه می‌دهد، کالیبراسیون بدون کلیک اعمال می‌شود (قابل برگشت).</span></span></label>
    <?php if ($edit): ?>
      <div class="row gap8"><button class="btn primary">ذخیره‌ی قواعد</button></div>
    <?php else: ?>
      <div class="small" style="color:var(--warn-t)">نقش فعلی اجازه‌ی تغییر قواعد را ندارد.</div>
    <?php endif; ?>
  </form>
  <?php if ($edit): ?>
    <form method="post" action="<?= e(url('/rules/reset')) ?>" data-confirm="همه‌ی آستانه‌ها به مقدار پیش‌فرض برگردند؟"><?= csrf_field() ?><button class="btn">بازگشت به پیش‌فرض</button></form>
  <?php endif; ?>
</div>
