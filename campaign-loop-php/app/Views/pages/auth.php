<?php
/** @var string $mode @var array $old @var string $error */
use App\Core\Settings;

$isSignup = $mode === 'signup';
?>
<div class="auth-grid">
  <?php include APP_ROOT . '/app/Views/partials/pitch.php'; ?>
  <div class="auth-card">
    <div class="auth-tabs" role="tablist">
      <a href="<?= e(url('/signup')) ?>" class="<?= $isSignup ? 'on' : '' ?>" role="tab" aria-selected="<?= $isSignup ? 'true' : 'false' ?>">ثبت‌نام</a>
      <a href="<?= e(url('/login')) ?>" class="<?= $isSignup ? '' : 'on' ?>" role="tab" aria-selected="<?= $isSignup ? 'false' : 'true' ?>">ورود</a>
    </div>
    <form method="post" action="<?= e(url($isSignup ? '/signup' : '/login')) ?>" class="col gap16" novalidate>
      <?= csrf_field() ?>
      <?php foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $u): ?><input type="hidden" name="<?= $u ?>" value="<?= e($_GET[$u] ?? '') ?>"><?php endforeach; ?>
      <?php if ($isSignup): ?>
        <div class="field"><label for="f-name">نام و نام خانوادگی</label><input id="f-name" class="inp" name="name" value="<?= e($old['name'] ?? '') ?>" placeholder="مثلاً محسن علی‌پور" required autocomplete="name"></div>
        <div class="field"><label for="f-co">نام کسب‌وکار</label><input id="f-co" class="inp" name="company" value="<?= e($old['company'] ?? '') ?>" placeholder="مثلاً دیجی‌استایل" required autocomplete="organization"></div>
      <?php endif; ?>
      <div class="field"><label for="f-email">ایمیل کاری</label><input id="f-email" class="inp ltr" type="email" name="email" value="<?= e($old['email'] ?? '') ?>" placeholder="name@company.ir" required autocomplete="email"></div>
      <div class="field"><label for="f-pass">رمز عبور</label><input id="f-pass" class="inp ltr" type="password" name="password" placeholder="<?= $isSignup ? 'حداقل ۸ کاراکتر، حرف و عدد' : '' ?>" required autocomplete="<?= $isSignup ? 'new-password' : 'current-password' ?>"></div>
      <?php if ($error): ?><div class="callout bad" role="alert"><?= e($error) ?></div><?php endif; ?>
      <button class="btn primary lg"><?= $isSignup ? 'ساختن حساب و شروع' : 'ورود به حساب' ?></button>
      <a href="<?= e(url('/forgot')) ?>" class="small" style="color:var(--primary)">رمز عبور را فراموش کرده‌اید؟</a>
    </form>
    <?php if (Settings::bool('demo_enabled', true)): ?>
      <div class="or">یا</div>
      <form method="post" action="<?= e(url('/demo')) ?>"><?= csrf_field() ?><button class="btn md" style="width:100%">ورود به حالت دمو با داده‌ی نمونه</button></form>
      <div class="xs muted" style="line-height:1.85">حساب دمو یک فضای کاری جدا با داده‌ی نمونه می‌سازد و پس از ۷ روز خودکار پاک می‌شود.</div>
    <?php endif; ?>
    <?php if ($isSignup): ?><div class="xs muted">با ثبت‌نام، <a href="<?= e(url('/legal/terms')) ?>">شرایط استفاده</a> و <a href="<?= e(url('/legal/privacy')) ?>">حریم خصوصی</a> را می‌پذیرید.</div><?php endif; ?>
  </div>
</div>
<div style="max-width:980px;margin:0 auto;padding:44px 24px 40px" class="col gap16">
  <div class="small muted" style="font-weight:600">سرویس در چهار قدم</div>
  <div class="grid" style="--min:210px;gap:14px">
    <?php foreach ([
        ['۱', 'مشاور چنددیدگاهی', 'ده تابع هدف روی یک داده، ده اینسایت با شاهد عددی. انسان انتخاب می‌کند و دلیلش ثبت می‌شود.', 'plan_id'],
        ['۲', 'پیش‌بینی با بازه', 'قیف نصب، خرید و درآمد با کران بالا و پایین از نوسان تاریخی همان کانال — به‌همراه حکم ریسک و سودآوری.', 'sim_id'],
        ['۳', 'راستی‌آزمایی نتیجه', 'انحراف به یکی از پنج علت نسبت داده می‌شود؛ کالیبراسیون فقط در شاخه‌های معتبر اعمال می‌شود.', 'run_id'],
        ['۴', 'اصلاح حافظه‌ی سیستم', 'نرخ کانال با وزن نمونه به‌روز می‌شود و گزارش نهایی و دفترچه‌ی دیدگاه‌ها ساخته می‌شود.', 'calibration_id'],
    ] as [$n, $t, $x, $id]): ?>
      <div class="card" style="gap:9px">
        <div class="row gap8"><span class="stepnum"><?= $n ?></span><div style="font-size:14px;font-weight:600"><?= e($t) ?></div></div>
        <div class="small t3" style="line-height:1.85"><?= e($x) ?></div>
        <div class="src mt4"><?= e($id) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card small t2" style="line-height:1.95">هر عددی که می‌بینید شناسه‌ای دارد که به یک ردیف نرخ ثبت‌شده برمی‌گردد. لایه‌ی هوش مصنوعی (مدل زبانی از طریق متیس) فقط روایت می‌کند و حق ساختن عدد ندارد؛ سرور هر عدد خروجی مدل را با داده‌ی ورودی‌اش مقایسه می‌کند.</div>
</div>
