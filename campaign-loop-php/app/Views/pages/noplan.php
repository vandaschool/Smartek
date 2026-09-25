<div class="col gap20">
  <div class="head"><h1 class="title"><?= e(['sim' => 'قیف پیش‌بینی با بازه', 'pace' => 'پایش حین اجرا'][$pageLabel] ?? 'طرحی ثبت نشده') ?></h1></div>
  <div class="empty">طرح و پیش‌بینی ثبت نشده. <a class="btn link" style="color:var(--teal)" href="<?= e(url('/c/' . $c['id'] . '/insights')) ?>">برو به اینسایت‌ها</a></div>
</div>
