// Dumps prototype engine outputs on the seed data, for PHP parity testing.
// Usage: node tests/parity.js <path-to-engine.js>
const { createEngine } = require(require('path').resolve(process.argv[2] || __dirname + '/ref-engine.js'));
const e = createEngine();
const out = {};
out.cpiAdj = e.state.rates.map(r => e.cpiAdj(r));
out.cac = e.state.rates.map(r => e.cac(r));
out.fmt = [0, 1, -1, 12.5, 999, 1234.5, 9999999, 10000000, 12345678, 999999999, 1500000000, 12000000000, -3456789].map(n => [e.num(n), e.money(n)]);
out.pct = [0, 0.001, 0.0123, 0.123, -0.32, 1.5, -0.004].map(x => [e.pct(x), e.pct(x, 0), e.signPct(x)]);
const variants = [
  { goal: 'سودآوری', risk: 'متعادل', budget: 500000000 },
  { goal: 'رشد', risk: 'تهاجمی', budget: 300000000 },
  { goal: 'نگهداشت', risk: 'محافظه‌کار', budget: 900000000 },
  { goal: 'سهم بازار', risk: 'متعادل', budget: 120000000 }
];
out.insights = variants.map(v => {
  e.state.profile = Object.assign({}, e.seedProfile(), { goal: v.goal });
  e.state.di = Object.assign({}, e.state.di, { risk: v.risk, budget: v.budget });
  return e.buildInsights().map(i => ({ id: i.id, fit: i.fit, claim: i.claim, proposal: i.proposal, evidence: i.evidence, risk: i.risk, success: i.successMetric, alloc: i.alloc.map(a => [a.ch, a.seg, a.budget, a.share]), exp: i.exp }));
});
e.state.profile = e.seedProfile();
e.state.di = Object.assign({}, e.state.di, { risk: 'متعادل', budget: 500000000 });
const ins = e.buildInsights();
e.state.insights = ins;
out.sims = [];
for (const [sel, sec, mix, conf, ext, occ, gt, gv] of [
  ['cfo', null, 60, 'معمول', 'عادی', 'بدون مناسبت', 'خرید', 2500],
  ['analyst', null, 60, 'باریک', 'فصل اوج', 'یلدا (آذر)', 'نصب', 5000],
  ['cfo', 'value', 70, 'محتاط', 'رکود', 'بلک‌فرایدی (آبان)', 'درآمد', 1500000000],
  ['ceo', 'analyst', 40, 'معمول', 'رقابت شدید', 'ماه رمضان', 'خرید', 100]
]) {
  e.state.sel = sel; e.state.sec = sec; e.state.mix = mix;
  const alloc = e.mergedAlloc();
  e.state.plan = { planId: 'x', seq: 101, alloc: alloc, goalType: gt, goalValue: gv };
  e.state.simOpts = { conf: conf, ext: ext };
  e.state.di = Object.assign({}, e.state.di, { occasion: occ });
  const s = e.simulate();
  out.sims.push({ key: [sel, sec, mix, conf, ext, occ, gt, gv], alloc: alloc.map(a => [a.ch, a.seg, a.budget, a.share]), s: { installs: s.installs, iLow: s.iLow, iHigh: s.iHigh, conv: s.conv, cLow: s.cLow, cHigh: s.cHigh, rev: s.rev, rLow: s.rLow, rHigh: s.rHigh, cac: s.cac, roas: s.roas, poas: s.poas, payback: s.payback, ltvCac: s.ltvCac, ret30: s.ret30, fraudInst: s.fraudInst, coverage: s.coverage, risk: s.risk.text, prof: s.prof.text } });
}
out.verify = e.presetList().map(p => {
  const vi = Object.assign(e.blankVI(), p.vi);
  const v = e.verify(vi);
  const r = { cause: v.cause, calib: v.calib, expl: v.expl, block: v.block, dB: v.dB, dI: v.dI, dCvr: v.dCvr, obsCpi: v.obsCpi, obsCvr: v.obsCvr, liftText: v.liftText };
  if (v.calib) {
    e.state.vi = vi; e.state.vr = v; e.state.calibHistory = []; e.state.rates = e.seedRates(); e.state.role = 'مالک';
    e.applyCalibration();
    r.calibAfter = e.state.calib.after; r.weights = e.state.calib.weights;
  }
  return r;
});
out.verifyExtra = [
  { window: 14 }, { fraud: 12 }, { pb: 100e6, pi: 10000, pc: 800, ab: 110e6, ai: 11000, ac: 600 }, { reach: 50000, ac: 590, holdout: 0.4 }
].map(o => { const v = e.verify(Object.assign(e.blankVI(), o)); return { cause: v.cause, calib: v.calib, expl: v.expl, liftText: v.liftText }; });
e.state.rates = e.seedRates();
out.answers = ['ارزان‌ترین کانال کدام است؟', 'گران‌ترین جذب کجاست؟', 'پایدارترین ردیف؟', 'بیشترین ماندگاری؟', 'نرخ تقلب کجا بالاست؟', 'کدام ردیف سودآورتر است؟', 'وضعیت تپسل', 'قیمت دلار فردا؟'].map(q => e.answer(q).text);
out.readiness = e.readiness();
console.log(JSON.stringify(out));
