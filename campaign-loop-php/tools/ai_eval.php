<?php
/**
 * AI evaluation over tests/ai-evals.jsonl (the «پرسش از داده» intent set).
 *   php tools/ai_eval.php            → deterministic matcher baseline + Metis (if configured in admin settings)
 *   php tools/ai_eval.php --smoke    → one live call per AI task, checks JSON contract + number firewall
 * Exit code 1 when Metis accuracy < 90% or any grounded answer violates the number firewall.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
define('APP_ROOT', dirname(__DIR__));
require APP_ROOT . '/app/bootstrap.php';

use App\AI\AI;
use App\AI\Firewall;
use App\Core\DB;
use App\Engine\Engine;
use App\Engine\Seed;
use App\Services\Ws;

$ws = (int) DB::val('SELECT id FROM workspaces WHERE deleted_at IS NULL ORDER BY id LIMIT 1');
$e = $ws ? Ws::engine($ws) : new Engine();
$rates = $ws ? Ws::rates($ws) : Seed::rates();
$live = AI::configured();
echo 'AI provider: ' . ($live ? 'metis (live)' : 'mock — only the deterministic baseline runs; set the Metis key in /admin') . "\n";

if (in_array('--smoke', $argv, true)) {
    if (!$live) {
        exit("smoke needs a configured Metis key\n");
    }
    $t = AI::test();
    echo ($t['ok'] ? 'OK  ' : 'FAIL') . ' connection · ' . $t['message'] . ' · ' . $t['ms'] . " ms\n";
    $ans = $e->answerIntent('cheapest_cac', null, null, $rates);
    $p = AI::askPhrase($ws, 'ارزان‌ترین کانال کدام است؟', 'cheapest_cac', $ans);
    echo ($p['ai'] ? 'OK  ' : 'FALLBACK') . " ask_phrase · {$p['text']}\n";
    $i = AI::askIntent($ws, 'CAC گوگل برای کاربر جدید چقدره؟', $e);
    echo ($i['ai'] ? 'OK  ' : 'FALLBACK') . " ask_intent · {$i['intent']} {$i['channel']} {$i['segment']}\n";
    $m = AI::csvMap('rates', ['کانال تبلیغ', 'بخش مشتری', 'هزینه هر نصب', 'نرخ تبدیل'], [['گوگل', 'کاربر جدید', '152000', '0.036']]);
    echo ($m && empty($m['fallback']) ? 'OK  ' : 'FALLBACK') . ' csv_map · ' . json_encode($m['mapping'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
    exit($t['ok'] ? 0 : 1);
}

$file = APP_ROOT . '/tests/ai-evals.jsonl';
$cases = array_values(array_filter(array_map(static fn ($l) => json_decode($l, true), file($file) ?: [])));
$score = ['base' => 0, 'ai' => 0, 'fw' => 0];
foreach ($cases as $n => $c) {
    $b = $e->matchIntent($c['q']);
    $okB = $b['intent'] === $c['intent'] && ($b['channel'] ?? null) === $c['channel'] && ($b['segment'] ?? null) === $c['segment'];
    $score['base'] += $okB ? 1 : 0;
    $line = sprintf('%2d %s base:%-18s', $n + 1, $okB ? '✓' : '✗', $b['intent']);
    if ($live) {
        $a = AI::askIntent($ws, $c['q'], $e);
        $okA = $a['intent'] === $c['intent'] && $a['channel'] === $c['channel'] && $a['segment'] === $c['segment'];
        $score['ai'] += $okA ? 1 : 0;
        $ans = $e->answerIntent($a['intent'], $a['channel'], $a['segment'], $rates);
        if ($ans['grounded'] !== $c['grounded']) {
            $okA = false;
        }
        if ($ans['grounded']) {
            $p = AI::askPhrase($ws, $c['q'], $a['intent'], $ans);
            $fw = Firewall::check(['text' => $p['text']], ['facts' => $ans['facts'], 'rows' => $ans['rows'], 'template_answer' => $ans['text']], false);
            if (!$fw['ok']) {
                $score['fw']++;
            }
        }
        $line .= sprintf(' metis:%s %-18s', $okA ? '✓' : '✗', $a['intent'] . ($a['ai'] ? '' : '(fallback)'));
    }
    echo $line . '  ' . $c['q'] . "\n";
}
$t = count($cases);
printf("\nbaseline accuracy: %d/%d (%.0f%%)\n", $score['base'], $t, $score['base'] / max($t, 1) * 100);
if ($live) {
    printf("metis accuracy:    %d/%d (%.0f%%) · firewall violations: %d\n", $score['ai'], $t, $score['ai'] / max($t, 1) * 100, $score['fw']);
    exit(($score['ai'] / max($t, 1) < 0.9 || $score['fw'] > 0) ? 1 : 0);
}
exit(0);
