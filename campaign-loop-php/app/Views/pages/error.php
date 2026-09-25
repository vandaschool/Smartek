<?php
$titles = [403 => 'دسترسی ندارید', 404 => 'این صفحه پیدا نشد یا جابه‌جا شده است.', 405 => 'روش درخواست مجاز نیست', 419 => 'نشست فرم منقضی شد', 429 => 'درخواست‌های زیاد', 500 => 'خطای سرور'];
?>
<div class="card" style="padding:48px;text-align:center;align-items:center;max-width:720px;margin:40px auto">
  <div style="font-size:40px;font-weight:700;color:var(--teal)"><?= fa($code) ?></div>
  <div style="font-size:15px"><?= e($titles[$code] ?? 'خطا') ?></div>
  <?php if ($msg && $msg !== ($titles[$code] ?? '')): ?><div class="small t3" style="line-height:1.9"><?= e($msg) ?></div><?php endif; ?>
  <a class="btn primary" href="<?= e(url(\App\Core\Auth::check() ? '/campaigns' : '/')) ?>"><?= \App\Core\Auth::check() ? 'بازگشت به کمپین‌ها' : 'بازگشت به صفحه‌ی اصلی' ?></a>
</div>
