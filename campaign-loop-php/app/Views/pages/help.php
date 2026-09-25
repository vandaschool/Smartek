<?php
/** @var array $tickets */
$faq = [
    ['چرا عدد پیش‌بینی با ماه قبل فرق دارد؟', 'نرخ‌ها با تورم ماهانه تعدیل می‌شوند و هر کالیبراسیون آن‌ها را به‌روز می‌کند. در لاگ تغییرات ببینید چه چیزی عوض شد.'],
    ['چرا کالیبراسیون اعمال نشد؟', 'در شاخه‌های «ناسازگاری داده»، «انحراف اجرا» و «مقیاس، نه کیفیت» عمداً اعمال نمی‌شود، چون نرخ کانال غلط نبوده.'],
    ['بازه‌ی تجربی یعنی چه؟', 'بازه از نوسان تاریخی همان ردیف ساخته می‌شود و ادعای احتمال آماری ندارد. جزئیات در صفحه‌ی روش‌شناسی.'],
    ['چطور داده‌ی خودم را وارد کنم؟', 'در صفحه‌ی داده‌ها قالب CSV را دانلود کنید، پر کنید و بارگذاری کنید. خطای هر سطر قبل از ثبت نشان داده می‌شود.'],
    ['چه کسی می‌تواند نرخ‌ها را تغییر دهد؟', 'فقط مالک. تحلیل‌گر می‌تواند کالیبراسیون اعمال کند؛ مسئول کمپین فقط نتیجه ثبت می‌کند؛ ناظر فقط می‌بیند.'],
];
?>
<div class="col gap18" style="max-width:860px">
  <div class="head">
    <h1 class="title">راهنما و پشتیبانی</h1>
    <p class="lead">پرسش‌های پرتکرار، تور محصول و ثبت درخواست.</p>
  </div>
  <div class="row gap8">
    <button type="button" class="btn primary" data-tour-start>شروع تور دمو</button>
    <a class="btn" href="<?= e(url('/method')) ?>">روش‌شناسی محاسبات</a>
  </div>
  <div class="card flush">
    <?php foreach ($faq as $q): ?>
      <div class="col" style="padding:14px 18px;border-bottom:1px solid var(--bd3);gap:6px"><div style="font-size:14px;font-weight:600"><?= e($q[0]) ?></div><div style="font-size:13px;color:var(--t2);line-height:1.9"><?= e($q[1]) ?></div></div>
    <?php endforeach; ?>
  </div>
  <form method="post" action="<?= e(url('/help/ticket')) ?>" class="card" style="gap:10px"><?= csrf_field() ?>
    <label class="h2" for="ticket" style="font-size:15px">ثبت درخواست پشتیبانی</label>
    <textarea id="ticket" class="inp" name="body" rows="3" required minlength="5" placeholder="مشکل یا پرسش خود را بنویسید" style="line-height:1.8;resize:vertical"></textarea>
    <div class="row gap10"><button class="btn primary">ارسال</button></div>
  </form>
  <?php if ($tickets): ?>
    <div class="card flush">
      <div class="h2" style="padding:14px 18px;border-bottom:1px solid var(--bd2)">درخواست‌های شما</div>
      <?php foreach ($tickets as $t): ?>
        <div class="col" style="padding:12px 18px;border-bottom:1px solid var(--bd3);gap:6px">
          <div class="row gap8"><span class="xs muted"><?= e(jdt($t['created_at'])) ?></span><span class="badge sm <?= $t['status'] === 'answered' ? 'teal' : 'gray' ?>"><?= $t['status'] === 'answered' ? 'پاسخ داده شد' : 'در انتظار پاسخ' ?></span></div>
          <div class="small t2" style="white-space:pre-line"><?= e($t['body']) ?></div>
          <?php if ($t['reply']): ?><div class="callout info small" style="white-space:pre-line"><?= e($t['reply']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
