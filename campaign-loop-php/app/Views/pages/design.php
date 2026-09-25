<?php
use App\Core\Jalali;
use App\Engine\Engine;
use App\Services\Loop;

$chs = Loop::j($c['channels']) ?: array_values(array_diff(Engine::CHANNELS, $blocked));
$segs = Loop::j($c['segments']) ?: Engine::SEGMENTS;
$dis = ($locked || !can('plan')) ? ' disabled' : '';
?>
<form method="post" action="<?= e(url('/c/' . $c['id'] . '/design')) ?>" class="col gap22"><?= csrf_field() ?>
  <div class="head">
    <div class="eyebrow">ایستگاه ۲ — DESIGNER</div>
    <h1 class="title">طراحی — ورودی</h1>
    <p class="lead">هدف و قیدهای این کمپین را بدهید. خروجی ده اینسایت از ده دیدگاه است — و <strong>انسان انتخاب می‌کند</strong>.</p>
  </div>
  <?php if ($locked): ?><div class="callout warn">نتیجه‌ی واقعی این کمپین ثبت شده؛ ورودی طراحی فقط‌خواندنی است. برای طرح تازه یک کمپین جدید بسازید.</div><?php endif; ?>
  <?php foreach ($errors as $er): ?><div class="callout bad"><?= e($er) ?></div><?php endforeach; ?>
  <div class="grid" style="--min:300px;gap:18px">
    <div class="card p20" style="gap:16px">
      <div class="h2">هدف و بودجه</div>
      <div class="field"><label for="d-n">نام کمپین</label><input id="d-n" class="inp" name="name" value="<?= e($c['name']) ?>"<?= $dis ?>></div>
      <div class="field"><label for="d-gt">نوع هدف</label><select id="d-gt" class="inp" name="goalType"<?= $dis ?>><?php foreach (Engine::GOAL_TYPES as $g): ?><option<?= $c['goal_type'] === $g ? ' selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="d-gv">عدد هدف</label><input id="d-gv" class="inp num" name="goalValue" value="<?= e($c['goal_value']) ?>" inputmode="decimal"<?= $dis ?>></div>
      <div class="field"><label for="d-b">بودجه‌ی کل (تومان)</label><input id="d-b" class="inp num" name="budget" value="<?= e($c['budget']) ?>" inputmode="decimal" data-money="#d-bh"<?= $dis ?>><div class="hint" id="d-bh"></div>
        <?php if ($profile['budget']): ?><div class="hint">بودجه‌ی ماهانه‌ی پروفایل: <?= e(money($profile['budget'])) ?> تومان</div><?php endif; ?></div>
      <div class="row" style="gap:12px;align-items:stretch">
        <?php $name = 'from'; $value = Jalali::fromIso($c['date_from']); $label = 'از تاریخ'; include APP_ROOT . '/app/Views/partials/jdate.php'; ?>
        <?php $name = 'to'; $value = Jalali::fromIso($c['date_to']); $label = 'تا تاریخ'; include APP_ROOT . '/app/Views/partials/jdate.php'; ?>
      </div>
      <div class="hint">مدت فعلی: <?= fa(Loop::durationDays($c)) ?> روز<?= Loop::durationDays($c) < 7 ? ' — کمپین کوتاه‌تر از ۷ روز نمونه‌ی کمی می‌سازد.' : '' ?></div>
    </div>

    <div class="card p20" style="gap:18px">
      <div class="h2">قیدها</div>
      <div class="col gap8"><div class="lbl-s">کانال‌های مجاز</div>
        <div class="pills" data-min-one><?php foreach (Engine::CHANNELS as $ch): ?><label class="pill<?= in_array($ch, $chs, true) ? ' on' : '' ?>"><input type="checkbox" name="channels[]" value="<?= e($ch) ?>"<?= in_array($ch, $chs, true) ? ' checked' : '' ?><?= $dis ?>><?= e($ch) ?><?= in_array($ch, $blocked, true) ? ' ⛔' : '' ?></label><?php endforeach; ?></div></div>
      <div class="col gap8"><div class="lbl-s">سگمنت‌های مجاز</div>
        <div class="pills" data-min-one><?php foreach (Engine::SEGMENTS as $sg): ?><label class="pill<?= in_array($sg, $segs, true) ? ' on' : '' ?>"><input type="checkbox" name="segments[]" value="<?= e($sg) ?>"<?= in_array($sg, $segs, true) ? ' checked' : '' ?><?= $dis ?>><?= e($sg) ?></label><?php endforeach; ?></div></div>
      <div class="field"><label for="d-o">مناسبت در بازه‌ی کمپین</label><select id="d-o" class="inp" name="occasion"<?= $dis ?>>
        <?php foreach ($occasions as $o): ?><option value="<?= e($o['k']) ?>"<?= $c['occasion'] === $o['k'] ? ' selected' : '' ?>><?= e($o['k'] . ($o['lift'] ? ' · اثر خرید ' . spct($o['lift']) . ' / هزینه ' . spct($o['cpi']) : '')) ?></option><?php endforeach; ?></select></div>
      <div class="col gap8"><div class="lbl-s">ریسک‌پذیری</div>
        <div class="seg"><?php foreach (Engine::RISKS as $r): ?><label class="<?= $c['risk'] === $r ? 'on' : '' ?>"><input type="radio" name="risk" value="<?= e($r) ?>"<?= $c['risk'] === $r ? ' checked' : '' ?><?= $dis ?>><?= e($r) ?></label><?php endforeach; ?></div></div>
      <div class="callout soft small">حاشیه‌ی سود، هدف کسب‌وکار، CAC هدف و LTV خودکار از پروفایل مرچنت خوانده می‌شوند.</div>
      <?php if (!$locked): ?><button class="btn primary lg"<?= perm('plan') ?>>تولید ده اینسایت ←</button><?php endif; ?>
      <?php if ($c['insights']): ?><a class="btn" href="<?= e(url('/c/' . $c['id'] . '/insights')) ?>">دیدن اینسایت‌های فعلی</a><?php endif; ?>
    </div>
  </div>
</form>
