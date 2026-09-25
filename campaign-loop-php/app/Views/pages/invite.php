<?php use App\Core\Auth; ?>
<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <?php if (!$inv): ?>
      <div style="font-size:16px;font-weight:600">دعوت نامعتبر</div>
      <div class="callout bad">این دعوت منقضی، لغو یا قبلاً پذیرفته شده است. از مالک فضای کاری بخواهید دوباره دعوت کند.</div>
      <a class="btn" href="<?= e(url('/login')) ?>">ورود</a>
    <?php else: ?>
      <div style="font-size:16px;font-weight:600">به تیم <?= e($inv['ws_name']) ?> بپیوندید</div>
      <div class="small t2" style="line-height:1.9">شما با نقش <b><?= e(Auth::ROLES[$inv['role']] ?? $inv['role']) ?></b> و ایمیل <span class="ltr"><?= e($inv['email']) ?></span> دعوت شده‌اید.</div>
      <form method="post" action="<?= e(url('/invite/' . $token)) ?>" class="col gap12"><?= csrf_field() ?>
        <?php if (!$exists): ?>
          <div class="field"><label>نام و نام خانوادگی</label><input class="inp" name="name" required></div>
          <div class="field"><label>رمز عبور</label><input class="inp ltr" type="password" name="password" required placeholder="حداقل ۸ کاراکتر، حرف و عدد" autocomplete="new-password"></div>
        <?php endif; ?>
        <?php if ($error): ?><div class="callout bad"><?= e($error) ?></div><?php endif; ?>
        <button class="btn primary lg"><?= $exists ? 'پذیرش دعوت' : 'ساختن حساب و پذیرش دعوت' ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>
