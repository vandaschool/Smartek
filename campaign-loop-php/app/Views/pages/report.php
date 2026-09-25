<?php use App\Core\Auth; ?>
<div class="col gap18">
  <div class="row end gap14 no-print">
    <div class="head grow" style="min-width:260px"><h1 class="title">گزارش کمپین</h1><p class="lead">خروجی نهایی سرویس: یک برگ که تصمیم، پیش‌بینی، نتیجه و اصلاح نرخ را با زنجیره‌ی شناسه کنار هم می‌گذارد.</p></div>
    <?php if ($report): ?>
      <a class="btn md" href="<?= e(url('/c/' . $c['id'] . '/report.csv')) ?>">خروجی CSV</a>
      <form method="post" action="<?= e(url('/c/' . $c['id'] . '/share')) ?>" class="inline"><?= csrf_field() ?><button class="btn md"<?= Auth::verified() ? '' : ' disabled title="ابتدا ایمیل خود را تأیید کنید"' ?>>ساختن لینک اشتراک</button></form>
      <button type="button" class="btn md" data-print>چاپ / PDF</button>
      <?php if (!$report['closed'] && $report['hasResult']): ?>
        <form method="post" action="<?= e(url('/c/' . $c['id'] . '/close')) ?>" class="inline" data-confirm="کمپین بسته و در دفترچه‌ی دیدگاه‌ها ثبت می‌شود. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn primary md"<?= perm('result') ?>>بستن کمپین و ثبت در دفترچه</button></form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
  <?php if ($share): ?><div class="callout ok row no-print"><span class="grow ltr" style="text-align:left;word-break:break-all"><?= e($share) ?></span><button type="button" class="btn sm" data-copy="<?= e($share) ?>">کپی لینک گزارش</button></div><?php endif; ?>
  <?php if (!$report): ?>
    <div class="empty">گزارشی برای نمایش نیست. ابتدا یک دیدگاه را انتخاب و طرح را ثبت کنید.</div>
  <?php else: ?>
    <?php if (!$report['hasResult']): ?><div class="callout info no-print">نتیجه‌ی واقعی هنوز ثبت نشده؛ برای بستن حلقه به <a href="<?= e(url('/c/' . $c['id'] . '/verify')) ?>">راستی‌آزمایی</a> بروید.</div><?php endif; ?>
    <?php if ($report['closed']): ?><div class="callout ok no-print">این کمپین بسته و در <a href="<?= e(url('/log')) ?>">دفترچه‌ی دیدگاه‌ها</a> ثبت شده است.</div><?php endif; ?>
    <?php include APP_ROOT . '/app/Views/partials/report_body.php'; ?>
  <?php endif; ?>
</div>
