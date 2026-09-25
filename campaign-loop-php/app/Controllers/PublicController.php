<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Crypto;
use App\Core\DB;
use App\Core\RateLimit;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\Loop;
use App\Services\Report;

final class PublicController extends Controller
{
    public function landing(): void
    {
        View::render('pages/landing', ['lead' => Session::get('lead_done'), 'leadErr' => Session::get('lead_err')], 'public');
        Session::forget('lead_done');
        Session::forget('lead_err');
    }

    public function legal(string $page): void
    {
        if (!in_array($page, ['terms', 'privacy', 'data'], true)) {
            Response::abort(404);
        }
        View::render('pages/legal', ['tab' => $page, 'title' => 'شرایط و حریم خصوصی · Campaign Loop'], 'public');
    }

    public function lead(): void
    {
        RateLimit::enforce('lead:' . Request::ip(), 5, 60);
        if (Request::str('website') !== '') {
            Response::redirect('/#demo'); // honeypot
        }
        $name = Request::str('name', '', 120);
        $email = mb_strtolower(Request::str('email', '', 190));
        $phone = Request::str('phone', '', 40);
        if (mb_strlen($name) < 2 || (!filter_var($email, FILTER_VALIDATE_EMAIL) && mb_strlen($phone) < 8)) {
            Session::set('lead_err', 'نام و یک راه تماس معتبر (ایمیل یا تلفن) لازم است.');
            Response::redirect('/#demo');
        }
        DB::insert('leads', [
            'name' => $name, 'company' => Request::str('company', '', 160), 'email' => $email, 'phone' => $phone,
            'spend_band' => Request::str('spend_band', '', 60), 'utm_source' => Request::str('utm_source', '', 80),
            'utm_medium' => Request::str('utm_medium', '', 80), 'utm_campaign' => Request::str('utm_campaign', '', 80),
            'referrer' => Request::str('referrer', '', 255), 'created_at' => DB::now(),
        ]);
        \App\Core\Audit::event('lead_submitted', ['spend_band' => Request::str('spend_band', '', 60), 'utm_source' => Request::str('utm_source', '', 80)], null);
        Session::set('lead_done', ['name' => $name, 'contact' => $email ?: $phone]);
        Response::redirect('/#demo');
    }

    /** Signed read-only report link: /r/{campaignId}.{sig} */
    public function sharedReport(string $token): void
    {
        [$id, $sig] = array_pad(explode('.', $token, 2), 2, '');
        if (!ctype_digit($id) || !hash_equals(Crypto::sign('report:' . $id), $sig)) {
            Response::abort(404, 'این لینک گزارش نامعتبر است.');
        }
        $c = DB::one('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$c) {
            Response::abort(404);
        }
        $rp = Report::build((int) $c['workspace_id'], $c);
        View::render('pages/report_public', ['report' => $rp, 'c' => $c, 'title' => 'گزارش کمپین'], 'bare');
    }
}
