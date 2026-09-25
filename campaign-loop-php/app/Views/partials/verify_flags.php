<?php
/** @var array $vi @var array $rules */
$v = static fn (string $k, $d) => $vi[$k] ?? $d;
$opt = static fn (array $opts, string $cur) => implode('', array_map(static fn ($o) => '<option' . ((string) $o === (string) $cur ? ' selected' : '') . '>' . e($o) . '</option>', $opts));
?>
<div class="frow"><label class="k">نرخ نصب مشکوک به تقلب (٪)</label><input class="inp sm num" name="fraud" value="<?= e($v('fraud', 3)) ?>" inputmode="decimal"></div>
<div class="frow"><label class="k">پنجره‌ی انتساب (روز)</label><select class="inp sm" name="window"><?= $opt(['1', '7', '14', '30'], (string) $v('window', $rules['attrWindow'] ?? 7)) ?></select></div>
<div class="frow"><label class="k">کمپین در فصل اوج بود؟</label><select class="inp sm" name="season"><?= $opt(['خیر', 'بله'], (string) $v('season', 'خیر')) ?></select></div>
<div class="frow"><label class="k">کاربران در معرض کمپین (برای گروه کنترل)</label><input class="inp sm num" name="reach" value="<?= e($v('reach', 0)) ?>" inputmode="numeric"></div>
<div class="frow"><label class="k">نرخ خرید گروه کنترل (٪)</label><input class="inp sm num" name="holdout" value="<?= e($v('holdout', 0)) ?>" inputmode="decimal"></div>
<div class="frow"><label class="k">کامل بودن داده</label><select class="inp sm" name="complete"><?= $opt(['بله', 'خیر'], (string) $v('complete', 'بله')) ?></select></div>
<div class="frow"><label class="k">انطباق ترکر</label><select class="inp sm" name="matched"><?= $opt(['بله', 'خیر'], (string) $v('matched', 'بله')) ?></select></div>
