<?php
// Unit tests: php tests/run.php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $c): void {
    $p = APP_ROOT . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (str_starts_with($c, 'App\\') && is_file($p)) {
        require $p;
    }
});

use App\Core\Fmt;
use App\Core\Jalali;
use App\Core\Totp;
use App\Engine\Engine;
use App\Engine\Seed;

$pass = 0;
$fail = 0;
function check(bool $ok, string $name): void
{
    global $pass, $fail;
    if ($ok) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL: $name\n";
    }
}

$e = new Engine(Engine::DEFAULT_RULES, Seed::profile());
$v = static fn (array $o, array $cfg = []) => (new Engine(array_merge(Engine::DEFAULT_RULES, $cfg), Seed::profile()))->verify(array_merge(Engine::blankVI(), $o));

// TEST-CASES.md T1–T9
$cases = [
    ['T1 estimation error', ['pb' => 500e6, 'pi' => 10000, 'pc' => 800, 'ab' => 520e6, 'ai' => 6800, 'ac' => 590], 'خطای برآورد', true],
    ['T2 execution deviation', ['pb' => 300e6, 'pi' => 4800, 'pc' => 360, 'ab' => 195e6, 'ai' => 3100, 'ac' => 230], 'انحراف اجرا', false],
    ['T3 scale not quality', ['pb' => 100e6, 'pi' => 11000, 'pc' => 680, 'ab' => 112e6, 'ai' => 12300, 'ac' => 762], 'مقیاس، نه کیفیت', false],
    ['T4 data mismatch', ['pb' => 200e6, 'pi' => 5200, 'pc' => 250, 'ab' => 210e6, 'ai' => 2900, 'ac' => 140, 'complete' => 'خیر'], 'ناسازگاری داده', false],
    ['T5 within expectation', ['pb' => 120e6, 'pi' => 9200, 'pc' => 875, 'ab' => 124e6, 'ai' => 8900, 'ac' => 845], 'در دامنه‌ی انتظار', true],
    ['T6 attribution window', ['window' => 14], 'ناسازگاری داده', false],
    ['T7 fraud', ['fraud' => 12], 'ناسازگاری داده', false],
    ['T8 CVR drop blocks scale', ['pb' => 100e6, 'pi' => 10000, 'pc' => 800, 'ab' => 110e6, 'ai' => 11000, 'ac' => 600], 'خطای برآورد', true],
];
foreach ($cases as [$name, $o, $cause, $calib]) {
    $r = $v($o);
    check($r['cause'] === $cause && $r['calib'] === $calib, $name . ' → ' . $r['cause']);
}
$r = $v(['pb' => 500e6, 'pi' => 10000, 'pc' => 800, 'ab' => 520e6, 'ai' => 6800, 'ac' => 590], ['execTh' => 0.02]);
check($r['cause'] === 'انحراف اجرا' && !$r['calib'], 'T9 configurable exec threshold');

// incrementality
$r = $v(['reach' => 50000, 'ac' => 590, 'holdout' => 0.4]);
check(abs((float) $r['lift'] - 0.661) < 0.01, 'lift ≈ 66%');
$r = $v(['holdout' => 0]);
check($r['lift'] === null && str_contains($r['liftText'], 'گروه کنترل ثبت نشده'), 'holdout 0 → not measured');

// calibration weights (TEST-CASES)
$row = null;
foreach (Seed::rates() as $x) {
    if ($x['ch'] === 'یکتانت' && $x['seg'] === 'فعال') {
        $row = $x;
    }
}
$vr = $v(['pb' => 500e6, 'pi' => 10000, 'pc' => 800, 'ab' => 520e6, 'ai' => 6800, 'ac' => 590]);
$c = $e->calibrate($row, 520e6, $vr, false);
check(abs($c['wNew'] - 2.888) < 0.01, 'w_new ≈ 2.89');
check(abs($c['wOld'] - 5.103) < 0.01, 'w_old ≈ 5.10');
check($c['after']['cpi'] === 57629.0 && (int) $c['after']['n'] === 8 && $c['row']['age'] === 0, 'calibrated cpi 57629, n 8, age 0');

