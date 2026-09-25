<?php
declare(strict_types=1);

namespace App\Core;

use App\Controllers\AdminController as Adm;
use App\Controllers\AiController as Ai;
use App\Controllers\ApiController as Api;
use App\Controllers\AuthController as A;
use App\Controllers\CampaignController as C;
use App\Controllers\DataController as D;
use App\Controllers\OutputController as O;
use App\Controllers\PlatformController as P;
use App\Controllers\PublicController as Pub;

final class App
{
    public static function routes(): Router
    {
        $r = new Router();
        $auth = ['auth' => true];

        // public
        $r->get('/', [Pub::class, 'landing']);
        $r->get('/legal/{page}', [Pub::class, 'legal']);
        $r->post('/lead', [Pub::class, 'lead']);
        $r->get('/r/{token}', [Pub::class, 'sharedReport']);

        // auth
        $r->get('/login', [A::class, 'loginForm'], ['guest' => true]);
        $r->post('/login', [A::class, 'login'], ['guest' => true]);
        $r->get('/signup', [A::class, 'signupForm'], ['guest' => true]);
        $r->post('/signup', [A::class, 'signup'], ['guest' => true]);
        $r->post('/demo', [A::class, 'demo'], ['guest' => true]);
        $r->post('/logout', [A::class, 'logout']);
        $r->get('/forgot', [A::class, 'forgotForm']);
        $r->post('/forgot', [A::class, 'forgot']);
        $r->get('/reset/{token}', [A::class, 'resetForm']);
        $r->post('/reset/{token}', [A::class, 'reset']);
        $r->get('/verify-email/{token}', [A::class, 'verifyEmail']);
        $r->post('/verify-email/resend', [A::class, 'resendVerification'], $auth + ['nows' => true]);
        $r->get('/2fa', [A::class, 'twoFaForm']);
        $r->post('/2fa', [A::class, 'twoFa']);
        $r->get('/invite/{token}', [A::class, 'inviteForm']);
        $r->post('/invite/{token}', [A::class, 'inviteAccept']);
        $r->get('/workspace/new', [A::class, 'workspaceForm'], $auth + ['nows' => true]);
        $r->post('/workspace/new', [A::class, 'workspaceCreate'], $auth + ['nows' => true]);
        $r->get('/settings/password', [A::class, 'passwordForm'], $auth + ['allow_pw' => true, 'nows' => true]);
        $r->post('/settings/password', [A::class, 'passwordSave'], $auth + ['allow_pw' => true, 'nows' => true]);

        // phase 0 — preparation
        $r->get('/campaigns', [C::class, 'index'], $auth);
        $r->post('/campaigns/new', [C::class, 'create'], $auth + ['can' => 'plan']);
        $r->post('/c/{id}/archive', [C::class, 'archive'], $auth + ['can' => 'plan']);
        $r->post('/c/{id}/delete', [C::class, 'deleteDraft'], $auth + ['can' => 'plan']);
        $r->get('/data', [D::class, 'index'], $auth);
        $r->post('/data/rates', [D::class, 'saveRates'], $auth + ['can' => 'editData']);
        $r->post('/data/rates/add', [D::class, 'addRate'], $auth + ['can' => 'editData']);
        $r->post('/data/rates/{id}/delete', [D::class, 'deleteRate'], $auth + ['can' => 'editData']);
        $r->post('/data/history/add', [D::class, 'addHistory'], $auth + ['can' => 'editData']);
        $r->post('/data/history/{id}/delete', [D::class, 'deleteHistory'], $auth + ['can' => 'editData']);
        $r->get('/data/template/{kind}', [D::class, 'template'], $auth);
        $r->get('/data/export/{kind}', [D::class, 'export'], $auth);
        $r->post('/data/import/{kind}', [D::class, 'upload'], $auth + ['can' => 'editData']);
        $r->post('/data/import-remap', [D::class, 'remap'], $auth + ['can' => 'editData']);
        $r->post('/data/import-commit', [D::class, 'commit'], $auth + ['can' => 'editData']);
        $r->post('/data/import-cancel', [D::class, 'cancel'], $auth);
        $r->post('/data/reseed', [D::class, 'reseed'], $auth + ['can' => 'editData']);
        $r->get('/setup', [D::class, 'setup'], $auth);
        $r->post('/setup', [D::class, 'saveProfile'], $auth + ['can' => 'editData']);
        $r->post('/setup/benchmarks', [D::class, 'benchmarks'], $auth + ['can' => 'editData']);

        // phase 1 — the loop
        $r->get('/c/{id}/design', [C::class, 'design'], $auth);
        $r->post('/c/{id}/design', [C::class, 'designSave'], $auth + ['can' => 'plan']);
        $r->get('/c/{id}/insights', [C::class, 'insights'], $auth);
        $r->post('/c/{id}/plan', [C::class, 'savePlan'], $auth + ['can' => 'plan']);
        $r->get('/c/{id}/sim', [C::class, 'sim'], $auth);
        $r->post('/c/{id}/sim', [C::class, 'simSave'], $auth);
        $r->post('/c/{id}/whatif', [C::class, 'whatIfApply'], $auth + ['can' => 'plan']);
        $r->post('/c/{id}/refresh-rates', [C::class, 'refreshRates'], $auth + ['can' => 'plan']);
        $r->get('/c/{id}/pace', [C::class, 'pace'], $auth);
        $r->post('/c/{id}/pace', [C::class, 'paceSave'], $auth + ['can' => 'result']);
        $r->post('/c/{id}/realloc', [C::class, 'realloc'], $auth + ['can' => 'plan']);
        $r->get('/c/{id}/verify', [C::class, 'verify'], $auth);
        $r->post('/c/{id}/verify', [C::class, 'verifyRun'], $auth + ['can' => 'result']);
        $r->get('/verify', [C::class, 'verifyManual'], $auth);
        $r->post('/verify', [C::class, 'verifyManualRun'], $auth + ['can' => 'result']);
        $r->post('/runs/{id}/confirm', [C::class, 'confirmRun'], $auth + ['can' => 'result']);
        $r->post('/runs/{id}/calibrate', [C::class, 'calibrate'], $auth + ['can' => 'calibrate']);
        $r->post('/calibrations/{id}/revert', [C::class, 'undo'], $auth + ['can' => 'calibrate']);

        // phase 2 — outputs
        $r->get('/c/{id}/loop', [C::class, 'loop'], $auth);
        $r->get('/c/{id}/report', [C::class, 'report'], $auth);
        $r->get('/c/{id}/report.csv', [C::class, 'reportCsv'], $auth);
        $r->get('/c/{id}/report.json', [C::class, 'reportJson'], $auth);
        $r->post('/c/{id}/share', [C::class, 'share'], $auth);
        $r->post('/c/{id}/close', [C::class, 'close'], $auth + ['can' => 'result']);
        $r->get('/log', [O::class, 'log'], $auth);
        $r->get('/log.csv', [O::class, 'logCsv'], $auth);
        $r->get('/dash', [O::class, 'dash'], $auth);
        $r->get('/method', [O::class, 'method'], $auth);
        $r->get('/ask', [O::class, 'ask'], $auth);
        $r->post('/ask', [O::class, 'answer'], $auth);

        // AI (JSON)
        $r->get('/ai/insight/{cid}/{pid}', [Ai::class, 'insightExplain'], $auth);
        $r->get('/ai/run/{id}', [Ai::class, 'verifyNarrative'], $auth);
        $r->post('/ai/reason-hint', [Ai::class, 'reasonHint'], $auth);

        // phase 3 — platform
        $r->get('/connect', [P::class, 'connect'], $auth);
        $r->post('/connect/{kind}', [P::class, 'connectSave'], $auth + ['can' => 'connect']);
        $r->post('/connect/{kind}/disconnect', [P::class, 'disconnect'], $auth + ['can' => 'connect']);
        $r->post('/connect/{kind}/sync', [P::class, 'syncNow'], $auth + ['can' => 'connect']);
        $r->post('/c/{id}/link', [P::class, 'linkCampaign'], $auth + ['can' => 'plan']);
        $r->post('/api-keys', [P::class, 'apiKeyCreate'], $auth + ['can' => 'connect']);
        $r->post('/api-keys/{id}/revoke', [P::class, 'apiKeyRevoke'], $auth + ['can' => 'connect']);
        $r->get('/team', [P::class, 'team'], $auth);
        $r->post('/team/invite', [P::class, 'invite'], $auth + ['can' => 'team']);
        $r->post('/team/invite/{id}/revoke', [P::class, 'inviteRevoke'], $auth + ['can' => 'team']);
        $r->post('/team/member/{uid}/remove', [P::class, 'memberRemove'], $auth + ['can' => 'team']);
        $r->post('/team/member/{uid}/role', [P::class, 'memberRole'], $auth + ['can' => 'team']);
        $r->get('/rules', [P::class, 'rules'], $auth);
        $r->post('/rules', [P::class, 'rulesSave'], $auth + ['can' => 'editData']);
        $r->post('/rules/reset', [P::class, 'rulesReset'], $auth + ['can' => 'editData']);
        $r->get('/audit', [P::class, 'audit'], $auth + ['can' => 'audit']);
        $r->get('/analytics', [P::class, 'analytics'], $auth + ['can' => 'analytics']);
        $r->get('/security', [P::class, 'security'], $auth);
        $r->post('/security/2fa/setup', [P::class, 'twoFaSetup'], $auth);
        $r->post('/security/2fa/enable', [P::class, 'twoFaEnable'], $auth);
        $r->post('/security/2fa/disable', [P::class, 'twoFaDisable'], $auth);
        $r->post('/security/sessions/{id}/end', [P::class, 'sessionEnd'], $auth);
        $r->get('/security/export', [P::class, 'exportData'], $auth + ['can' => 'editData']);
        $r->post('/security/delete-request', [P::class, 'deleteRequest'], $auth + ['can' => 'team']);
        $r->get('/billing', [P::class, 'billing'], $auth);
        $r->post('/billing/checkout', [P::class, 'checkout'], $auth + ['can' => 'billing']);
        $r->get('/billing/callback', [P::class, 'paymentCallback']);
        $r->get('/help', [P::class, 'help'], $auth);
        $r->post('/help/ticket', [P::class, 'ticket'], $auth);
        $r->get('/settings', [P::class, 'settings'], $auth);
        $r->post('/settings/workspace', [P::class, 'settingsWorkspace'], $auth + ['can' => 'team']);
        $r->post('/settings/account', [P::class, 'settingsAccount'], $auth);
        $r->post('/settings/reset', [P::class, 'settingsReset'], $auth + ['can' => 'team']);
        $r->post('/ws/switch', [P::class, 'switchWs'], $auth);
        $r->post('/notifications/read', [P::class, 'notificationsRead'], $auth);
        $r->post('/prefs', [P::class, 'prefs'], $auth);

        // admin
        $r->get('/admin', [Adm::class, 'index'], $auth + ['admin' => true]);
        $r->post('/admin/settings', [Adm::class, 'save'], $auth + ['admin' => true]);
        $r->post('/admin/ai-test', [Adm::class, 'aiTest'], $auth + ['admin' => true]);
        $r->post('/admin/mail-test', [Adm::class, 'mailTest'], $auth + ['admin' => true]);
        $r->post('/admin/occasions', [Adm::class, 'occasions'], $auth + ['admin' => true]);
        $r->post('/admin/ticket/{id}', [Adm::class, 'ticketReply'], $auth + ['admin' => true]);

        // server-to-server API (API key)
        $r->post('/api/v1/results', [Api::class, 'results'], ['nocsrf' => true]);
        $r->get('/api/v1/campaigns/{code}', [Api::class, 'chain'], ['nocsrf' => true]);
        $r->post('/api/v1/webhooks/{kind}', [Api::class, 'webhook'], ['nocsrf' => true]);
        $r->post('/billing/zarinpal-callback', [P::class, 'paymentCallback'], ['nocsrf' => true]);
        return $r;
    }

    public static function run(): void
    {
        Response::securityHeaders();
        Session::start();
        try {
            self::routes()->dispatch(Request::method(), Request::path());
        } catch (HttpStop $e) {
            // response already sent
        }
    }
}
