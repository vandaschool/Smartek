<div class="wrap" style="margin:0 auto">
  <div class="row between no-print" style="margin-bottom:14px"><div class="small t3">نسخه‌ی فقط‌خواندنی گزارش · Campaign Loop</div><button type="button" class="btn sm" data-print>چاپ / PDF</button></div>
  <?php if (!$report): ?><div class="empty">گزارشی برای نمایش نیست.</div><?php else: include APP_ROOT . '/app/Views/partials/report_body.php'; endif; ?>
</div>