// diminishing returns
$r0 = Seed::rates()[0];
$B = $r0['ceiling'] * $e->cpiAdj($r0);
check(abs($e->installsAt($r0, $B) / ($B / $e->cpiAdj($r0)) - 2 / 3) < 1e-9, 'diminishing returns at ceiling = 2/3');
// property: budget up => installs up, marginal installs down
$prev = 0.0;
$prevGain = INF;
for ($b = 1e7; $b <= 5e9; $b *= 1.5) {
    $i = $e->installsAt($r0, $b);
    $gain = $i - $prev;
    check($i > $prev, 'installs increase with budget ' . $b);
    if ($prev > 0) {
        check($gain / ($b / 3) < $prevGain, 'marginal installs decrease ' . $b);
        $prevGain = $gain / ($b / 3);
    }
    $prev = $i;
}

// insights: 10, valid evidence, diversity
$ins = $e->buildInsights(Seed::rates(), ['budget' => 500e6, 'channels' => Engine::CHANNELS, 'segments' => Engine::SEGMENTS, 'risk' => 'متعادل']);
check(count($ins) === 10, 'ten insights');
foreach ($ins as $i) {
    check(Engine::insightValid($i), 'insight valid ' . $i['id']);
}
$tops = array_unique(array_column($ins, 'topRow'));
check(count($tops) >= 2, 'at least two different top rows (diversity)');

// simulator band ordering (acceptance #5) + POAS verdict (#6)
foreach ($ins as $i) {
    foreach (Engine::BAND as $band => $_) {
        $s = $e->simulate($i['alloc'], 'خرید', 2500, ['band' => $band]);
        check($s['iLow'] < $s['installs'] && $s['installs'] < $s['iHigh'], "band order installs {$i['id']} $band");
        check($s['cLow'] < $s['conv'] && $s['conv'] < $s['cHigh'], "band order conv {$i['id']} $band");
        check(($s['poas'] < 0) === ($s['prof']['level'] === 'bad'), "poas verdict {$i['id']}");
    }
}

// multi-row verification (FIX B2)
$rows = [
    ['ch' => 'پوش', 'seg' => 'فعال', 'pb' => 100e6, 'pi' => 10000, 'pc' => 600, 'ab' => 102e6, 'ai' => 6500, 'ac' => 400],
    ['ch' => 'پیامک', 'seg' => 'بازگشتی', 'pb' => 80e6, 'pi' => 6000, 'pc' => 400, 'ab' => 81e6, 'ai' => 5900, 'ac' => 395],
];
$m = $e->verifyRows($rows, ['complete' => 'بله', 'matched' => 'بله', 'fraud' => 0, 'window' => 7]);
check(count($m['rows']) === 2 && $m['rows'][0]['vr']['cause'] === 'خطای برآورد' && $m['rows'][1]['vr']['cause'] === 'در دامنه‌ی انتظار', 'per-row causes');

// pacing (TEST-CASES)
$sim = ['budget' => 100.0, 'installs' => 100.0, 'conv' => 100.0];
$p = $e->pace($sim, ['day' => 12, 'spend' => 52, 'installs' => 40, 'conv' => 29], 30, [['ch' => 'a', 'seg' => 'b', 'budget' => 1]]);
check(abs($p['rows'][0]['raw'] - 0.3) < 1e-9 && $p['alerts'][0]['type'] === 'pace_spend', 'pace spend +30% alert');
check((bool) array_filter($p['alerts'], static fn ($a) => $a['type'] === 'pace_conv'), 'pace conversion alert');

