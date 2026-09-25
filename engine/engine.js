// Campaign Loop — Layer 1 engine (deterministic).
// Extracted verbatim from Campaign Loop v4.dc.html so the backend starts from the exact logic the UI was designed against.
// Refactor target: move pure methods (see list below) into typed functions; drop renderVals/UI methods.
// Pure engine methods: infl, cpiAdj, cac, installsAt, poas, allocate, expected, score, fitScore, buildInsights,
// narrate, mergedAlloc, simulate, verify, applyCalibration, shiftAlloc, scaleAlloc, answer, readImport, parseCsv.
class DCLogic {
  constructor(props) { this.props = props || {}; this.state = {}; }
  setState(u) { const v = typeof u === 'function' ? u(this.state) : u; this.state = Object.assign({}, this.state, v); }
  forceUpdate() {}
}
const _mem = {};
const localStorage = { getItem: k => (k in _mem ? _mem[k] : null), setItem: (k, v) => { _mem[k] = String(v); } };
const window = { addEventListener() {}, removeEventListener() {} };
const document = { createElement: () => ({ click() {} }) };
const React = {};

class Component extends DCLogic {
  constructor(p) {
    super(p);
    this.state = this.boot();
  }

  /* ---------- seed data ---------- */
  channels() { return ['گوگل', 'تپسل', 'یکتانت', 'اینستاگرام', 'پوش', 'پیامک']; }
  segments() { return ['کاربر جدید', 'فعال', 'در معرض ریزش', 'پرارزش', 'بازگشتی']; }

  seedRates() {
    const d = [
      ['گوگل', 'کاربر جدید', 62000, 0.075, 850000, 0.22, 6, 25000, 0.06],
      ['گوگل', 'فعال', 58000, 0.110, 1150000, 0.18, 4, 8000, 0.05],
      ['تپسل', 'کاربر جدید', 45000, 0.055, 850000, 0.30, 5, 30000, 0.12],
      ['تپسل', 'بازگشتی', 41000, 0.090, 980000, 0.26, 3, 6000, 0.09],
      ['یکتانت', 'فعال', 50000, 0.135, 1150000, 0.12, 7, 9000, 0.08],
      ['یکتانت', 'کاربر جدید', 54000, 0.070, 850000, 0.19, 5, 14000, 0.07],
      ['اینستاگرام', 'کاربر جدید', 38000, 0.048, 850000, 0.34, 8, 40000, 0.21],
      ['اینستاگرام', 'پرارزش', 72000, 0.065, 2400000, 0.28, 2, 2500, 0.18],
      ['پوش', 'فعال', 9000, 0.062, 1150000, 0.14, 11, 12000, 0.04],
      ['پوش', 'در معرض ریزش', 8500, 0.041, 780000, 0.20, 9, 9000, 0.03],
      ['پوش', 'پرارزش', 9500, 0.088, 2400000, 0.16, 6, 3000, 0.05],
      ['پیامک', 'در معرض ریزش', 12000, 0.052, 780000, 0.23, 7, 11000, 0.10],
      ['پیامک', 'بازگشتی', 11500, 0.068, 980000, 0.21, 5, 7000, 0.11],
      ['پیامک', 'پرارزش', 13000, 0.095, 2400000, 0.17, 3, 2600, 0.14]
    ];
    const owned = ['پوش', 'پیامک'];
    const ret = { 'کاربر جدید': [0.32, 0.14], 'فعال': [0.61, 0.42], 'در معرض ریزش': [0.22, 0.09], 'پرارزش': [0.71, 0.55], 'بازگشتی': [0.44, 0.24] };
    const fraud = { 'گوگل': 0.02, 'تپسل': 0.07, 'یکتانت': 0.04, 'اینستاگرام': 0.05, 'پوش': 0, 'پیامک': 0 };
    return d.map((r, i) => ({ ch: r[0], seg: r[1], type: owned.indexOf(r[0]) > -1 ? 'owned' : 'paid', cpi: r[2], cvr: r[3], aov: r[4], variance: r[5], n: r[6], ceiling: r[7], lift: r[8], d7: ret[r[1]][0], d30: ret[r[1]][1], fraud: fraud[r[0]], age: [2, 4, 1, 6, 3, 2, 5, 7, 1, 3, 2, 4, 2, 5][i], upd: '۱۴۰۵/۰۶/۲۸' }));
  }

  seedProfile() {
    return {
      budget: 800000000, margin: 0.38, targetCac: 320000, ltv: 4200000,
      goal: 'سودآوری', season: 'اوج فروش در آبان و اسفند', blocked: ''
    };
  }

  seedHistory() {
    const d = [
      ['ک-۱۰۲', 'نصب پاییزه گوگل', 'گوگل', 'کاربر جدید', 180000000, 2790, 205, 174000000, 'مهر', 'اسمارتک'],
      ['ک-۱۰۳', 'ریتارگت تپسل', 'تپسل', 'کاربر جدید', 135000000, 2810, 148, 125000000, 'مهر', 'اسمارتک'],
      ['ک-۱۰۴', 'یکتانت فعال‌ها', 'یکتانت', 'فعال', 90000000, 1760, 231, 265000000, 'مهر', 'اسمارتک'],
      ['ک-۱۰۵', 'پوش هفتگی', 'پوش', 'فعال', 24000000, 2600, 158, 181000000, 'مهر', 'اسمارتک'],
      ['ک-۱۰۶', 'اینستا برند', 'اینستاگرام', 'کاربر جدید', 210000000, 5320, 251, 213000000, 'آبان', 'اسمارتک'],
      ['ک-۱۰۷', 'یازده‌یازده یکتانت', 'یکتانت', 'فعال', 160000000, 3180, 441, 507000000, 'آبان', 'اسمارتک'],
      ['ک-۱۰۸', 'پوش پرارزش', 'پوش', 'پرارزش', 19000000, 1980, 176, 422000000, 'آبان', 'اسمارتک'],
      ['ک-۱۰۹', 'پیامک بازگشت', 'پیامک', 'بازگشتی', 46000000, 3950, 265, 259000000, 'آبان', 'ورود دستی'],
      ['ک-۱۱۰', 'گوگل فعال', 'گوگل', 'فعال', 70000000, 1190, 129, 148000000, 'آذر', 'اسمارتک'],
      ['ک-۱۱۱', 'ضدریزش پوش', 'پوش', 'در معرض ریزش', 21000000, 2420, 97, 75000000, 'آذر', 'اسمارتک'],
      ['ک-۱۱۲', 'پیامک ریزش', 'پیامک', 'در معرض ریزش', 33000000, 2680, 133, 103000000, 'آذر', 'ورود دستی'],
      ['ک-۱۱۳', 'اینستا پرارزش', 'اینستاگرام', 'پرارزش', 88000000, 1190, 74, 177000000, 'آذر', 'اسمارتک'],
      ['ک-۱۱۴', 'تپسل بازگشتی', 'تپسل', 'بازگشتی', 52000000, 1250, 110, 107000000, 'دی', 'اسمارتک'],
      ['ک-۱۱۵', 'یکتانت جدید', 'یکتانت', 'کاربر جدید', 108000000, 1980, 136, 115000000, 'دی', 'اسمارتک'],
      ['ک-۱۱۶', 'پوش فعال دی', 'پوش', 'فعال', 27000000, 3050, 183, 210000000, 'دی', 'اسمارتک'],
      ['ک-۱۱۷', 'پیامک پرارزش', 'پیامک', 'پرارزش', 31000000, 2350, 219, 525000000, 'بهمن', 'ورود دستی'],
      ['ک-۱۱۸', 'گوگل نوروزی', 'گوگل', 'کاربر جدید', 240000000, 3720, 285, 242000000, 'اسفند', 'اسمارتک'],
      ['ک-۱۱۹', 'اینستا نوروزی', 'اینستاگرام', 'کاربر جدید', 265000000, 6980, 321, 273000000, 'اسفند', 'اسمارتک'],
      ['ک-۱۲۰', 'یکتانت اسفند', 'یکتانت', 'فعال', 140000000, 2740, 384, 442000000, 'اسفند', 'اسمارتک']
    ];
    return d.map(r => ({ id: r[0], name: r[1], channel: r[2], segment: r[3], spend: r[4], installs: r[5], conversions: r[6], revenue: r[7], month: r[8], source: r[9] }));
  }

  blankVI() {
    return {
      name: 'کمپین بدون عنوان', ch: 'یکتانت', seg: 'فعال', from: '۱۴۰۵/۰۷/۰۱', to: '۱۴۰۵/۰۷/۳۰',
      pb: 500000000, pi: 10000, pc: 800, src: 'مکتوب و عددی',
      ab: 520000000, ai: 6800, ac: 590, complete: 'بله', matched: 'بله', fromSim: false,
      fraud: 3, window: 7, season: 'خیر', reach: 0, holdout: 0
    };
  }

  boot() {
    let saved = null;
    try { saved = JSON.parse(localStorage.getItem('smartech_loop_v5') || 'null'); } catch (e) { }
    const base = {
      page: 'data', archive: this.seedArchive(), rates: this.seedRates(), profile: this.seedProfile(), history: this.seedHistory(),
      di: { goalType: 'خرید', goalValue: 2500, budget: 500000000, from: '۱۴۰۵/۰۷/۰۱', to: '۱۴۰۵/۰۷/۳۰', channels: this.channels(), segments: this.segments(), risk: 'متعادل' },
      insights: [], sel: null, sec: null, mix: 60, reason: '', plan: null,
      simOpts: { conf: 'معمول', ext: 'عادی' }, sim: null,
      vi: this.blankVI(), vr: null, calib: null, dash: 'cfo',
      workspace: { name: 'فضای کاری مرچنت نمونه', currency: 'تومان', tz: 'تهران (UTC+۳:۳۰)' },
      team: [
        { name: 'محسن علی‌پور', email: 'mohsen@merchant.ir', role: 'مالک' },
        { name: 'مهدی اکبری', email: 'mehdi@merchant.ir', role: 'تحلیل‌گر' },
        { name: 'ناظر مالی', email: 'finance@merchant.ir', role: 'ناظر' }
      ],
      invite: { email: '', role: 'تحلیل‌گر' },
      integrations: { adtress: 'available', intrack: 'available', adverge: 'soon', affilio: 'soon' },
      apiKey: '', importPrev: null, verified: false, forgotSent: false, calibHistory: [], notifs: [], notifOpen: false, askQ: '', askA: null, whatIf: { budget: 0, shift: 0 }, plan2fa: false, sessions: [{ dev: 'Chrome · macOS', where: 'تهران', now: true }, { dev: 'Safari · iPhone', where: 'تهران', now: false }], plan_tier: 'آزمایشی', events: [], online: true, welcome: false, celebrate: false, seqNo: 101, campaigns: [], audit: [], role: 'مالک', tour: 0,
      cfg: { execTh: 0.15, estTh: 0.25, scaleLo: 0.8, scaleHi: 1.2, inflation: 0.035, attrWindow: 7, fraudTh: 0.08 },
      pace: { day: 10, spend: 0, installs: 0, conv: 0 },
      auth: null, registered: null, authMode: 'signup', authForm: { name: '', company: '', email: '', pass: '' }, authError: ''
    };
    const s = saved && saved.rates ? Object.assign(base, saved) : base;
    if (typeof s.page !== 'string') s.page = 'data';
    if (!Array.isArray(s.archive)) s.archive = this.seedArchive();
    if (!s.signupPending) s.verified = true;
    return s;
  }

  componentDidMount() {
    this._on = () => this.setState({ online: true }); this._off = () => this.setState({ online: false });
    window.addEventListener('online', this._on); window.addEventListener('offline', this._off);
  }
  componentWillUnmount() { window.removeEventListener('online', this._on); window.removeEventListener('offline', this._off); }

  componentDidUpdate() {
    try { localStorage.setItem('smartech_loop_v5', JSON.stringify(this.state)); } catch (e) { }
  }

  seedArchive() {
    const d = [
      ['ک-۱۱۲', 'پیامک ریزش — آذر', 'سگمنت در معرض ریزش', 'CAC جذب مجدد از کاربر جدید ارزان‌تر بود', 'در دامنه‌ی انتظار', true, 248000, 261000],
      ['ک-۱۱۳', 'اینستا پرارزش — آذر', 'سگمنت پرارزش', 'AOV این سگمنت دو برابر میانگین بود', 'خطای برآورد', true, 890000, 1189000],
      ['ک-۱۱۴', 'تپسل بازگشتی — دی', 'مدیرعامل', 'فشار هدف سهم بازار در فصل', 'انحراف اجرا', false, 430000, 473000],
      ['ک-۱۱۶', 'پوش فعال — دی', 'مسئول کمپین', 'کمترین ریسک عملیاتی برای راه‌اندازی سریع', 'مقیاس، نه کیفیت', false, 141000, 148000],
      ['ک-۱۱۷', 'پیامک پرارزش — بهمن', 'مدیر مالی', 'تنها ترکیب زیر سقف حاشیه', 'در دامنه‌ی انتظار', true, 139000, 142000]
    ];
    return d.map((r, i) => ({
      id: r[0], name: r[1], perspective: r[2], reason: r[3], cause: r[4], calib: r[5],
      plannedCac: r[6], actualCac: r[7], seeded: true
    }));
  }

  readiness() {
    const { history, rates, profile } = this.state;
    const strong = rates.filter(r => r.n >= 5).length;
    const c = [
      { label: 'کمپین تاریخی ثبت‌شده', need: 'حداقل ۱۵ ردیف', have: this.fa(history.length) + ' ردیف', ok: history.length >= 15 },
      { label: 'ردیف‌های نرخ با نمونه‌ی کافی', need: 'حداقل ۸ ردیف با sample_n ≥ ۵', have: this.fa(strong) + ' ردیف', ok: strong >= 8 },
      { label: 'حاشیه‌ی سود ناخالص', need: 'اجباری — دیدگاه مدیر مالی بدون آن کار نمی‌کند', have: profile.margin ? this.pct(profile.margin, 0) : 'خالی', ok: !!profile.margin },
      { label: 'هدف کسب‌وکار', need: 'اجباری — مبنای مرتب‌سازی اینسایت‌ها', have: profile.goal || 'خالی', ok: !!profile.goal },
      { label: 'بودجه‌ی ماهانه‌ی تبلیغات', need: 'اجباری — مقیاس پیشنهادها', have: profile.budget ? this.money(profile.budget) + ' ت' : 'خالی', ok: !!profile.budget },
      { label: 'CAC هدف و LTV', need: 'اختیاری — دیدگاه سگمنت پرارزش', have: profile.targetCac && profile.ltv ? 'ثبت شده' : 'ناقص', ok: !!(profile.targetCac && profile.ltv), soft: true }
    ];
    const hard = c.filter(x => !x.soft);
    return { checks: c, pass: hard.filter(x => x.ok).length, total: hard.length, ready: hard.every(x => x.ok) };
  }

  reportData() {
    const S = this.state, sim = this.simulate(), vr = S.vr, vi = S.vi;
    if (!S.plan) return null;
    const dev = (a, p) => (p > 0 ? (a - p) / p : 0);
    return {
      title: vi.name,
      merchant: (S.auth ? S.auth.company : 'مرچنت') + ' · هدف کسب‌وکار: ' + S.profile.goal + ' · حاشیه‌ی سود ' + this.pct(S.profile.margin, 0),
      window: S.di.from + ' تا ' + S.di.to,
      chain: [
        { k: 'plan_id', v: S.plan.planId },
        { k: 'sim_id', v: sim ? sim.simId : '—' },
        { k: 'run_id', v: S.calib ? S.calib.runId : (vr ? 'run-۱۴۰۵-' + this.fa(S.plan.seq || S.seqNo) : '—') },
        { k: 'calibration_id', v: S.calib ? S.calib.id : '—' }
      ],
      perspective: S.plan.perspective,
      reason: S.plan.reason || '—',
      goal: 'هدف: ' + this.num(S.plan.goalValue) + ' ' + S.plan.goalType + ' با بودجه‌ی ' + this.money(S.di.budget) + ' تومان',
      alloc: S.plan.alloc.map(a => ({
        label: a.ch + ' · ' + a.seg, budget: this.money(a.budget), share: this.pct(a.share, 0),
        cac: this.money(this.cac(a)), evidence: 'rates: ' + a.ch + '|' + a.seg + ' · n=' + this.fa(a.n)
      })),
      forecast: sim ? [
        { k: 'نصب', v: this.num(sim.installs), band: this.num(sim.iLow) + ' – ' + this.num(sim.iHigh) },
        { k: 'خرید', v: this.num(sim.conv), band: this.num(sim.cLow) + ' – ' + this.num(sim.cHigh) },
        { k: 'درآمد', v: this.money(sim.rev), band: this.money(sim.rLow) + ' – ' + this.money(sim.rHigh) },
        { k: 'CAC پیش‌بینی‌شده', v: this.money(sim.cac), band: 'ROAS ' + this.dec(sim.roas, 2) + '×' }
      ] : [],
      verdicts: sim ? [sim.risk.text, sim.prof.text] : [],
      result: vr ? [
        { k: 'بودجه', p: this.money(+vi.pb), a: this.money(+vi.ab), d: this.signPct(vr.dB) },
        { k: 'نصب', p: this.num(+vi.pi), a: this.num(+vi.ai), d: this.signPct(vr.dI) },
        { k: 'خرید', p: this.num(+vi.pc), a: this.num(+vi.ac), d: this.signPct(vr.dC) }
      ] : [],
      cause: vr ? vr.cause : '—',
      causeText: vr ? vr.expl : 'نتیجه‌ی واقعی ثبت نشده است.',
      calib: S.calib ? 'ردیف ' + S.calib.row + ': CPI از ' + this.num(S.calib.before.cpi) + ' به ' + this.num(S.calib.after.cpi) + ' · CVR از ' + this.dec(S.calib.before.cvr * 100, 3) + '٪ به ' + this.dec(S.calib.after.cvr * 100, 3) + '٪ · sample_n از ' + this.fa(S.calib.before.n) + ' به ' + this.fa(S.calib.after.n) : 'کالیبراسیونی اعمال نشده' + (vr && !vr.calib ? ' — شاخه‌ی «' + vr.cause + '» عمداً نرخ‌ها را دست نمی‌زند.' : '.'),
      hasResult: !!vr
    };
  }

