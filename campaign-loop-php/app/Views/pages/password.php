<?php use App\Core\Auth; $must = !empty(Auth::user()['must_change_password']); ?>
<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <div style="font-size:16px;font-weight:600">تغییر رمز عبور</div>
    <?php if ($must): ?><div class="callout warn">برای امنیت حساب، پیش از ادامه رمز پیش‌فرض را عوض کنید.</div><?php endif; ?>
    <form method="post" action="<?= e(url('/settings/password')) ?>" class="col gap12"><?= csrf_field() ?>
      <div class="field"><label>رمز فعلی</label><input class="inp ltr" type="password" name="current" required autocomplete="current-password"></div>
      <div class="field"><label>رمز تازه</label><input class="inp ltr" type="password" name="password" required autocomplete="new-password" placeholder="حداقل ۸ کاراکتر، حرف و عدد"></div>
      <div class="field"><label>تکرار رمز تازه</label><input class="inp ltr" type="password" name="password2" required autocomplete="new-password"></div>
      <?php if ($error): ?><div class="callout bad"><?= e($error) ?></div><?php endif; ?>
      <button class="btn primary lg">ثبت رمز تازه</button>
    </form>
    <?php if (!$must): ?><a class="small" href="<?= e(url('/settings')) ?>" style="color:var(--primary)">← بازگشت</a><?php endif; ?>
  </div>
</div>
