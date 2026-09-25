<?php
// Compares the PHP engine with the prototype JS engine on the seed data.
// Usage: node tests/parity.js tests/ref-engine.js > /tmp/js.json && php tests/parity.php /tmp/js.json
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $c): void {
    $p = APP_ROOT . '/app/' . str_replace('\\', '/', substr($c, 4)) . '.php';
    if (str_starts_with($c, 'App\\') && is_file($p)) {
        require $p;
    }
});

use App\Core\Fmt;
use App\Engine\Engine;
use App\Engine\Seed;

$js = json_decode((string) file_get_contents($argv[1] ?? ''), true);
if (!$js) {
    fwrite(STDERR, "missing js json\n");
    exit(2);
}
$fail = 0;
$pass = 0;
$eq = static function ($a, $b, string $what) use (&$fail, &$pass): void {
    $ok = is_float($a) || is_float($b) || is_int($a) || is_int($b)
        ? (abs((float) $a - (float) $b) <= 1e-6 * max(1, abs((float) $b)))
        : $a === $b;
    if ($ok) {
        $pass++;
    } else {
        $fail++;
        echo "FAIL $what\n  php: " . json_encode($a, JSON_UNESCAPED_UNICODE) . "\n  js : " . json_encode($b, JSON_UNESCAPED_UNICODE) . "\n";
    }
};

$rules = Engine::DEFAULT_RULES;
$e = new Engine($rules, Seed::profile());
$rates = Seed::rates();
foreach ($rates as $i => $r) {
    $eq($e->cpiAdj($r), $js['cpiAdj'][$i], "cpiAdj $i");
    $eq($e->cac($r), $js['cac'][$i], "cac $i");
}
foreach ([0, 1, -1, 12.5, 999, 1234.5, 9999999, 10000000, 12345678, 999999999, 1500000000, 12000000000, -3456789] as $i => $n) {
    $eq(Fmt::num($n), $js['fmt'][$i][0], "num $n");
    $eq(Fmt::money($n), $js['fmt'][$i][1], "money $n");
}
foreach ([0, 0.001, 0.0123, 0.123, -0.32, 1.5, -0.004] as $i => $x) {
    $eq(Fmt::pct($x), $js['pct'][$i][0], "pct $x");
    $eq(Fmt::pct($x, 0), $js['pct'][$i][1], "pct0 $x");
    $eq(Fmt::signPct($x), $js['pct'][$i][2], "signPct $x");
}

$variants = [['سودآوری', 'متعادل', 500000000], ['رشد', 'تهاجمی', 300000000], ['نگهداشت', 'محافظه‌کار', 900000000], ['سهم بازار', 'متعادل', 120000000]];
foreach ($variants as $vi => [$goal, $risk, $budget]) {
    $e = new Engine($rules, array_merge(Seed::profile(), ['goal' => $goal]));
    $ins = $e->buildInsights($rates, ['budget' => $budget, 'channels' => Engine::CHANNELS, 'segments' => Engine::SEGMENTS, 'risk' => $risk]);
    foreach ($ins as $k => $i) {
        $j = $js['insights'][$vi][$k];
        $eq($i['id'], $j['id'], "ins[$vi][$k].id");
        $eq($i['fit'], $j['fit'], "ins[$vi][$k].fit");
        foreach (['claim', 'proposal', 'evidence', 'risk'] as $f) {
            if ($i['id'] === 'season' && in_array($f, ['claim', 'evidence'], true)) {
                // FIX B1: prototype prints NaN here
                $eq(str_contains($j[$f], 'NaN'), true, "js season NaN $f");
                $eq(str_contains($i[$f], 'NaN'), false, "php season no NaN $f");
                continue;
            }
            $eq($i[$f], $j[$f], "ins[$vi][$k].$f ({$i['id']})");
        }
        if ($i['id'] !== 'season') {
            $eq($i['successMetric'], $j['success'], "ins[$vi][$k].success ({$i['id']})");
        }
        $eq(count($i['alloc']), count($j['alloc']), "ins[$vi][$k].alloc.count");
        foreach ($i['alloc'] as $ai => $a) {
            $eq($a['ch'] . '|' . $a['seg'], $j['alloc'][$ai][0] . '|' . $j['alloc'][$ai][1], "alloc row $ai");
            $eq($a['budget'], $j['alloc'][$ai][2], "alloc budget $ai");
        }
        foreach (['installs', 'conv', 'revenue', 'cac', 'poas'] as $f) {
            $eq($i['exp'][$f], $j['exp'][$f], "ins[$vi][$k].exp.$f");
        }
        $eq(Engine::insightValid($i), true, "insightValid {$i['id']}");
    }
}