  /* ---------- formatting ---------- */
  fa(s) { return String(s).replace(/[0-9]/g, d => '۰۱۲۳۴۵۶۷۸۹'[+d]).replace(/\./g, '٫'); }
  num(n) { return this.fa(Math.round(n).toLocaleString('en-US').replace(/,/g, '٬')); }
  dec(n, d) { return this.fa(Number(n).toFixed(d)); }
  money(n) {
    const a = Math.abs(n);
    if (a >= 1e9) return this.dec(n / 1e9, a >= 1e10 ? 0 : 1) + ' میلیارد';
    if (a >= 1e7) { const m = n / 1e6; return this.dec(m, Math.abs(m - Math.round(m)) < 0.05 ? 0 : 1) + ' م'; }
    if (a >= 1e3) return this.num(n);
    return this.num(n);
  }
  pct(x, dec) { return this.fa((x * 100).toFixed(dec === undefined ? 1 : dec)) + '٪'; }
  signPct(x) { const a = Math.abs(x); const s = x >= 0 ? '+' : '−'; return s + this.dec(a * 100, a < 0.01 ? 2 : a < 0.1 ? 1 : 0) + '٪'; }

  /* ---------- engine (layer 1) ---------- */
  infl(r) { const c = this.state.cfg || {}; return Math.pow(1 + (c.inflation || 0), r.age || 0); }
  cpiAdj(r) { return Math.round(r.cpi * this.infl(r)); }
  cac(r) { return this.cpiAdj(r) / r.cvr; }
  // diminishing returns: installs = B/cpi * 1/(1 + 0.5*B/(ceiling*cpi))
  installsAt(a, budget) { const cpi = this.cpiAdj(a); const k = budget / Math.max(a.ceiling * cpi, 1); return budget / cpi / (1 + 0.5 * k); }
  poas(rev, spend) { return spend > 0 ? (rev * this.state.profile.margin - spend) / spend : 0; }
  cap(r) { return this.state.profile.margin * r.aov; }

  eligibleRows() {
    const { rates, di } = this.state;
    return rates.filter(r => di.channels.indexOf(r.ch) > -1 && di.segments.indexOf(r.seg) > -1);
  }

  perspectives() {
    return [
      { id: 'cfo', name: 'مدیر مالی', obj: 'max_unit_profit' },
      { id: 'ceo', name: 'مدیرعامل', obj: 'max_volume' },
      { id: 'cmo', name: 'مدیر بازاریابی', obj: 'max_composite_rank' },
      { id: 'analyst', name: 'تحلیل‌گر داده', obj: 'min_variance' },
      { id: 'ops', name: 'مسئول کمپین', obj: 'max_sample_n' },
      { id: 'value', name: 'سگمنت پرارزش', obj: 'max_aov_x_cvr' },
      { id: 'churn', name: 'سگمنت در معرض ریزش', obj: 'min_reacquisition_cost' },
      { id: 'season', name: 'فصلی و مناسبتی', obj: 'max_seasonal_lift' },
      { id: 'compete', name: 'رقابتی', obj: 'min_sample_n_acceptable' },
      { id: 'safe', name: 'محافظه‌کار', obj: 'top_objective_x_0.2' }
    ];
  }

  score(id, r, rows) {
    const cac = this.cac(r), cap = this.cap(r);
    if (id === 'cfo') return (cac <= cap ? 1000 : 0) + cap / cac;
    if (id === 'ceo') return 1 / r.cpi * 1e6;
    if (id === 'cmo') {
      const volRank = rows.filter(x => 1 / x.cpi > 1 / r.cpi).length;
      const effRank = rows.filter(x => this.cac(x) < cac).length;
      return -(volRank + effRank);
    }
    if (id === 'analyst') return -r.variance;
    if (id === 'ops') return r.n;
    if (id === 'value') return r.aov * r.cvr / 1e5;
    if (id === 'churn') return (r.seg === 'در معرض ریزش' ? 1000 : 0) + 1e6 / cac;
    if (id === 'season') return r.lift;
    if (id === 'compete') return (cac <= cap ? 100 : 30) / Math.max(r.n, 1);
    return 0;
  }

  allocate(sorted, budget) {
    const out = []; let rem = budget;
    for (const r of sorted) {
      if (rem <= budget * 0.005) break;
      const capB = r.ceiling * this.cpiAdj(r);
      const b = Math.min(rem, capB);
      out.push({ ch: r.ch, seg: r.seg, type: r.type, cpi: r.cpi, age: r.age, cvr: r.cvr, aov: r.aov, variance: r.variance, n: r.n, ceiling: r.ceiling, d7: r.d7, d30: r.d30, fraud: r.fraud, budget: b });
      rem -= b;
    }
    if (out.length === 0) return out;
    if (rem > 0) out[0].budget += rem;
    const tot = out.reduce((s, x) => s + x.budget, 0);
    out.forEach(x => { x.share = x.budget / tot; });
    return out;
  }

  expected(alloc) {
    let inst = 0, conv = 0, rev = 0, budget = 0;
    alloc.forEach(a => {
      const i = this.installsAt(a, a.budget), c = i * a.cvr;
      inst += i; conv += c; rev += c * a.aov; budget += a.budget;
    });
    return { installs: inst, conv: conv, revenue: rev, budget: budget, cac: conv > 0 ? budget / conv : 0, roas: budget > 0 ? rev / budget : 0, poas: this.poas(rev, budget) };
  }

  fitScore(p) {
    const goal = this.state.profile.goal, risk = this.state.di.risk;
    const byGoal = {
      'رشد': { ceo: 3, cmo: 2, compete: 2, season: 1 },
      'سودآوری': { cfo: 3, value: 2, churn: 2, analyst: 1 },
      'نگهداشت': { churn: 3, value: 2, ops: 2, cfo: 1 },
      'سهم بازار': { ceo: 3, compete: 3, cmo: 2, season: 1 }
    }[goal] || {};
    const byRisk = {
      'محافظه‌کار': { safe: 3, analyst: 2, ops: 2 },
      'متعادل': { cmo: 2, cfo: 1, value: 1 },
      'تهاجمی': { ceo: 2, compete: 2, season: 1 }
    }[risk] || {};
    return (byGoal[p.id] || 0) + (byRisk[p.id] || 0);
  }

  buildInsights() {
    const rows = this.eligibleRows();
    if (rows.length === 0) return [];
    const budget = +this.state.di.budget || 0;
    const persp = this.perspectives();
    const ranked = persp.filter(p => p.id !== 'safe').slice().sort((a, b) => this.fitScore(b) - this.fitScore(a));
    const topId = ranked[0].id;

    const out = persp.map(p => {
      const useId = p.id === 'safe' ? topId : p.id;
      const sorted = rows.slice().sort((a, b) => this.score(useId, b, rows) - this.score(useId, a, rows));
      const alloc = this.allocate(sorted, p.id === 'safe' ? budget * 0.2 : budget);
      const e = this.expected(alloc);
      const w = alloc[0] || sorted[0];
      const t = this.narrate(p, w, alloc, e, rows, topId);
      return {
        id: p.id, perspective: p.name, objective: p.obj, claim: t.claim, proposal: t.proposal,
        evidence: t.evidence, risk: t.risk, successMetric: t.success, alloc: alloc, exp: e,
        fit: this.fitScore(p) + (p.id === 'safe' ? this.fitScore({ id: 'safe' }) : 0)
      };
    });
    return out.sort((a, b) => b.fit - a.fit);
  }

  narrate(p, w, alloc, e, rows, topId) {
    const M = n => this.money(n) + ' تومان';
    const wCac = this.cac(w), wCap = this.cap(w);
    const share = a => this.pct(a.share || 1, 0);
    const second = alloc[1];
    const evi = (r, extra) => 'ردیف rates: ' + r.ch + '|' + r.seg + ' · ' + this.fa(r.n) + ' کمپین پشت این ردیف' + (extra ? ' · ' + extra : '');
    const prop = 'بودجه بین ' + this.fa(alloc.length) + ' ردیف: ' + alloc.map(a => a.ch + '/' + a.seg + ' ' + share(a)).join(' · ');
    const base = { proposal: prop, evidence: evi(w, 'CAC ' + M(wCac)), risk: '—', success: '—' };

    if (p.id === 'cfo') return Object.assign(base, {
      claim: 'از دید سود: روی ' + w.ch + ' و سگمنت «' + w.seg + '» تمرکز کن. CAC آنجا ' + M(wCac) + ' است در برابر سقف حاشیه‌ی ' + M(wCap) + ' — یعنی ' + this.pct(1 - wCac / wCap, 0) + ' فاصله‌ی امن زیر سقف. CAC کل این تخصیص ' + M(e.cac) + ' می‌شود.',
      risk: 'سقف حجم این ردیف ' + this.num(w.ceiling) + ' نصب است؛ بیش از آن بودجه به ردیف‌های گران‌تر سرریز می‌شود.',
      success: 'اگر CAC واقعی زیر ' + M(wCap) + ' ماند، این انتخاب درست بوده.'
    });
    if (p.id === 'ceo') return Object.assign(base, {
      claim: 'از دید رشد: ' + w.ch + ' بیشترین حجم را به ازای بودجه می‌دهد — کل این تخصیص ' + this.num(e.installs) + ' نصب و ' + this.num(e.conv) + ' خرید. CAC بالاتر است (' + M(e.cac) + ') ولی اگر هدف سهم بازار است، این گزینه است.',
      evidence: evi(w, 'CPI ' + M(w.cpi)),
      risk: 'کارایی فدای حجم می‌شود؛ اگر حاشیه‌ی سود کم شود، این تخصیص در سطح واحد ضررده است.',
      success: 'اگر نصب واقعی بالای ' + this.num(e.installs * 0.85) + ' ماند، حجم محقق شده.'
    });
    if (p.id === 'cmo') return Object.assign(base, {
      claim: 'از دید تعادل: تقسیم بودجه بین ' + w.ch + ' (' + share(w) + ') و ' + (second ? second.ch + ' (' + share(second) + ')' : 'ردیف بعدی') + ' هم حجم می‌دهد (' + this.num(e.installs) + ' نصب) هم CAC کل را روی ' + M(e.cac) + ' نگه می‌دارد.',
      risk: 'مدیریت دو کانال هم‌زمان بار عملیاتی و اندازه‌گیری بیشتری دارد.',
      success: 'اگر هم نصب بالای ' + this.num(e.installs * 0.85) + ' و هم CAC زیر ' + M(e.cac * 1.15) + ' بماند.'
    });
    if (p.id === 'analyst') return Object.assign(base, {
      claim: 'از دید قابلیت پیش‌بینی: ' + w.ch + ' روی سگمنت «' + w.seg + '» کمترین نوسان تاریخی را دارد (±' + this.pct(w.variance, 0) + '). اگر تصمیم بعدی به این نتیجه وابسته است، اینجا امن‌تر است.',
      evidence: evi(w, 'نوسان ±' + this.pct(w.variance, 0)),
      risk: 'کم‌نوسان بودن به معنی بهینه بودن نیست؛ ممکن است CAC از گزینه‌های پرنوسان بدتر باشد.',
      success: 'اگر نتیجه‌ی واقعی داخل بازه‌ی ±' + this.pct(w.variance, 0) + ' افتاد، مدل نرخ قابل اتکاست.'
    });
    if (p.id === 'ops') return Object.assign(base, {
      claim: 'از دید اجرا: ' + w.ch + ' را ' + this.fa(w.n) + ' بار اجرا کرده‌اید — سریع‌ترین راه‌اندازی و کمترین ریسک عملیاتی. CAC کل ' + M(e.cac) + '.',
      evidence: evi(w, 'بیشترین sample_n میان ردیف‌های مجاز'),
      risk: 'آشنایی، سوگیری است: کانال‌های کم‌آزموده هیچ‌وقت شانس نمی‌گیرند.',
      success: 'اگر کمپین در کمتر از یک هفته و بدون انحراف اجرا راه افتاد.'
    });
    if (p.id === 'value') return Object.assign(base, {
      claim: 'از دید ارزش مشتری: سگمنت «' + w.seg + '» ارزش سفارش ' + M(w.aov) + ' دارد. با همین بودجه درآمد مورد انتظار ' + M(e.revenue) + ' و ROAS ' + this.fa(e.roas.toFixed(1)) + ' برابر می‌شود — بودجه‌ی کمتر، بازگشت بیشتر.',
      evidence: evi(w, 'AOV ' + M(w.aov) + ' · CVR ' + this.pct(w.cvr)),
      risk: 'اندازه‌ی این سگمنت کوچک است (سقف ' + this.num(w.ceiling) + ' نصب)؛ مقیاس‌پذیر نیست.',
      success: 'اگر ROAS واقعی بالای ' + this.fa((e.roas * 0.8).toFixed(1)) + ' ماند.'
    });
    if (p.id === 'churn') return Object.assign(base, {
      claim: 'از دید نگهداشت: به‌جای جذب جدید، سگمنت در معرض ریزش را هدف بگیر — CAC جذب مجدد ' + M(wCac) + ' در برابر ' + M(this.newUserCac(rows)) + ' برای کاربر جدید.',
      evidence: evi(w, 'CAC جذب مجدد ' + M(wCac)),
      risk: 'سقف این سگمنت محدود است و اثر آن روی رشد مطلق صفر است.',
      success: 'اگر نرخ بازگشت این سگمنت بالای CVR تاریخی ' + this.pct(w.cvr) + ' ماند.'
    });
    if (p.id === 'season') return Object.assign(base, {
      claim: 'از دید زمان‌بندی: ' + w.ch + ' در دوره‌های اوج تاریخی ' + this.pct(w.lift, 0) + ' بهتر عمل کرده. ' + this.state.profile.season + ' — جابه‌جایی بازه‌ی کمپین را بررسی کن.',
      evidence: evi(w, 'ضریب اوج تاریخی +' + this.pct(w.lift, 0)),
      risk: 'رقابت و CPI هم در اوج بالا می‌رود؛ این ضریب تضمین‌شده نیست.',
      success: 'اگر CVR واقعی در بازه‌ی اوج بالای ' + this.pct(w.cvr * (1 + w.lift)) + ' بود.'
    });
    if (p.id === 'compete') return Object.assign(base, {
      claim: 'از دید تمایز: ' + w.ch + ' روی «' + w.seg + '» کمتر استفاده شده (فقط ' + this.fa(w.n) + ' کمپین) ولی نرخش قابل‌قبول است — CAC ' + M(wCac) + '. فرصت کم‌رقابت‌تر.',
      evidence: evi(w, 'کمترین sample_n با CAC قابل‌قبول'),
      risk: 'داده‌ی کم یعنی بازه‌ی عدم‌قطعیت عریض؛ پیش‌بینی این ردیف ضعیف‌تر است.',
      success: 'اگر CAC واقعی زیر ' + M(wCac * 1.3) + ' ماند، ردیف ارزش سرمایه‌گذاری بیشتر دارد.'
    });
    return Object.assign(base, {
      claim: 'از دید ریسک: پیش از تعهد کامل، ۲۰٪ بودجه (' + M(e.budget) + ') را روی ' + w.ch + ' تست کن. هزینه‌ی یادگیری ' + M(e.budget) + ' است در برابر ریسک ' + M(this.state.di.budget) + ' بودجه‌ی کل.',
      evidence: evi(w, 'همان تابع هدف دیدگاه برتر (' + topId + ') با قید بودجه × ۰٫۲'),
      risk: 'نمونه‌ی کوچک ممکن است به آستانه‌ی یادگیری کانال نرسد و نتیجه‌اش بی‌معنا شود.',
      success: 'اگر CAC تست زیر ' + M(wCap) + ' درآمد، بودجه‌ی کامل آزاد شود.'
    });
  }

  newUserCac(rows) {
    const ns = rows.filter(r => r.seg === 'کاربر جدید');
    if (!ns.length) return 0;
    return Math.min.apply(null, ns.map(r => this.cac(r)));
  }

