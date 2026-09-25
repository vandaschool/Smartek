<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <div style="font-size:16px;font-weight:600">بازیابی رمز عبور</div>
    <?php if (!$sent): ?>
      <form method="post" action="<?= e(url('/forgot')) ?>" class="col gap12"><?= csrf_field() ?>
        <div class="small t2" style="line-height:1.85">ایمیل کاری حساب را وارد کنید تا لینک تعیین رمز تازه برایتان ارسال شود. لینک ۳۰ دقیقه اعتبار دارد.</div>
        <input class="inp ltr" type="email" name="email" value="<?= e($email) ?>" placeholder="name@company.ir" required>
        <?php if ($error): ?><div class="callout bad"><?= e($error) ?></div><?php endif; ?>
        <button class="btn primary lg">ارسال لینک بازیابی</button>
      </form>
    <?php else: ?>
      <div class="callout ok">اگر حسابی با <span class="ltr"><?= e($email) ?></span> وجود داشته باشد، لینک بازیابی ارسال شد. پوشه‌ی هرزنامه را هم ببینید.</div>
    <?php endif; ?>
    <a href="<?= e(url('/login')) ?>" class="small" style="color:var(--primary)">← بازگشت به ورود</a>
  </div>
</div>
