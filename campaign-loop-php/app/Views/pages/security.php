<?php
/** @var array $u @var array $sessions @var ?string $setup @var ?array $codes @var array $requests */
use App\Core\Auth;
use App\Core\Totp;

$on = !empty($u['totp_enabled_at']);
?>
<div class="col gap18" style="max-width:860px">
  <div class="head">
    <h1 class="title">امنیت</h1>
    <p class="lead">ورود دومرحله‌ای، نشست‌های فعال و حریم خصوصی داده‌ی مرچنت.</p>
  </div>

  <div class="card" id="twofa">
    <div class="row" style="gap:14px">
      <div class="col grow" style="gap:4px;min-width:220px"><div style="font-size:15px;font-weight:600">ورود دومرحله‌ای</div><div class="small t3">کد یک‌بارمصرف از اپ احراز هویت (Google Authenticator، Microsoft Authenticator، …)</div></div>
      <?php if ($on): ?>
        <span class="badge teal">فعال</span>
      <?php elseif (!$setup): ?>
        <form method="post" action="<?= e(url('/security/2fa/setup')) ?>"><?= csrf_field() ?><button class="btn primary">فعال‌سازی</button></form>
      <?php endif; ?>
    </div>
    <?php if ($codes): ?>
      <div class="callout ok small col gap8">
        <div>کدهای بازیابی (هر کد یک بار؛ اگر گوشی در دسترس نبود به‌جای کد اپ وارد کنید):</div>
        <div class="mono" style="display:grid;grid-template-columns:repeat(4,auto);gap:6px 14px;justify-content:start"><?php foreach ($codes as $c): ?><span><?= e($c) ?></span><?php endforeach; ?></div>
        <button type="button" class="btn sm" style="align-self:start" data-copy="<?= e(implode("\n", $codes)) ?>">کپی کدها</button>
      </div>
    <?php endif; ?>
    <?php if ($on): ?>
      <form method="post" action="<?= e(url('/security/2fa/disable')) ?>" class="row gap8" data-confirm="ورود دومرحله‌ای غیرفعال شود؟"><?= csrf_field() ?>
        <input class="inp sm num" style="width:140px" name="code" inputmode="numeric" autocomplete="one-time-code" placeholder="کد ۶ رقمی" aria-label="کد فعلی اپ">
        <span class="xs muted">یا</span>
        <input class="inp sm" style="width:160px" type="password" name="password" autocomplete="current-password" placeholder="رمز عبور" aria-label="رمز عبور">
        <button class="btn">غیرفعال‌سازی</button>
      </form>
    <?php elseif ($setup): ?>
      <div class="row start" style="gap:18px;align-items:flex-start">
        <div data-qr="<?= e(Totp::uri($setup, (string) $u['email'])) ?>" style="width:180px;min-height:180px;border:1px solid var(--bd2);border-radius:8px;display:flex;align-items:center;justify-content:center" aria-label="QR"></div>
        <div class="col gap8 grow" style="min-width:220px">
          <div class="small t2" style="line-height:1.9">۱. QR را با اپ احراز هویت اسکن کنید (یا کلید زیر را دستی وارد کنید).<br>۲. کد ۶ رقمی نمایش‌داده‌شده را وارد کنید.</div>
          <div class="mono small" style="word-break:break-all"><?= e(trim(chunk_split($setup, 4, ' '))) ?></div>
          <form method="post" action="<?= e(url('/security/2fa/enable')) ?>" class="row gap8"><?= csrf_field() ?>
            <input class="inp num" style="width:150px" name="code" inputmode="numeric" autocomplete="one-time-code" required placeholder="۱۲۳۴۵۶" aria-label="کد ۶ رقمی">
            <button class="btn primary">تأیید و فعال‌سازی</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div class="card flush">
    <div class="h2" style="padding:14px 18px;border-bottom:1px solid var(--bd2)">نشست‌های فعال</div>
    <?php foreach ($sessions as $s): $now = $s['id'] === Auth::sessionId(); ?>
      <div class="row" style="gap:12px;padding:12px 18px;border-bottom:1px solid var(--bd3);font-size:13px">
        <div class="grow"><?= e($s['device'] ?: 'مرورگر') ?> · <span class="mono xs"><?= e($s['ip']) ?></span> · <span class="xs muted"><?= e(jdt($s['last_seen_at'])) ?></span></div>
        <?php if ($now): ?><span class="xs" style="color:var(--teal-t)">همین دستگاه</span>
        <?php else: ?><form method="post" action="<?= e(url('/security/sessions/' . $s['id'] . '/end')) ?>"><?= csrf_field() ?><button class="btn link danger" style="font-size:12.5px">پایان نشست</button></form><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <div class="card" style="gap:8px">
    <div style="font-size:15px;font-weight:600">حریم خصوصی داده</div>
    <div style="font-size:13px;color:var(--t2);line-height:1.95">داده‌ی هر مرچنت فقط در فضای کاری خودش استفاده می‌شود. مقایسه با صنعت فقط از آمار تجمیعی و بی‌نام ساخته می‌شود. کلید API و اعتبار اتصال‌ها رمزگذاری‌شده ذخیره می‌شوند.</div>
    <div class="row gap8">
      <?php if (can('editData')): ?><a class="btn" href="<?= e(url('/security/export')) ?>">دریافت نسخه‌ی داده</a><?php endif; ?>
      <?php if (can('team')): ?><form method="post" action="<?= e(url('/security/delete-request')) ?>" data-confirm="همه‌ی داده‌ی این فضای کاری ظرف ۳۰ روز پاک می‌شود. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn danger">درخواست حذف داده</button></form><?php endif; ?>
      <a class="btn link" href="<?= e(url('/legal/privacy')) ?>" style="padding:9px 15px">شرایط استفاده و حریم خصوصی</a>
    </div>
    <?php foreach ($requests as $r): ?>
      <div class="xs muted"><?= $r['type'] === 'delete' ? 'درخواست حذف' : 'دریافت نسخه' ?> · <?= e(jdt($r['created_at'])) ?> · <?= $r['status'] === 'done' ? 'انجام شد' : ($r['status'] === 'cancelled' ? 'لغو شد' : 'در انتظار') ?></div>
    <?php endforeach; ?>
  </div>
</div>