  mergedAlloc() {
    const { insights, sel, sec, mix } = this.state;
    const A = insights.find(i => i.id === sel);
    if (!A) return null;
    const B = sec ? insights.find(i => i.id === sec) : null;
    if (!B) return A.alloc.slice();
    const w = mix / 100, map = {};
    const add = (alloc, factor) => alloc.forEach(a => {
      const k = a.ch + '|' + a.seg;
      if (!map[k]) map[k] = Object.assign({}, a, { budget: 0 });
      map[k].budget += a.budget * factor;
    });
    const tb = +this.state.di.budget;
    const sA = A.exp.budget ? tb * w / A.exp.budget : 0;
    const sB = B.exp.budget ? tb * (1 - w) / B.exp.budget : 0;
    add(A.alloc, sA); add(B.alloc, sB);
    const out = Object.keys(map).map(k => map[k]);
    let spill = 0;
    out.forEach(x => { const capB = x.ceiling * this.cpiAdj(x) * 1.5; if (x.budget > capB) { spill += x.budget - capB; x.budget = capB; x.capped = true; } });
    if (spill > 0) { const free = out.filter(x => !x.capped); if (free.length) free.forEach(x => { x.budget += spill / free.length; }); else out[0].budget += spill; }
    const tot = out.reduce((s, x) => s + x.budget, 0);
    out.forEach(x => { x.share = x.budget / tot; });
    return out.sort((a, b) => b.budget - a.budget);
  }

  extFactor() { return { 'عادی': 1, 'فصل اوج': 1.15, 'رکود': 0.85, 'رقابت شدید': 0.92 }[this.state.simOpts.ext] || 1; }
  // empirical band width multiplier (not a statistical CI)
  confWide() { return { 'باریک': 0.7, 'معمول': 1, 'محتاط': 1.4 }[this.state.simOpts.conf] || 1; }

  simulate() {
    const plan = this.state.plan;
    if (!plan) return null;
    const occ = this.occasions().find(o => o.k === this.state.di.occasion) || { lift: 0, cpi: 0 };
    const P = this.state.profile, f = this.extFactor() * (1 + occ.lift) / (1 + occ.cpi), k = this.confWide();
    let inst = 0, iLow = 0, iHigh = 0, conv = 0, cLow = 0, cHigh = 0, rev = 0, rLow = 0, rHigh = 0, budget = 0, paid = 0, owned = 0, fraudInst = 0, ret30 = 0;
    const rows = plan.alloc.map(a => {
      // one combined spread: sqrt(v_cpi^2 + v_cvr^2) with shared variance → v*sqrt(2)/2 per side
      const v = Math.min(a.variance * k, 0.6);
      const i = this.installsAt(a, a.budget) * f;
      const fr = a.type === 'paid' ? (a.fraud || 0) : 0;
      const iClean = i * (1 - fr);
      const c = iClean * a.cvr;
      const spread = v * 0.85;
      inst += iClean; iLow += iClean * (1 - v * 0.7); iHigh += iClean * (1 + v * 0.7);
      conv += c; cLow += c * (1 - spread); cHigh += c * (1 + spread);
      rev += c * a.aov; rLow += c * (1 - spread) * a.aov; rHigh += c * (1 + spread) * a.aov;
      budget += a.budget; fraudInst += i * fr; ret30 += iClean * (a.d30 || 0);
      if (a.type === 'owned') owned += a.budget; else paid += a.budget;
      return { label: a.ch + ' · ' + a.seg, kind: a.type === 'owned' ? 'کانال خودی' : 'جذب پولی', budget: this.money(a.budget), share: this.pct(a.share, 0), installs: this.num(iClean), conv: this.num(c), revenue: this.money(c * a.aov) };
    });
    const cac = conv ? budget / conv : 0;
    const aovW = conv ? rev / conv : 0;
    const marginCap = P.margin * aovW;
    const poas = this.poas(rev, budget);
    const marginPerOrder = P.margin * aovW;
    const ordersPerMonth = 1.2;
    const payback = marginPerOrder > 0 ? cac / (marginPerOrder * ordersPerMonth) : 0;
    const ltvCac = cac > 0 && P.ltv ? P.ltv / cac : 0;
    const goalValue = +plan.goalValue || 0;
    const target = plan.goalType === 'نصب' ? inst : plan.goalType === 'درآمد' ? rev : conv;
    const tLow = plan.goalType === 'نصب' ? iLow : plan.goalType === 'درآمد' ? rLow : cLow;
    let risk;
    if (goalValue <= 0) risk = { kind: 'حکم ریسک', text: 'عدد هدفی ثبت نشده؛ پوشش هدف قابل ارزیابی نیست.', warn: true };
    else if (tLow >= goalValue) risk = { kind: 'حکم ریسک', text: 'هدف پوشش داده می‌شود — حتی کران پایین بازه‌ی تجربی از هدف بالاتر است.', ok: true };
    else if (target >= goalValue) risk = { kind: 'حکم ریسک', text: 'هدف در دامنه است ولی تضمین‌شده نیست: مورد انتظار ' + this.num(target) + ' در برابر هدف ' + this.num(goalValue) + '، کران پایین ' + this.num(tLow) + '.', warn: true };
    else risk = { kind: 'حکم ریسک', text: 'با این بودجه هدف محقق نمی‌شود — کسری ' + this.num(goalValue - target) + ' ' + plan.goalType + '. با بازده نزولی، افزایش بودجه کسری را خطی جبران نمی‌کند.', bad: true };
    const prof = poas < 0
      ? { kind: 'حکم سودآوری (POAS)', text: 'سود ناخالص پیش‌بینی‌شده از هزینه کمتر است (POAS ' + this.signPct(poas) + '). CAC ' + this.money(cac) + ' در برابر حاشیه‌ی هر سفارش ' + this.money(marginCap) + ' تومان — بازگشت فقط با خرید تکراری ممکن است (دوره‌ی بازگشت ' + this.dec(payback, 1) + ' ماه).', bad: true }
      : { kind: 'حکم سودآوری (POAS)', text: 'سود ناخالص از هزینه بیشتر است (POAS ' + this.signPct(poas) + '). CAC ' + this.money(cac) + ' زیر حاشیه‌ی هر سفارش ' + this.money(marginCap) + ' تومان.', ok: true };
    const seq = plan.seq || 101;
    return {
      simId: 'sim-۱۴۰۵-' + this.fa(seq),
      installs: inst, iLow: iLow, iHigh: iHigh, conv: conv, cLow: cLow, cHigh: cHigh,
      rev: rev, rLow: rLow, rHigh: rHigh, cac: cac, roas: budget ? rev / budget : 0, poas: poas,
      payback: payback, ltvCac: ltvCac, ret30: ret30, fraudInst: fraudInst, paid: paid, owned: owned,
      budget: budget, rows: rows, risk: risk, prof: prof,
      coverage: goalValue ? target / goalValue : 0
    };
  }

  /* ---------- verifier ---------- */
  verify(vi) {
    const C = this.state.cfg;
    const dev = (a, p) => (p > 0 ? (a - p) / p : 0);
    const dB = dev(+vi.ab, +vi.pb), dI = dev(+vi.ai, +vi.pi), dC = dev(+vi.ac, +vi.pc);
    const pCvr = +vi.pi > 0 ? +vi.pc / +vi.pi : 0, aCvr = +vi.ai > 0 ? +vi.ac / +vi.ai : 0;
    const dCvr = dev(aCvr, pCvr);
    const fraud = (+vi.fraud || 0) / 100;
    const winOk = +vi.window === +C.attrWindow;
    // incrementality: treated conv rate vs holdout conv rate
    const hold = (+vi.holdout || 0) / 100;
    const treat = +vi.reach > 0 ? +vi.ac / +vi.reach : 0;
    const lift = hold > 0 && treat > 0 ? (treat - hold) / treat : null;
    let cause, expl, calib = false, block = '';
    if (vi.complete === 'خیر' || vi.matched === 'خیر' || !winOk) {
      cause = 'ناسازگاری داده';
      expl = !winOk ? 'پنجره‌ی انتساب نتیجه (' + this.fa(vi.window) + ' روز) با پنجره‌ی طرح (' + this.fa(C.attrWindow) + ' روز) نمی‌خواند؛ مقایسه‌ی پیش‌بینی و واقعی معتبر نیست.' : 'داده‌ی ناقص یا شناسه‌ی ترکر نامنطبق، هر تفسیری از انحراف را بی‌اعتبار می‌کند.';
      block = 'ابتدا کیفیت داده، انطباق ترکر و پنجره‌ی انتساب اصلاح شود؛ تا آن زمان نرخ‌ها دست نمی‌خورند.';
    } else if (fraud > C.fraudTh) {
      cause = 'ناسازگاری داده';
      expl = 'نرخ نصب مشکوک به تقلب ' + this.pct(fraud, 0) + ' است، بالاتر از آستانه‌ی ' + this.pct(C.fraudTh, 0) + '. نصب‌ها قابل اتکا نیستند.';
      block = 'نصب تقلبی نرخ کانال را به‌طور مصنوعی بهتر نشان می‌دهد؛ کالیبراسیون با این داده حافظه را آلوده می‌کند.';
    } else if (Math.abs(dB) > C.execTh) {
      cause = 'انحراف اجرا';
      expl = 'بودجه‌ی مصرف‌شده با طرح نمی‌خواند (' + this.signPct(dB) + ')؛ پیش از قضاوت درباره‌ی برآورد، اجرا بررسی شود.';
      block = 'انحراف از اجرا آمده، نه از نرخ؛ کالیبره‌کردن در این حالت حافظه‌ی سیستم را با نویز خراب می‌کند.';
    } else if (Math.abs(dB) > 0.02 && dI / dB >= C.scaleLo && dI / dB <= C.scaleHi && Math.abs(dCvr) <= 0.15) {
      cause = 'مقیاس، نه کیفیت';
      expl = 'کمپین بزرگ‌تر یا کوچک‌تر از طرح اجرا شد (بودجه ' + this.signPct(dB) + '، نصب ' + this.signPct(dI) + ') و CVR ثابت ماند (' + this.signPct(dCvr) + ')؛ کارایی تغییر نکرده.';
      block = 'نسبت انحراف نصب به بودجه نزدیک یک و CVR پایدار است؛ نرخ کانال غلط نبوده.';
    } else if (Math.abs(dI) > C.estTh || Math.abs(dCvr) > C.estTh) {
      cause = 'خطای برآورد'; calib = true;
      expl = 'بودجه طبق طرح خرج شد (' + this.signPct(dB) + ') ولی خروجی نخواند — نصب ' + this.signPct(dI) + '، CVR ' + this.signPct(dCvr) + '؛ نرخ‌ها باید اصلاح شوند.';
    } else {
      cause = 'در دامنه‌ی انتظار'; calib = true;
      expl = 'بودجه و خروجی داخل دامنه‌ی انتظار ماندند؛ مشاهده به‌عنوان نمونه‌ی معتبر به نرخ‌ها اضافه می‌شود.';
    }
    const cleanInst = +vi.ai * (1 - fraud);
    const obsCpi = cleanInst > 0 ? +vi.ab / cleanInst : 0;
    const obsCvr = cleanInst > 0 ? +vi.ac / cleanInst : 0;
    const liftText = lift === null ? 'گروه کنترل ثبت نشده — اثر افزایشی قابل سنجش نیست؛ بخشی از خریدها ممکن است ارگانیک باشد.'
      : lift <= 0 ? 'گروه کنترل به همان اندازه خرید داشت — اثر افزایشی کمپین صفر یا منفی است.'
        : this.pct(lift, 0) + ' از خریدها افزایشی است؛ بقیه بدون کمپین هم اتفاق می‌افتاد.';
    return { dB: dB, dI: dI, dC: dC, dCvr: dCvr, cause: cause, expl: expl, calib: calib, block: block, obsCpi: obsCpi, obsCvr: obsCvr, lift: lift, liftText: liftText, fraud: fraud, applied: false };
  }

  presetList() {
    return [
      { label: 'خطای برآورد', vi: { name: 'یکتانت فعال — مهر', ch: 'یکتانت', seg: 'فعال', pb: 500000000, pi: 10000, pc: 800, ab: 520000000, ai: 6800, ac: 590, complete: 'بله', matched: 'بله', src: 'مکتوب و عددی' } },
      { label: 'انحراف اجرا', vi: { name: 'گوگل نصب — آبان', ch: 'گوگل', seg: 'کاربر جدید', pb: 300000000, pi: 4800, pc: 360, ab: 195000000, ai: 3100, ac: 230, complete: 'بله', matched: 'بله', src: 'عددی ولی نامکتوب' } },
      { label: 'مقیاس، نه کیفیت', vi: { name: 'پوش فعال — آذر', ch: 'پوش', seg: 'فعال', pb: 100000000, pi: 11000, pc: 680, ab: 112000000, ai: 12300, ac: 762, complete: 'بله', matched: 'بله', src: 'مکتوب و عددی' } },
      { label: 'ناسازگاری داده', vi: { name: 'اینستاگرام — دی', ch: 'اینستاگرام', seg: 'کاربر جدید', pb: 200000000, pi: 5200, pc: 250, ab: 210000000, ai: 2900, ac: 140, complete: 'خیر', matched: 'خیر', src: 'تجربی و ذهنی' } },
      { label: 'در دامنه‌ی انتظار', vi: { name: 'پیامک پرارزش — بهمن', ch: 'پیامک', seg: 'پرارزش', pb: 120000000, pi: 9200, pc: 875, ab: 124000000, ai: 8900, ac: 845, complete: 'بله', matched: 'بله', src: 'مکتوب و عددی' } }
    ];
  }

  applyCalibration() {
    const vr = this.state.vr, vi = this.state.vi;
    if (!vr || !vr.calib || vr.applied) return;
    if (!this.can('calibrate')) return;
    const rates = this.state.rates.map(r => Object.assign({}, r));
    const idx = rates.findIndex(r => r.ch === vi.ch && r.seg === vi.seg);
    if (idx < 0) return;
    const r = rates[idx];
    const before = { cpi: r.cpi, cvr: r.cvr, n: r.n };
    // weights: spend-size relative to row's typical campaign, recency decay on old rows, season de-lift
    const typical = Math.max(r.ceiling * r.cpi * 0.4, 1);
    const wNew = Math.min(Math.max(+vi.ab / typical, 0.3), 3);
    const wOld = r.n * Math.pow(0.9, r.age || 0);
    const seasonal = vi.season === 'بله' ? 1 + (r.lift || 0) : 1;
    const oCpi = vr.obsCpi / this.infl(r) * seasonal;
    const oCvr = vr.obsCvr / seasonal;
    const newCpi = (r.cpi * wOld + oCpi * wNew) / (wOld + wNew);
    const newCvr = (r.cvr * wOld + oCvr * wNew) / (wOld + wNew);
    rates[idx] = Object.assign({}, r, { cpi: Math.round(newCpi), cvr: +newCvr.toFixed(6), n: r.n + 1, age: 0, upd: '۱۴۰۵/۰۷/۰۱' });
    const after = { cpi: rates[idx].cpi, cvr: rates[idx].cvr, n: rates[idx].n };
    const seq = (this.state.plan && this.state.plan.seq) || this.state.seqNo;
    this.setState({
      rates: rates,
      vr: Object.assign({}, vr, { applied: true }),
      calibHistory: [{ idx: idx, prev: r, id: 'cal-۱۴۰۵-' + this.fa(seq), row: vi.ch + '|' + vi.seg }].concat(this.state.calibHistory || []).slice(0, 10),
      calib: {
        id: 'cal-۱۴۰۵-' + this.fa(seq), runId: 'run-۱۴۰۵-' + this.fa(seq),
        row: vi.ch + '|' + vi.seg, before: before, after: after, cause: vr.cause,
        weights: 'وزن مشاهده‌ی جدید ' + this.dec(wNew, 2) + ' (اندازه‌ی کمپین) در برابر ' + this.dec(wOld, 2) + ' (نمونه‌های قبلی با کاهش تازگی)' + (seasonal > 1 ? ' · اثر فصل حذف شد' : '')
      }
    });
    this.log('کالیبراسیون ردیف ' + vi.ch + '|' + vi.seg, 'CPI ' + this.num(before.cpi) + ' → ' + this.num(after.cpi));
  }

