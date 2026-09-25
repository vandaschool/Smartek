<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <div style="font-size:16px;font-weight:600">ورود دومرحله‌ای</div>
    <div class="small t2" style="line-height:1.85">کد ۶ رقمی اپ احراز هویت (Google Authenticator، Authy و مانند آن) یا یکی از کدهای بازیابی را وارد کنید.</div>
    <form method="post" action="<?= e(url('/2fa')) ?>" class="col gap12"><?= csrf_field() ?>
      <input class="inp ltr" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="123456" required autofocus style="font-size:18px;letter-spacing:4px;text-align:center">
      <?php if ($error): ?><div class="callout bad"><?= e($error) ?></div><?php endif; ?>
      <button class="btn primary lg">تأیید و ورود</button>
    </form>
    <a href="<?= e(url('/login')) ?>" class="small" style="color:var(--primary)">← بازگشت به ورود</a>
  </div>
</div>
