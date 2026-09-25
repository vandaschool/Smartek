<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <div style="font-size:16px;font-weight:600">تعیین رمز تازه</div>
    <?php if (!$valid): ?>
      <div class="callout bad">این لینک نامعتبر یا منقضی است (اعتبار ۳۰ دقیقه، یک‌بار مصرف).</div>
      <a class="btn primary" href="<?= e(url('/forgot')) ?>">درخواست لینک تازه</a>
    <?php else: ?>
      <form method="post" action="<?= e(url('/reset/' . $token)) ?>" class="col gap12"><?= csrf_field() ?>
        <div class="field"><label>رمز تازه</label><input class="inp ltr" type="password" name="password" required autocomplete="new-password" placeholder="حداقل ۸ کاراکتر، حرف و عدد"></div>
        <div class="field"><label>تکرار رمز</label><input class="inp ltr" type="password" name="password2" required autocomplete="new-password"></div>
        <?php if ($error): ?><div class="callout bad"><?= e($error) ?></div><?php endif; ?>
        <button class="btn primary lg">ثبت رمز تازه</button>
      </form>
    <?php endif; ?>
  </div>
</div>