  /* ---------- csv import ---------- */
  parseCsv(text) {
    const rows = []; let row = [], cur = '', q = false;
    text = text.replace(/^\ufeff/, '');
    for (let i = 0; i < text.length; i++) {
      const c = text[i];
      if (q) { if (c === '"' && text[i + 1] === '"') { cur += '"'; i++; } else if (c === '"') q = false; else cur += c; }
      else if (c === '"') q = true;
      else if (c === ',') { row.push(cur); cur = ''; }
      else if (c === '\n' || c === '\r') { if (c === '\r' && text[i + 1] === '\n') i++; row.push(cur); if (row.some(x => x.trim() !== '')) rows.push(row); row = []; cur = ''; }
      else cur += c;
    }
    row.push(cur); if (row.some(x => x.trim() !== '')) rows.push(row);
    return rows;
  }
  toNum(s) { const n = +String(s).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٬,\s]/g, '').replace('٫', '.'); return isFinite(n) ? n : NaN; }
  importSpec() {
    return {
      history: { cols: ['name', 'channel', 'segment', 'spend', 'installs', 'conversions', 'revenue', 'month'], nums: ['spend', 'installs', 'conversions', 'revenue'],
        sample: [['نصب پاییزه گوگل', 'گوگل', 'کاربر جدید', 180000000, 2790, 205, 174000000, 'مهر'], ['پوش هفتگی', 'پوش', 'فعال', 24000000, 2600, 158, 181000000, 'مهر']] },
      rates: { cols: ['channel', 'segment', 'unit_cost', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd30', 'fraud'], nums: ['unit_cost', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd30', 'fraud'],
        sample: [['گوگل', 'کاربر جدید', 62000, 0.075, 850000, 0.22, 6, 25000, 0.14, 0.02], ['پیامک', 'پرارزش', 13000, 0.095, 2400000, 0.17, 3, 2600, 0.55, 0]] }
    };
  }
  readImport(kind, text) {
    const spec = this.importSpec()[kind];
    const rows = this.parseCsv(text);
    if (!rows.length) return { kind: kind, ok: [], errors: [{ line: 0, msg: 'فایل خالی است.' }] };
    const head = rows[0].map(h => h.trim().toLowerCase());
    const missing = spec.cols.filter(c => head.indexOf(c) < 0);
    if (missing.length) return { kind: kind, ok: [], errors: [{ line: 1, msg: 'ستون‌های لازم پیدا نشد: ' + missing.join('، ') }] };
    const ok = [], errors = [];
    rows.slice(1).forEach((r, i) => {
      const o = {}; let bad = '';
      spec.cols.forEach(c => { const v = (r[head.indexOf(c)] || '').trim(); if (spec.nums.indexOf(c) > -1) { const n = this.toNum(v); if (isNaN(n)) bad = bad || ('«' + c + '» عدد نیست'); o[c] = n; } else { if (!v) bad = bad || ('«' + c + '» خالی است'); o[c] = v; } });
      if (!bad && this.channels().indexOf(o.channel) < 0) bad = 'کانال ناشناخته: ' + o.channel;
      if (!bad && this.segments().indexOf(o.segment) < 0) bad = 'سگمنت ناشناخته: ' + o.segment;
      if (!bad && kind === 'history' && o.conversions > o.installs && o.channel !== 'پوش' && o.channel !== 'پیامک') bad = 'خرید از نصب بیشتر است';
      if (!bad && kind === 'rates' && (o.cvr <= 0 || o.cvr > 1)) bad = 'CVR باید بین ۰ و ۱ باشد';
      if (bad) errors.push({ line: i + 2, msg: bad }); else ok.push(o);
    });
    return { kind: kind, ok: ok, errors: errors };
  }
  applyImport() {
    const p = this.state.importPrev; if (!p || !p.ok.length || !this.can('editData')) return;
    if (p.kind === 'history') {
      const base = this.state.history.length;
      const add = p.ok.map((o, i) => ({ id: 'ک-' + this.fa(200 + base + i), name: o.name, channel: o.channel, segment: o.segment, spend: o.spend, installs: o.installs, conversions: o.conversions, revenue: o.revenue, month: o.month, source: 'آپلود CSV' }));
      this.setState({ history: this.state.history.concat(add), importPrev: null });
    } else {
      const owned = ['پوش', 'پیامک'];
      const rates = this.state.rates.slice();
      p.ok.forEach(o => {
        const row = { ch: o.channel, seg: o.segment, type: owned.indexOf(o.channel) > -1 ? 'owned' : 'paid', cpi: o.unit_cost, cvr: o.cvr, aov: o.aov, variance: o.variance, n: o.sample_n, ceiling: o.ceiling, lift: 0.05, d7: Math.min(o.d30 * 2, 0.9), d30: o.d30, fraud: o.fraud, age: 0, upd: 'آپلود CSV' };
        const i = rates.findIndex(r => r.ch === o.channel && r.seg === o.segment);
        if (i > -1) rates[i] = row; else rates.push(row);
      });
      this.setState({ rates: rates, importPrev: null, benchmark: false });
    }
    this.log('آپلود CSV ' + (p.kind === 'history' ? 'تاریخچه' : 'نرخ‌ها'), this.fa(p.ok.length) + ' ردیف');
  }

  scaleAlloc(alloc, factor) { return alloc.map(a => Object.assign({}, a, { budget: a.budget * factor })); }
  shiftAlloc(alloc, pct) {
    if (alloc.length < 2) return alloc.slice();
    const out = alloc.map(a => Object.assign({}, a)); const mv = out[0].budget * pct;
    out[0].budget -= mv; out[1].budget += mv;
    const tot = out.reduce((s, x) => s + x.budget, 0); out.forEach(x => { x.share = x.budget / tot; }); return out;
  }
  occasions() {
    return [
      { k: 'بدون مناسبت', lift: 0, cpi: 0 }, { k: 'یلدا (آذر)', lift: 0.12, cpi: 0.08 }, { k: 'بلک‌فرایدی (آبان)', lift: 0.35, cpi: 0.25 },
      { k: 'نوروز (اسفند–فروردین)', lift: 0.28, cpi: 0.18 }, { k: 'ماه رمضان', lift: -0.08, cpi: -0.05 }, { k: 'بازگشایی مدارس (شهریور)', lift: 0.10, cpi: 0.06 }
    ];
  }
  industry() { return { 'گوگل': 1.08, 'تپسل': 0.95, 'یکتانت': 1.02, 'اینستاگرام': 0.9, 'پوش': 1.1, 'پیامک': 1.05 }; }
  answer(q) {
    const S = this.state, rates = S.rates; q = (q || '').trim();
    if (!q) return null;
    const by = (fn, dir) => rates.slice().sort((a, b) => dir * (fn(a) - fn(b)))[0];
    const src = r => 'rates: ' + r.ch + '|' + r.seg + ' · n=' + this.fa(r.n);
    if (/ارزان|کمترین.*cac|cac.*کم/i.test(q)) { const r = by(x => this.cac(x), 1); return { text: 'کمترین CAC متعلق به ' + r.ch + ' روی «' + r.seg + '» است: ' + this.money(this.cac(r)) + ' تومان (با تعدیل تورم).', src: src(r) }; }
    if (/گران|بیشترین.*cac/i.test(q)) { const r = by(x => this.cac(x), -1); return { text: 'گران‌ترین جذب: ' + r.ch + ' روی «' + r.seg + '» با CAC ' + this.money(this.cac(r)) + ' تومان.', src: src(r) }; }
    if (/نوسان|پایدار|مطمئن/i.test(q)) { const r = by(x => x.variance, 1); return { text: 'پایدارترین ردیف ' + r.ch + ' / ' + r.seg + ' با نوسان ±' + this.pct(r.variance, 0) + ' است.', src: src(r) }; }
    if (/ماندگار|retention|d30/i.test(q)) { const r = by(x => x.d30 || 0, -1); return { text: 'بیشترین ماندگاری روز ۳۰: ' + r.ch + ' / ' + r.seg + ' با ' + this.pct(r.d30 || 0, 0) + '.', src: src(r) }; }
    if (/تقلب|fraud/i.test(q)) { const r = by(x => x.fraud || 0, -1); return { text: 'بیشترین نرخ تقلب در ' + r.ch + ' است: ' + this.pct(r.fraud || 0, 0) + ' از نصب‌ها.', src: src(r) }; }
    if (/سود|poas|بهترین/i.test(q)) { const r = by(x => (x.aov * S.profile.margin * x.cvr - this.cpiAdj(x)) / this.cpiAdj(x), -1); const p = (r.aov * S.profile.margin * r.cvr - this.cpiAdj(r)) / this.cpiAdj(r); return { text: 'سودآورترین ردیف در سفارش اول: ' + r.ch + ' / ' + r.seg + ' با POAS ' + this.signPct(p) + '.', src: src(r) }; }
    const ch = this.channels().find(c => q.indexOf(c) > -1);
    if (ch) { const rs = rates.filter(r => r.ch === ch); const r = rs.slice().sort((a, b) => this.cac(a) - this.cac(b))[0]; return { text: ch + ' در ' + this.fa(rs.length) + ' سگمنت داده دارد؛ بهترین CAC روی «' + r.seg + '»: ' + this.money(this.cac(r)) + ' تومان.', src: src(r) }; }
    return { text: 'این پرسش به یک عدد مشخص در جدول نرخ نگاشت نشد. لایه‌ی ۲ حق ساختن عدد ندارد؛ یکی از پرسش‌های پیشنهادی را امتحان کنید.', src: '—', none: true };
  }

  undoCalibration() {
    const h = (this.state.calibHistory || [])[0]; if (!h || !this.can('calibrate')) return;
    const rates = this.state.rates.slice(); rates[h.idx] = h.prev;
    this.setState({ rates: rates, calibHistory: this.state.calibHistory.slice(1), calib: null, vr: this.state.vr ? Object.assign({}, this.state.vr, { applied: false }) : null });
    this.log('بازگردانی کالیبراسیون ' + h.id, 'ردیف ' + h.row);
  }
  track(ev) { this.setState(s => ({ events: (s.events || []).concat([{ ev: ev, t: Date.now() }]).slice(-300) })); }
  notify(text, kind) { this.setState(s => ({ notifs: [{ text: text, kind: kind || 'info', read: false }].concat(s.notifs || []).slice(0, 30) })); }

  /* ---------- roles & audit ---------- */
  can(action) {
    const role = this.state.role;
    const m = {
      'مالک': ['editData', 'plan', 'result', 'calibrate', 'team'],
      'تحلیل‌گر': ['plan', 'result', 'calibrate'],
      'مسئول کمپین': ['result'],
      'ناظر': []
    };
    return (m[role] || []).indexOf(action) > -1;
  }
  log(what, detail) {
    const who = (this.state.auth && this.state.auth.name) || 'کاربر';
    const d = new Date();
    const t = this.fa(String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'));
    this.setState(s => ({ audit: [{ who: who, role: s.role, what: what, detail: detail || '', t: t }].concat(s.audit || []).slice(0, 60) }));
  }

  /* ---------- dashboard ---------- */
  dashData() {
    const { history, profile, rates } = this.state;
    const d = this.state.dash;
    const tot = history.reduce((s, h) => ({ spend: s.spend + h.spend, conv: s.conv + h.conversions, rev: s.rev + h.revenue, inst: s.inst + h.installs }), { spend: 0, conv: 0, rev: 0, inst: 0 });
    const cac = tot.conv ? tot.spend / tot.conv : 0;
    const byChannel = {};
    history.forEach(h => {
      if (!byChannel[h.channel]) byChannel[h.channel] = { spend: 0, conv: 0, rev: 0, inst: 0 };
      const b = byChannel[h.channel]; b.spend += h.spend; b.conv += h.conversions; b.rev += h.revenue; b.inst += h.installs;
    });
    const chKeys = Object.keys(byChannel);
    const losing = history.filter(h => h.conversions > 0 && (h.spend / h.conversions) > profile.margin * (h.revenue / Math.max(h.conversions, 1)));
    const marginCap = profile.margin * (tot.conv ? tot.rev / tot.conv : 0);

    if (d === 'cfo') {
      const list = chKeys.map(k => ({ label: k, raw: byChannel[k].rev - byChannel[k].spend })).sort((a, b) => b.raw - a.raw);
      const max = Math.max.apply(null, list.map(x => Math.abs(x.raw)));
      return {
        title: 'سود واحد هر کانال (درآمد − هزینه)',
        cards: [
          { label: 'CAC کل در برابر سقف حاشیه', value: this.money(cac), note: 'سقف حاشیه: ' + this.money(marginCap) + ' تومان · ' + (cac <= marginCap ? 'زیر سقف' : 'بالای سقف'), src: 'campaign_history + merchant_profile.gross_margin' },
          { label: 'POAS کل (سود ناخالص ÷ هزینه)', value: this.signPct(this.poas(tot.rev, tot.spend)), note: 'ROAS درآمدی ' + this.dec(tot.rev / tot.spend, 2) + '× — ولی با حاشیه‌ی ' + this.pct(profile.margin, 0) + ' سود واقعی این است', src: 'campaign_history × merchant_profile.gross_margin' },
          { label: 'کمپین‌های ضررده در سطح واحد', value: this.fa(losing.length), note: 'از ' + this.fa(history.length) + ' کمپین ثبت‌شده', src: 'campaign_history × gross_margin' },
          { label: 'CAC هدف مرچنت', value: this.money(profile.targetCac), note: cac <= profile.targetCac ? 'CAC واقعی زیر هدف است' : 'CAC واقعی ' + this.pct(cac / profile.targetCac - 1, 0) + ' بالای هدف است', src: 'merchant_profile.target_cac' }
        ],
        list: list.map(x => ({ label: x.label, value: this.money(x.raw) + ' ت', pct: max ? Math.round(Math.abs(x.raw) / max * 100) : 0 }))
      };
    }
    if (d === 'ceo') {
      const months = ['مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
      const mv = months.map(m => ({ label: m, raw: history.filter(h => h.month === m).reduce((s, h) => s + h.conversions, 0) }));
      const max = Math.max.apply(null, mv.map(x => x.raw)) || 1;
      const goalV = +this.state.di.goalValue || 0;
      return {
        title: 'روند خرید ماهانه',
        cards: [
          { label: 'حجم کل خروجی', value: this.num(tot.conv), note: this.num(tot.inst) + ' نصب · ' + this.fa(history.length) + ' کمپین', src: 'campaign_history.conversions' },
          { label: 'درآمد کل', value: this.money(tot.rev), note: 'میانگین ' + this.money(tot.rev / history.length) + ' به ازای هر کمپین', src: 'campaign_history.revenue' },
          { label: 'بیشترین سهم کانال', value: chKeys.sort((a, b) => byChannel[b].spend - byChannel[a].spend)[0], note: this.pct(byChannel[chKeys.sort((a, b) => byChannel[b].spend - byChannel[a].spend)[0]].spend / tot.spend, 0) + ' از بودجه', src: 'campaign_history.spend by channel' },
          { label: 'فاصله تا هدف کمپین بعدی', value: goalV ? this.num(goalV) : '—', note: goalV ? 'هدف ثبت‌شده در فرم طراحی' : 'هنوز هدفی ثبت نشده', src: 'designer input.campaign_goal_value' }
        ],
        list: mv.map(x => ({ label: x.label, value: this.num(x.raw) + ' خرید', pct: Math.round(x.raw / max * 100) }))
      };
    }
    if (d === 'analyst') {
      const list = rates.slice().sort((a, b) => b.variance - a.variance).slice(0, 7).map(r => ({ label: r.ch + '/' + r.seg, value: '±' + this.pct(r.variance, 0), pct: Math.round(r.variance / 0.4 * 100) }));
      const thin = rates.filter(r => r.n < 5).length;
      return {
        title: 'پرنوسان‌ترین ردیف‌های نرخ',
        cards: [
          { label: 'میانگین نوسان جدول نرخ', value: '±' + this.pct(rates.reduce((s, r) => s + r.variance, 0) / rates.length, 0), note: 'بازه‌ی Simulator از همین ستون ساخته می‌شود', src: 'rates.variance' },
          { label: 'ردیف‌های کم‌نمونه', value: this.fa(thin), note: 'زیر ۵ نمونه — پیش‌بینی این ردیف‌ها ضعیف است', src: 'rates.sample_n < 5' },
          { label: 'کیفیت داده‌ی تاریخی', value: this.pct(history.filter(h => h.source === 'اسمارتک').length / history.length, 0), note: 'سهم ردیف‌های با منبع اسمارتک (نه ورود دستی)', src: 'campaign_history.source' },
          { label: 'کل نمونه‌های پشت نرخ‌ها', value: this.fa(rates.reduce((s, r) => s + r.n, 0)), note: 'مجموع sample_n همه‌ی ردیف‌ها', src: 'rates.sample_n' }
        ],
        list: list
      };
    }
    const vr = this.state.vr;
    const list = chKeys.map(k => ({ label: k, raw: history.filter(h => h.channel === k).length }));
    const max = Math.max.apply(null, list.map(x => x.raw)) || 1;
    return {
      title: 'تعداد کمپین اجراشده در هر کانال',
      cards: [
        { label: 'کمپین‌های ثبت‌شده', value: this.fa(history.length), note: 'حافظه‌ی عملیاتی سیستم', src: 'campaign_history' },
        { label: 'کارت انحراف باز', value: vr ? (vr.applied ? '۰' : '۱') : '۰', note: vr ? 'علت: ' + vr.cause : 'کارتی در انتظار بررسی نیست', src: 'verifier.cause' },
        { label: 'آشناترین ردیف', value: rates.slice().sort((a, b) => b.n - a.n)[0].ch, note: this.fa(rates.slice().sort((a, b) => b.n - a.n)[0].n) + ' کمپین اجراشده', src: 'rates.sample_n' },
        { label: 'کالیبراسیون اعمال‌شده', value: this.state.calib ? '۱' : '۰', note: this.state.calib ? 'ردیف ' + this.state.calib.row : 'هنوز نرخی به‌روزرسانی نشده', src: 'calibration_id' }
      ],
      list: list.map(x => ({ label: x.label, value: this.fa(x.raw) + ' کمپین', pct: Math.round(x.raw / max * 100) }))
    };
  }

  /* ---------- helpers ---------- */
  setDi(k, v) { this.setState({ di: Object.assign({}, this.state.di, { [k]: v }) }); }
  setVi(k, v) { this.setState({ vi: Object.assign({}, this.state.vi, { [k]: v }) }); }
  toggle(key, val) {
    const arr = this.state.di[key].slice();
    const i = arr.indexOf(val);
    if (i > -1) { if (arr.length > 1) arr.splice(i, 1); } else arr.push(val);
    this.setDi(key, arr);
  }

  renderVals() {
    const S = this.state;
    const PAGES = [
      { k: 'campaigns', label: 'کمپین‌ها', ph: 0 },
      { k: 'data', label: 'داده‌ها', ph: 0 },
      { k: 'setup', label: 'پروفایل و آمادگی', ph: 0 },
      { k: 'design', label: 'طراحی', ph: 1 },
      { k: 'insights', label: 'اینسایت‌ها', ph: 1 },
      { k: 'sim', label: 'شبیه‌سازی', ph: 1 },
      { k: 'pace', label: 'پایش حین اجرا', ph: 1 },
      { k: 'verify', label: 'راستی‌آزمایی', ph: 1 },
      { k: 'loop', label: 'حلقه', ph: 2 },
      { k: 'report', label: 'گزارش کمپین', ph: 2 },
      { k: 'log', label: 'دفترچه‌ی دیدگاه‌ها', ph: 2 },
      { k: 'dash', label: 'داشبورد ذی‌نفع', ph: 2 },
      { k: 'method', label: 'روش‌شناسی', ph: 2 },
      { k: 'ask', label: 'پرسش از داده', ph: 2 },
      { k: 'connect', label: 'اتصال داده', ph: 3 },
      { k: 'team', label: 'تیم و نقش‌ها', ph: 3 },
      { k: 'rules', label: 'قواعد و آستانه‌ها', ph: 3 },
      { k: 'audit', label: 'لاگ تغییرات', ph: 3 },
      { k: 'analytics', label: 'آنالیتیکس محصول', ph: 3 },
      { k: 'security', label: 'امنیت', ph: 3 },
      { k: 'billing', label: 'پلن و صورتحساب', ph: 3 },
      { k: 'help', label: 'راهنما و پشتیبانی', ph: 3 },
      { k: 'settings', label: 'تنظیمات', ph: 3 }
    ];
    const found = PAGES.find(p => p.k === S.page);
    const cur = found || PAGES[1];
    const go = k => () => this.setState({ page: k });
    const phaseDefs = [
      { label: 'آماده‌سازی', sub: 'کمپین‌ها، داده، پروفایل' },
      { label: 'حلقه', sub: 'طرح ← پیش‌بینی ← پایش ← نتیجه' },
      { label: 'خروجی', sub: 'گزارش و یادگیری' },
      { label: 'پلتفرم', sub: 'اتصال، تیم، قواعد، لاگ' }
    ];
    const phases = phaseDefs.map((p, i) => ({
      num: this.fa(i + 1), label: p.label, sub: p.sub, on: cur.ph === i, off: cur.ph !== i,
      go: go(PAGES.filter(x => x.ph === i)[0].k)
    }));
    const steps = PAGES.filter(p => p.ph === cur.ph).map((p, i) => ({
      num: this.fa(i + 1), label: p.label, on: S.page === p.k, off: S.page !== p.k, go: go(p.k)
    }));
    const simNow = this.simulate();
    const loopChips = [
      { label: 'طرح', note: S.plan ? S.plan.perspective : 'انتخاب نشده', done: !!S.plan, pending: !S.plan },
      { label: 'پیش‌بینی', note: simNow ? 'POAS ' + this.signPct(simNow.poas) : 'ساخته نشده', done: !!simNow, pending: !simNow },
      { label: 'پایش', note: S.pace && S.pace.spend > 0 ? 'روز ' + this.fa(S.pace.day) : 'داده‌ای نیست', done: !!(S.pace && S.pace.spend > 0), pending: !(S.pace && S.pace.spend > 0) },
      { label: 'نتیجه‌ی واقعی', note: S.vr ? S.vr.cause : 'ثبت نشده', done: !!S.vr, pending: !S.vr },
      { label: 'کالیبراسیون', note: S.calib ? 'ردیف ' + S.calib.row : (S.vr && !S.vr.calib ? 'در این شاخه اعمال نمی‌شود' : 'اعمال نشده'), done: !!S.calib, pending: !S.calib }
    ];
    const rd = this.readiness();
    const nextAction = !rd.ready ? { label: 'تکمیل آمادگی داده', go: go('setup') }
      : S.insights.length === 0 ? { label: 'تولید اینسایت‌ها', go: go('design') }
        : !S.plan ? { label: 'انتخاب یک دیدگاه', go: go('insights') }
          : !(S.pace && S.pace.spend > 0) ? { label: 'پایش حین اجرا', go: go('pace') }
          : !S.vr ? { label: 'ثبت نتیجه‌ی واقعی', go: go('verify') }
            : !S.calib && S.vr.calib ? { label: 'اعمال کالیبراسیون', go: go('verify') }
              : { label: 'دیدن گزارش کمپین', go: go('report') };

    const rateRows = S.rates.map((r, i) => ({
      ch: r.ch, seg: r.seg, cpi: String(r.cpi), cvr: String(r.cvr), kind: r.type === 'owned' ? 'خودی · هزینه به ازای ارسال' : 'جذب پولی', isOwned: r.type === 'owned', isPaid: r.type !== 'owned',
      cpiAdj: this.num(this.cpiAdj(r)), age: this.fa(r.age || 0) + ' ماه', d30: this.pct(r.d30 || 0, 0), fraud: r.type === 'owned' ? '—' : this.pct(r.fraud || 0, 0), canEdit: this.can('editData'), readOnly: !this.can('editData'),
      aov: this.money(r.aov), cac: this.money(this.cac(r)), variance: '±' + this.pct(r.variance, 0),
      n: this.fa(r.n), ceiling: this.num(r.ceiling),
      onCpi: e => { if (!this.can('editData')) return; const v = +e.target.value; const rs = S.rates.slice(); rs[i] = Object.assign({}, r, { cpi: v || r.cpi }); this.setState({ rates: rs }); },
      onCvr: e => { if (!this.can('editData')) return; const v = +e.target.value; const rs = S.rates.slice(); rs[i] = Object.assign({}, r, { cvr: v || r.cvr }); this.setState({ rates: rs }); }
    }));

    const pf = (label, key, kind) => ({
      label: label, value: String(S.profile[key]), isNum: kind === 'num', isText: kind === 'text', isGoal: kind === 'goal',
      onChange: e => {
        const raw = e.target.value;
        const v = kind === 'num' ? (+raw || 0) : raw;
        this.setState({ profile: Object.assign({}, S.profile, { [key]: v }) });
      }
    });
    const profileRows = [
      pf('بودجه‌ی ماهانه‌ی تبلیغات (تومان)', 'budget', 'num'),
      pf('حاشیه‌ی سود ناخالص (کسری)', 'margin', 'num'),
      pf('CAC هدف (تومان)', 'targetCac', 'num'),
      pf('میانگین LTV (تومان)', 'ltv', 'num'),
      pf('هدف کسب‌وکار', 'goal', 'goal'),
      pf('یادداشت فصلی', 'season', 'text')
    ];

    const historyRows = S.history.map(h => ({
      name: h.name, channel: h.channel, segment: h.segment, spend: this.money(h.spend),
      installs: this.num(h.installs), conversions: this.num(h.conversions), revenue: this.money(h.revenue),
      cac: this.money(h.spend / h.conversions), source: h.source
    }));

    const chip = (key, v) => ({ label: v, on: S.di[key].indexOf(v) > -1, off: S.di[key].indexOf(v) < 0, toggle: () => this.toggle(key, v) });
    const riskChips = ['محافظه‌کار', 'متعادل', 'تهاجمی'].map(v => ({ label: v, on: S.di.risk === v, off: S.di.risk !== v, toggle: () => this.setDi('risk', v) }));

    const insightRows = S.insights.map((i, k) => ({
      num: this.fa(k + 1), perspective: i.perspective, objective: i.objective, claim: i.claim,
      proposal: i.proposal, evidence: i.evidence, risk: i.risk, successMetric: i.successMetric,
      cac: this.money(i.exp.cac) + ' ت', conv: this.num(i.exp.conv),
      alloc: i.alloc.map(a => ({ label: a.ch + ' · ' + a.seg, share: this.pct(a.share, 0) })),
      isSelected: S.sel === i.id, notSelected: S.sel !== i.id, isSecondary: S.sec === i.id,
      select: () => this.setState({ sel: i.id, sec: S.sec === i.id ? null : S.sec }),
      combine: () => { if (S.sel && S.sel !== i.id) this.setState({ sec: i.id }); else this.setState({ sel: i.id }); }
    }));

    const selIns = S.insights.find(i => i.id === S.sel);
    const secIns = S.insights.find(i => i.id === S.sec);
    const sim = this.simulate();

    const band = (low, high, exp) => {
      const top = high || 1;
      return { lowPct: Math.round(low / top * 100), bandPct: Math.max(Math.round((high - low) / top * 100), 2), expPct: Math.round(exp / top * 100) };
    };
    const funnel = sim ? [
      Object.assign({ label: 'نصب', expected: this.num(sim.installs), low: this.num(sim.iLow), high: this.num(sim.iHigh) }, band(sim.iLow, sim.iHigh, sim.installs)),
      Object.assign({ label: 'خرید', expected: this.num(sim.conv), low: this.num(sim.cLow), high: this.num(sim.cHigh) }, band(sim.cLow, sim.cHigh, sim.conv)),
      Object.assign({ label: 'درآمد', expected: this.money(sim.rev), low: this.money(sim.rLow), high: this.money(sim.rHigh) }, band(sim.rLow, sim.rHigh, sim.rev))
    ] : [];
    const simKpis = sim ? [
      { label: 'CAC پیش‌بینی‌شده', value: this.money(sim.cac) + ' ت' },
      { label: 'POAS (سود ÷ هزینه)', value: this.signPct(sim.poas) },
      { label: 'ROAS درآمدی', value: this.fa(sim.roas.toFixed(2)) + '×' },
      { label: 'دوره‌ی بازگشت CAC', value: this.dec(sim.payback, 1) + ' ماه' },
      { label: 'LTV ÷ CAC', value: sim.ltvCac ? this.dec(sim.ltvCac, 1) + '×' : '—' },
      { label: 'کاربر ماندگار روز ۳۰', value: this.num(sim.ret30) },
      { label: 'نصب تقلبی حذف‌شده', value: this.num(sim.fraudInst) },
      { label: 'پوشش هدف', value: sim.coverage ? this.pct(sim.coverage, 0) : '—' },
      { label: 'بودجه‌ی تخصیص‌یافته', value: this.money(sim.budget) + ' ت' }
    ] : [];
    const verdicts = sim ? [sim.risk, sim.prof].map(v => ({ kind: v.kind, text: v.text, ok: !!v.ok, warn: !!v.warn, bad: !!v.bad })) : [];

    const vi = S.vi;
    const viField = (label, key, opts) => ({
      label: label, value: String(vi[key]), isSelect: !!opts, isInput: !opts,
      options: opts ? opts.map(o => ({ v: o })) : [],
      onChange: e => this.setVi(key, e.target.value)
    });
    const viFields = [
      viField('نام کمپین', 'name'),
      viField('کانال', 'ch', this.channels()),
      viField('سگمنت', 'seg', this.segments()),
      viField('بودجه‌ی طرح', 'pb'),
      viField('نصب مورد انتظار', 'pi'),
      viField('خرید مورد انتظار', 'pc'),
      viField('منبع پیش‌بینی', 'src', ['مکتوب و عددی', 'عددی ولی نامکتوب', 'تجربی و ذهنی']),
      viField('هزینه‌ی واقعی', 'ab'),
      viField('نصب واقعی', 'ai'),
      viField('خرید واقعی', 'ac'),
      viField('نرخ نصب مشکوک به تقلب (٪)', 'fraud'),
      viField('پنجره‌ی انتساب (روز)', 'window', ['1', '7', '14', '30']),
      viField('کمپین در فصل اوج بود؟', 'season', ['خیر', 'بله']),
      viField('کاربران در معرض کمپین (برای گروه کنترل)', 'reach'),
      viField('نرخ خرید گروه کنترل (٪)', 'holdout'),
      viField('کامل بودن داده', 'complete', ['بله', 'خیر']),
      viField('انطباق ترکر', 'matched', ['بله', 'خیر'])
    ];

    const vr = S.vr;
    const devRow = (metric, p, a, d, money) => ({
      metric: metric, planned: money ? this.money(p) : this.num(p), actual: money ? this.money(a) : this.num(a),
      dev: this.signPct(d), ok: Math.abs(d) <= 0.15, bad: Math.abs(d) > 0.15
    });
    const vrView = vr ? {
      head: vi.name + ' · ' + vi.ch + ' · ' + vi.seg + ' · ' + vi.from + ' تا ' + vi.to,
      ids: (S.plan ? 'plan_id: ' + S.plan.planId + '  ·  ' : '') + (vi.fromSim && sim ? 'sim_id: ' + sim.simId + '  ·  ' : 'sim_id: —  ·  ') + 'run_id: run-۱۴۰۵-' + this.fa(S.plan ? S.plan.seq : S.seqNo),
      rows: [devRow('بودجه', +vi.pb, +vi.ab, vr.dB, true), devRow('نصب', +vi.pi, +vi.ai, vr.dI, false), devRow('خرید', +vi.pc, +vi.ac, vr.dC, false)],
      cause: vr.cause, explanation: vr.expl,
      perspective: S.plan ? S.plan.perspective : 'ثبت نشده (مسیر دستی)',
      source: vi.fromSim ? 'ایستگاه ۳ — sim_id' : 'ورود دستی مرچنت · ' + vi.src,
      lift: vr.liftText,
      quality: 'تقلب ' + this.pct(vr.fraud || 0, 0) + ' · پنجره ' + this.fa(vi.window) + ' روز · ' + (vi.complete === 'بله' ? 'کامل' : 'ناقص') + ' · شناسه‌ی ترکر: ' + (vi.matched === 'بله' ? 'منطبق' : 'نامنطبق'),
      calibratable: vr.calib && !vr.applied && this.can('calibrate'), noPerm: vr.calib && !vr.applied && !this.can('calibrate'), blocked: !vr.calib, applied: !!vr.applied,
      blockReason: vr.block,
      calibPreview: 'CPI مشاهده‌شده ' + this.money(vr.obsCpi) + ' و CVR مشاهده‌شده ' + this.pct(vr.obsCvr) + ' با وزن sample_n در نرخ ردیف ' + vi.ch + '|' + vi.seg + ' میانگین می‌شود.',
      appliedText: S.calib ? (S.calib.weights || '') + ' — ردیف ' + S.calib.row + ': CPI از ' + this.money(S.calib.before.cpi) + ' به ' + this.money(S.calib.after.cpi) + ' · CVR از ' + this.pct(S.calib.before.cvr) + ' به ' + this.pct(S.calib.after.cvr) + ' · sample_n = ' + this.fa(S.calib.after.n) : ''
    } : {};

    const C = S.cfg;
    const treeTexts = [
      'داده ناقص، ترکر نامنطبق، پنجره‌ی انتساب ناهمسان یا تقلب > ' + this.pct(C.fraudTh, 0) + ' → «ناسازگاری داده» · کالیبراسیون اعمال نمی‌شود',
      '|انحراف بودجه| > ' + this.pct(C.execTh, 0) + ' → «انحراف اجرا» · کالیبراسیون اعمال نمی‌شود',
      'نسبت انحراف نصب÷بودجه بین ' + this.dec(C.scaleLo, 1) + ' و ' + this.dec(C.scaleHi, 1) + ' و CVR پایدار (±۱۵٪) → «مقیاس، نه کیفیت» · اعمال نمی‌شود',
      '|انحراف نصب| یا |انحراف CVR| > ' + this.pct(C.estTh, 0) + ' → «خطای برآورد» · ✅ کالیبراسیون اعمال می‌شود',
      'در غیر این صورت → «در دامنه‌ی انتظار» · ✅ کالیبراسیون اعمال می‌شود'
    ];
    const causeOrder = ['ناسازگاری داده', 'انحراف اجرا', 'مقیاس، نه کیفیت', 'خطای برآورد', 'در دامنه‌ی انتظار'];
    const treeRows = treeTexts.map((t, i) => ({
      num: this.fa(i + 1), text: t,
      active: !!vr && causeOrder[i] === vr.cause, idle: !vr || causeOrder[i] !== vr.cause
    }));

    const chain = [
      { station: 'ایستگاه ۲ — طرح', id: S.plan ? S.plan.planId : '', note: S.plan ? 'دیدگاه: ' + S.plan.perspective : 'اینسایتی انتخاب نشده', done: !!S.plan, pending: !S.plan },
      { station: 'ایستگاه ۳ — پیش‌بینی', id: sim ? sim.simId : '', note: sim ? 'CAC ' + this.money(sim.cac) + ' · بازه از نوسان تاریخی' : 'طرحی برای پیش‌بینی نیست', done: !!sim, pending: !sim },
      { station: 'اجرای واقعی', id: S.calib ? S.calib.runId : '', note: vr ? 'علت: ' + vr.cause : 'نتیجه‌ای ثبت نشده', done: !!S.calib, pending: !S.calib },
      { station: 'ایستگاه ۱ — کالیبراسیون', id: S.calib ? S.calib.id : '', note: S.calib ? 'ردیف ' + S.calib.row + ' به‌روزرسانی شد' : 'کالیبراسیونی اعمال نشده', done: !!S.calib, pending: !S.calib }
    ];
    const calibRows = S.calib ? [
      { row: S.calib.row, metric: 'cpi', before: this.num(S.calib.before.cpi), after: this.num(S.calib.after.cpi), delta: this.signPct(S.calib.after.cpi / S.calib.before.cpi - 1) },
      { row: S.calib.row, metric: 'cvr', before: this.dec(S.calib.before.cvr * 100, 3) + '٪', after: this.dec(S.calib.after.cvr * 100, 3) + '٪', delta: this.signPct(S.calib.after.cvr / S.calib.before.cvr - 1) },
      { row: S.calib.row, metric: 'sample_n', before: this.fa(S.calib.before.n), after: this.fa(S.calib.after.n), delta: '+۱' }
    ] : [];

    const dash = this.dashData();
    const dashTabs = [['cfo', 'مدیر مالی'], ['ceo', 'مدیرعامل'], ['analyst', 'تحلیل‌گر داده'], ['ops', 'مسئول کمپین']].map(t => ({
      label: t[1], on: S.dash === t[0], off: S.dash !== t[0], pick: () => this.setState({ dash: t[0] })
    }));

    const report = this.reportData();
    const archive = S.archive || [];
    const logRows = archive.slice().reverse().map(r => ({
      id: r.id, name: r.name, perspective: r.perspective, reason: r.reason, cause: r.cause,
      calib: r.calib ? 'اعمال شد' : 'اعمال نشد', calibOn: !!r.calib, calibOff: !r.calib,
      plannedCac: this.money(r.plannedCac), actualCac: this.money(r.actualCac),
      gap: this.signPct(r.actualCac / r.plannedCac - 1),
      source: r.seeded ? 'داده‌ی دمو' : 'این نشست'
    }));
    const groups = {};
    archive.forEach(r => {
      if (!groups[r.perspective]) groups[r.perspective] = { n: 0, err: 0, calib: 0, good: 0 };
      const g = groups[r.perspective];
      const poas = r.actualPoas !== undefined ? r.actualPoas : (r.plannedCac / r.actualCac - 1);
      const good = poas >= 0 && r.cause !== 'انحراف اجرا' && r.cause !== 'ناسازگاری داده';
      g.n += 1; g.err += Math.abs(r.actualCac / r.plannedCac - 1); g.calib += r.calib ? 1 : 0; g.good += good ? 1 : 0;
    });
    // decision quality: share of campaigns where the choice was profitable and outcome was attributable
    const gk = Object.keys(groups).sort((a, b) => groups[b].good / groups[b].n - groups[a].good / groups[a].n || groups[b].n - groups[a].n);
    const perspRows = gk.map(k => ({
      label: k, value: 'تصمیم درست ' + this.fa(groups[k].good) + ' از ' + this.fa(groups[k].n) + ' · خطای پیش‌بینی ' + this.signPct(groups[k].err / groups[k].n) + (groups[k].n < 3 ? ' · نمونه کم' : ''),
      pct: Math.max(Math.round(groups[k].good / groups[k].n * 100), 6)
    }));
    const bestPersp = gk.length ? gk[0] : '—';
    const closeCampaign = () => {
      if (!S.plan || !S.vr) return;
      const sm = this.simulate();
      const rec = {
        id: 'ک-' + this.fa(String(121 + archive.filter(x => !x.seeded).length)),
        name: S.vi.name, perspective: S.plan.perspective, reason: S.plan.reason || '—',
        cause: S.vr.cause, calib: !!S.calib,
        plannedCac: sm ? sm.cac : 0, actualCac: +S.vi.ac > 0 ? +S.vi.ab / +S.vi.ac : 0, seeded: false,
        goalHit: (+S.vi.ac || 0) >= (+S.plan.goalValue || 0) * (S.plan.goalType === 'خرید' ? 1 : 0),
        actualPoas: +S.vi.ab > 0 ? ((+S.vi.ac * (S.plan.alloc[0] || {}).aov || 0) * S.profile.margin - +S.vi.ab) / +S.vi.ab : 0,
        lift: S.vr.lift
      };
      this.log('بستن کمپین ' + rec.id, rec.cause); this.track('campaign_closed'); this.notify('کمپین ' + rec.id + ' بسته و در دفترچه ثبت شد.', 'ok');
      const first = !archive.some(x => !x.seeded);
      this.setState({ celebrate: first, archive: archive.concat([rec]), campaigns: (S.campaigns || []).map(c => c.seq === S.plan.seq ? Object.assign({}, c, { status: 'بسته‌شده' }) : c), page: 'log' });
    };
    const pc = S.pace || { day: 0, spend: 0, installs: 0, conv: 0 };
    const days = 30, frac = Math.min(Math.max(+pc.day || 0, 1), days) / days;
    const paceRows = sim ? [
      { k: 'هزینه', exp: sim.budget * frac, act: +pc.spend, money: true },
      { k: 'نصب', exp: sim.installs * frac, act: +pc.installs },
      { k: 'خرید', exp: sim.conv * frac, act: +pc.conv }
    ].map(x => { const d = x.exp > 0 ? x.act / x.exp - 1 : 0; return { k: x.k, exp: x.money ? this.money(x.exp) : this.num(x.exp), act: x.money ? this.money(x.act) : this.num(x.act), dev: this.signPct(d), ok: Math.abs(d) <= 0.15, warn: Math.abs(d) > 0.15 && Math.abs(d) <= 0.3, bad: Math.abs(d) > 0.3, raw: d }; }) : [];
    const paceAlerts = [];
    if (sim && +pc.spend > 0) {
      const sp = paceRows[0].raw, cv = paceRows[2].raw;
      if (sp > 0.2) paceAlerts.push({ text: 'بودجه سریع‌تر از برنامه خرج می‌شود (' + this.signPct(sp) + '). با این سرعت بودجه روز ' + this.fa(Math.round(30 / (1 + sp))) + ' تمام می‌شود.', bad: true, warn: false });
      if (sp < -0.2) paceAlerts.push({ text: 'بودجه کندتر از برنامه خرج می‌شود (' + this.signPct(sp) + '). احتمالاً پیشنهاد قیمت یا سقف روزانه پایین است.', bad: false, warn: true });
      if (cv < -0.25) paceAlerts.push({ text: 'خرید ' + this.signPct(cv) + ' عقب است. اگر تا روز ۱۵ جبران نشود، هدف از دست می‌رود — جابه‌جایی بودجه به ردیف دوم تخصیص را بررسی کنید.', bad: true, warn: false });
      if (!paceAlerts.length) paceAlerts.push({ text: 'کمپین در مسیر پیش‌بینی است.', bad: false, warn: false, ok: true });
    }
    const setPace = k => e => this.setState({ pace: Object.assign({}, pc, { [k]: +e.target.value || 0 }) });
    const faToEn = s => String(s).replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
    const dOk = s => /^\d{4}\/\d{2}\/\d{2}$/.test(faToEn(s));
    const dateErr = !dOk(S.di.from) || !dOk(S.di.to) ? 'تاریخ را به شکل ۱۴۰۵/۰۷/۰۱ وارد کنید.' : faToEn(S.di.from) >= faToEn(S.di.to) ? 'تاریخ پایان باید بعد از تاریخ شروع باشد.' : '';
    const budgetErr = +S.di.budget <= 0 ? 'بودجه باید بیشتر از صفر باشد.' : +S.di.budget > S.profile.budget * 3 ? 'بودجه بیش از سه برابر بودجه‌ی ماهانه‌ی پروفایل است.' : '';
    const csv = rows => rows.map(r => r.map(x => '"' + String(x).replace(/"/g, '""') + '"').join(',')).join('\n');
    const download = (name, text) => { try { const b = new Blob(['\ufeff' + text], { type: 'text/csv;charset=utf-8' }); const u = URL.createObjectURL(b); const a = document.createElement('a'); a.href = u; a.download = name; a.click(); setTimeout(() => URL.revokeObjectURL(u), 1000); } catch (e) { } };
    const tourSteps = [
      { title: 'از داده شروع کنید', text: 'سه جدول حافظه‌ی سیستم‌اند. نرخ‌ها با تورم ماهانه تعدیل می‌شوند و پوش و پیامک جدا از جذب پولی محاسبه می‌شوند.', page: 'data' },
      { title: 'آمادگی را بسنجید', text: 'اگر داده کم است، با نرخ‌های مرجع صنعت شروع کنید؛ سیستم برچسب «فرض» روی اینسایت‌ها می‌زند.', page: 'setup' },
      { title: 'ده دیدگاه', text: 'یک دیدگاه انتخاب و دلیلش را بنویسید. این دلیل بعداً در دفترچه سنجیده می‌شود.', page: 'insights' },
      { title: 'پیش‌بینی با POAS', text: 'بازده نزولی، تقلب و ماندگاری روز ۳۰ در پیش‌بینی لحاظ شده‌اند. بازه تجربی است، نه فاصله‌ی اطمینان.', page: 'sim' },
      { title: 'پایش حین اجرا', text: 'عدد روز جاری را وارد کنید تا ببینید کمپین از مسیر خارج شده یا نه — قبل از اینکه دیر شود.', page: 'pace' },
      { title: 'راستی‌آزمایی', text: 'پنج شاخه، آستانه‌های قابل تنظیم، گروه کنترل برای اثر افزایشی. کالیبراسیون فقط در شاخه‌های معتبر.', page: 'verify' },
      { title: 'گزارش و یادگیری', text: 'گزارش را چاپ یا CSV کنید، کمپین را ببندید و در دفترچه ببینید کدام دیدگاه تصمیم درست‌تری داده.', page: 'report' }
    ];
    const tourIdx = S.tour || 0;
    const cfgRows = [
      ['آستانه‌ی انحراف اجرا (بودجه)', 'execTh', 'کسری · پیش‌فرض ۰٫۱۵'],
      ['آستانه‌ی خطای برآورد', 'estTh', 'کسری · پیش‌فرض ۰٫۲۵'],
      ['کران پایین نسبت مقیاس', 'scaleLo', 'پیش‌فرض ۰٫۸'],
      ['کران بالای نسبت مقیاس', 'scaleHi', 'پیش‌فرض ۱٫۲'],
      ['تورم ماهانه‌ی هزینه‌ی رسانه', 'inflation', 'کسری · پیش‌فرض ۰٫۰۳۵'],
      ['پنجره‌ی انتساب طرح (روز)', 'attrWindow', 'پیش‌فرض ۷'],
      ['آستانه‌ی تقلب', 'fraudTh', 'کسری · پیش‌فرض ۰٫۰۸']
    ].map(x => ({ label: x[0], hint: x[2], value: String(S.cfg[x[1]]), onChange: e => { if (!this.can('editData')) return; this.setState({ cfg: Object.assign({}, S.cfg, { [x[1]]: +e.target.value || 0 }) }); }, onBlur: () => this.log('تغییر قاعده: ' + x[0], String(S.cfg[x[1]])) }));
    const benchmarks = () => {
      const base = this.seedRates().map(r => Object.assign({}, r, { n: 3, age: 0, upd: 'مرجع صنعت' }));
      this.log('بارگذاری نرخ‌های مرجع صنعت', this.fa(base.length) + ' ردیف');
      this.setState({ rates: base, history: this.seedHistory().slice(0, 15), benchmark: true });
    };
    const af = S.authForm;
    const setAf = (k, v) => this.setState({ authForm: Object.assign({}, af, { [k]: v }), authError: '' });
    const field = (label, key, type, ph) => ({ label: label, value: af[key], type: type || 'text', ph: ph || '', onChange: e => setAf(key, e.target.value) });
    const isSignup = S.authMode === 'signup';
    const authFields = isSignup
      ? [field('نام و نام خانوادگی', 'name', 'text', 'مثلاً محسن علی‌پور'), field('نام کسب‌وکار', 'company', 'text', 'مثلاً دیجی‌استایل'), field('ایمیل کاری', 'email', 'email', 'name@company.ir'), field('رمز عبور', 'pass', 'password', 'حداقل ۶ کاراکتر')]
      : [field('ایمیل کاری', 'email', 'email', 'name@company.ir'), field('رمز عبور', 'pass', 'password', '')];
    const enter = (name, company, register) => {
      const acct = { name: name, company: company, email: af.email || 'demo@smartech.ir' };
      this.setState({
        auth: acct, verified: register ? false : true, signupPending: !!register, registered: register ? acct : S.registered, authError: '', page: 'data', welcome: !!register || company === 'مرچنت نمونه',
        authForm: { name: '', company: '', email: '', pass: '' }
      });
    };
    const submitAuth = () => {
      const ok = /.+@.+\..+/.test(af.email);
      if (!ok) return this.setState({ authError: 'ایمیل کاری معتبر وارد کنید.' });
      if ((af.pass || '').length < 6) return this.setState({ authError: 'رمز عبور باید حداقل ۶ کاراکتر باشد.' });
      if (isSignup && (af.name || '').trim().length < 3) return this.setState({ authError: 'نام را کامل وارد کنید.' });
      if (isSignup && (af.company || '').trim().length < 2) return this.setState({ authError: 'نام کسب‌وکار را وارد کنید.' });
      if (isSignup) return enter(af.name.trim(), af.company.trim(), true);
      const reg = S.registered;
      if (reg && reg.email === af.email) return enter(reg.name, reg.company);
      enter('کاربر اسمارتک', 'مرچنت ثبت‌نشده');
    };

    return {
      showAuth: !S.auth, showApp: !!S.auth,
      account: S.auth ? { name: S.auth.name, company: S.auth.company, initial: (S.auth.name || '؟').trim().charAt(0) } : { name: '', company: '', initial: '' },
      signOut: () => this.setState({ auth: null, authMode: 'login' }),
      authTabs: [['signup', 'ثبت‌نام'], ['login', 'ورود']].map(t => ({ label: t[1], on: S.authMode === t[0], off: S.authMode !== t[0], pick: () => this.setState({ authMode: t[0], authError: '' }) })),
      authFields: authFields,
      authCta: isSignup ? 'ساختن حساب و شروع' : 'ورود به حساب',
      authError: !!S.authError, authErrorText: S.authError,
      submitAuth: submitAuth,
      demoEnter: () => enter('کاربر دمو', 'مرچنت نمونه'),
      landingSteps: [
        { num: '۱', title: 'مشاور چنددیدگاهی', text: 'ده تابع هدف روی یک داده، ده اینسایت با شاهد عددی. انسان انتخاب می‌کند و دلیلش ثبت می‌شود.', id: 'plan_id' },
        { num: '۲', title: 'پیش‌بینی با بازه', text: 'قیف نصب، خرید و درآمد با کران بالا و پایین از نوسان تاریخی همان کانال — به‌همراه حکم ریسک و سودآوری.', id: 'sim_id' },
        { num: '۳', title: 'راستی‌آزمایی نتیجه', text: 'انحراف به یکی از پنج علت نسبت داده می‌شود؛ کالیبراسیون فقط در شاخه‌های معتبر اعمال می‌شود.', id: 'run_id' },
        { num: '۴', title: 'اصلاح حافظه‌ی سیستم', text: 'نرخ کانال با وزن نمونه به‌روز می‌شود و گزارش نهایی و دفترچه‌ی دیدگاه‌ها ساخته می‌شود.', id: 'calibration_id' }
      ],
      authPoints: [
        { text: 'ده اینسایت از ده دیدگاه — و هر کدام با فیلد «شاهد» که به یک ردیف نرخ برمی‌گردد.' },
        { text: 'پیش‌بینی با بازه‌ی عدم‌قطعیت، ساخته‌شده از نوسان تاریخی همان کانال.' },
        { text: 'انتساب علت انحراف و کالیبراسیون مشروط نرخ‌ها — فقط در شاخه‌های معتبر.' }
      ],
      steps: steps, phases: phases, loopChips: loopChips, nextAction: nextAction,
      phaseLabel: (phaseDefs[cur.ph] || {}).label + ' · ' + (phaseDefs[cur.ph] || {}).sub,
      pData: S.page === 'data', pSetup: S.page === 'setup', pDesign: S.page === 'design',
      pInsights: S.page === 'insights', pSim: S.page === 'sim', pVerify: S.page === 'verify',
      pLoop: S.page === 'loop', pReport: S.page === 'report', pLog: S.page === 'log', pDash: S.page === 'dash',
      pConnect: S.page === 'connect', pTeam: S.page === 'team', pSettings: S.page === 'settings',
      integrationRows: [
        { key: 'adtress', name: 'ادتریس', note: 'کمپین‌های تبلیغاتی و هزینه‌ی هر کانال — ورودی جدول نرخ‌ها', slot: 'int-adtress' },
        { key: 'intrack', name: 'اینترک', note: 'رویدادهای کاربر، سگمنت‌ها و نصب/خرید — ورودی تاریخچه‌ی کمپین', slot: 'int-intrack' },
        { key: 'adverge', name: 'ادورج', note: 'هدف‌گیری و نمایش تبلیغات — در نسخه‌ی بعد', slot: 'int-adverge' },
        { key: 'affilio', name: 'افیلیو', note: 'شبکه‌ی همکاری در فروش — در نسخه‌ی بعد', slot: 'int-affilio' }
      ].map(it => {
        const st = (S.integrations || {})[it.key] || 'available';
        return {
          name: it.name, note: it.note, slot: it.slot,
          connected: st === 'connected', available: st === 'available', soon: st === 'soon',
          statusLabel: st === 'connected' ? 'متصل' : st === 'soon' ? 'به‌زودی' : 'متصل نیست',
          toggle: () => {
            if (st === 'soon') return;
            this.setState({ integrations: Object.assign({}, S.integrations, { [it.key]: st === 'connected' ? 'available' : 'connected' }) });
          },
          ctaLabel: st === 'connected' ? 'قطع اتصال' : 'اتصال'
        };
      }),
      apiKey: S.apiKey,
      onApiKey: e => this.setState({ apiKey: e.target.value }),
      genApiKey: () => this.setState({ apiKey: 'sk_loop_' + Math.random().toString(36).slice(2, 10) + Math.random().toString(36).slice(2, 6) }),
      teamRows: (S.team || []).map((m, i) => ({
        name: m.name, email: m.email, role: m.role, initial: (m.name || '?').charAt(0),
        isOwner: m.role === 'مالک', notOwner: m.role !== 'مالک',
        remove: () => { const t = S.team.slice(); t.splice(i, 1); this.setState({ team: t }); }
      })),
      invite: {
        email: S.invite.email, role: S.invite.role,
        onEmail: e => this.setState({ invite: Object.assign({}, S.invite, { email: e.target.value }) }),
        onRole: e => this.setState({ invite: Object.assign({}, S.invite, { role: e.target.value }) }),
        send: () => {
          if (S.verified === false) return this.notify('برای دعوت هم‌تیمی ابتدا ایمیل خود را تأیید کنید.', 'bad');
          if (!/.+@.+\..+/.test(S.invite.email)) return;
          this.setState({
            team: S.team.concat([{ name: S.invite.email.split('@')[0], email: S.invite.email, role: S.invite.role, pending: true }]),
            invite: { email: '', role: 'تحلیل‌گر' }
          });
        }
      },
      wsRows: [
        { label: 'نام فضای کاری', value: S.workspace.name, onChange: e => this.setState({ workspace: Object.assign({}, S.workspace, { name: e.target.value }) }) },
        { label: 'واحد پول', value: S.workspace.currency, onChange: e => this.setState({ workspace: Object.assign({}, S.workspace, { currency: e.target.value }) }) },
        { label: 'منطقه‌ی زمانی', value: S.workspace.tz, onChange: e => this.setState({ workspace: Object.assign({}, S.workspace, { tz: e.target.value }) }) }
      ],
      accountRows: [
        { label: 'نام کاربر', value: S.auth ? S.auth.name : '' },
        { label: 'ایمیل', value: S.auth ? S.auth.email : '' },
        { label: 'کسب‌وکار', value: S.auth ? S.auth.company : '' },
        { label: 'نقش', value: 'مالک' }
      ],
      resetAll: () => this.setState({
        rates: this.seedRates(), profile: this.seedProfile(), history: this.seedHistory(), archive: this.seedArchive(),
        insights: [], sel: null, sec: null, plan: null, vr: null, calib: null, reason: '', page: 'data'
      }),
      readyChecks: rd.checks.map(c => ({ label: c.label, need: c.need, have: c.have, ok: c.ok, no: !c.ok })),
      readyScore: this.fa(rd.pass) + ' از ' + this.fa(rd.total),
      isReady: rd.ready, notReady: !rd.ready,
      report: report || {}, hasReport: !!report, noReport: !report,
      reportAlloc: report ? report.alloc : [], reportForecast: report ? report.forecast : [],
      reportResult: report ? report.result : [], reportChain: report ? report.chain : [],
      reportVerdicts: report ? report.verdicts.map(t => ({ text: t })) : [],
      printReport: () => { try { window.print(); } catch (e) { } },
      closeCampaign: closeCampaign,
      logRows: logRows, logCount: this.fa(archive.length),
      perspRows: perspRows, bestPersp: bestPersp,
      startNext: () => this.setState({ plan: null, sim: null, vr: null, calib: null, sel: null, sec: null, reason: '', insights: [], page: 'design' }),
      next: () => this.setState({ page: 'setup' }),
      goSetup: () => this.setState({ page: 'setup' }),
      goDesign: () => this.setState({ page: 'design' }),
      goInsights: () => this.setState({ page: 'insights' }),
      goLoop: () => this.setState({ page: 'loop' }),
      goReport: () => this.setState({ page: 'report' }),
      reseed: () => this.setState({ rates: this.seedRates(), profile: this.seedProfile(), history: this.seedHistory(), insights: [], sel: null, sec: null, plan: null, vr: null, calib: null }),

      rateRows: rateRows, profileRows: profileRows, historyRows: historyRows, historyCount: this.fa(S.history.length),

      di: {
        goalType: S.di.goalType, goalValue: String(S.di.goalValue), budget: String(S.di.budget),
        from: S.di.from, to: S.di.to, budgetHuman: this.money(S.di.budget) + ' تومان',
        onGoalType: e => this.setDi('goalType', e.target.value),
        onGoalValue: e => this.setDi('goalValue', +e.target.value || 0),
        onBudget: e => this.setDi('budget', +e.target.value || 0),
        onFrom: e => this.setDi('from', e.target.value),
        onTo: e => this.setDi('to', e.target.value)
      },
      chanChips: this.channels().map(v => chip('channels', v)),
      segChips: this.segments().map(v => chip('segments', v)),
      riskChips: riskChips,
      runDesigner: () => { if (dateErr || budgetErr || !this.can('plan')) return; this.setState({ insights: this.buildInsights(), sel: null, sec: null, page: 'insights' }); },

      goalLabel: S.profile.goal, riskLabel: S.di.risk,
      insightRows: insightRows, noInsights: S.insights.length === 0,
      hasSelection: !!selIns, selLabel: selIns ? selIns.perspective : '',
      hasSecondary: !!secIns, mix: S.mix, mixPct: this.fa(S.mix) + '٪',
      mixLabel: secIns ? ' + ' + secIns.perspective + ' (ترکیب ' + this.fa(S.mix) + '٪ / ' + this.fa(100 - S.mix) + '٪)' : '',
      onMix: e => this.setState({ mix: +e.target.value }),
      clearSecondary: () => this.setState({ sec: null }),
      reason: S.reason, onReason: e => this.setState({ reason: e.target.value }),
      savePlan: () => {
        const alloc = this.mergedAlloc();
        if (!alloc || !this.can('plan')) return;
        const editing = !!(S.plan && !S.vr);
        const seq = editing ? S.plan.seq : (S.seqNo || 101);
        const version = editing ? (S.plan.version || 1) + 1 : 1;
        const versions = editing ? [{ v: S.plan.version || 1, perspective: S.plan.perspective, reason: S.plan.reason }].concat(S.plan.versions || []) : [];
        this.log((editing ? 'ویرایش طرح v' + this.fa(version) + ' ' : 'ثبت طرح ') + 'plan-۱۴۰۵-' + this.fa(seq), selIns.perspective);
        this.track(editing ? 'plan_edited' : 'plan_created');
        this.setState({
          seqNo: editing ? S.seqNo : seq + 1, pace: { day: 10, spend: 0, installs: 0, conv: 0 }, vr: null, calib: null,
          campaigns: editing ? (S.campaigns || []).map(c => c.seq === seq ? Object.assign({}, c, { perspective: selIns.perspective, version: version }) : c) : [{ seq: seq, name: 'کمپین ' + S.di.from, perspective: selIns.perspective, status: 'در حال اجرا', budget: S.di.budget }].concat((S.campaigns || []).map(c => c.status === 'در حال اجرا' ? Object.assign({}, c, { status: 'متوقف' }) : c)),
          whatIf: { budget: 0, shift: 0 },
          plan: {
            planId: 'plan-۱۴۰۵-' + this.fa(seq), seq: seq, version: version, versions: versions,
            perspective: selIns.perspective + (secIns ? ' + ' + secIns.perspective : ''),
            reason: S.reason, alloc: alloc, goalType: S.di.goalType, goalValue: S.di.goalValue,
            insightId: S.sel, secondaryId: S.sec
          }, page: 'sim'
        });
      },

      noPlan: !S.plan, hasSim: !!sim, planId: S.plan ? 'plan_id: ' + S.plan.planId : '',
      simId: sim ? sim.simId : '',
      simOpts: {
        conf: S.simOpts.conf, ext: S.simOpts.ext,
        onConf: e => this.setState({ simOpts: Object.assign({}, S.simOpts, { conf: e.target.value }) }),
        onExt: e => this.setState({ simOpts: Object.assign({}, S.simOpts, { ext: e.target.value }) })
      },
      funnel: funnel, simKpis: simKpis, verdicts: verdicts, simRows: sim ? sim.rows : [],
      saveSim: () => this.setState({ page: S.page === 'sim' ? 'pace' : 'verify' }),

      presets: this.presetList().map(p => ({
        label: p.label,
        load: () => this.setState({ vi: Object.assign(this.blankVI(), p.vi, { fromSim: false }), vr: null })
      })),
      loadFromSim: () => {
        if (!sim || !S.plan) return;
        const a = S.plan.alloc[0];
        this.setState({
          vi: Object.assign(this.blankVI(), {
            name: 'کمپین طرح ' + S.plan.planId, ch: a.ch, seg: a.seg, from: S.di.from, to: S.di.to,
            pb: Math.round(sim.budget), pi: Math.round(sim.installs), pc: Math.round(sim.conv),
            ab: Math.round(sim.budget * 1.03), ai: Math.round(sim.installs * 0.7), ac: Math.round(sim.conv * 0.74),
            src: 'مکتوب و عددی', fromSim: true
          }), vr: null
        });
      },
      viFields: viFields,
      runVerifier: () => { if (!this.can('result')) return; const v = this.verify(S.vi); this.track('run_verified'); this.log('انتساب علت', v.cause); this.setState({ vr: v }); },
      noVr: !vr, hasVr: !!vr, vr: vrView, treeRows: treeRows,
      applyCalib: () => this.applyCalibration(),

      chain: chain, noCalib: !S.calib, hasCalib: !!S.calib, calibRows: calibRows,

      pMethod: S.page === 'method',
      mCfg: { exec: this.pct(S.cfg.execTh, 0), est: this.pct(S.cfg.estTh, 0), lo: this.dec(S.cfg.scaleLo, 1), hi: this.dec(S.cfg.scaleHi, 1), infl: this.dec(S.cfg.inflation * 100, 1) + '٪', win: this.fa(S.cfg.attrWindow), fraud: this.pct(S.cfg.fraudTh, 0) },
      goMethod: () => this.setState({ page: 'method' }), goRules: () => this.setState({ page: 'rules' }),
      tplHistory: () => { const s = this.importSpec().history; download('template-campaign-history.csv', csv([s.cols].concat(s.sample))); },
      tplRates: () => { const s = this.importSpec().rates; download('template-rates.csv', csv([s.cols].concat(s.sample))); },
      onImportHistory: e => { const file = e.target.files && e.target.files[0]; if (!file) return; const rd = new FileReader(); rd.onload = () => this.setState({ importPrev: Object.assign(this.readImport('history', String(rd.result)), { file: file.name }) }); rd.readAsText(file, 'utf-8'); e.target.value = ''; },
      onImportRates: e => { const file = e.target.files && e.target.files[0]; if (!file) return; const rd = new FileReader(); rd.onload = () => this.setState({ importPrev: Object.assign(this.readImport('rates', String(rd.result)), { file: file.name }) }); rd.readAsText(file, 'utf-8'); e.target.value = ''; },
      hasImport: !!S.importPrev, noImport: !S.importPrev,
      imp: S.importPrev ? { file: S.importPrev.file || '', kindLabel: S.importPrev.kind === 'history' ? 'تاریخچه‌ی کمپین' : 'جدول نرخ', okN: this.fa(S.importPrev.ok.length), errN: this.fa(S.importPrev.errors.length), hasErr: S.importPrev.errors.length > 0, canApply: S.importPrev.ok.length > 0, cantApply: !S.importPrev.ok.length,
        errors: S.importPrev.errors.slice(0, 8).map(x => ({ line: this.fa(x.line), msg: x.msg })),
        preview: S.importPrev.ok.slice(0, 5).map(o => ({ a: o.name || o.channel, b: o.channel + ' · ' + o.segment, c: o.spend !== undefined ? this.money(o.spend) + ' ت' : this.num(o.unit_cost) + ' ت / واحد' })) } : { file: '', kindLabel: '', okN: '', errN: '', errors: [], preview: [] },
      applyImport: () => this.applyImport(), cancelImport: () => this.setState({ importPrev: null }),
      showWelcome: !!S.welcome && !!S.auth, welcomeName: S.auth ? S.auth.name : '',
      welcomeTour: () => this.setState({ welcome: false, tour: 1, page: 'data' }),
      welcomeUpload: () => this.setState({ welcome: false, page: 'data' }),
      welcomeDemo: () => this.setState({ welcome: false, page: 'setup' }),
      welcomeClose: () => this.setState({ welcome: false }),
      showCelebrate: !!S.celebrate && S.page === 'log', closeCelebrate: () => this.setState({ celebrate: false }),
      notFound: !found, goHome: () => this.setState({ page: 'campaigns' }),
      isOffline: S.online === false, simulateOffline: () => this.setState({ online: !(S.online !== false) }),
      needVerify: !!S.auth && !!S.signupPending && S.verified === false, verifyNow: () => { this.setState({ verified: true, signupPending: false }); this.notify('ایمیل شما تأیید شد.', 'ok'); this.track('email_verified'); },
      isForgot: S.authMode === 'forgot', notForgot: S.authMode !== 'forgot', forgotSent: !!S.forgotSent, forgotNotSent: !S.forgotSent,
      goForgot: () => this.setState({ authMode: 'forgot', forgotSent: false, authError: '' }), backToLogin: () => this.setState({ authMode: 'login', forgotSent: false }),
      sendReset: () => { if (!/.+@.+\..+/.test(S.authForm.email)) return this.setState({ authError: 'ایمیل کاری معتبر وارد کنید.' }); this.setState({ forgotSent: true, authError: '' }); },
      forgotEmail: S.authForm.email, onForgotEmail: e => this.setState({ authForm: Object.assign({}, S.authForm, { email: e.target.value }), authError: '' }),
      pendingInvites: (S.team || []).filter(m => m.pending).map(m => ({ email: m.email, role: m.role, accept: () => { this.setState({ team: S.team.map(x => x.email === m.email ? Object.assign({}, x, { pending: false }) : x) }); this.log('پذیرش دعوت', m.email); }, revoke: () => this.setState({ team: S.team.filter(x => x.email !== m.email) }) })),
      hasPending: (S.team || []).some(m => m.pending),
      planVersionLabel: S.plan ? 'نسخه‌ی ' + this.fa(S.plan.version || 1) : '', planVersions: S.plan ? (S.plan.versions || []).map(v => ({ v: 'v' + this.fa(v.v), perspective: v.perspective, reason: v.reason || '—' })) : [], hasVersions: !!(S.plan && (S.plan.versions || []).length),
      editPlan: () => this.setState({ page: 'insights' }), canEditPlan: !!(S.plan && !S.vr && this.can('plan')),
      archiveCampaign: seq => () => { this.setState({ campaigns: (S.campaigns || []).map(c => c.seq === seq ? Object.assign({}, c, { status: 'بایگانی' }) : c) }); this.log('بایگانی کمپین', 'plan-۱۴۰۵-' + this.fa(seq)); },
      canUndo: !!(S.calibHistory || []).length && this.can('calibrate'), undoCalib: () => this.undoCalibration(), undoLabel: (S.calibHistory || [])[0] ? 'بازگردانی ' + S.calibHistory[0].id : '',
      occasionOpts: this.occasions().map(o => ({ v: o.k, label: o.k + (o.lift ? ' · اثر خرید ' + this.signPct(o.lift) + ' / هزینه ' + this.signPct(o.cpi) : '') })),
      occasion: S.di.occasion || 'بدون مناسبت', onOccasion: e => this.setDi('occasion', e.target.value),
      notifList: (S.notifs || []).map(n => ({ text: n.text, bad: n.kind === 'bad', ok: n.kind === 'ok', info: n.kind !== 'bad' && n.kind !== 'ok' })),
      unread: this.fa((S.notifs || []).filter(n => !n.read).length), hasUnread: (S.notifs || []).some(n => !n.read), notifOpen: !!S.notifOpen, noNotifs: !(S.notifs || []).length, hasNotifs: !!(S.notifs || []).length,
      toggleNotif: () => this.setState({ notifOpen: !S.notifOpen, notifs: (S.notifs || []).map(n => Object.assign({}, n, { read: true })) }),
      pAsk: S.page === 'ask', askQ: S.askQ, onAskQ: e => this.setState({ askQ: e.target.value }),
      doAsk: () => { this.track('question_asked'); this.setState({ askA: this.answer(S.askQ) }); },
      askSuggest: ['ارزان‌ترین کانال کدام است؟', 'پایدارترین ردیف؟', 'کدام ردیف سودآورتر است؟', 'بیشترین ماندگاری؟', 'نرخ تقلب کجا بالاست؟', 'وضعیت تپسل'].map(q => ({ q: q, pick: () => this.setState({ askQ: q, askA: this.answer(q) }) })),
      hasAnswer: !!S.askA, askA: S.askA ? { text: S.askA.text, src: S.askA.src, grounded: !S.askA.none, ungrounded: !!S.askA.none } : { text: '', src: '' },
      pAnalytics: S.page === 'analytics', pSecurity: S.page === 'security', pBilling: S.page === 'billing', pHelp: S.page === 'help',
      twoFa: !!S.plan2fa, noTwoFa: !S.plan2fa, toggle2fa: () => { this.setState({ plan2fa: !S.plan2fa }); this.log(S.plan2fa ? 'غیرفعال‌سازی ورود دومرحله‌ای' : 'فعال‌سازی ورود دومرحله‌ای', ''); },
      sessionRows: (S.sessions || []).map((s, i) => ({ dev: s.dev, where: s.where, now: s.now, other: !s.now, end: () => this.setState({ sessions: S.sessions.filter((_, j) => j !== i) }) })),
      tierRows: [
        { k: 'آزمایشی', price: 'رایگان', note: '۳ کمپین در ماه · ۱ کاربر · داده‌ی دستی و CSV', feats: 'ده دیدگاه، شبیه‌سازی، راستی‌آزمایی' },
        { k: 'رشد', price: '۴٫۹ میلیون تومان / ماه', note: 'کمپین نامحدود · ۵ کاربر · اتصال ادتریس و اینترک', feats: 'پایش خودکار، اعلان، گزارش ماهانه' },
        { k: 'سازمانی', price: 'تماس با فروش', note: 'چند فضای کاری · SSO · SLA', feats: 'اکانت‌منیجر، قواعد اختصاصی، API کامل' }
      ].map(t => ({ k: t.k, price: t.price, note: t.note, feats: t.feats, current: S.plan_tier === t.k, other: S.plan_tier !== t.k, pick: () => { this.setState({ plan_tier: t.k }); this.log('تغییر پلن', t.k); this.notify('پلن به «' + t.k + '» تغییر کرد.', 'ok'); } })),
      usageText: 'مصرف این ماه: ' + this.fa((S.campaigns || []).length) + (S.plan_tier === 'آزمایشی' ? ' از ۳ کمپین' : ' کمپین'), overLimit: S.plan_tier === 'آزمایشی' && (S.campaigns || []).length >= 3,
      wi: (() => {
        if (!S.plan) return { has: false };
        const w = S.whatIf || { budget: 0, shift: 0 };
        const base = this.expected(S.plan.alloc);
        const alt = this.expected(this.shiftAlloc(this.scaleAlloc(S.plan.alloc, 1 + (w.budget || 0) / 100), (w.shift || 0) / 100));
        const d = (a, b) => b ? this.signPct(a / b - 1) : '—';
        return { has: true, budget: String(w.budget || 0), shift: String(w.shift || 0), budgetLbl: this.signPct((w.budget || 0) / 100), shiftLbl: this.fa(w.shift || 0) + '٪',
          rows: [
            { k: 'بودجه', a: this.money(base.budget), b: this.money(alt.budget), d: d(alt.budget, base.budget) },
            { k: 'نصب', a: this.num(base.installs), b: this.num(alt.installs), d: d(alt.installs, base.installs) },
            { k: 'خرید', a: this.num(base.conv), b: this.num(alt.conv), d: d(alt.conv, base.conv) },
            { k: 'CAC', a: this.money(base.cac), b: this.money(alt.cac), d: d(alt.cac, base.cac) },
            { k: 'POAS', a: this.signPct(base.poas), b: this.signPct(alt.poas), d: '' }
          ],
          note: (w.budget || 0) > 0 && alt.conv / base.conv - 1 < (w.budget || 0) / 100 ? 'بازده نزولی: ' + this.signPct((w.budget || 0) / 100) + ' بودجه فقط ' + this.signPct(alt.conv / base.conv - 1) + ' خرید بیشتر می‌دهد.' : '' };
      })(),
      onWiBudget: e => this.setState({ whatIf: Object.assign({}, S.whatIf, { budget: +e.target.value }) }),
      onWiShift: e => this.setState({ whatIf: Object.assign({}, S.whatIf, { shift: +e.target.value }) }),
      applyWhatIf: () => { if (!S.plan || !this.can('plan')) return; const w = S.whatIf || {}; const alloc = this.shiftAlloc(this.scaleAlloc(S.plan.alloc, 1 + (w.budget || 0) / 100), (w.shift || 0) / 100); this.log('اعمال سناریو روی طرح', 'بودجه ' + this.fa(w.budget || 0) + '٪ · جابه‌جایی ' + this.fa(w.shift || 0) + '٪'); this.setState({ plan: Object.assign({}, S.plan, { alloc: alloc, version: (S.plan.version || 1) + 1, versions: [{ v: S.plan.version || 1, perspective: S.plan.perspective, reason: 'پیش از سناریو' }].concat(S.plan.versions || []) }), whatIf: { budget: 0, shift: 0 } }); },
      realloc: (() => {
        if (!S.plan || S.plan.alloc.length < 2 || !(+pc.spend > 0) || !paceRows.length || paceRows[2].raw > -0.25) return { has: false };
        const base = this.expected(S.plan.alloc), alt = this.expected(this.shiftAlloc(S.plan.alloc, 0.3));
        return { has: alt.conv > base.conv, text: 'انتقال ۳۰٪ بودجه‌ی باقی‌مانده از ' + S.plan.alloc[0].ch + ' به ' + S.plan.alloc[1].ch + ' خرید مورد انتظار را از ' + this.num(base.conv) + ' به ' + this.num(alt.conv) + ' می‌رساند.' };
      })(),
      applyRealloc: () => { if (!S.plan || !this.can('plan')) return; this.log('جابه‌جایی بودجه حین اجرا', '۳۰٪'); this.setState({ plan: Object.assign({}, S.plan, { alloc: this.shiftAlloc(S.plan.alloc, 0.3), version: (S.plan.version || 1) + 1, versions: [{ v: S.plan.version || 1, perspective: S.plan.perspective, reason: 'پیش از جابه‌جایی حین اجرا' }].concat(S.plan.versions || []) }) }); this.notify('بودجه‌ی کمپین جابه‌جا شد؛ نسخه‌ی تازه‌ی طرح ثبت شد.', 'ok'); },
      benchRows: (() => { const ind = this.industry(); const chs = this.channels(); return chs.map(ch => { const rs = S.rates.filter(r => r.ch === ch); if (!rs.length) return null; const mine = rs.reduce((s, r) => s + this.cac(r), 0) / rs.length; const med = mine * ind[ch]; const d = mine / med - 1; return { ch: ch, mine: this.money(mine), med: this.money(med), d: this.signPct(d), better: d < 0, worse: d >= 0 }; }).filter(Boolean); })(),
      funnelRows: (() => { const ev = S.events || []; const c = k => ev.filter(e => e.ev === k).length; const steps = [['ثبت طرح', 'plan_created'], ['ویرایش طرح', 'plan_edited'], ['ثبت پایش', 'pace_logged'], ['راستی‌آزمایی', 'run_verified'], ['بستن حلقه', 'campaign_closed'], ['پرسش از داده', 'question_asked']]; const mx = Math.max(1, ...steps.map(s => c(s[1]))); return steps.map(s => ({ label: s[0], n: this.fa(c(s[1])), pct: Math.max(Math.round(c(s[1]) / mx * 100), 3), key: s[1] })); })(),
      northStar: this.fa(archive.filter(a => !a.seeded).length), eventCount: this.fa((S.events || []).length),
      faqRows: [
        { q: 'چرا عدد پیش‌بینی با ماه قبل فرق دارد؟', a: 'نرخ‌ها با تورم ماهانه تعدیل می‌شوند و هر کالیبراسیون آن‌ها را به‌روز می‌کند. در لاگ تغییرات ببینید چه چیزی عوض شد.' },
        { q: 'چرا کالیبراسیون اعمال نشد؟', a: 'در شاخه‌های «ناسازگاری داده»، «انحراف اجرا» و «مقیاس، نه کیفیت» عمداً اعمال نمی‌شود، چون نرخ کانال غلط نبوده.' },
        { q: 'بازه‌ی تجربی یعنی چه؟', a: 'بازه از نوسان تاریخی همان ردیف ساخته می‌شود و ادعای احتمال آماری ندارد. جزئیات در صفحه‌ی روش‌شناسی.' },
        { q: 'چطور داده‌ی خودم را وارد کنم؟', a: 'در صفحه‌ی داده‌ها قالب CSV را دانلود کنید، پر کنید و بارگذاری کنید. خطای هر سطر قبل از ثبت نشان داده می‌شود.' },
        { q: 'چه کسی می‌تواند نرخ‌ها را تغییر دهد؟', a: 'فقط مالک. تحلیل‌گر می‌تواند کالیبراسیون اعمال کند؛ مسئول کمپین فقط نتیجه ثبت می‌کند؛ ناظر فقط می‌بیند.' }
      ],
      ticket: S.ticket || '', onTicket: e => this.setState({ ticket: e.target.value, ticketSent: false }), sendTicket: () => { if (!(S.ticket || '').trim()) return; this.setState({ ticketSent: true, ticket: '' }); this.notify('درخواست پشتیبانی ثبت شد. پاسخ تا ۴ ساعت کاری.', 'ok'); }, ticketSent: !!S.ticketSent,
      pCampaigns: S.page === 'campaigns', pPace: S.page === 'pace', pRules: S.page === 'rules', pAudit: S.page === 'audit',
      campaignRows: (S.campaigns || []).map(c => ({ id: 'plan-۱۴۰۵-' + this.fa(c.seq), name: c.name, perspective: c.perspective, status: c.status, budget: this.money(c.budget), live: c.status === 'در حال اجرا', closed: c.status === 'بسته‌شده', stopped: c.status === 'متوقف', archived: c.status === 'بایگانی', canArchive: c.status !== 'بایگانی' && c.status !== 'در حال اجرا', archive: () => { this.setState({ campaigns: (S.campaigns || []).map(x => x.seq === c.seq ? Object.assign({}, x, { status: 'بایگانی' }) : x) }); this.log('بایگانی کمپین', 'plan-۱۴۰۵-' + this.fa(c.seq)); } })),
      noCampaigns: !(S.campaigns || []).length, hasCampaigns: !!(S.campaigns || []).length,
      goBilling: () => this.setState({ page: 'billing' }),
      newCampaign: () => (S.plan_tier === 'آزمایشی' && (S.campaigns || []).length >= 3) ? this.setState({ page: 'billing' }) : this.setState({ plan: null, vr: null, calib: null, sel: null, sec: null, reason: '', insights: [], page: 'design' }),
      paceRows: paceRows, paceAlerts: paceAlerts, hasPaceData: +pc.spend > 0,
      pace: { day: String(pc.day), spend: String(pc.spend), installs: String(pc.installs), conv: String(pc.conv), onDay: setPace('day'), onSpend: setPace('spend'), onInstalls: setPace('installs'), onConv: setPace('conv'), pct: Math.round(frac * 100), dayLabel: 'روز ' + this.fa(pc.day) + ' از ۳۰' },
      logPace: () => { this.track('pace_logged'); paceAlerts.filter(a => a.bad).forEach(a => this.notify(a.text, 'bad')); this.setState({ page: 'pace' }); },
      loadPaceDemo: () => sim && this.setState({ pace: { day: 12, spend: Math.round(sim.budget * 0.52), installs: Math.round(sim.installs * 0.44), conv: Math.round(sim.conv * 0.29) } }),
      dateErr: dateErr, hasDateErr: !!dateErr, budgetErr: budgetErr, hasBudgetErr: !!budgetErr, designBlocked: !!(dateErr || budgetErr),
      exportRates: () => download('rates.csv', csv([['channel', 'segment', 'type', 'cpi', 'cpi_adj', 'cvr', 'aov', 'variance', 'sample_n', 'ceiling', 'd7', 'd30', 'fraud', 'age_months']].concat(S.rates.map(r => [r.ch, r.seg, r.type, r.cpi, this.cpiAdj(r), r.cvr, r.aov, r.variance, r.n, r.ceiling, r.d7, r.d30, r.fraud, r.age])))),
      exportReport: () => { if (S.verified === false) return this.notify('برای خروجی گزارش ابتدا ایمیل خود را تأیید کنید.', 'bad'); const rp = this.reportData(); if (!rp) return; download('campaign-report.csv', csv([['section', 'key', 'value']].concat(rp.chain.map(c => ['chain', c.k, c.v]), rp.alloc.map(a => ['allocation', a.label, a.budget + ' | ' + a.share]), rp.forecast.map(x => ['forecast', x.k, x.v + ' (' + x.band + ')']), rp.result.map(x => ['result', x.k, x.p + ' → ' + x.a + ' ' + x.d]), [['cause', 'cause', rp.cause], ['calibration', 'calibration', rp.calib]]))); },
      exportLog: () => download('perspective-log.csv', csv([['id', 'name', 'perspective', 'reason', 'cause', 'calibrated', 'planned_cac', 'actual_cac']].concat(archive.map(r => [r.id, r.name, r.perspective, r.reason, r.cause, r.calib, Math.round(r.plannedCac), Math.round(r.actualCac)])))),
      copyShare: () => { if (S.verified === false) return this.notify('برای اشتراک گزارش ابتدا ایمیل خود را تأیید کنید.', 'bad'); try { navigator.clipboard.writeText(location.href.split('#')[0] + '#report-' + (S.plan ? S.plan.seq : '')); } catch (e) { } this.setState({ copied: true }); setTimeout(() => this.setState({ copied: false }), 1600); },
      shareLabel: S.copied ? 'لینک کپی شد' : 'کپی لینک گزارش',
      tourOn: tourIdx > 0, tourOff: tourIdx === 0,
      tourStep: tourIdx > 0 ? Object.assign({ n: this.fa(tourIdx) + ' از ' + this.fa(tourSteps.length) }, tourSteps[tourIdx - 1]) : { title: '', text: '', n: '' },
      tourIsLast: tourIdx === tourSteps.length, tourNotLast: tourIdx > 0 && tourIdx < tourSteps.length,
      startTour: () => this.setState({ tour: 1, page: tourSteps[0].page }),
      tourNext: () => { const n = tourIdx + 1; if (n > tourSteps.length) return this.setState({ tour: 0 }); this.setState({ tour: n, page: tourSteps[n - 1].page }); },
      tourEnd: () => this.setState({ tour: 0 }),
      cfgRows: cfgRows, canEditRules: this.can('editData'), cannotEditRules: !this.can('editData'),
      resetCfg: () => { this.setState({ cfg: { execTh: 0.15, estTh: 0.25, scaleLo: 0.8, scaleHi: 1.2, inflation: 0.035, attrWindow: 7, fraudTh: 0.08 } }); this.log('بازنشانی قواعد', 'پیش‌فرض'); },
      auditRows: (S.audit || []).map(a => ({ who: a.who, role: a.role, what: a.what, detail: a.detail, t: a.t })), noAudit: !(S.audit || []).length, hasAudit: !!(S.audit || []).length,
      roleOpts: ['مالک', 'تحلیل‌گر', 'مسئول کمپین', 'ناظر'].map(r => ({ label: r, on: S.role === r, off: S.role !== r, pick: () => { this.setState({ role: r }); this.log('تغییر نقش فعال', r); } })),
      roleLabel: S.role, onRole: e => { const r = e.target.value; this.setState({ role: r }); this.log('تغییر نقش فعال', r); }, roleNote: this.can('plan') ? '' : this.can('result') ? 'این نقش فقط نتیجه‌ی واقعی ثبت می‌کند.' : 'این نقش فقط‌خواندنی است.', hasRoleNote: !this.can('plan'),
      canPlan: this.can('plan'), cannotPlan: !this.can('plan'), canResult: this.can('result'), cannotResult: !this.can('result'),
      loadBenchmarks: benchmarks, isBenchmark: !!S.benchmark,
      dashTabs: dashTabs, dashCards: dash.cards, dashList: dash.list, dashListTitle: dash.title
    };
  }
}


/** Create an engine with optional state overrides (rates, profile, cfg, di, plan, vi...). */
function createEngine(overrides) {
  const e = new Component({});
  if (overrides) e.state = Object.assign({}, e.state, overrides);
  return e;
}
module.exports = { createEngine, Component };
