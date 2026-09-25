<?php
/** @var array $members @var array $invites @var int $seats */
use App\Core\Auth;

$canTeam = can('team');
$roles = ['analyst' => 'تحلیل‌گر', 'campaign_ops' => 'مسئول کمپین', 'viewer' => 'ناظر'];
?>
<div class="col gap20">
  <div class="head">
    <h1 class="title">تیم و نقش‌ها</h1>
    <p class="lead">انتخاب دیدگاه یک تصمیم انسانی است، پس باید معلوم باشد چه کسی آن را گرفته. نقش‌ها تعیین می‌کنند چه کسی طرح را ثبت می‌کند و چه کسی فقط می‌بیند.</p>
  </div>

  <div class="card flush">
    <div class="row" style="padding:15px 18px;border-bottom:1px solid var(--bd2)"><div class="h2 grow">اعضا</div><div class="xs muted"><?= fa(count($members) + count($invites)) ?> از <?= $seats >= 1000 ? 'نامحدود' : fa($seats) ?> کاربر</div></div>
    <?php foreach ($members as $m): ?>
      <div class="row" style="gap:13px;padding:14px 18px;border-bottom:1px solid var(--bd3)">
        <div class="avatar" style="width:34px;height:34px;font-size:13.5px"><?= e(mb_substr((string) $m['name'], 0, 1)) ?></div>
        <div class="col grow" style="gap:2px;min-width:160px">
          <div style="font-size:13.5px;font-weight:500"><?= e($m['name']) ?><?= (int) $m['id'] === Auth::id() ? ' <span class="xs muted">(شما)</span>' : '' ?></div>
          <div class="xs muted ltr" style="text-align:right"><?= e($m['email']) ?></div>
        </div>
        <?php if ($canTeam && $m['role'] !== 'owner' && (int) $m['id'] !== Auth::id()): ?>
          <form method="post" action="<?= e(url('/team/member/' . $m['id'] . '/role')) ?>"><?= csrf_field() ?>
            <select class="inp sm" name="role" data-autosubmit aria-label="نقش <?= e($m['name']) ?>" style="width:130px"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"<?= $m['role'] === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
          </form>
          <form method="post" action="<?= e(url('/team/member/' . $m['id'] . '/remove')) ?>" data-confirm="<?= e($m['name']) ?> از فضای کاری حذف شود؟ نشست‌های او پایان می‌یابد."><?= csrf_field() ?><button class="btn link danger" style="font-size:12.5px">حذف</button></form>
        <?php else: ?>
          <div class="badge" style="background:var(--soft);border:1px solid var(--bd2);color:var(--t2)"><?= e(Auth::roleLabel((string) $m['role'])) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if ($invites): ?>
      <div class="col gap8" style="padding:12px 18px;background:var(--blue-soft);border-bottom:1px solid var(--bd2)">
        <div style="font-size:13px;font-weight:600;color:var(--primary-t)">دعوت‌های در انتظار پذیرش</div>
        <?php foreach ($invites as $p): ?>
          <div class="row gap10 small">
            <span class="grow ltr" style="text-align:right"><?= e($p['email']) ?></span><span><?= e(Auth::roleLabel((string) $p['role'])) ?></span><span class="xs muted">تا <?= e(jdate(substr((string) $p['expires_at'], 0, 10))) ?></span>
            <?php if ($canTeam): ?><form method="post" action="<?= e(url('/team/invite/' . $p['id'] . '/revoke')) ?>"><?= csrf_field() ?><button class="btn link danger xs">لغو دعوت</button></form><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($canTeam): ?>
      <form method="post" action="<?= e(url('/team/invite')) ?>" class="row gap8" style="padding:16px 18px"><?= csrf_field() ?>
        <input class="inp grow ltr" type="email" name="email" required placeholder="ایمیل هم‌تیمی" aria-label="ایمیل هم‌تیمی" style="min-width:190px;text-align:right">
        <select class="inp" name="role" style="width:auto" aria-label="نقش"><?php foreach ($roles as $k => $l): ?><option value="<?= e($k) ?>"><?= e($l) ?></option><?php endforeach; ?></select>
        <button class="btn primary">دعوت</button>
      </form>
    <?php else: ?>
      <div class="small" style="padding:14px 18px;color:var(--warn-t)">فقط مالک می‌تواند اعضا را مدیریت کند.</div>
    <?php endif; ?>
  </div>

  <div class="grid" style="--min:240px">
    <div class="card" style="gap:7px"><div style="font-size:14px;font-weight:600">مالک</div><div class="small t3" style="line-height:1.9">ویرایش داده‌ها و پروفایل، ثبت طرح، اعمال کالیبراسیون، مدیریت اعضا.</div></div>
    <div class="card" style="gap:7px"><div style="font-size:14px;font-weight:600">تحلیل‌گر</div><div class="small t3" style="line-height:1.9">تولید اینسایت، انتخاب دیدگاه، ساخت پیش‌بینی و ثبت نتیجه — بدون مدیریت اعضا.</div></div>
    <div class="card" style="gap:7px"><div style="font-size:14px;font-weight:600">مسئول کمپین</div><div class="small t3" style="line-height:1.9">ثبت نتیجه‌ی واقعی و دو پرچم کیفیت داده؛ اجازه‌ی تغییر نرخ‌ها را ندارد.</div></div>
    <div class="card" style="gap:7px"><div style="font-size:14px;font-weight:600">ناظر</div><div class="small t3" style="line-height:1.9">دسترسی فقط‌خواندنی به گزارش، داشبورد و دفترچه‌ی دیدگاه‌ها.</div></div>
  </div>
</div>
