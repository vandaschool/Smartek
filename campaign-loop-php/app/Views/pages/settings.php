<?php
/** @var array $u @var array $ws @var array $prefs */
use App\Core\Auth;

$canWs = can('team');
$off = (array) ($prefs['email_off'] ?? []);
$mails = ['pace_alert' => 'هشدار پایش (عقب‌ماندن از برنامه)', 'campaign_ending' => 'یادآور پایان کمپین و ثبت نتیجه', 'monthly_report' => 'گزارش ماهانه‌ی حلقه‌ها'];
?>
<div class="col gap20">
  <div class="head">
    <h1 class="title">تنظیمات</h1>
    <p class="lead">فضای کاری، حساب کاربری و مدیریت داده‌ی این نمونه.</p>
  </div>

  <div class="grid" style="--min:300px">
    <form method="post" action="<?= e(url('/settings/workspace')) ?>" class="card" style="gap:13px"><?= csrf_field() ?>
      <div class="h2">فضای کاری</div>
      <div class="frow"><label class="k" for="w-n">نام فضای کاری</label><input id="w-n" class="inp sm" style="width:200px" name="name" value="<?= e($ws['name']) ?>"<?= $canWs ? '' : ' disabled' ?>></div>
      <div class="frow"><label class="k" for="w-c">واحد پول</label><input id="w-c" class="inp sm" style="width:200px" name="currency" value="<?= e($ws['currency']) ?>"<?= $canWs ? '' : ' disabled' ?>></div>
      <div class="frow"><label class="k" for="w-t">منطقه‌ی زمانی</label><input id="w-t" class="inp sm" style="width:200px" name="tz" value="<?= e($ws['tz']) ?>"<?= $canWs ? '' : ' disabled' ?>></div>
      <label class="check"><input type="checkbox" name="ai_enabled" value="1"<?= !empty($ws['ai_enabled']) ? ' checked' : '' ?><?= $canWs ? '' : ' disabled' ?>> توضیح هوشمند (لایه‌ی روایت با هوش مصنوعی) در این فضای کاری فعال باشد</label>
      <div class="hint">با خاموش کردن، همه‌ی متن‌ها از قالب‌های قطعی ساخته می‌شوند؛ اعداد در هر دو حالت از موتور می‌آیند.</div>
      <?php if ($canWs): ?><button class="btn primary" style="align-self:start">ذخیره</button><?php else: ?><div class="small" style="color:var(--warn-t)">فقط مالک می‌تواند تنظیمات فضای کاری را تغییر دهد.</div><?php endif; ?>
    </form>

    <form method="post" action="<?= e(url('/settings/account')) ?>" class="card" style="gap:13px" id="notif"><?= csrf_field() ?>
      <div class="h2">حساب کاربری</div>
      <div class="frow"><label class="k" for="a-n">نام کاربر</label><input id="a-n" class="inp sm" style="width:200px" name="name" value="<?= e($u['name']) ?>" minlength="3"></div>
      <div class="row" style="border-top:1px solid var(--bd3);padding-top:10px"><div class="grow small muted">ایمیل</div><div class="small ltr"><?= e($u['email']) ?></div></div>
      <div class="row" style="border-top:1px solid var(--bd3);padding-top:10px"><div class="grow small muted">کسب‌وکار</div><div class="small"><?= e($u['company'] ?: '—') ?></div></div>
      <div class="row" style="border-top:1px solid var(--bd3);padding-top:10px"><div class="grow small muted">نقش</div><div class="small"><?= e(Auth::roleLabel()) ?></div></div>
      <div class="col gap6" style="border-top:1px solid var(--bd3);padding-top:10px">
        <div class="small t2" style="font-weight:600">ایمیل‌های اعلان</div>
        <?php foreach ($mails as $k => $l): ?><label class="check small"><input type="checkbox" name="mail_<?= e($k) ?>" value="1"<?= in_array($k, $off, true) ? '' : ' checked' ?>> <?= e($l) ?></label><?php endforeach; ?>
      </div>
      <div class="row gap8"><button class="btn primary">ذخیره</button><a class="btn" href="<?= e(url('/settings/password')) ?>">تغییر رمز عبور</a><a class="btn" href="<?= e(url('/security')) ?>">امنیت</a></div>
    </form>
  </div>
  <form method="post" action="<?= e(url('/logout')) ?>"><?= csrf_field() ?><button class="btn">خروج از حساب</button></form>

  <?php if ($canWs): ?>
  <div class="card" style="border-color:#f3d3ce;gap:11px">
    <div style="font-size:15px;font-weight:600;color:#a8342a">بازنشانی داده</div>
    <div class="small t3" style="line-height:1.9">همه‌ی نرخ‌ها، تاریخچه، طرح‌ها و دفترچه به داده‌ی دموی اولیه برمی‌گردند. برای تمرین دمو پیش از دفاع مفید است.</div>
    <form method="post" action="<?= e(url('/settings/reset')) ?>" data-confirm="همه‌ی نرخ‌ها، تاریخچه، طرح‌ها و دفترچه پاک و با داده‌ی دمو جایگزین می‌شوند. این کار برگشت‌پذیر نیست. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn danger-strong">بازنشانی به داده‌ی دمو</button></form>
  </div>
  <?php endif; ?>
</div>