$e = new Engine($rules, Seed::profile());
$ins = $e->buildInsights($rates, ['budget' => 500000000, 'channels' => Engine::CHANNELS, 'segments' => Engine::SEGMENTS, 'risk' => 'متعادل']);
$occ = [];
foreach (Engine::defaultOccasions() as $o) {
    $occ[$o['k']] = $o;
}
foreach ($js['sims'] as $si => $case) {
    [$sel, $sec, $mix, $conf, $ext, $oc, $gt, $gv] = $case['key'];
    $alloc = $e->mergedAlloc($ins, $sel, $sec, (int) $mix, 500000000);
    foreach ($alloc as $ai => $a) {
        $eq($a['ch'] . '|' . $a['seg'], $case['alloc'][$ai][0] . '|' . $case['alloc'][$ai][1], "sim$si alloc $ai");
        $eq($a['budget'], $case['alloc'][$ai][2], "sim$si alloc budget $ai");
    }
    $s = $e->simulate($alloc, $gt, (float) $gv, ['band' => $conf, 'ext' => $ext, 'occasion' => $occ[$oc]]);
    foreach ($case['s'] as $k => $v) {
        $mine = $k === 'risk' ? $s['risk']['text'] : ($k === 'prof' ? $s['prof']['text'] : $s[$k]);
        $eq($mine, $v, "sim$si.$k");
    }
}

foreach (Engine::presets() as $pi => $p) {
    $vi = array_merge(Engine::blankVI(), $p['vi']);
    $v = $e->verify($vi);
    $j = $js['verify'][$pi];
    foreach (['cause', 'calib', 'expl', 'block', 'dB', 'dI', 'dCvr', 'obsCpi', 'obsCvr', 'liftText'] as $k) {
        $eq($v[$k], $j[$k], "verify $pi.$k");
    }
    if ($v['calib']) {
        $row = null;
        foreach ($rates as $r) {
            if ($r['ch'] === $vi['ch'] && $r['seg'] === $vi['seg']) {
                $row = $r;
            }
        }
        $c = $e->calibrate($row, (float) $vi['ab'], $v, false);
        $eq($c['after']['cpi'], $j['calibAfter']['cpi'], "calib $pi cpi");
        $eq($c['after']['cvr'], $j['calibAfter']['cvr'], "calib $pi cvr");
        $eq($c['after']['n'], $j['calibAfter']['n'], "calib $pi n");
        $eq($c['weights'], $j['weights'], "calib $pi weights");
    }
}
foreach ([['window' => 14], ['fraud' => 12], ['pb' => 100e6, 'pi' => 10000, 'pc' => 800, 'ab' => 110e6, 'ai' => 11000, 'ac' => 600], ['reach' => 50000, 'ac' => 590, 'holdout' => 0.4]] as $xi => $o) {
    $v = $e->verify(array_merge(Engine::blankVI(), $o));
    foreach (['cause', 'calib', 'expl', 'liftText'] as $k) {
        $eq($v[$k], $js['verifyExtra'][$xi][$k], "verifyExtra $xi.$k");
    }
}
$qs = ['ارزان‌ترین کانال کدام است؟', 'گران‌ترین جذب کجاست؟', 'پایدارترین ردیف؟', 'بیشترین ماندگاری؟', 'نرخ تقلب کجا بالاست؟', 'کدام ردیف سودآورتر است؟', 'وضعیت تپسل', 'قیمت دلار فردا؟'];
foreach ($qs as $qi => $q) {
    $m = $e->matchIntent($q);
    $a = $e->answerIntent($m['intent'], $m['channel'], $m['segment'], $rates);
    $eq($a['text'], $js['answers'][$qi], "answer $qi");
}
$rd = $e->readiness(count(Seed::history()), $rates);
$eq($rd['ready'], $js['readiness']['ready'], 'readiness');
foreach ($rd['checks'] as $ci => $c) {
    $eq($c['have'], $js['readiness']['checks'][$ci]['have'], "readiness have $ci");
}

echo "parity: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
