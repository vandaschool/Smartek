<?php
/** @var string $tier @var ?string $until @var int $usage @var array $payments @var string $provider */
use App\Core\Settings;

$expired = $tier !== 'trial' && $until && $until < date('Y-m-d');
$eff = $expired ? 'trial' : $tier;
$price = Settings::get('price_growth_label', '۴٫۹ میلیون تومان / ماه');
$tiers = [
    'trial' => ['آزمایشی', 'رایگان', '۳ کمپین در ماه · ۱ کاربر · داده‌ی دستی و CSV', 'ده دیدگاه، شبیه‌سازی، راستی‌آزمایی'],
    'growth' => ['رشد', $price, 'کمپین نامحدود · ۵ کاربر · اتصال ادتریس و اینترک', 'پایش خودکار، اعلان، گزارش ماهانه'],
    'enterprise' => ['سازمانی', 'تماس با فروش', 'چند فضای کاری · SSO · SLA', 'اکانت‌منیجر، قواعد اختصاصی، API کامل'],
];
?>
<div class="col gap18">
  <div class="head">
    <h1 class="title">پلن و صورتحساب</h1>
    <p class="lead">مصرف این ماه: <?= fa($usage) ?><?= $eff === 'trial' ? ' از ۳ کمپین' : ' کمپین' ?><?= $eff !== 'trial' && $until ? ' · اعتبار تا ' . e(jdate($until)) : '' ?></p>
  </div>
  <?php if ($expired): ?><div class="callout warn small">اعتبار پلن «<?= e($tiers[$tier][0] ?? $tier) ?>» در <?= e(jdate($until)) ?> تمام شده و فضای کاری به محدودیت پلن آزمایشی برگشته است.</div><?php endif; ?>
  <div class="grid" style="--min:260px;gap:14px">
    <?php foreach ($tiers as $k => $t): ?>
      <div class="card p20" style="gap:10px">
        <div style="font-size:17px;font-weight:700"><?= e($t[0]) ?></div>
        <div style="font-size:15px;color:var(--primary);font-weight:600"><?= e($t[1]) ?></div>
        <div style="font-size:13px;color:var(--t2);line-height:1.85"><?= e($t[2]) ?></div>
        <div class="small t3" style="line-height:1.85"><?= e($t[3]) ?></div>
        <?php if ($eff === $k): ?>
          <div style="margin-top:auto;font-size:13px;color:var(--teal-t);background:var(--teal-bg);border-radius:6px;padding:9px;text-align:center">پلن فعلی</div>
        <?php elseif ($k !== 'trial'): ?>
          <form method="post" action="<?= e(url('/billing/checkout')) ?>" style="margin-top:auto"><?= csrf_field() ?><input type="hidden" name="tier" value="<?= e($k) ?>">
            <button class="btn primary" style="width:100%"<?= perm('billing') ?>><?= $k === 'growth' && $tier === 'growth' ? 'تمدید ۳۰ روزه' : 'انتخاب این پلن' ?></button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="small t3"><?= $provider === 'sandbox' ? 'درگاه پرداخت در حالت آزمایشی است: انتخاب پلن بدون پرداخت واقعی فعال می‌شود. مدیر سامانه می‌تواند زرین‌پال را فعال کند.' : 'پرداخت از طریق درگاه امن زرین‌پال انجام می‌شود. مبلغ به ریال به درگاه ارسال می‌شود.' ?></div>
  <?php if ($payments): ?>
    <div class="card flush">
      <div class="h2" style="padding:14px 18px;border-bottom:1px solid var(--bd2)">پرداخت‌ها</div>
      <?php foreach ($payments as $p): ?>
        <div class="list-row">
          <div style="min-width:110px" class="small muted"><?= e(jdt($p['created_at'])) ?></div>
          <div class="grow"><?= e($tiers[$p['tier']][0] ?? $p['tier']) ?> · <?= e(money((int) $p['amount_rial'] / 10)) ?> تومان</div>
          <div class="mono xs muted"><?= e($p['ref_id']) ?></div>
          <span class="badge sm <?= $p['status'] === 'paid' ? 'teal' : ($p['status'] === 'failed' ? 'bad' : 'gray') ?>"><?= $p['status'] === 'paid' ? 'پرداخت شد' : ($p['status'] === 'failed' ? 'ناموفق' : 'در انتظار') ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
