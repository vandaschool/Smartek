<?php
declare(strict_types=1);

namespace App\Controllers;

use App\AI\AI;
use App\Core\DB;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Services\Loop;
use App\Services\Ws;

final class AiController extends Controller
{
    public function insightExplain(string $cid, string $pid): void
    {
        $ws = $this->ws();
        $c = Loop::campaign($ws, (int) $cid);
        if (!$c || !AI::enabled($ws)) {
            Response::json(['ok' => false]);
        }
        $ins = current(array_filter(Loop::j($c['insights']) ?: [], static fn ($i) => $i['id'] === $pid));
        if (!$ins) {
            Response::json(['ok' => false]);
        }
        $r = AI::insightExplain($ws, $ins, Ws::profile($ws), (string) $c['risk'], Ws::engine($ws));
        if (!$r) {
            Response::json(['ok' => false, 'ai' => ['provider' => 'metis', 'fallback' => true]]);
        }
        Response::json(['ok' => true, 'items' => [['k' => 'چرا', 'v' => $r['why']], ['k' => 'ریسک از این زاویه', 'v' => $r['risk']]], 'ai' => ['provider' => 'metis', 'grounded' => true, 'fallback' => false]]);
    }

    public function verifyNarrative(string $id): void
    {
        $ws = $this->ws();
        $run = Loop::run((int) $id);
        if (!$run || (int) $run['workspace_id'] !== $ws || !AI::enabled($ws) || !$run['ver']) {
            Response::json(['ok' => false]);
        }
        $cached = $run['ver']['narrative'] ? json_decode((string) $run['ver']['narrative'], true) : null;
        $calCount = count($run['cals']);
        if (!$cached || ($cached['cals'] ?? -1) !== $calCount) {
            $plan = $run['campaign_id'] ? Loop::plan((int) $run['campaign_id']) : null;
            $r = AI::verifyNarrative($ws, $run, $plan['perspective'] ?? null, Ws::rules($ws));
            if (!$r) {
                Response::json(['ok' => false, 'ai' => ['provider' => 'metis', 'fallback' => true]]);
            }
            $cached = $r + ['cals' => $calCount];
            DB::update('verifications', ['narrative' => json_encode($cached, JSON_UNESCAPED_UNICODE)], ['id' => $run['ver']['id']]);
        }
        Response::json(['ok' => true, 'items' => [['k' => 'خلاصه', 'v' => $cached['summary']], ['k' => 'قدم بعدی', 'v' => $cached['next_step']]], 'ai' => ['provider' => 'metis', 'grounded' => true, 'fallback' => false]]);
    }

    public function reasonHint(): void
    {
        $ws = $this->ws();
        RateLimit::enforce('hint:' . Auth::id(), 30, 60);
        $in = Request::json() ?? [];
        $reason = mb_substr((string) ($in['reason'] ?? ''), 0, 600);
        if (mb_strlen(trim($reason)) < 10 || !AI::enabled($ws)) {
            Response::json(['hint' => null]);
        }
        $r = AI::reasonHint($ws, $reason, (string) ($in['perspective'] ?? ''), (string) (Ws::profile($ws)['goal'] ?? ''), '');
        Response::json(['hint' => $r['hint'] ?? null, 'is_specific' => $r['is_specific'] ?? null, 'ai' => ['provider' => 'metis', 'fallback' => $r === null]]);
    }
}
