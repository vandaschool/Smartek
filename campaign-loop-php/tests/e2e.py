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

print('\nRESULT:', 'PASS' if not fails else f'{len(fails)} FAIL')
for f in fails[:8]:
    print('---', f[0], f[1], f[2]); print(f[3][:600])
sys.exit(1 if fails else 0)
