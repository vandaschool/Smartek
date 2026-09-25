<?php
/** @var array $accs @var string $mode @var array $camps @var array $keys @var ?string $newKey @var string $tier */
use App\Services\Connectors;

$canConn = can('connect');
$soon = [['ادورج', 'هدف‌گیری و نمایش تبلیغات — در نسخه‌ی بعد'], ['افیلیو', 'شبکه‌ی همکاری در فروش — در نسخه‌ی بعد']];
?>
<div class="col gap20">
  <div class="head">
    <h1 class="title">اتصال داده</h1>
    <p class="lead">هر منبع، یک یا دو جدول از سه جدول مشترک را پر می‌کند. پس از اتصال، پایش روزانه و پیش‌نویس نتیجه خودکار ساخته می‌شود؛ تا آن زمان داده دستی وارد می‌شود.</p>
  </div>
  <?php if ($mode === 'mock'): ?>
    <div class="callout info small">حالت آزمایشی اتصال فعال است: داده‌ی روزانه از روی پیش‌بینی خود کمپین ساخته می‌شود تا کل حلقه قابل آزمون باشد. مدیر سامانه می‌تواند در «مدیریت سامانه» حالت واقعی را فعال کند.</div>
  <?php endif; ?>
  <?php if ($tier === 'trial'): ?>
    <div class="callout warn small">اتصال ادتریس و اینترک در پلن «رشد» فعال است. <a href="<?= e(url('/billing')) ?>">مشاهده‌ی پلن‌ها</a></div>
  <?php endif; ?>

  <div class="grid" style="--min:290px">
    <?php foreach (Connectors::KINDS as $k => $it): $a = $accs[$k] ?? null; $on = $a && $a['status'] === 'connected'; $err = $a && $a['status'] === 'error'; ?>
      <div class="card" style="gap:13px">
        <div class="row" style="gap:12px;flex-wrap:nowrap">
          <div style="width:44px;height:44px;border-radius:10px;background:var(--soft);border:1px solid var(--bd2);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--primary);flex:none"><?= e(mb_substr($it['name'], 0, 1)) ?></div>
          <div class="col grow" style="gap:3px">
            <div style="font-size:15px;font-weight:600"><?= e($it['name']) ?></div>
            <?php if ($on): ?><div class="xs" style="color:var(--teal);font-weight:600">متصل<?= $a['last_sync_at'] ? ' · آخرین همگام‌سازی ' . e(jdt($a['last_sync_at'])) : '' ?></div>
            <?php elseif ($err): ?><div class="xs" style="color:var(--bad-s);font-weight:600">خطا در همگام‌سازی</div>
            <?php else: ?><div class="xs muted">متصل نیست</div><?php endif; ?>
          </div>
        </div>
        <div class="small t3" style="line-height:1.85"><?= e($it['note']) ?></div>
        <?php if ($a && $a['last_error'] !== ''): ?><div class="callout bad xs"><?= e($a['last_error']) ?></div><?php endif; ?>
        <?php if ($on): ?>
          <div class="row gap8">
            <form method="post" action="<?= e(url('/connect/' . $k . '/sync')) ?>"><?= csrf_field() ?><button class="btn sm"<?= perm('connect') ?>>همگام‌سازی اکنون</button></form>
            <form method="post" action="<?= e(url('/connect/' . $k . '/disconnect')) ?>" data-confirm="اتصال <?= e($it['name']) ?> قطع شود؟ داده‌ی همگام‌شده باقی می‌ماند."><?= csrf_field() ?><button class="btn sm"<?= perm('connect') ?>>قطع اتصال</button></form>
          </div>
        <?php elseif ($canConn && $tier !== 'trial'): ?>
          <form method="post" action="<?= e(url('/connect/' . $k)) ?>" class="col gap8"><?= csrf_field() ?>
            <label class="k small" for="key-<?= e($k) ?>">کلید API <?= e($it['name']) ?></label>
            <input id="key-<?= e($k) ?>" class="inp ltr mono" name="api_key" required autocomplete="off" placeholder="API key">
            <?php if ($mode === 'live'): ?><input class="inp ltr sm" name="base_url" placeholder="https://api.example.com/v1 (اختیاری)"><?php endif; ?>
            <button class="btn primary" style="align-self:start">اتصال</button>
          </form>
        <?php else: ?>
          <button class="btn primary" disabled<?= $canConn ? ' title="نیازمند پلن رشد"' : perm('connect') ?>>اتصال</button>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php foreach ($soon as $s): ?>
      <div class="card" style="gap:13px">
        <div class="row" style="gap:12px;flex-wrap:nowrap">
          <div style="width:44px;height:44px;border-radius:10px;background:var(--soft);border:1px solid var(--bd2);display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--muted);flex:none"><?= e(mb_substr($s[0], 0, 1)) ?></div>
          <div class="col grow" style="gap:3px"><div style="font-size:15px;font-weight:600"><?= e($s[0]) ?></div><div class="xs" style="color:var(--warn-s)">به‌زودی</div></div>
        </div>
        <div class="small t3" style="line-height:1.85"><?= e($s[1]) ?></div>
        <button class="btn" style="border-style:dashed;background:var(--soft);color:var(--faint);cursor:default" disabled>در نسخه‌ی بعد</button>
      </div>
    <?php endforeach; ?>
  </div>

  <?php $anyOn = array_filter($accs, static fn ($a) => $a && $a['status'] === 'connected'); ?>
  <?php if ($anyOn): ?>
  <div class="card flush">
    <div style="padding:14px 18px;border-bottom:1px solid var(--bd2)"><div class="h2">اتصال کمپین‌های در حال اجرا</div><div class="hint">شناسه‌ی کمپین در سامانه‌ی مبدأ را وارد کنید تا پایش روزانه و پیش‌نویس نتیجه خودکار ساخته شود.</div></div>
    <?php if (!$camps): ?><div class="empty" style="border:none">کمپین در حال اجرایی وجود ندارد.</div><?php endif; ?>
    <?php foreach ($camps as $c): ?>
      <form method="post" action="<?= e(url('/c/' . $c['id'] . '/link')) ?>" class="list-row"><?= csrf_field() ?>
        <div class="grow" style="min-width:180px"><div style="font-weight:500"><?= e($c['name']) ?></div><div class="idlbl"><?= e($c['code']) ?><?= $c['links'] ? ' · ' . e($c['links']) : '' ?></div></div>
        <select class="inp sm" name="kind" style="width:120px"><?php foreach ($anyOn as $k => $_): ?><option value="<?= e($k) ?>"><?= e(Connectors::KINDS[$k]['name']) ?></option><?php endforeach; ?></select>
        <input class="inp sm ltr" name="external_id" placeholder="external campaign id" style="width:190px">
        <button class="btn sm"<?= perm('plan') ?>>اتصال کمپین</button>
      </form>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="grid" style="--min:300px">
    <div class="card" id="api">
      <div class="h2">کلید دسترسی API</div>
      <div class="small t3" style="line-height:1.85">برای نوشتن نتیجه‌ی کمپین از سمت سرور شما در ایستگاه ۱، و خواندن زنجیره‌ی شناسه.</div>
      <?php if ($newKey): ?>
        <div class="callout ok small col gap6"><div>کلید ساخته شد. فقط همین یک بار نمایش داده می‌شود؛ آن را کپی و جایی امن نگه دارید.</div>
          <div class="row gap8"><input class="inp ltr mono sm grow" value="<?= e($newKey) ?>" readonly id="newkey"><button type="button" class="btn sm" data-copy="<?= e($newKey) ?>">کپی</button></div></div>
      <?php endif; ?>
      <?php foreach ($keys as $k): ?>
        <div class="row" style="border-top:1px solid var(--bd3);padding-top:8px">
          <div class="grow small"><?= e($k['name']) ?> <span class="mono xs muted">sk_loop_…<?= e($k['last4']) ?></span></div>
          <div class="xs muted"><?= $k['last_used_at'] ? 'آخرین استفاده ' . e(jdt($k['last_used_at'])) : 'استفاده نشده' ?></div>
          <?php if ($canConn): ?><form method="post" action="<?= e(url('/api-keys/' . $k['id'] . '/revoke')) ?>" data-confirm="این کلید باطل شود؟"><?= csrf_field() ?><button class="btn link danger xs">لغو</button></form><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <form method="post" action="<?= e(url('/api-keys')) ?>" class="row gap8"><?= csrf_field() ?>
        <input class="inp grow" name="name" placeholder="نام کلید (مثلاً سرور فروشگاه)" style="min-width:160px"<?= $canConn ? '' : ' disabled' ?>>
        <button class="btn"<?= perm('connect') ?>>ساختن کلید</button>
      </form>
      <details class="small"><summary class="t2" style="cursor:pointer">نمونه‌ی فراخوانی</summary>
        <pre class="formula" style="white-space:pre-wrap;font-size:11.5px">curl -X POST <?= e(url('/api/v1/results', [], true)) ?> \
  -H "Authorization: Bearer sk_loop_…" -H "Content-Type: application/json" \
  -d '{"plan_code":"plan-1405-101","rows":[{"channel":"گوگل","segment":"کاربر جدید","spend":120000000,"installs":2400,"conversions":180}],"fraud_pct":3,"window_days":7}'</pre>
        <pre class="formula" style="font-size:11.5px">GET <?= e(url('/api/v1/campaigns/plan-1405-101', [], true)) ?></pre>
      </details>
    </div>

    <div class="card">
      <div class="h2">ورود دستی داده</div>
      <div class="small t3" style="line-height:1.85">تا زمان اتصال، سه جدول مشترک را روی صفحه‌ی داده‌ها ویرایش کنید.</div>
      <div class="col gap8">
        <div class="row" style="justify-content:space-between;border-top:1px solid var(--bd3);padding-top:8px;font-size:13px"><span class="mono muted">rates</span><span class="t2">نرخ کانال × سگمنت</span></div>
        <div class="row" style="justify-content:space-between;border-top:1px solid var(--bd3);padding-top:8px;font-size:13px"><span class="mono muted">merchant_profile</span><span class="t2">پروفایل مرچنت</span></div>
        <div class="row" style="justify-content:space-between;border-top:1px solid var(--bd3);padding-top:8px;font-size:13px"><span class="mono muted">campaign_history</span><span class="t2">کمپین‌های گذشته</span></div>
      </div>
      <a class="btn sm" href="<?= e(url('/data')) ?>" style="align-self:start">رفتن به داده‌ها</a>
    </div>
  </div>
</div>
