<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <div style="font-size:16px;font-weight:600">فضای کاری تازه</div>
    <div class="small t2" style="line-height:1.85">هر مرچنت یک فضای کاری دارد: نرخ‌ها، کمپین‌ها و دفترچه‌ی دیدگاه‌ها داخل آن جدا نگه داشته می‌شوند.</div>
    <form method="post" action="<?= e(url('/workspace/new')) ?>" class="col gap12"><?= csrf_field() ?>
      <div class="field"><label>نام فضای کاری</label><input class="inp" name="name" required placeholder="مثلاً فروشگاه اصلی"></div>
      <label class="check"><input type="checkbox" name="demo" value="1" checked> شروع با داده‌ی دمو</label>
      <button class="btn primary lg">ساختن فضای کاری</button>
    </form>
    <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn link" style="font-weight:400">خروج از حساب</button></form>
  </div>
</div>
