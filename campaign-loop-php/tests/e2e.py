#!/usr/bin/env python3
"""End-to-end smoke test: full campaign loop over HTTP.
Usage: python3 tests/e2e.py http://127.0.0.1:8080 admin@test.ir Test12345
"""
import sys, re, json, urllib.request, urllib.parse, http.cookiejar

BASE, EMAIL, PASS = sys.argv[1].rstrip('/'), sys.argv[2], sys.argv[3]
cj = http.cookiejar.CookieJar()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(cj), NoRedirect)
fails = []
csrf = ''


def req(method, path, data=None, json_body=None, expect=(200, 303, 302)):
    global csrf
    url = BASE + path
    headers = {}
    body = None
    if json_body is not None:
        body = json.dumps(json_body).encode()
        headers = {'Content-Type': 'application/json', 'X-CSRF-Token': csrf, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
    elif data is not None:
        d = dict(data)
        d['_csrf'] = csrf
        body = urllib.parse.urlencode(d, doseq=True).encode()
        headers = {'Content-Type': 'application/x-www-form-urlencoded'}
    r = urllib.request.Request(url, data=body, method=method, headers=headers)
    try:
        resp = opener.open(r)
        code, text, loc = resp.status, resp.read().decode('utf-8', 'replace'), resp.headers.get('Location', '')
    except urllib.error.HTTPError as e:
        code, text, loc = e.code, e.read().decode('utf-8', 'replace'), e.headers.get('Location', '')
    m = re.search(r'name="csrf-token" content="([^"]+)"', text)
    if m:
        csrf = m.group(1)
    ok = code in expect
    for bad in ['Fatal error', 'Warning:', 'Notice:', 'Deprecated:', 'Uncaught', 'خطای سرور']:
        if bad in text:
            ok = False
    print(('OK  ' if ok else 'FAIL'), code, method, path, ('-> ' + loc) if loc else '')
    if not ok:
        fails.append((method, path, code, text[:800]))
    return code, text, loc


def must(cond, msg):
    print(('OK  ' if cond else 'FAIL') + ' ' + msg)
    if not cond:
        fails.append(('assert', msg, 0, ''))


req('GET', '/login')
c, t, loc = req('POST', '/login', {'email': EMAIL, 'password': PASS})
must('/campaigns' in loc or '/settings/password' in loc, 'login redirects')
for p in ['/campaigns', '/data', '/setup', '/log', '/dash', '/dash?view=ceo', '/dash?view=analyst', '/dash?view=ops', '/method', '/ask', '/verify']:
    req('GET', p)

c, t, loc = req('POST', '/campaigns/new', {})
cid = re.search(r'/c/(\d+)/design', loc).group(1)
req('GET', f'/c/{cid}/design')
c, t, loc = req('POST', f'/c/{cid}/design', {'name': 'کمپین آزمون', 'goalType': 'خرید', 'goalValue': '2500', 'budget': '500000000', 'from': '1405/08/01', 'to': '1405/08/30',
                                             'channels[]': ['گوگل', 'تپسل', 'یکتانت', 'اینستاگرام', 'پوش', 'پیامک'], 'segments[]': ['کاربر جدید', 'فعال', 'در معرض ریزش', 'پرارزش', 'بازگشتی'], 'occasion': 'بدون مناسبت', 'risk': 'متعادل'})
must(loc.endswith(f'/c/{cid}/insights'), 'design → insights')
c, t, _ = req('GET', f'/c/{cid}/insights')
must(t.count('class="ins-h"') == 10, 'ten insight cards')
must('NaN' not in t, 'no NaN on insights page')
req('GET', f'/c/{cid}/insights?sel=analyst')
c, t, loc = req('POST', f'/c/{cid}/plan', {'sel': 'analyst', 'sec': '', 'mix': '100', 'reason': 'کم‌نوسان‌ترین ردیف برای تصمیم بعدی لازم است'})
must(loc.endswith(f'/c/{cid}/sim'), 'plan → sim')
c, t, _ = req('GET', f'/c/{cid}/sim')
must('plan-' in t and 'حکم سودآوری' in t, 'sim page shows plan id and verdicts')
req('GET', f'/c/{cid}/sim?wb=20&ws=0')
req('POST', f'/c/{cid}/sim', {'band': 'محتاط', 'ext': 'عادی'})
c, t, loc = req('POST', f'/c/{cid}/sim', {'band': 'معمول', 'ext': 'عادی', 'action': 'save'})
must(loc.endswith(f'/c/{cid}/pace'), 'save sim → pace')
req('GET', f'/c/{cid}/pace?demo=1')
req('POST', f'/c/{cid}/pace', {'day': '12', 'spend': '260000000', 'installs': '3000', 'conv': '100'})
c, t, _ = req('GET', f'/c/{cid}/pace')
must('عقب است' in t, 'pace conversion alert shown')
c, t, _ = req('GET', f'/c/{cid}/verify?demo=1')
vals = re.findall(r'name="(a[bic])\[(\d+)\]" value="([^"]*)"', t)
form = {'name': 'کمپین آزمون', 'fraud': '3', 'window': '7', 'season': 'خیر', 'reach': '0', 'holdout': '0', 'complete': 'بله', 'matched': 'بله'}
for k, i, v in vals:
    form[f'{k}[{i}]'] = v
must(len(vals) >= 3, 'verify form has per-row inputs')
c, t, loc = req('POST', f'/c/{cid}/verify', form)
c, t, _ = req('GET', f'/c/{cid}/verify')
must('علت محتمل: خطای برآورد' in t, 'verify cause = estimation error')
run_id = re.search(r'/runs/(\d+)/calibrate', t)
must(run_id is not None, 'calibrate button present')
if run_id:
    c, t, loc = req('POST', f'/runs/{run_id.group(1)}/calibrate', {})
    c, t, _ = req('GET', f'/c/{cid}/verify')
    must('کالیبراسیون اعمال شد' in t, 'calibration applied')
req('GET', f'/c/{cid}/loop')
c, t, _ = req('GET', f'/c/{cid}/sim')
must('نرخ‌های این طرح از زمان ثبت تغییر کرده‌اند' in t, 'stale-rate notice after calibration')
c, t, _ = req('GET', f'/c/{cid}/report')
must('۵ · اثر روی حافظه‌ی سیستم' in t, 'report rendered')
req('GET', f'/c/{cid}/report.csv')
req('GET', f'/c/{cid}/report.json')
c, t, loc = req('POST', f'/c/{cid}/close', {})
must(loc.endswith('/log'), 'close → log')
c, t, _ = req('GET', '/log')
must('ک-' in t, 'closed loop listed in log')
req('GET', '/log.csv')

# manual verifier, all five presets
for i, cause in enumerate(['خطای برآورد', 'انحراف اجرا', 'مقیاس، نه کیفیت', 'ناسازگاری داده', 'در دامنه‌ی انتظار']):
    c, t, _ = req('GET', f'/verify?preset={i}')
    fields = dict(re.findall(r'name="(name|ch|seg|from|to|pb|pi|pc|ab|ai|ac|fraud|reach|holdout)" value="([^"]*)"', t))
    for s in ['ch', 'seg', 'src', 'window', 'season', 'complete', 'matched']:
        m = re.search(r'name="' + s + r'">.*?<option selected>([^<]*)</option>', t, re.S)
        if m:
            fields[s] = m.group(1)
    c, t, loc = req('POST', '/verify', fields)
    c, t, _ = req('GET', '/verify')
    must('علت محتمل: ' + cause in t, f'manual preset {i} → {cause}')

c, t, _ = req('POST', '/ask', json_body={'q': 'ارزان‌ترین کانال کدام است؟'})
d = json.loads(t)
must(d.get('grounded') and 'کمترین CAC' in d.get('text', ''), 'ask grounded answer')
c, t, _ = req('POST', '/ask', json_body={'q': 'قیمت دلار فردا؟'})
must(not json.loads(t).get('grounded'), 'ask ungrounded')

for p in ['/connect', '/team', '/rules', '/audit', '/analytics', '/security', '/billing', '/help', '/settings', '/admin']:
    req('GET', p)


# ---------------------------------------------------------------- platform actions
c, t, loc = req('POST', '/billing/checkout', {'tier': 'growth'})
c, t, _ = req('GET', '/billing')
must('پلن فعلی' in t and 'پرداخت شد' in t, 'sandbox checkout activates growth')
c, t, loc = req('POST', '/rules', {'execTh': '0.15', 'estTh': '0.25', 'scaleLo': '0.8', 'scaleHi': '1.2', 'inflation': '0.035', 'attrWindow': '7', 'fraudTh': '0.08', 'auto_calibrate': '1'})
c, t, _ = req('GET', '/rules')
must('name="auto_calibrate" value="1" checked' in t, 'rules saved (auto calibrate on)')
req('POST', '/rules', {'execTh': '0.15', 'estTh': '0.25', 'scaleLo': '0.8', 'scaleHi': '1.2', 'inflation': '0.035', 'attrWindow': '7', 'fraudTh': '0.08'})
import random
inv = 'e2e-%d@example.com' % random.randint(1000, 999999)
req('POST', '/team/invite', {'email': inv, 'role': 'analyst'})
c, t, _ = req('GET', '/team')
must(inv in t, 'invite listed as pending')
iid = re.search(r'/team/invite/(\d+)/revoke', t)
if iid:
    req('POST', f'/team/invite/{iid.group(1)}/revoke', {})
req('POST', '/security/2fa/setup', {})
c, t, _ = req('GET', '/security')
must('data-qr="otpauth://totp/' in t, '2FA setup shows QR')
req('POST', '/security/2fa/enable', {'code': '000000'})
req('GET', '/security/export')
req('POST', '/help/ticket', {'body': 'آزمون پشتیبانی خودکار'})
c, t, _ = req('GET', '/help')
must('آزمون پشتیبانی خودکار' in t, 'ticket stored')
req('POST', '/settings/account', {'name': 'مدیر آزمون', 'mail_pace_alert': '1', 'mail_monthly_report': '1'})
c, t, _ = req('GET', '/settings')
must('name="mail_campaign_ending" value="1">' in t, 'email pref off persisted')
req('POST', '/settings/account', {'name': 'مدیر آزمون', 'mail_pace_alert': '1', 'mail_monthly_report': '1', 'mail_campaign_ending': '1'})
req('POST', '/notifications/read', {})

# second campaign (live) → connector + API
c, t, loc = req('POST', '/campaigns/new', {})
cid2 = re.search(r'/c/(\d+)/design', loc).group(1)
req('POST', f'/c/{cid2}/design', {'name': 'کمپین API', 'goalType': 'خرید', 'goalValue': '1500', 'budget': '300000000', 'from': '1405/08/01', 'to': '1405/08/20',
                                  'channels[]': ['گوگل', 'تپسل', 'پوش'], 'segments[]': ['کاربر جدید', 'فعال'], 'occasion': 'بدون مناسبت', 'risk': 'متعادل'})
req('GET', f'/c/{cid2}/insights')
req('POST', f'/c/{cid2}/plan', {'sel': 'cfo', 'sec': '', 'mix': '100', 'reason': 'سود واحد مثبت برای این فصل مهم‌ترین معیار است'})
req('POST', f'/c/{cid2}/sim', {'band': 'معمول', 'ext': 'عادی', 'action': 'save'})
req('POST', '/connect/adtrace', {'api_key': 'mock-key-123'})
c, t, _ = req('GET', '/connect')
must('آخرین همگام‌سازی' in t or 'همگام‌سازی اکنون' in t, 'adtrace connected (mock)')
req('POST', f'/c/{cid2}/link', {'kind': 'adtrace', 'external_id': 'ext-' + cid2})
req('POST', '/connect/adtrace/sync', {})
c, t, loc = req('POST', '/api-keys', {'name': 'e2e'})
c, t, _ = req('GET', '/connect')
km = re.search(r'value="(sk_loop_[a-f0-9]+)" readonly', t)
must(km is not None, 'API key shown once')
c, t, _ = req('GET', f'/c/{cid2}/report.json')
rep = json.loads(t)['report'] or {}
rows = []
for a in rep.get('alloc', []):
    ch, seg = a['label'].split(' · ')
    rows.append({'channel': ch, 'segment': seg, 'spend': 50000000, 'installs': 1000, 'conversions': 100 if ch == 'پوش' else 60})
code = rep.get('chain', [{}])[0].get('v', '').split(' ')[0]
if km:
    def api(method, path, body=None, key=km.group(1)):
        r = urllib.request.Request(BASE + path, data=json.dumps(body).encode() if body is not None else None, method=method,
                                   headers={'Authorization': 'Bearer ' + key, 'Content-Type': 'application/json'})
        try:
            resp = urllib.request.urlopen(r)
            return resp.status, json.loads(resp.read().decode())
        except urllib.error.HTTPError as e:
            return e.code, json.loads(e.read().decode() or '{}')
    st, d = api('POST', '/api/v1/results', {'plan_code': code, 'rows': rows, 'fraud_pct': 2, 'window_days': 7})
    must(st == 201 and d.get('ok') and d['data'].get('cause'), f'API results → run ({st} {d.get("data", {}).get("cause", d.get("error"))})')
    st, d = api('GET', '/api/v1/campaigns/' + code)
    must(st == 200 and d['data']['plan']['code'] == code, 'API chain lookup')
    st, d = api('GET', '/api/v1/campaigns/' + code, key='sk_loop_' + '0' * 40)
    must(st == 401, 'API rejects bad key')
c, t, _ = req('GET', f'/c/{cid2}/verify')
must('/confirm' in t, 'API draft run awaiting confirmation')
rid = re.search(r'/runs/(\d+)/confirm', t)
if rid:
    req('POST', f'/runs/{rid.group(1)}/confirm', {})

# admin
c, t, _ = req('GET', '/admin')
must('کلید API متیس' in t, 'admin AI settings rendered')
req('POST', '/admin/settings', {'tab': 'ai', 'ai_provider': 'mock', 'metis_base_url': 'https://api.metisai.ir/openai/v1', 'metis_model_fast': 'gpt-4o-mini', 'metis_model_smart': 'gpt-4o', 'ai_timeout_fast_ms': '8000', 'ai_timeout_smart_ms': '20000', 'ai_budget_trial': '200000', 'ai_budget_growth': '2000000', 'ai_budget_enterprise': '20000000', 'ai_json_schema__present': '1', 'ai_json_schema': '1', 'ai_debug__present': '1'})
req('POST', '/admin/ai-test', {})
c, t, _ = req('GET', '/admin')
must('کلید متیس وارد نشده است' in t, 'AI test reports missing key')
tk = re.search(r'/admin/ticket/(\d+)', t)
if tk:
    req('POST', f'/admin/ticket/{tk.group(1)}', {'reply': 'پاسخ آزمایشی'})
req('GET', '/audit?q=' + urllib.parse.quote('قاعده'))
req('GET', '/analytics')

print('\nRESULT:', 'PASS' if not fails else f'{len(fails)} FAIL')
for f in fails[:8]:
    print('---', f[0], f[1], f[2]); print(f[3][:600])
sys.exit(1 if fails else 0)