// formatting
check(Fmt::num(1234567) === '۱٬۲۳۴٬۵۶۷', 'num');
check(Fmt::money(500000000) === '۵۰۰ م', 'money م');
check(Fmt::money(1500000000) === '۱٫۵ میلیارد', 'money میلیارد');
check(Fmt::signPct(-0.32) === '−۳۲٪', 'signPct');
check(Fmt::parseNum('۱٬۲۳۴٫۵') === 1234.5, 'parseNum persian');

// Jalali
check(Jalali::fromIso('2026-09-25') === '1405/07/03', 'jalali 2026-09-25');
check(Jalali::fromIso('2026-03-21') === '1405/01/01', 'jalali nowruz 1405');
check(Jalali::toIso('1405/07/03') === '2026-09-25', 'jalali → iso');
check(Jalali::toIso('۱۴۰۵/۱۲/۳۰') === null || Jalali::monthLength(1405, 12) === 30, 'esfand length consistent');
check(Jalali::monthLength(1403, 12) === 30 && Jalali::monthLength(1404, 12) === 29, 'leap esfand 1403');
for ($t = strtotime('2020-01-01'); $t < strtotime('2031-01-01'); $t += 86400 * 7) {
    $iso = date('Y-m-d', $t);
    if (Jalali::toIso(Jalali::fromIso($iso)) !== $iso) {
        check(false, 'jalali roundtrip ' . $iso);
    }
}
check(true, 'jalali roundtrip');

// TOTP RFC 6238 vector (SHA1, secret "12345678901234567890", T=59 → 94287082 → 6 digits 287082)
$sec = Totp::b32encode('12345678901234567890');
check(Totp::code($sec, 59) === '287082', 'totp rfc vector');

// CSV import
$csv = "name,channel,segment,spend,installs,conversions,revenue,month\n\"الف, ب\",گوگل,فعال,۱۲۰٬۰۰۰,100,10,5000,مهر\nx,ناشناخته,فعال,1,1,1,1,مهر\ny,گوگل,فعال,abc,1,1,1,مهر\nz,گوگل,فعال,1,1,5,1,مهر\n";
$imp = Engine::readImport('history', $csv);
check(count($imp['ok']) === 1 && $imp['ok'][0]['name'] === 'الف, ب' && $imp['ok'][0]['spend'] === 120000.0, 'csv ok row');
check(count($imp['errors']) === 3, 'csv 3 errors');
$imp = Engine::readImport('history', "a,b\n1,2\n");
check(str_contains($imp['errors'][0]['msg'], 'ستون‌های لازم پیدا نشد'), 'csv missing columns');
$imp = Engine::readImport('history', "عنوان,کانال,سگمنت,هزینه,نصب,خرید,فروش,ماه\nk,پوش,فعال,1,2,1,3,مهر\n", ['name' => 'عنوان', 'channel' => 'کانال', 'segment' => 'سگمنت', 'spend' => 'هزینه', 'installs' => 'نصب', 'conversions' => 'خرید', 'revenue' => 'فروش', 'month' => 'ماه']);
check(count($imp['ok']) === 1, 'csv mapped headers');

// decision quality
$dq = $e->decisionQuality(Seed::perspectiveLog());
check($dq['best'] !== '—' && count($dq['rows']) === 5, 'decision quality groups');

// ask
$m = $e->matchIntent('CAC یکتانت روی کاربر جدید چند است؟');
check($m['intent'] === 'channel_segment' && $m['channel'] === 'یکتانت' && $m['segment'] === 'کاربر جدید', 'intent channel_segment');
$a = $e->answerIntent('channel_segment', 'یکتانت', 'کاربر جدید', Seed::rates());
check($a['grounded'] && count($a['facts']) >= 3, 'answer channel_segment grounded');
$a = $e->answerIntent('unsupported', null, null, Seed::rates());
check(!$a['grounded'] && !preg_match('/[0-9۰-۹]/u', str_replace('لایه‌ی ۲', '', $a['text'])), 'unsupported has no number');

echo "unit: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
