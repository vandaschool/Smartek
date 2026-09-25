<div class="col gap18" style="max-width:860px">
  <div class="head"><h1 class="title">پرسش از داده</h1><p class="lead">به فارسی بپرسید؛ پاسخ فقط از جدول نرخ ساخته می‌شود و منبعش کنارش می‌آید. اگر پرسش به یک عدد مشخص نگاشت نشود، سیستم عدد نمی‌سازد.</p></div>
  <div class="card">
    <form id="ask-form" action="<?= e(url('/ask')) ?>" method="post" class="row gap8" style="flex-wrap:nowrap"><?= csrf_field() ?>
      <input name="q" class="inp grow" style="font-size:14px;padding:11px 13px" placeholder="مثلاً: ارزان‌ترین کانال کدام است؟" required maxlength="300" aria-label="پرسش">
      <button class="btn primary">بپرس</button>
    </form>
    <div class="row" style="gap:7px">
      <?php foreach (['ارزان‌ترین کانال کدام است؟', 'پایدارترین ردیف؟', 'کدام ردیف سودآورتر است؟', 'بیشترین ماندگاری؟', 'نرخ تقلب کجا بالاست؟', 'وضعیت تپسل', 'CAC یکتانت روی کاربر جدید چند است؟'] as $q): ?>
        <button type="button" class="btn sm" style="border-radius:20px;background:var(--soft)" data-ask="<?= e($q) ?>"><?= e($q) ?></button>
      <?php endforeach; ?>
    </div>
    <?php if ($aiOn): ?><div class="xs t3">فهم پرسش و نگارش پاسخ با مدل زبانی (متیس) انجام می‌شود؛ عدد همیشه از موتور و جدول نرخ می‌آید.</div><?php endif; ?>
  </div>
  <div id="ask-out" role="status" aria-live="polite"></div>
</div>
