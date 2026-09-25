<?php
/** @var string $name @var string $value 'YYYY/MM/DD' @var string $label */
use App\Core\Jalali;

$v = \App\Core\Fmt::en($value ?: Jalali::today());
[$y, $m, $d] = array_map('intval', explode('/', $v) + [1405, 1, 1]);
$cy = Jalali::year();
$years = range($cy - 1, $cy + 2);
$esf = [];
foreach ($years as $yy) {
    $esf[$yy] = Jalali::monthLength($yy, 12);
}
?>
<div class="field" style="flex:1;min-width:210px">
  <label><?= e($label) ?></label>
  <div class="row" style="gap:6px;flex-wrap:nowrap" role="group" aria-label="<?= e($label) ?>" data-jdate="<?= e(json_encode($esf)) ?>">
    <select class="inp" data-part="d" aria-label="روز" style="flex:1;padding:9px 6px"><?php for ($i = 1; $i <= 31; $i++): ?><option value="<?= $i ?>"<?= $i === $d ? ' selected' : '' ?>><?= fa($i) ?></option><?php endfor; ?></select>
    <select class="inp" data-part="m" aria-label="ماه" style="flex:2;padding:9px 6px"><?php foreach (Jalali::MONTHS as $i => $mn): ?><option value="<?= $i + 1 ?>"<?= $i + 1 === $m ? ' selected' : '' ?>><?= e($mn) ?></option><?php endforeach; ?></select>
    <select class="inp" data-part="y" aria-label="سال" style="flex:1.3;padding:9px 6px"><?php foreach ($years as $yy): ?><option value="<?= $yy ?>"<?= $yy === $y ? ' selected' : '' ?>><?= fa($yy) ?></option><?php endforeach; ?></select>
    <input type="hidden" name="<?= e($name) ?>" value="<?= e($v) ?>">
  </div>
</div>
