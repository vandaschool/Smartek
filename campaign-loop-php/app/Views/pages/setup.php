<?php
/** @var array $p @var array $rd @var bool $benchmark */
use App\Engine\Engine;

$edit = can('editData');
$blocked = array_filter(array_map('trim', preg_split('/[،,]/u', (string) $p['blocked']) ?: []));
?>
<div class="col gap22">
  <div class="head">
    <h1 class="title">پروفایل و آمادگی</h1>
    <p class="lead">پیش از طراحی، سیستم بررسی می‌کند داده کافی است یا نه. زیر ۱۰ کمپین تاریخی، اینسایت‌ها بی‌پشتوانه می‌شوند — و مشاور بی‌شاهد، حدس است.</p>
  </div>
  <div class="grid" style="--min:320px;gap:18px">
    <form method="post" action="<?= e(url('/setup')) ?>" class="card"><?= csrf_field() ?>
      <div class="row base gap10"><div class="h2">پروفایل مرچنت</div><div class="mono xs muted">merchant_profile</div></div>
      <div class="col gap10">
        <div class="frow"><label class="k" for="p-b">بودجه‌ی ماهانه‌ی تبلیغات (تومان)</label><input id="p-b" class="inp sm num" style="width:150px" name="budget" value="<?= e($p['budget']) ?>" inputmode="decimal" data-money="#p-b-h" <?= $edit ? '' : 'disabled' ?>></div>
        <div class="hint" id="p-b-h" style="text-align:left"></div>
        <div class="frow"><label class="k" for="p-m">حاشیه‌ی سود ناخالص (کسری)</label><input id="p-m" class="inp sm num" style="width:150px" name="margin" value="<?= e($p['margin']) ?>" inputmode="decimal" <?= $edit ? '' : 'disabled' ?>></div>
        <div class="frow"><label class="k" for="p-t">CAC هدف (تومان)</label><input id="p-t" class="inp sm num" style="width:150px" name="targetCac" value="<?= e($p['targetCac']) ?>" inputmode="decimal" <?= $edit ? '' : 'disabled' ?>></div>
        <div class="frow"><label class="k" for="p-l">میانگین LTV (تومان)</label><input id="p-l" class="inp sm num" style="width:150px" name="ltv" value="<?= e($p['ltv']) ?>" inputmode="decimal" <?= $edit ? '' : 'disabled' ?>></div>
        <div class="frow"><label class="k" for="p-g">هدف کسب‌وکار</label>
          <select id="p-g" class="inp sm" style="width:150px" name="goal" <?= $edit ? '' : 'disabled' ?>><option value="">—</option><?php foreach (Engine::GOALS as $g): ?><option<?= $p['goal'] === $g ? ' selected' : '' ?>><?= e($g) ?></option><?php endforeach; ?></select></div>
        <div class="frow"><label class="k" for="p-s">یادداشت فصلی</label><input id="p-s" class="inp sm" style="width:190px" name="season" value="<?= e($p['season']) ?>" <?= $edit ? '' : 'disabled' ?>></div>
        <div class="col gap6"><div class="small t2">کانال‌های غیرمجاز (از پیش‌فرض طراحی حذف می‌شوند)</div>
          <div class="pills"><?php foreach (Engine::CHANNELS as $c): ?><label class="pill<?= in_array($c, $blocked, true) ? ' on' : '' ?>"><input type="checkbox" name="blocked[]" value="<?= e($c) ?>"<?= in_array($c, $blocked, true) ? ' checked' : '' ?> <?= $edit ? '' : 'disabled' ?>><?= e($c) ?></label><?php endforeach; ?></div></div>
      </div>
      <?php if ($edit): ?><button class="btn primary" style="align-self:start">ذخیره‌ی پروفایل</button><?php else: ?><div class="small" style="color:var(--warn-t)">فقط مالک می‌تواند پروفایل را ویرایش کند.</div><?php endif; ?>
    </form>

    <div class="card" style="gap:14px">
      <div class="row base gap10"><div class="h2">آمادگی داده</div><div class="small muted"><?= fa($rd['pass']) ?> از <?= fa($rd['total']) ?> شرط اجباری</div></div>
      <?php foreach ($rd['checks'] as $c): ?>
        <div class="row start" style="gap:11px;border-top:1px solid var(--bd3);padding-top:11px;flex-wrap:nowrap">
          <?php if ($c['ok']): ?><span style="flex:none;width:18px;height:18px;border-radius:50%;background:var(--teal-bg);color:var(--teal);font-size:11px;display:flex;align-items:center;justify-content:center;margin-top:2px">✓</span>
          <?php else: ?><span style="flex:none;width:18px;height:18px;border-radius:50%;background:var(--warn-bg);color:var(--warn-s);font-size:11px;display:flex;align-items:center;justify-content:center;margin-top:2px">!</span><?php endif; ?>
          <div class="col gap4" style="gap:3px"><div style="font-size:13.5px;font-weight:500"><?= e($c['label']) ?></div><div class="hint"><?= e($c['need']) ?> · وضعیت فعلی: <?= e($c['have']) ?></div></div>
        </div>
      <?php endforeach; ?>
      <div class="card soft" style="border-radius:6px;padding:12px;gap:8px">
        <div class="small t2" style="line-height:1.85">مرچنت تازه‌اید؟ با نرخ‌های مرجع صنعت شروع کنید. اینسایت‌ها برچسب «بر پایه‌ی مرجع» می‌گیرند تا اولین کالیبراسیون.</div>
        <?php if ($edit): ?><form method="post" action="<?= e(url('/setup/benchmarks')) ?>" data-confirm="جدول نرخ فعلی با نرخ‌های مرجع صنعت جایگزین می‌شود. ادامه می‌دهید؟"><?= csrf_field() ?><button class="btn md"<?= $benchmark ? ' disabled' : '' ?>><?= $benchmark ? 'نرخ مرجع صنعت فعال است' : 'شروع با نرخ مرجع صنعت' ?></button></form><?php endif; ?>
      </div>
      <?php if ($rd['ready']): ?>
        <form method="post" action="<?= e(url('/campaigns/new')) ?>"><?= csrf_field() ?><button class="btn primary lg" style="width:100%"<?= perm('plan') ?>>داده کافی است — شروع طراحی ←</button></form>
      <?php else: ?>
        <div class="callout warn small">شرط‌های اجباری کامل نیست. می‌توانید ادامه دهید، ولی اینسایت‌ها پشتوانه‌ی کافی ندارند.</div>
        <form method="post" action="<?= e(url('/campaigns/new')) ?>"><?= csrf_field() ?><button class="btn"<?= perm('plan') ?>>ادامه به طراحی</button></form>
      <?php endif; ?>
    </div>
  </div>
</div>
